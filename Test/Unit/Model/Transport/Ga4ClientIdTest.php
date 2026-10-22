<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Unit\Model\Transport;

use Perspective\ZarazServerSide\Model\Transport\Ga4ClientId;
use PHPUnit\Framework\TestCase;

class Ga4ClientIdTest extends TestCase
{
    /**
     * Same structure as the cookie on a live Zaraz domain (values replaced)
     *
     * @return array
     */
    private function zarazCookies(): array
    {
        return [
            'cfz_google-analytics_v4' => rawurlencode((string)json_encode([
                'nQAh_engagementDuration' => ['v' => '0', 'e' => 1791999999999],
                'nQAh_engagementStart' => ['v' => '1791900000000', 'e' => 1791999999999],
                'nQAh_counter' => ['v' => '12', 'e' => 1791999999999],
                'nQAh_ga4' => ['v' => '0a1b2c3d-1234-41ab-a12b-12abc3defabc', 'e' => 1791999999999],
                'nQAh_let' => ['v' => '1791900000000', 'e' => 1791999999999],
                'nQAh_ga4sid' => ['v' => '87654321', 'e' => 1791999999999],
            ])),
            'cfzs_google-analytics_v4' => '{"nQAh_pageviewCounter":{"v":"3"}}',
        ];
    }

    /**
     * @return void
     */
    public function testZarazGa4Storage(): void
    {
        $parser = new Ga4ClientId();

        $this->assertSame('0a1b2c3d-1234-41ab-a12b-12abc3defabc', $parser->getClientId($this->zarazCookies()));
        $this->assertSame('87654321', $parser->getSessionId($this->zarazCookies()));
    }

    /**
     * @return void
     */
    public function testGtagCookieAndMissingValues(): void
    {
        $parser = new Ga4ClientId();

        $this->assertSame('1234567890.1700000000', $parser->getClientId(['_ga' => 'GA1.1.1234567890.1700000000']));
        $this->assertNull($parser->getClientId([]));
        $this->assertNull($parser->getClientId(['cfz_google-analytics_v4' => 'not json']));
        $this->assertNull($parser->getSessionId(['_ga' => 'GA1.1.1234567890.1700000000']));
    }
}
