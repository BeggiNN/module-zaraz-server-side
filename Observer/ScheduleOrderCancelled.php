<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Perspective\ZarazServerSide\Model\Event\EventCode;
use Perspective\ZarazServerSide\Model\Scheduler;

/**
 * order_cancel_after: Order Cancelled (off unless enabled in "Events")
 */
class ScheduleOrderCancelled implements ObserverInterface
{
    /**
     * @param Scheduler $scheduler
     */
    public function __construct(
        private readonly Scheduler $scheduler,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        if ($order instanceof OrderInterface) {
            $this->scheduler->schedule(EventCode::ORDER_CANCELLED, $order, (string)$order->getIncrementId());
        }
    }
}
