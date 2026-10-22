<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Integration\Model;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Test\Fixture\Creditmemo as CreditmemoFixture;
use Magento\Sales\Test\Fixture\Invoice as InvoiceFixture;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;
use Perspective\ZarazServerSide\Api\ServerEventRepositoryInterface;
use Perspective\ZarazServerSide\Model\Processor;
use Perspective\ZarazServerSide\Model\Transport\TransportPool;
use Perspective\ZarazServerSide\Test\Integration\FakeTransport;
use PHPUnit\Framework\TestCase;

/**
 * A refunded credit memo is sent as Order Refunded at once (server-only event, no row in "Events" needed)
 */
#[AppArea('adminhtml')]
#[DbIsolation(true)]
class OrderRefundedTest extends TestCase
{
    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/enabled', '1', 'store', 'default')]
    #[DataFixture(ProductFixture::class, ['sku' => 'zaraz-refund', 'price' => 10], 'product')]
    #[DataFixture(GuestCartFixture::class, as: 'cart')]
    #[DataFixture(
        AddProductToCartFixture::class,
        ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 3]
    )]
    #[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order')]
    #[DataFixture(InvoiceFixture::class, ['order_id' => '$order.id$'], 'invoice')]
    #[DataFixture(CreditmemoFixture::class, ['order_id' => '$order.id$'], 'creditmemo')]
    public function testRefundIsScheduledAndSent(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $storage = DataFixtureStorageManager::getStorage();
        $creditmemo = $storage->get('creditmemo');
        $order = $storage->get('order');

        $row = $objectManager->create(ServerEventRepositoryInterface::class)
            ->getByReference('order_refunded', (string)$creditmemo->getIncrementId());
        $this->assertSame(ServerEventInterface::STATUS_PENDING, $row->getStatus());
        $this->assertSame((int)$creditmemo->getId(), $row->getSourceId());

        $transport = new FakeTransport();
        $summary = $objectManager->create(Processor::class, [
            'transportPool' => new TransportPool(['zaraz_http' => $transport]),
        ])->process(100, false, [(int)$order->getId()]);

        $this->assertSame(1, $summary['sent']);
        $event = $transport->calls[0][0]->event;
        $this->assertSame('Order Refunded', $event->getName());
        $payload = $event->getPayload();
        $this->assertSame((string)$order->getIncrementId(), $payload['order_id']);
        $this->assertEquals(round((float)$creditmemo->getGrandTotal(), 2), $payload['total']);
        $this->assertSame(3.0, (float)$payload['products'][0]['quantity']);
    }
}
