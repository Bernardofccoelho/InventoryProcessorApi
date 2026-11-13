<?php

declare(strict_types=1);

namespace App\Features\InventoryEventProcessing;

use App\Http\Requests\BaseRequest;

final class Request extends BaseRequest
{
    public function rules(): array
    {
        return [
            'eventId' => ['required', 'string'],
            'eventType' => ['required', 'string', 'in:SellerInventoryChanged'],
            'schemaVersion' => ['required', 'integer'],
            'occurredAt' => ['required', 'date'],
            'sellerId' => ['required', 'string'],
            'productId' => ['required', 'string'],
            'sizeId' => ['required', 'string'],
            'countryCode' => ['required', 'string', 'size:2'],
            'availableQuantity' => ['required', 'integer'],
            'sourceVersion' => ['required', 'integer'],
        ];
    }
}
