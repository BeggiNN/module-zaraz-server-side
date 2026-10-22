<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\App\Emulation;
use Perspective\Zaraz\Api\EventGeneratorInterface;
use Perspective\Zaraz\Model\Event\ContextFactory;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;
use Perspective\ZarazServerSide\Api\ServerEventRepositoryInterface;
use Perspective\ZarazServerSide\Model\ClientContext\Storage;
use Perspective\ZarazServerSide\Model\Event\Builder\OrderRefunded;
use Perspective\ZarazServerSide\Model\Event\EventCode;
use Perspective\ZarazServerSide\Model\Transport\Message;
use Perspective\ZarazServerSide\Model\Transport\Result;
use Perspective\ZarazServerSide\Model\Transport\TransportPool;
use Psr\Log\LoggerInterface;

/**
 * Sends due server-side events: one batch per store, retries with exponential backoff
 */
class Processor
{
    private const BACKOFF_MINUTES = 5;
    private const BACKOFF_MAX_MINUTES = 360;

    /**
     * @param ServerEventRepositoryInterface $repository
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param EventGeneratorInterface $generator
     * @param ContextFactory $contextFactory
     * @param Storage $storage
     * @param TransportPool $transportPool
     * @param Config $config
     * @param Emulation $emulation
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ServerEventRepositoryInterface $repository,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly CreditmemoRepositoryInterface $creditmemoRepository,
        private readonly EventGeneratorInterface $generator,
        private readonly ContextFactory $contextFactory,
        private readonly Storage $storage,
        private readonly TransportPool $transportPool,
        private readonly Config $config,
        private readonly Emulation $emulation,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Send due events
     *
     * @param int $limit
     * @param bool $ignoreSchedule send pending events before their time (CLI)
     * @param int[] $orderIds only these orders
     * @param bool $dryRun build request bodies, send and change nothing
     * @return array{sent: int, retry: int, failed: int, skipped: int, requests: array}
     */
    public function process(
        int $limit = 200,
        bool $ignoreSchedule = false,
        array $orderIds = [],
        bool $dryRun = false,
    ): array {
        $summary = ['sent' => 0, 'retry' => 0, 'failed' => 0, 'skipped' => 0, 'requests' => []];
        $rows = $this->repository->getDue($limit, $ignoreSchedule, $orderIds);
        if (!$rows) {
            return $summary;
        }
        $orders = $this->loadOrders($rows);

        $byStore = [];
        foreach ($rows as $row) {
            $byStore[$row->getStoreId()][] = $row;
        }
        foreach ($byStore as $storeId => $storeRows) {
            $this->processStore((int)$storeId, $storeRows, $orders, $dryRun, $summary);
        }
        return $summary;
    }

