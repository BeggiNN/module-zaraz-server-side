<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\ClientContext;

use Magento\Framework\App\Area;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Perspective\ZarazServerSide\Model\ClientContext;
use Perspective\ZarazServerSide\Model\Config;

/**
 * Client context of the current storefront request (frontend pages and the checkout REST calls)
 */
class Collector
{
    private const AREAS = [Area::AREA_FRONTEND, Area::AREA_WEBAPI_REST, Area::AREA_GRAPHQL];

    /**
     * @param RequestInterface $request
     * @param RemoteAddress $remoteAddress
     * @param State $appState
     * @param Config $config
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RemoteAddress $remoteAddress,
        private readonly State $appState,
        private readonly Config $config,
    ) {
    }

    /**
     * Context of the request, or null outside a storefront HTTP request (admin, cron, CLI)
     *
     * @param int|null $storeId
     * @return ClientContext|null
     */
    public function collect(?int $storeId = null): ?ClientContext
    {
        if (!$this->request instanceof HttpRequest || !$this->isStorefront()) {
            return null;
        }
        $context = new ClientContext(
            $this->filterCookies(
                $this->parseCookieHeader((string)$this->request->getHeader('Cookie')),
                $this->config->getForwardCookies($storeId)
            ),
            $this->getClientIp(),
            (string)$this->request->getHeader('User-Agent'),
            $this->getLanguage((string)$this->request->getHeader('Accept-Language')),
            (string)$this->request->getHeader('Referer'),
        );
        return $context->isEmpty() ? null : $context;
    }

    /**
     * Cookies whose name starts with one of the prefixes
     *
     * @param array $cookies
     * @param string[] $prefixes
     * @return array name => value
     */
    public function filterCookies(array $cookies, array $prefixes): array
    {
        $result = [];
        foreach ($cookies as $name => $value) {
            if (!is_scalar($value) || (string)$value === '') {
                continue;
            }
            foreach ($prefixes as $prefix) {
                if (str_starts_with((string)$name, $prefix)) {
                    $result[(string)$name] = (string)$value;
                    break;
                }
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Customer IP
     *
     * Behind a proxy (Cloudflare Tunnel, load balancer, Varnish) the connection address is private,
     * then CF-Connecting-IP or the first public X-Forwarded-For address is used.
     *
     * @return string
     */
    private function getClientIp(): string
    {
        $remote = (string)$this->remoteAddress->getRemoteAddress();
        if ($remote !== '' && $this->isPublic($remote)) {
            return $remote;
        }
        $candidates = array_merge(
            [(string)$this->request->getHeader('CF-Connecting-IP')],
            explode(',', (string)$this->request->getHeader('X-Forwarded-For'))
        );
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && $this->isPublic($candidate)) {
                return $candidate;
            }
        }
        return $remote;
    }

    /**
     * Whether the value is a public IPv4 / IPv6 address
     *
     * @param string $ip
     * @return bool
     */
    private function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Cookies of the "Cookie" request header (the request cookie reader returns single cookies only)
     *
     * @param string $header "name=value; name2=value2"
     * @return array name => decoded value
     */
    public function parseCookieHeader(string $header): array
    {
        $cookies = [];
        foreach (explode(';', $header) as $pair) {
            $parts = explode('=', $pair, 2);
            $name = trim($parts[0]);
            if ($name !== '' && isset($parts[1]) && !isset($cookies[$name])) {
                // phpcs:ignore Magento2.Functions.DiscouragedFunction -- cookie values are URL-encoded
                $cookies[$name] = rawurldecode(trim($parts[1]));
            }
        }
        return $cookies;
    }

    /**
     * First language tag of Accept-Language: "uk-UA,uk;q=0.9,en;q=0.8" → "uk-UA"
     *
     * @param string $header
     * @return string
     */
    private function getLanguage(string $header): string
    {
        $tag = trim(explode(';', explode(',', $header)[0])[0]);
        return preg_match('/^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$/', $tag) ? $tag : '';
    }

    /**
     * Whether the request is a storefront one
     *
     * @return bool
     */
    private function isStorefront(): bool
    {
        try {
            return in_array($this->appState->getAreaCode(), self::AREAS, true);
        } catch (LocalizedException) {
            return false;
        }
    }
}
