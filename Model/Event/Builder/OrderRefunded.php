<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Event\Builder;

use Magento\Sales\Api\Data\CreditmemoInterface;
use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\OrderTotals;
use Perspective\Zaraz\Model\Price\PriceResolver;

/**
 * "Order Refunded" — a credit memo (context data "creditmemo"); amounts follow "Prices and totals" like Order
 * Completed, products are the refunded items with refunded quantities (GA4 "refund": partial refund)
 */
class OrderRefunded implements EventBuilderInterface
{
    public const CONTEXT_CREDITMEMO = 'creditmemo';

    /**
     * @param LineResolver $lineResolver
     * @param ProductMapper $productMapper
     * @param PriceResolver $priceResolver
     * @param OrderTotals $orderTotals
     */
    public function __construct(
        private readonly LineResolver $lineResolver,
        private readonly ProductMapper $productMapper,
        private readonly PriceResolver $priceResolver,
        private readonly OrderTotals $orderTotals,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Order Refunded';
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
        $creditmemo = $context->getData(self::CONTEXT_CREDITMEMO);
        if ($order === null || !$creditmemo instanceof CreditmemoInterface) {
            return null;
        }
        $orderItems = [];
        foreach ($order->getItems() as $orderItem) {
            $orderItems[(int)$orderItem->getItemId()] = $orderItem;
        }
        $lines = [];
        foreach ($creditmemo->getItems() as $item) {
            $orderItem = $orderItems[(int)$item->getOrderItemId()] ?? null;
            if ($orderItem === null || $orderItem->getParentItemId() || (float)$item->getQty() <= 0.0) {
                continue;
            }
            $lines[] = $this->lineResolver->fromOrderItem($orderItem, (float)$item->getQty());
        }

        $data = [
            'order_id' => (string)$order->getIncrementId(),
            ...$this->orderTotals->calculate($creditmemo),
            'currency' => $this->priceResolver->getOrderCurrency($order),
            'products' => $this->productMapper->mapList($lines, (int)$order->getStoreId()),
        ];
        return array_filter($data, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
