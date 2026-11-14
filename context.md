# Availability import: implementation and design decisions

This document describes the current Laravel implementation and the decisions recommended for production. The local Compose stack is an exercise environment: Redis provides cache locks, while WireMock simulates Product Context, Availability API, and an HTTP event sink. The sink is not a production broker.

## Run the project

Prerequisites: Docker Engine/Desktop with Compose v2, and PHP 8.3 with Composer if running the API outside Docker. From the repository root:

```sh
docker compose up -d --build
```

The compose file starts the API on `http://localhost:5000`, Redis, and WireMock on host port `8080`. In the container, the mock base URL must resolve to `http://wiremock:8080`; from the host it is `http://localhost:8880`. Set `CONTEXT_API_BASE_URL`, `AVAILABILITY_API_BASE_URL`, and `MOCK_BROKER_API_BASE_URL` accordingly. The checked-in `.env.example` uses port 8080 and `REDIS_HOST=redis`, so adjust the mock URL to match this Compose mapping when running the supplied stack. Ensure `QUEUE_CONNECTION` selects a queue driver supported by the configured `background` connection used by the import chain.

## Processing and assumptions

Upload handling streams the CSV into temporary files of at most 1,000 data rows, then dispatches those batches as a queue chain. A single import's batches are sequential; separate imports may overlap according to queue capacity. Processing is row-by-row, and Product Context is currently fetched separately for each row. Empty lines are ignored. The job status and row counters are currently held in the file cache for seven days; this is suitable for the exercise, not durable production tracking.

The design assumes `sourceVersion` increases monotonically for each `(sellerId, productId, sizeId, countryCode)` key. Product eligibility is checked against the Context response during processing. Availability writes and event consumers should support idempotency or deduplication. The import's generated event ID is deterministic for a row: `bulk-{jobId}-{CSV line number}`.

## Retry, idempotency, and stale versions

The logical inventory key is `(sellerId, productId, sizeId, countryCode)`. The code acquires a distributed lock on that key, checks a cached latest version and processed event IDs, and skips an event if its version is less than or equal to the cached version or its event ID was already recorded. The version and event ID are written to cache only after the Context lookup, gateway update, and synchronous event publication all succeed.

That is the current exercise behavior, not a durable production guarantee. The lock lasts ten seconds, and the version check and cache update are not a durable compare-and-set. Production should store an inbox/idempotency record and latest version atomically, use a unique event ID, and make the gateway enforce `incomingVersion > storedVersion` (or an equivalent conditional write). This closes races between importer instances and other writers. Use a durable outbox with a unique key derived from the input event ID so duplicate deliveries cannot create duplicate logical events.

The HTTP helpers retry `429` responses up to three additional attempts and sleep for the supplied `Retry-After` (defaulting to one second). They currently do not retry network errors or `5xx`; those and other non-success responses throw. Production retry policy should retry only transient timeouts/connection failures, `429` (bounded `Retry-After`), and selected `5xx`, using exponential backoff with jitter, an overall deadline, and a bounded attempt count. Avoid stacking broad queue retries on top of HTTP retries. Permanent validation and eligibility errors should not be retried.

### Gateway update succeeds, event publication fails

Currently the gateway update happens first, followed by synchronous publication. If publication fails, the row throws and is counted as failed even though availability changed. The cached version is not advanced, so a later replay may repeat the gateway write. The gateway therefore must tolerate duplicate writes for this to be safe.

In production, persist the availability change and publication intent atomically when both share a database. Otherwise, own a durable outbox/workflow: record the intent, issue an idempotent gateway command keyed by event ID/version, and retry publication independently. Keep the row/job pending publication until broker acknowledgement, then mark it successful. After bounded retries, expose a publication-specific error and keep the outbox record available for replay or reconciliation. Do not attempt to reverse a successful gateway update just because publishing failed.

## Concurrency and dependency protection

The 1,000-row batch size bounds each queued payload and chain ordering limits work inside one upload. The per-key lock serializes updates for the same inventory key; it does not limit overall dependency traffic. During bursts, production should cap worker concurrency and use separate global rate limits/semaphores for Context reads, gateway writes, and broker publishing. Add bounded connection pools, request timeouts, circuit breakers, queue backpressure, and per-tenant upload/row quotas. Scale workers only within downstream capacity. Stream parsing and persist results in chunks rather than retaining an entire large upload in memory. Schedule delayed retries instead of sleeping worker slots for long `Retry-After` intervals. Monitor queue depth and age, dependency latency/error rates, retries, rate limits, and outbox age.

The current lock lease is ten seconds while a row can make several network calls and sleep during rate limiting. Production should ensure a lock lease safely exceeds the operation or renew it, and use fencing tokens or the gateway's conditional version write so an expired lock cannot permit stale overwrites.

## Row outcomes, counters, and partial failure

Every non-empty data row should have one terminal outcome:

| Outcome | Meaning | Counter |
|---|---|---|
| `successful` | Context is eligible, the availability update succeeds, and event publication is acknowledged. | `successfulRows` |
| `skipped` | The row is stale or its event ID was already processed; no new side effect is applied. | `skippedRows` |
| `failed` | The row is malformed, context is missing/ineligible, or a dependency operation fails after applicable retries. | `failedRows` |

Stale rows are **skipped**, not failed: they are expected ordering outcomes and should not be retried. They still increment `processedRows` and `skippedRows`; they do not affect success or failure counts. For finalized jobs, maintain `processedRows = successfulRows + skippedRows + failedRows` (excluding blank CSV lines, which are ignored).

The current status endpoint returns aggregate counters and a maximum of 100 error messages; it does not persist or return a complete row-level result for each row. It also currently collapses permanent row errors into an exception/message and has no stable reason-code schema. A production implementation should persist `(jobId, rowNumber, outcome, reasonCode)` and expose paginated results or an export. Row-level errors do not stop later rows: completion is `completed_with_errors` if any row failed, otherwise `completed_with_skips` if any were skipped, otherwise `completed`. A worker/job failure that prevents remaining rows from running should be represented as job-level `failed`, separately from a completed job with row failures.

## Product matching and eligibility decisions

Unknown product/size/context (`404`), inactive products, and products not enabled for the requested country are **row failures**, with distinct reason codes such as `unknown_product_or_size`, `inactive_product`, and `country_not_enabled`. Do not silently skip them: the uploader needs actionable feedback. The current Context helper validates active status and country enablement but throws generic runtime exceptions, so those distinct row reason codes require explicit exception mapping. A transient lookup failure becomes a dependency failure after retries and should be distinguishable from permanent ineligibility.

For a bulk matcher using a full Product Context snapshot, build a hash map keyed by `(productId, sizeId, countryCode)`, then probe it for each import row. With `P` context records and `R` rows, expected time is **O(P + R)** and the index uses **O(P)** space. If all rows and results are also retained, total auxiliary space is **O(P + R)**. Streaming rows/results in bounded chunks of size `B` reduces this to **O(P + B)**. Hash lookup is expected O(1); pathological hash collisions can increase lookup cost. The current code makes one Context API request per row, so this linear matcher is a recommended optimization, not current behavior.

## Production broker choice

For this event stream, Kafka is a reasonable production choice when per-key ordering, durable retention, consumer groups, and replay matter. Use the composite inventory key as the partition key and publish through the durable outbox. The local WireMock HTTP sink only simulates the output contract. If the deployment needs simpler managed queue semantics and does not need partition replay, a managed queue (for example, AWS SQS) plus the same outbox, idempotency, dead-letter handling, and observability can also fit. The broker cannot replace the gateway's conditional version enforcement or durable row/job state.
