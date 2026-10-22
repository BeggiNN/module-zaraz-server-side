<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

use Laminas\Uri\UriFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * "Server-side events" settings (perspective_zaraz/server/*)
 */
class Config
{
    public const XML_ENABLED = 'perspective_zaraz/server/enabled';
    public const XML_TRANSPORT = 'perspective_zaraz/server/transport';
    public const XML_ZARAZ_ENDPOINT_PATH = 'perspective_zaraz/server/zaraz_endpoint_path';
    public const XML_ZARAZ_ENDPOINT_HOST = 'perspective_zaraz/server/zaraz_endpoint_host';
    public const XML_GA4_MEASUREMENT_ID = 'perspective_zaraz/server/ga4_measurement_id';
    public const XML_GA4_API_SECRET = 'perspective_zaraz/server/ga4_api_secret';
    public const XML_GA4_DEBUG = 'perspective_zaraz/server/ga4_debug';
    public const XML_PURCHASE_DELAY = 'perspective_zaraz/server/purchase_delay';
    public const XML_ADMIN_ORDERS = 'perspective_zaraz/server/admin_orders';
    public const XML_MAX_ATTEMPTS = 'perspective_zaraz/server/max_attempts';
    public const XML_BOT_USER_AGENTS = 'perspective_zaraz/server/bot_user_agents';
    public const XML_FORWARD_COOKIES = 'perspective_zaraz/server/forward_cookies';
    public const XML_RETENTION_DAYS = 'perspective_zaraz/server/retention_days';
    public const XML_LOG_PAYLOADS = 'perspective_zaraz/server/log_payloads';

    public const TRANSPORT_ZARAZ = 'zaraz_http';
    public const TRANSPORT_GA4 = 'ga4_mp';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
    ) {
    }

    /**
     * Whether server-side events are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Transport code: zaraz_http | ga4_mp
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTransport(?int $storeId = null): string
    {
        return $this->value(self::XML_TRANSPORT, $storeId) ?: self::TRANSPORT_ZARAZ;
    }

    /**
     * HTTP Events API path from the Zaraz dashboard (Settings → Endpoints), "/" prefixed
     *
     * @param int|null $storeId
     * @return string
     */
    public function getZarazEndpointPath(?int $storeId = null): string
    {
        $path = trim($this->value(self::XML_ZARAZ_ENDPOINT_PATH, $storeId));
        return $path === '' ? '' : '/' . ltrim($path, '/');
    }

    /**
     * Host of the HTTP Events API; empty = host of the store secure base URL
     *
     * @param int|null $storeId
     * @return string
     */
    public function getZarazEndpointHost(?int $storeId = null): string
    {
        $host = trim($this->value(self::XML_ZARAZ_ENDPOINT_HOST, $storeId));
        if ($host === '') {
            return '';
        }
        return (string)UriFactory::factory(str_contains($host, '//') ? $host : 'https://' . $host)->getHost();
    }

    /**
     * GA4 Measurement ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGa4MeasurementId(?int $storeId = null): string
    {
        return trim($this->value(self::XML_GA4_MEASUREMENT_ID, $storeId));
    }

    /**
     * Decrypted Measurement Protocol API secret
     *
     * @param int|null $storeId
     * @return string
     */
    public function getGa4ApiSecret(?int $storeId = null): string
    {
        $value = $this->value(self::XML_GA4_API_SECRET, $storeId);
        return $value === '' ? '' : trim($this->encryptor->decrypt($value));
    }

    /**
     * Send to the GA4 validation endpoint (nothing is recorded)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isGa4Debug(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_GA4_DEBUG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Minutes to wait for the browser Order Completed before the server sends it
     *
     * @param int|null $storeId
     * @return int
     */
    public function getPurchaseDelay(?int $storeId = null): int
    {
        return max(0, (int)$this->value(self::XML_PURCHASE_DELAY, $storeId));
    }

    /**
     * Whether orders created in the admin are sent
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAdminOrders(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ADMIN_ORDERS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Send attempts before an event is failed
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMaxAttempts(?int $storeId = null): int
    {
        return max(1, (int)$this->value(self::XML_MAX_ATTEMPTS, $storeId));
    }

    /**
     * Lower-case user agent fragments of bots
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getBotUserAgents(?int $storeId = null): array
    {
        return array_map('strtolower', $this->lines(self::XML_BOT_USER_AGENTS, $storeId));
    }

    /**
     * Prefixes of cookies passed to the server-side event
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getForwardCookies(?int $storeId = null): array
    {
        return $this->lines(self::XML_FORWARD_COOKIES, $storeId);
    }

    /**
     * Days to keep processed events and client context
     *
     * @return int
     */
    public function getRetentionDays(): int
    {
        return max(1, (int)$this->value(self::XML_RETENTION_DAYS, null));
    }

    /**
     * Whether request bodies are written to the log
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLogPayloads(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_LOG_PAYLOADS, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Store config value
     *
     * @param string $path
     * @param int|null $storeId
     * @return string
     */
    private function value(string $path, ?int $storeId): string
    {
        return (string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Non-empty lines (commas also separate)
     *
     * @param string $path
     * @param int|null $storeId
     * @return string[]
     */
    private function lines(string $path, ?int $storeId): array
    {
        $values = preg_split('/[\r\n,]+/', $this->value($path, $storeId)) ?: [];
        return array_values(array_filter(array_map('trim', $values), 'strlen'));
    }
}
