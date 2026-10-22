<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Observer;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Model\Event\EventCode;
use Perspective\Zaraz\Model\Event\Generator;
use Perspective\ZarazServerSide\Model\Config;
use Perspective\ZarazServerSide\Model\Scheduler;

/**
 * sales_model_service_quote_submit_success: Order Completed fallback, sent after "Purchase fallback delay"
 * unless the success page reports the browser delivery; sent at once when the browser channel is off
 */
class ScheduleOrderCompleted implements ObserverInterface
{
    /**
     * @param Scheduler $scheduler
     * @param Generator $generator
     * @param Config $config
     * @param State $appState
     */
    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly Generator $generator,
        private readonly Config $config,
        private readonly State $appState,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order instanceof OrderInterface) {
            return;
        }
        $storeId = (int)$order->getStoreId();
        if ($this->isAdmin() && !$this->config->isAdminOrders($storeId)) {
            return;
        }
        $browser = !$this->isAdmin() && $this->generator->isAllowed(
            EventCode::ORDER_COMPLETED,
            EventGeneratorInterface::CHANNEL_BROWSER,
            $storeId
        );

        $this->scheduler->schedule(
            EventCode::ORDER_COMPLETED,
            $order,
            (string)$order->getIncrementId(),
            null,
            $browser ? $this->config->getPurchaseDelay($storeId) : 0
        );
    }

    /**
     * Whether the order is created in the admin
     *
     * @return bool
     */
    private function isAdmin(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
        } catch (LocalizedException) {
            return false;
        }
    }
}
