<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Unit\Model\Transport;

use Perspective\Zaraz\Model\Event\Event;
use Perspective\ZarazServerSide\Model\Transport\Ga4Mapper;
use PHPUnit\Framework\TestCase;

class Ga4MapperTest extends TestCase
{
    /**
     * @return void
     */
    public function testPurchaseMirrorsZarazGa4Component(): void
    {
        $event = new Event('ecommerce', 'Order Completed', [
            'order_id' => '000000014',
            'total' => 214.0,
            'revenue' => 199.0,
            'shipping' => 15.0,
            'tax' => 0.0,
            'coupon' => '',
            'affiliation' => 'Main Website Store',
            'currency' => 'USD',
            'products' => [
                ['product_id' => '1', 'sku' => 'WJ12', 'name' => 'Jacket', 'price' => 77.0, 'quantity' => 2.0,
                    'category' => 'Women/Jackets', 'variant' => 'S / Blue', 'list_name' => 'Jackets'],
                ['product_id' => 'MJ12', 'name' => 'Jackshirt', 'price' => 45.0, 'quantity' => 1.0],
            ],
        ], 'order-000000014', 'order_completed');

        $this->assertSame(
            [
                'name' => 'purchase',
                'params' => [
                    'transaction_id' => '000000014',
                    'currency' => 'USD',
                    'value' => 214.0,
                    'tax' => 0.0,
                    'shipping' => 15.0,
                    'affiliation' => 'Main Website Store',
                    'items' => [
                        ['item_id' => 'WJ12', 'item_name' => 'Jacket', 'item_category' => 'Women/Jackets',
                            'item_variant' => 'S / Blue', 'item_list_name' => 'Jackets', 'price' => 77.0,
                            'quantity' => 2.0],
                        ['item_id' => 'MJ12', 'item_name' => 'Jackshirt', 'price' => 45.0, 'quantity' => 1.0],
                    ],
                ],
            ],
            (new Ga4Mapper())->map($event)
        );
    }

    /**
     * @return void
     */
    public function testRefundAndUnsupported(): void
    {
        $mapper = new Ga4Mapper();
        $refund = $mapper->map(new Event('ecommerce', 'Order Refunded', [
            'order_id' => '000000014',
            'total' => 77.0,
            'currency' => 'USD',
        ], null, 'order_refunded'));

        $this->assertSame('refund', $refund['name']);
        $this->assertSame(['transaction_id' => '000000014', 'currency' => 'USD', 'value' => 77.0], $refund['params']);
        $this->assertNull($mapper->map(new Event('ecommerce', 'Order Cancelled', [], null, 'order_cancelled')));
    }
}
