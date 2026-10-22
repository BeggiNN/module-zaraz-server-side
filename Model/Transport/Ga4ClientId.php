<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Transport;

/**
 * GA4 client / session ids from the forwarded cookies.
 *
 * Zaraz GA4 tool keeps its storage in the cookie `cfz_google-analytics_v4` (session values in `cfzs_…`):
 * JSON {"<tool id>_ga4": {"v": "<client uuid>", "e": <expiry>}, "<tool id>_ga4sid": {"v": "<session id>"}, ...}
 * (checked on a live Zaraz domain). A gtag `_ga` cookie "GA1.1.<random>.<timestamp>" is used when present instead.
 */
class Ga4ClientId
{
    private const ZARAZ_COOKIES = ['cfz_google-analytics_v4', 'cfzs_google-analytics_v4'];

    /**
     * GA4 client id, or null
     *
     * @param array $cookies name => value
     * @return string|null
     */
    public function getClientId(array $cookies): ?string
    {
        $value = $this->findZarazValue($cookies, '_ga4');
        if ($value !== null) {
            return $value;
        }
        if (isset($cookies['_ga']) && preg_match('/^GA\d\.\d+\.(\d+\.\d+)$/', $cookies['_ga'], $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * GA4 session id, or null
     *
     * @param array $cookies name => value
     * @return string|null
     */
    public function getSessionId(array $cookies): ?string
    {
        $value = $this->findZarazValue($cookies, '_ga4sid');
        return $value !== null && ctype_digit($value) ? $value : null;
    }

    /**
     * Value of the first Zaraz storage key that ends with the suffix
     *
     * @param array $cookies name => value
     * @param string $suffix
     * @return string|null
     */
    private function findZarazValue(array $cookies, string $suffix): ?string
    {
        foreach (self::ZARAZ_COOKIES as $name) {
            $raw = (string)($cookies[$name] ?? '');
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- cookie values are URL-encoded
            $data = json_decode($raw, true) ?? json_decode(rawurldecode($raw), true);
            if (!is_array($data)) {
                continue;
            }
            foreach ($data as $key => $item) {
                if (str_ends_with((string)$key, $suffix) && isset($item['v']) && is_scalar($item['v'])
                    && (string)$item['v'] !== ''
                ) {
                    return (string)$item['v'];
                }
            }
        }
        return null;
    }
}
