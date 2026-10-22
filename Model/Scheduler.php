<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Model\Event\Generator;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterfaceFactory;
use Perspective\ZarazServerSide\Api\ServerEventRepositoryInterface;
use Perspective\ZarazServerSide\Model\ClientContext\Storage;
use Psr\Log\LoggerInterface;

/**
 * Adds a server-side event row for an order; never breaks the order / credit memo flow (errors are logged)
 */
class Scheduler
{
    /**
     * @param Config $config
     * @param Generator $generator
     * @param Storage $storage
     * @param BotDetector $botDetector
     * @param ServerEventRepositoryInterface $repository
     * @param ServerEventInterfaceFactory $eventFactory
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly Generator $generator,
        private readonly Storage $storage,
        private readonly BotDetector $botDetector,
        private readonly ServerEventRepositoryInterface $repository,
        private readonly ServerEventInterfaceFactory $eventFactory,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Whether the store sends the event from the server
     *
     * @param string $code
     * @param int $storeId
     * @return bool
     */
    public function isActive(string $code, int $storeId): bool
    {
        return $this->config->isEnabled($storeId)
            && $this->generator->isAllowed($code, EventGeneratorInterface::CHANNEL_SERVER, $storeId);
    }

    /**
     * Add the event row for the order (idempotent)
     *
     * @param string $code
     * @param OrderInterface $order
     * @param string $reference order / credit memo increment id
     * @param int|null $sourceId credit memo id
     * @param int $delayMinutes
     * @return void
     */
    public function schedule(
        string $code,
        OrderInterface $order,
        string $reference,
        ?int $sourceId = null,
        int $delayMinutes = 0,
    ): void {
        $storeId = (int)$order->getStoreId();
        if (!$order->getEntityId() || $reference === '' || !$this->isActive($code, $storeId)) {
            return;
        }
        $client = $this->storage->read($order);
        $bot = $client !== null ? $this->botDetector->match($client, $storeId) : null;

        /** @var ServerEventInterface $event */
        $event = $this->eventFactory->create();
        $event->setEventCode($code)
            ->setReference($reference)
            ->setOrderId((int)$order->getEntityId())
            ->setSourceId($sourceId)
            ->setStoreId($storeId)
            ->setStatus($bot === null ? ServerEventInterface::STATUS_PENDING : ServerEventInterface::STATUS_SKIPPED)
            ->setLastError($bot === null ? null : sprintf('Bot user agent ("%s")', $bot))
            ->setScheduledAt($this->dateTime->gmtDate(null, time() + $delayMinutes * 60));
        try {
            $this->repository->add($event);
        } catch (CouldNotSaveException $e) {
            $this->logger->error($e->getMessage(), ['event' => $code, 'reference' => $reference]);
        }
    }
}
