<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Unit\Model\ClientContext;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\State;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Perspective\ZarazServerSide\Model\BotDetector;
use Perspective\ZarazServerSide\Model\ClientContext;
use Perspective\ZarazServerSide\Model\ClientContext\Collector;
use Perspective\ZarazServerSide\Model\Config;
use PHPUnit\Framework\TestCase;

class CollectorTest extends TestCase
{
    /**
     * @param string $area
     * @param string $remoteIp
     * @return Collector
     */
    private function createCollector(string $area = 'webapi_rest', string $remoteIp = '8.8.8.8'): Collector
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getHeader')->willReturnMap([
            [
                'Cookie',
                false,
                'PHPSESSID=secret-session; cfz_google-analytics_v4=%7B%22a_ga4%22%3A%7B%22v%22%3A%221%22%7D%7D; '
                . 'cfzs_google-analytics_v4={}; _fbp=fb.1.1.2; cf_zaraz_client=; form_key=abc',
            ],
            ['User-Agent', false, 'Mozilla/5.0 Chrome/130'],
            ['Accept-Language', false, 'uk-UA,uk;q=0.9,en;q=0.8'],
            ['Referer', false, 'https://shop.example.com/checkout/'],
            ['CF-Connecting-IP', false, '8.8.4.4'],
            ['X-Forwarded-For', false, '10.0.0.2, 1.1.1.1'],
        ]);
        $remoteAddress = $this->createMock(RemoteAddress::class);
        $remoteAddress->method('getRemoteAddress')->willReturn($remoteIp);
        $state = $this->createMock(State::class);
        $state->method('getAreaCode')->willReturn($area);
        $config = $this->createMock(Config::class);
        $config->method('getForwardCookies')->willReturn(['cfz_', 'cfzs_', 'cf_zaraz', '_fbp']);

        return new Collector($request, $remoteAddress, $state, $config);
    }

    /**
     * Only Zaraz / tool cookies are kept (never the Magento session), empty ones dropped
     *
     * @return void
     */
    public function testCollectCheckoutRequest(): void
    {
        $context = $this->createCollector()->collect(1);

        $this->assertSame(
            [
                '_fbp' => 'fb.1.1.2',
                'cfz_google-analytics_v4' => '{"a_ga4":{"v":"1"}}',
                'cfzs_google-analytics_v4' => '{}',
            ],
            $context->cookies
        );
        $this->assertSame('8.8.8.8', $context->ip);
        $this->assertSame('uk-UA', $context->language);
        $this->assertSame('https://shop.example.com/checkout/', $context->url);
    }

    /**
     * Behind Cloudflare Tunnel the connection comes from a private address
     *
     * @return void
     */
    public function testClientIpBehindProxy(): void
    {
        $this->assertSame('8.8.4.4', $this->createCollector('webapi_rest', '172.20.0.5')->collect(1)->ip);
    }

    /**
     * @return void
     */
    public function testNoContextInAdmin(): void
    {
        $this->assertNull($this->createCollector('adminhtml')->collect(1));
    }

    /**
     * @return void
     */
    public function testBotDetector(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getBotUserAgents')->willReturn(['bot', 'headless']);
        $detector = new BotDetector($config);

        $googlebot = new ClientContext([], '', 'Mozilla/5.0 (compatible; Googlebot/2.1)');
        $this->assertSame('bot', $detector->match($googlebot, 1));
        $this->assertSame('headless', $detector->match(new ClientContext([], '', 'HeadlessChrome/120'), 1));
        $this->assertNull($detector->match(new ClientContext([], '', 'Mozilla/5.0 Chrome/130'), 1));
        $this->assertNull($detector->match(new ClientContext(), 1));
    }
}
