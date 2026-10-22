<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Unit\Model\Transport;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Event\Event;
use Perspective\ZarazServerSide\Model\ClientContext;
use Perspective\ZarazServerSide\Model\Config;
use Perspective\ZarazServerSide\Model\Transport\HttpPost;
use Perspective\ZarazServerSide\Model\Transport\Message;
use Perspective\ZarazServerSide\Model\Transport\Result;
use Perspective\ZarazServerSide\Model\Transport\ZarazHttpEvents;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ZarazHttpEventsTest extends TestCase
{
    /**
     * @var HttpPost|MockObject
     */
    private HttpPost|MockObject $http;

    /**
     * @param string $path
     * @return ZarazHttpEvents
     */
    private function createTransport(string $path = '/zaraz/api-test'): ZarazHttpEvents
    {
        $config = $this->createMock(Config::class);
        $config->method('getZarazEndpointPath')->willReturn($path);
        $config->method('getZarazEndpointHost')->willReturn('');
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example.com/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->http = $this->createMock(HttpPost::class);

        return new ZarazHttpEvents(
            $config,
            $storeManager,
            $this->http,
            new Json(),
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @return Message[]
     */
    private function messages(): array
    {
        return [
            7 => new Message(
                new Event('ecommerce', 'Order Completed', ['order_id' => '000000014', 'total' => 214.0]),
                new ClientContext(
                    ['cfz_google-analytics_v4' => '{"x_ga4":{"v":"id"}}'],
                    '203.0.113.5',
                    'Mozilla/5.0',
                    'uk-UA',
                    'https://shop.example.com/checkout/'
                ),
                1791900000
            ),
            9 => new Message(new Event('track', 'custom_event', ['a' => 1]), new ClientContext(), 1791900000),
        ];
    }

    /**
     * @return void
     */
    public function testBatchRequestBody(): void
    {
        $transport = $this->createTransport();
        $this->http->expects($this->once())
            ->method('post')
            ->with(
                'https://shop.example.com/zaraz/api-test',
                $this->callback(function (string $body): bool {
                    $events = json_decode($body, true)['events'];
                    $this->assertSame(
                        [
                            'client' => [
                                '__zarazTrack' => 'Order Completed',
                                'order_id' => '000000014',
                                'total' => 214,
                                '__zarazEcommerce' => true,
                            ],
                            'system' => [
                                'cookies' => ['cfz_google-analytics_v4' => '{"x_ga4":{"v":"id"}}'],
                                'device' => [
                                    'ip' => '203.0.113.5',
                                    'user-agent' => 'Mozilla/5.0',
                                    'language' => 'uk-UA',
                                ],
                                'page' => ['url' => 'https://shop.example.com/checkout/'],
                            ],
                        ],
                        $events[0]
                    );
                    $this->assertSame(['__zarazTrack' => 'custom_event', 'a' => 1], $events[1]['client']);
                    return true;
                })
            )
            ->willReturn(['status' => 200, 'body' => '[{},{}]']);

        $results = $transport->send($this->messages(), 1);

        $this->assertSame([7, 9], array_keys($results));
        $this->assertSame(Result::SENT, $results[7]->status);
    }

    /**
     * @return void
     */
    public function testAccessRedirectIsRetriedError(): void
    {
        $transport = $this->createTransport();
        $this->http->method('post')->willReturn(['status' => 302, 'body' => '']);

        $result = $transport->send($this->messages(), 1)[7];

        $this->assertSame(Result::ERROR, $result->status);
        $this->assertStringContainsString('Cloudflare Access', $result->message);
    }

    /**
     * @return void
     */
    public function testEmptyPath(): void
    {
        $transport = $this->createTransport('');
        $this->http->expects($this->never())->method('post');

        $this->assertSame(Result::ERROR, $transport->send($this->messages(), 1)[9]->status);
    }
}
