<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Perspective\ZarazServerSide\Model\ClientContext\Collector;
use Perspective\ZarazServerSide\Model\ClientContext\Storage;

/**
 * sales_model_service_quote_submit_before: client context of the placing request (checkout REST call),
 * or the one kept on the quote; saved with the order
 */
class AssignOrderContext implements ObserverInterface
{
    /**
     * @param Collector $collector
     * @param Storage $storage
     */
    public function __construct(
        private readonly Collector $collector,
        private readonly Storage $storage,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getData('order');
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        if (!$order) {
            return;
        }
        $storeId = (int)$order->getStoreId();
        $context = $this->collector->collect($storeId);
        if ($context === null || !$context->cookies) {
            $context = ($quote ? $this->storage->read($quote) : null) ?? $context;
        }
        if ($context !== null) {
            $this->storage->assign($order, $context);
        }
    }
}
