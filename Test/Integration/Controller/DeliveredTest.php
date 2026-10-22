<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Integration\Controller;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Test\Fixture\PlaceOrder as PlaceOrderFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractController;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;
use Perspective\ZarazServerSide\Api\ServerEventRepositoryInterface;

/**
 * The success page report marks the fallback delivered only for an order of the visitor's session
 */
#[DbIsolation(true)]
class DeliveredTest extends AbstractController
{
    /**
     * Increment id of the fixture order; its Order Completed row was scheduled on placement
     *
     * @return string
     */
    private function prepare(): string
    {
        $order = DataFixtureStorageManager::getStorage()->get('order');
        $repository = $this->_objectManager->create(ServerEventRepositoryInterface::class);
        $repository->getByReference('order_completed', (string)$order->getIncrementId());
        return (string)$order->getIncrementId();
    }

    /**
     * @param string $incrementId
     * @return array
     */
    private function post(string $incrementId): array
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue([
            'order' => $incrementId,
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
        ]);
        $this->dispatch('perspective_zaraz_server/order/delivered');
        return json_decode($this->getResponse()->getBody(), true);
    }

    /**
     * @param string $incrementId
     * @return string
     */
    private function getRowStatus(string $incrementId): string
    {
        return $this->_objectManager->create(ServerEventRepositoryInterface::class)
            ->getByReference('order_completed', $incrementId)
            ->getStatus();
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/enabled', '1', 'store', 'default')]
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    #[DataFixture(GuestCartFixture::class, as: 'cart')]
    #[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$'])]
    #[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order')]
    public function testSessionOrderIsMarkedDelivered(): void
    {
        $incrementId = $this->prepare();
        $this->_objectManager->get(CheckoutSession::class)->setLastRealOrderId($incrementId);

        $this->assertSame(['delivered' => true], $this->post($incrementId));
        $this->assertSame(ServerEventInterface::STATUS_DELIVERED, $this->getRowStatus($incrementId));
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/enabled', '1', 'store', 'default')]
    #[DataFixture(ProductFixture::class, ['price' => 10], 'product')]
    #[DataFixture(GuestCartFixture::class, as: 'cart')]
    #[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$'])]
    #[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
    #[DataFixture(PlaceOrderFixture::class, ['cart_id' => '$cart.id$'], 'order')]
    public function testOrderOfAnotherVisitorIsIgnored(): void
    {
        $incrementId = $this->prepare();
        $this->_objectManager->get(CheckoutSession::class)->setLastRealOrderId('999999999');

        $this->assertSame(['delivered' => false], $this->post($incrementId));
        $this->assertSame(ServerEventInterface::STATUS_PENDING, $this->getRowStatus($incrementId));
    }
}
