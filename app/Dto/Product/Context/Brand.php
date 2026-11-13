<?php

declare(strict_types=1);

namespace App\Dto\Product\Context;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Brand implements Arrayable
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
