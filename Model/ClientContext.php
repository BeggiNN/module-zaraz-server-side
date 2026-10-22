<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

/**
 * Browser data of the customer who placed the order: identifies the same Zaraz / tool user on the server
 */
class ClientContext
{
    /**
     * @param array $cookies forwarded cookies name => value (Zaraz tool cookies cfz_* / cfzs_*, _ga, _fbp, ...)
     * @param string $ip
     * @param string $userAgent
     * @param string $language first Accept-Language tag
     * @param string $url page of the request that captured the context
     */
    public function __construct(
        public readonly array $cookies = [],
        public readonly string $ip = '',
        public readonly string $userAgent = '',
        public readonly string $language = '',
        public readonly string $url = '',
    ) {
    }

    /**
     * Whether there is nothing to identify the client
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !$this->cookies && $this->userAgent === '';
    }

    /**
     * Data for storage
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'cookies' => $this->cookies,
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'language' => $this->language,
            'url' => $this->url,
        ];
    }
}
