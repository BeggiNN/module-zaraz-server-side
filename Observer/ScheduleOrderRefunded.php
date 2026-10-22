<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Perspective\ZarazServerSide\Model\Event\EventCode;
use Perspective\ZarazServerSide\Model\Scheduler;

/**
 * sales_order_creditmemo_save_after: Order Refunded for a refunded credit memo (once per credit memo)
 */
class ScheduleOrderRefunded implements ObserverInterface
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
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo instanceof Creditmemo || (int)$creditmemo->getState() !== Creditmemo::STATE_REFUNDED) {
            return;
        }
        $order = $creditmemo->getOrder();
        if ($order === null) {
            return;
        }
        $this->scheduler->schedule(
            EventCode::ORDER_REFUNDED,
            $order,
            (string)$creditmemo->getIncrementId(),
            (int)$creditmemo->getId()
        );
    }
}
