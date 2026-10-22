<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Integration\Model;

use Laminas\Http\Headers;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress as SetBillingAddressFixture;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetGuestEmail as SetGuestEmailFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress as SetShippingAddressFixture;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Test\Fixture\AddProductToCart as AddProductToCartFixture;
use Magento\Quote\Test\Fixture\GuestCart as GuestCartFixture;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Perspective\ZarazServerSide\Api\BrowserDeliveryInterface;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;
use Perspective\ZarazServerSide\Api\ServerEventRepositoryInterface;
use Perspective\ZarazServerSide\Model\ClientContext\Storage;
use Perspective\ZarazServerSide\Model\Processor;
use Perspective\ZarazServerSide\Model\Transport\Result;
use Perspective\ZarazServerSide\Model\Transport\TransportPool;
use Perspective\ZarazServerSide\Test\Integration\FakeTransport;
use PHPUnit\Framework\TestCase;

/**
 * Order Completed fallback: scheduled on placement with the browser context, cancelled by the browser delivery,
 * otherwise sent by the processor with retries
 */
#[AppArea('frontend')]
#[DbIsolation(true)]
#[DataFixture(ProductFixture::class, ['sku' => 'zaraz-server', 'price' => 10], 'product')]
#[DataFixture(GuestCartFixture::class, as: 'cart')]
#[DataFixture(AddProductToCartFixture::class, ['cart_id' => '$cart.id$', 'product_id' => '$product.id$', 'qty' => 2])]
#[DataFixture(SetBillingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetShippingAddressFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetGuestEmailFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$cart.id$'])]
#[DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$cart.id$'])]
class OrderCompletedFallbackTest extends TestCase
{
    private const GA4_COOKIE = '{"nQAh_ga4":{"v":"0a1b2c3d-client","e":1791999999999},"nQAh_ga4sid":{"v":"123"}}';

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager;

    /**
     * @var FakeTransport
     */
    private FakeTransport $transport;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->transport = new FakeTransport();
    }

    /**
     * Place the fixture cart from a "browser" request
     *
     * @param string $userAgent
     * @return Order
     */
    private function placeOrder(string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130.0'): Order
    {
        /** @var \Magento\TestFramework\Request $request */
        $request = $this->objectManager->get(RequestInterface::class);
        // The test request is shared between tests: replace the headers instead of adding duplicates
        $request->setHeaders((new Headers())->addHeaders([
            'Cookie' => 'PHPSESSID=session; cfz_google-analytics_v4=' . rawurlencode(self::GA4_COOKIE),
            'User-Agent' => $userAgent,
            'Accept-Language' => 'uk-UA,uk;q=0.9',
        ]));
        $cartId = (int)DataFixtureStorageManager::getStorage()->get('cart')->getId();
        $payment = $this->objectManager->get(PaymentMethodManagementInterface::class)->get($cartId);
        $orderId = $this->objectManager->get(CartManagementInterface::class)->placeOrder($cartId, $payment);

        return $this->objectManager->get(OrderRepositoryInterface::class)->get((int)$orderId);
    }

    /**
     * @param Order $order
     * @return ServerEventInterface
     */
    private function getRow(Order $order): ServerEventInterface
    {
        return $this->objectManager->create(ServerEventRepositoryInterface::class)
            ->getByReference('order_completed', (string)$order->getIncrementId());
    }

    /**
     * @param bool $ignoreSchedule
     * @return array
     */
    private function process(bool $ignoreSchedule = true): array
    {
        return $this->objectManager->create(Processor::class, [
            'transportPool' => new TransportPool(['zaraz_http' => $this->transport]),
        ])->process(100, $ignoreSchedule);
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/enabled', '1', 'store', 'default')]
    public function testScheduledWithContextAndSentWhenTheBrowserDidNotDeliver(): void
    {
        $order = $this->placeOrder();
        $row = $this->getRow($order);

        $this->assertSame(ServerEventInterface::STATUS_PENDING, $row->getStatus());
        $this->assertGreaterThan(time() + 19 * 60, strtotime($row->getScheduledAt() . ' UTC'));
        $context = $this->objectManager->get(Storage::class)->read($order);
        $this->assertSame(['cfz_google-analytics_v4' => self::GA4_COOKIE], $context->cookies);
        $this->assertSame('uk-UA', $context->language);

        $this->process(false);
        $this->assertSame([], $this->transport->calls, 'not due yet');

        $summary = $this->process();
        $this->assertSame(1, $summary['sent']);
        $message = $this->transport->calls[0][0];
        $this->assertSame('Order Completed', $message->event->getName());
        $this->assertSame((string)$order->getIncrementId(), $message->event->getPayload()['order_id']);
        $this->assertSame(
            'order-' . $order->getIncrementId(),
            $message->event->getPayload()['event_id'],
            'the same event_id as the browser Order Completed'
        );
        $this->assertSame(2.0, (float)$message->event->getPayload()['products'][0]['quantity']);
        $this->assertSame(self::GA4_COOKIE, $message->client->cookies['cfz_google-analytics_v4']);
        $this->assertSame(ServerEventInterface::STATUS_SENT, $this->getRow($order)->getStatus());
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/enabled', '1', 'store', 'default')]
    public function testBrowserDeliveryCancelsTheFallback(): void
    {
        $order = $this->placeOrder();

        $delivery = $this->objectManager->get(BrowserDeliveryInterface::class);
        $this->assertTrue($delivery->markDelivered((string)$order->getIncrementId()));
        $this->assertFalse($delivery->markDelivered((string)$order->getIncrementId()), 'only pending rows');

        $this->process();
        $this->assertSame([], $this->transport->calls);
        $this->assertSame(ServerEventInterface::STATUS_DELIVERED, $this->getRow($order)->getStatus());
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/max_attempts', '2', 'store', 'default')]
    #[Config('perspective_zaraz/server/enabled', '1', 'store', 'default')]
    public function testRetryWithBackoffThenFailed(): void
    {
        $order = $this->placeOrder();
        $this->transport->result = new Result(Result::ERROR, 'HTTP Events API responded 503');

        $this->assertSame(1, $this->process()['retry']);
        $row = $this->getRow($order);
        $this->assertSame(ServerEventInterface::STATUS_PENDING, $row->getStatus());
        $this->assertSame(1, $row->getAttempts());
        $this->assertGreaterThan(time() + 4 * 60, strtotime($row->getScheduledAt() . ' UTC'));
        $this->assertSame('HTTP Events API responded 503', $row->getLastError());

        $this->assertSame(1, $this->process()['failed']);
        $this->assertSame(ServerEventInterface::STATUS_FAILED, $this->getRow($order)->getStatus());
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/enabled', '1', 'store', 'default')]
    public function testBotOrderIsSkipped(): void
    {
        $row = $this->getRow($this->placeOrder('Mozilla/5.0 (compatible; Googlebot/2.1)'));

        $this->assertSame(ServerEventInterface::STATUS_SKIPPED, $row->getStatus());
        $this->assertStringContainsString('bot', (string)$row->getLastError());
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/server/enabled', '0', 'store', 'default')]
    public function testNothingIsScheduledWhenDisabled(): void
    {
        $order = $this->placeOrder();

        $this->expectException(\Magento\Framework\Exception\NoSuchEntityException::class);
        $this->getRow($order);
    }
}
