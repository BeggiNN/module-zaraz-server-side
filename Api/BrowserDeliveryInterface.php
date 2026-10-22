<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Api;

/**
 * The browser delivered Order Completed to Zaraz: the server fallback is not needed
 *
 * @api
 */
interface BrowserDeliveryInterface
{
    /**
     * Mark the pending Order Completed of the order as delivered by the browser
     *
     * @param string $incrementId
     * @return bool false when there is no pending server event for the order
     */
    public function markDelivered(string $incrementId): bool;
}
