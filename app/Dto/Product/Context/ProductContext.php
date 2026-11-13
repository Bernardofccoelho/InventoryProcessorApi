<?php

declare(strict_types=1);

namespace App\Dto\Product\Context;

final readonly class ProductContext
{
    public function __construct(
        private string $productId,
        private string $productName,
        private Brand $brand,
        private Category $category,
        private Color $color,
        private string $sizeId,
        private string $sizeLabel,
        private string $sizeSystem
    ) {}

    public function toAvailabilityRequestArray(): array
    {
        return [
            'productId' => $this->productId,
            'brandId' => $this->brand->id,
            'categoryId' => $this->category->id,
            'colorId' => $this->color->id,
            'sizeId' => $this->sizeId,
            'sizeSystem' => $this->sizeSystem,
        ];
    }

    public function toMockBrokerEventRequestArray(): array
    {
        return [
            'productName' => $this->productName,
            'brand' => $this->brand->toArray(),
            'sizeLabel' => $this->sizeLabel,
            'sizeSystem' => $this->sizeSystem,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['productId'],
            productName: $data['productName'],
            brand: new Brand(
                id: $data['brand']['id'],
                name: $data['brand']['name']
            ),
            category: new Category(
                id: $data['category']['id'],
                name: $data['category']['name']
            ),
            color: new Color(
                id: $data['color']['id'],
                name: $data['color']['name']
            ),
            sizeId: $data['sizeId'],
            sizeLabel: $data['sizeLabel'],
            sizeSystem: $data['sizeSystem']
        );
    }
}
