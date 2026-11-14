<?php

use App\Features\AvailabilityImport\Import\Controller as AvailabilityImportController;
use App\Features\AvailabilityImport\Show\Controller as AvailabilityImportShowController;
use App\Features\InventoryEventProcessing\Controller as InventoryEventController;
use Illuminate\Support\Facades\Route;

Route::post('/_dev/messages', InventoryEventController::class);
Route::post('/v1/availability/imports', AvailabilityImportController::class);
Route::get('/v1/availability/imports/{jobId}', AvailabilityImportShowController::class)
    ->name('availability-imports.show');
