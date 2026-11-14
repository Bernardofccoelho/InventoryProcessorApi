<?php

declare(strict_types=1);

namespace App\Features\AvailabilityImport\Import;

use App\Http\Requests\BaseRequest;

final class Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,json', 'max:51200'],
        ];
    }
}