    /**
     * Generate and send the events of one store
     *
     * @param int $storeId
     * @param ServerEventInterface[] $rows
     * @param OrderInterface[] $orders
     * @param bool $dryRun
     * @param array $summary
     * @return void
     */
    private function processStore(int $storeId, array $rows, array $orders, bool $dryRun, array &$summary): void
    {
        $messages = [];
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        try {
            foreach ($rows as $key => $row) {
                $message = $this->createMessage($row, $orders[$row->getOrderId()] ?? null, $reason);
                if ($message === null) {
                    $this->finish($row, ServerEventInterface::STATUS_SKIPPED, $reason, $dryRun, $summary);
                    continue;
                }
                $messages[$key] = $message;
            }
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
        if (!$messages) {
            return;
        }
        try {
            $transport = $this->transportPool->get($this->config->getTransport($storeId));
        } catch (LocalizedException $e) {
            $error = new Result(Result::ERROR, $e->getMessage());
            $results = array_map(static fn (): Result => $error, $messages);
        }
        if ($dryRun && isset($transport)) {
            $summary['requests'][$storeId] = $transport->preview($messages, $storeId);
            return;
        }
        $results ??= $transport->send($messages, $storeId);
        foreach ($messages as $key => $message) {
            $result = $results[$key] ?? new Result(Result::ERROR, 'No transport result');
            $this->apply($rows[$key], $result, $dryRun, $summary);
        }
    }

    /**
     * Event + client context of a row, or null when the row is skipped
     *
     * @param ServerEventInterface $row
     * @param OrderInterface|null $order
     * @param string|null $reason skip reason when null is returned
     * @return Message|null
     */
    private function createMessage(ServerEventInterface $row, ?OrderInterface $order, ?string &$reason): ?Message
    {
        $reason = null;
        if ($order === null) {
            $reason = 'Order does not exist';
            return null;
        }
        if (!$this->config->isEnabled($row->getStoreId())) {
            $reason = 'Server-side events are disabled for the store';
            return null;
        }
        $data = [];
        $createdAt = (string)$order->getCreatedAt();
        if ($row->getEventCode() === EventCode::ORDER_REFUNDED) {
            try {
                $creditmemo = $this->creditmemoRepository->get((int)$row->getSourceId());
            } catch (LocalizedException) {
                $reason = 'Credit memo does not exist';
                return null;
            }
            $data[OrderRefunded::CONTEXT_CREDITMEMO] = $creditmemo;
            $createdAt = (string)$creditmemo->getCreatedAt();
        }
        $event = $this->generator->generate(
            $row->getEventCode(),
            $this->contextFactory->create(['storeId' => $row->getStoreId(), 'order' => $order, 'data' => $data]),
            EventGeneratorInterface::CHANNEL_SERVER,
            $row->getEventCode() . '-' . $row->getReference()
        );
        if ($event === null) {
            $reason = 'Not generated: disabled in "Events" or filtered by the "Orders" settings';
            return null;
        }
        return new Message(
            $event,
            $this->storage->read($order) ?? new ClientContext(),
            (int)(strtotime($createdAt . ' UTC') ?: time())
        );
    }

    /**
     * Row status from the transport result
     *
     * @param ServerEventInterface $row
     * @param Result $result
     * @param bool $dryRun
     * @param array $summary
     * @return void
     */
    private function apply(ServerEventInterface $row, Result $result, bool $dryRun, array &$summary): void
    {
        if ($result->status === Result::SENT) {
            $row->setSentAt($this->dateTime->gmtDate());
            $this->finish($row, ServerEventInterface::STATUS_SENT, null, $dryRun, $summary);
            return;
        }
        if ($result->status === Result::UNSUPPORTED) {
            $this->finish($row, ServerEventInterface::STATUS_SKIPPED, $result->message, $dryRun, $summary);
            return;
        }
        $attempts = $row->getAttempts() + 1;
        $row->setAttempts($attempts);
        if ($attempts >= $this->config->getMaxAttempts($row->getStoreId())) {
            $this->finish($row, ServerEventInterface::STATUS_FAILED, $result->message, $dryRun, $summary);
            return;
        }
        $delay = min(self::BACKOFF_MAX_MINUTES, self::BACKOFF_MINUTES * 2 ** ($attempts - 1));
        $row->setScheduledAt($this->dateTime->gmtDate(null, time() + $delay * 60));
        $row->setLastError($result->message);
        $summary['retry']++;
        $this->logger->warning(
            sprintf('Zaraz server event failed, retry in %d min: %s', $delay, $result->message),
            $this->logContext($row)
        );
        $this->save($row, $dryRun);
    }

    /**
     * Final status of a row
     *
     * @param ServerEventInterface $row
     * @param string $status
     * @param string|null $message
     * @param bool $dryRun
     * @param array $summary
     * @return void
     */
    private function finish(
        ServerEventInterface $row,
        string $status,
        ?string $message,
        bool $dryRun,
        array &$summary,
    ): void {
        $row->setStatus($status)->setLastError($message);
        $summary[$status]++;
        if ($status === ServerEventInterface::STATUS_FAILED) {
            $this->logger->error('Zaraz server event failed: ' . $message, $this->logContext($row));
        } elseif ($status === ServerEventInterface::STATUS_SENT) {
            $this->logger->info('Zaraz server event sent', $this->logContext($row));
        } else {
            $this->logger->info('Zaraz server event skipped: ' . $message, $this->logContext($row));
        }
        $this->save($row, $dryRun);
    }

    /**
     * Save a row unless dry run
     *
     * @param ServerEventInterface $row
     * @param bool $dryRun
     * @return void
     */
    private function save(ServerEventInterface $row, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }
        try {
            $this->repository->save($row);
        } catch (LocalizedException $e) {
            $this->logger->error($e->getMessage(), $this->logContext($row));
        }
    }

    /**
     * Orders of the rows in one query
     *
     * @param ServerEventInterface[] $rows
     * @return OrderInterface[] by entity id
     */
    private function loadOrders(array $rows): array
    {
        $ids = array_unique(array_map(static fn (ServerEventInterface $row): int => $row->getOrderId(), $rows));
        $orders = [];
        foreach ($this->orderCollectionFactory->create()->addFieldToFilter('entity_id', ['in' => $ids]) as $order) {
            $orders[(int)$order->getEntityId()] = $order;
        }
        return $orders;
    }

    /**
     * Log context of a row
     *
     * @param ServerEventInterface $row
     * @return array
     */
    private function logContext(ServerEventInterface $row): array
    {
        return [
            'event' => $row->getEventCode(),
            'reference' => $row->getReference(),
            'store' => $row->getStoreId(),
            'attempts' => $row->getAttempts(),
        ];
    }
}
