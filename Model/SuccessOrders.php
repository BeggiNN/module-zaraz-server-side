<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

use Magento\Checkout\Model\Session as CheckoutSession;

/**
 * Orders the visitor placed in this checkout session
 */
class SuccessOrders
{
    /** Written by Perspective\Zaraz\Model\Page\SuccessPageContext */
    private const RENDERED_ORDERS = 'perspective_zaraz_rendered_orders';

    /**
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
    ) {
    }

    /**
     * Whether the visitor placed the order in this session
     *
     * @param string $incrementId
     * @return bool
     */
    public function isSessionOrder(string $incrementId): bool
    {
        if ((string)$this->checkoutSession->getLastRealOrderId() === $incrementId) {
            return true;
        }
        return in_array($incrementId, (array)$this->checkoutSession->getData(self::RENDERED_ORDERS), true);
    }

    /**
     * Increment id of the last placed order
     *
     * @return string
     */
    public function getLastOrderIncrementId(): string
    {
        return (string)$this->checkoutSession->getLastRealOrderId();
    }
}
