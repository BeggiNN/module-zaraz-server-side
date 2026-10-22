<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Price\OrderTotals;
use Perspective\Zaraz\Model\Price\PriceResolver;

/**
 * "Order Cancelled" — no GA4 / Meta / TikTok mapping in Zaraz, useful for custom tools; off by default
 */
class OrderCancelled implements EventBuilderInterface
{
    /**
     * @param PriceResolver $priceResolver
     * @param OrderTotals $orderTotals
     */
    public function __construct(
        private readonly PriceResolver $priceResolver,
        private readonly OrderTotals $orderTotals,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Order Cancelled';
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return EventInterface::METHOD_ECOMMERCE;
    }

    /**
     * @inheritDoc
     */
    public function build(EventContextInterface $context): ?array
    {
        $order = $context->getOrder();
        if ($order === null) {
            return null;
        }
        return [
            'order_id' => (string)$order->getIncrementId(),
            ...$this->orderTotals->calculate($order),
            'currency' => $this->priceResolver->getOrderCurrency($order),
        ];
    }
}
