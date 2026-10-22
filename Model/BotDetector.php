<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

/**
 * Zaraz HTTP Events API does not apply Cloudflare bot detection (changelog 2024-08-15), so the module filters bots
 */
class BotDetector
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * Matched fragment of the "Bot user agents" setting, or null for a regular browser
     *
     * @param ClientContext $context
     * @param int $storeId
     * @return string|null
     */
    public function match(ClientContext $context, int $storeId): ?string
    {
        $userAgent = strtolower($context->userAgent);
        if ($userAgent === '') {
            return null;
        }
        foreach ($this->config->getBotUserAgents($storeId) as $fragment) {
            if (str_contains($userAgent, $fragment)) {
                return $fragment;
            }
        }
        return null;
    }
}
