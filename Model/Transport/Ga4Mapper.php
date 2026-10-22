<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Transport;

use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Model\Event\EventCode;

/**
 * Zaraz e-commerce payload → GA4 event, mirroring the Zaraz GA4 Managed Component:
 * item_id = sku ?: product_id, one item_category, value = total ?: value ?: price
 */
class Ga4Mapper
{
    private const EVENTS = [
        EventCode::ORDER_COMPLETED => 'purchase',
        'order_refunded' => 'refund',
    ];

    private const ITEM_FIELDS = [
        'name' => 'item_name',
        'category' => 'item_category',
        'variant' => 'item_variant',
        'brand' => 'item_brand',
        'coupon' => 'coupon',
        'list_name' => 'item_list_name',
        'list_id' => 'item_list_id',
    ];

    /**
     * GA4 event {name, params}, or null when GA4 has no equivalent
     *
     * @param EventInterface $event
     * @return array{name: string, params: array}|null
     */
    public function map(EventInterface $event): ?array
    {
        $name = self::EVENTS[$event->getCode()] ?? null;
        if ($name === null) {
            return null;
        }
        $payload = $event->getPayload();
        $params = array_filter([
            'transaction_id' => (string)($payload['order_id'] ?? ''),
            'currency' => (string)($payload['currency'] ?? ''),
            'value' => $this->number($payload['total'] ?? $payload['value'] ?? $payload['price'] ?? null),
            'tax' => $this->number($payload['tax'] ?? null),
            'shipping' => $this->number($payload['shipping'] ?? null),
            'coupon' => (string)($payload['coupon'] ?? ''),
            'affiliation' => (string)($payload['affiliation'] ?? ''),
        ], static fn ($value): bool => $value !== null && $value !== '');

        $items = [];
        foreach ((array)($payload['products'] ?? []) as $product) {
            if (is_array($product)) {
                $items[] = $this->mapItem($product);
            }
        }
        if ($items) {
            $params['items'] = $items;
        }
        return ['name' => $name, 'params' => $params];
    }

    /**
     * GA4 item of a Zaraz product
     *
     * @param array $product
     * @return array
     */
    private function mapItem(array $product): array
    {
        $item = ['item_id' => (string)($product['sku'] ?? $product['product_id'] ?? '')];
        foreach (self::ITEM_FIELDS as $from => $to) {
            if (isset($product[$from]) && (string)$product[$from] !== '') {
                $item[$to] = (string)$product[$from];
            }
        }
        foreach (['price' => 'price', 'quantity' => 'quantity', 'position' => 'index'] as $from => $to) {
            $number = $this->number($product[$from] ?? null);
            if ($number !== null) {
                $item[$to] = $number;
            }
        }
        return $item;
    }

    /**
     * Float or null
     *
     * @param mixed $value
     * @return float|null
     */
    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float)$value : null;
    }
}
