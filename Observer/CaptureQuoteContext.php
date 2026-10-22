<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Perspective\Zaraz\Model\Event\EventCode;
use Perspective\ZarazServerSide\Model\ClientContext\Collector;
use Perspective\ZarazServerSide\Model\ClientContext\Storage;
use Perspective\ZarazServerSide\Model\Scheduler;
use Psr\Log\LoggerInterface;

/**
 * controller_action_predispatch_checkout_index_index: keep the browser context on the quote, for orders placed
 * later without the customer's browser (payment gateway callbacks)
 */
class CaptureQuoteContext implements ObserverInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param Collector $collector
     * @param Storage $storage
     * @param Scheduler $scheduler
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly Collector $collector,
        private readonly Storage $storage,
        private readonly Scheduler $scheduler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        if (!$this->checkoutSession->hasQuote()) {
            return;
        }
        try {
            $quote = $this->checkoutSession->getQuote();
            $storeId = (int)$quote->getStoreId();
            if (!$quote->getId() || !$this->scheduler->isActive(EventCode::ORDER_COMPLETED, $storeId)) {
                return;
            }
            $context = $this->collector->collect($storeId);
            if ($context !== null) {
                $this->storage->saveToQuote($quote, $context);
            }
        } catch (LocalizedException $e) {
            $this->logger->error('Cannot store the Zaraz client context on the quote: ' . $e->getMessage());
        }
    }
}
