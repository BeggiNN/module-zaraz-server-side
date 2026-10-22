<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Transport;

use Magento\Framework\HTTP\Client\CurlFactory;

/**
 * JSON POST without following redirects (a redirect here is Cloudflare Access / a login page, i.e. a failure)
 */
class HttpPost
{
    private const TIMEOUT = 10;

    /**
     * @param CurlFactory $curlFactory
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
    ) {
    }

    /**
     * POST a JSON body
     *
     * @param string $url
     * @param string $body
     * @param array $headers name => value
     * @return array{status: int, body: string}
     * @throws \Exception transport error (DNS, TLS, timeout)
     */
    public function post(string $url, string $body, array $headers = []): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout(self::TIMEOUT);
        $curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $curl->setHeaders(['Content-Type' => 'application/json'] + $headers);
        $curl->post($url, $body);

        return ['status' => (int)$curl->getStatus(), 'body' => (string)$curl->getBody()];
    }
}
