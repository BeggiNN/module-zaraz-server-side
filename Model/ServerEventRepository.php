<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterfaceFactory;
use Perspective\ZarazServerSide\Api\ServerEventRepositoryInterface;
use Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent as ServerEventResource;
use Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent\CollectionFactory;

/**
 * Server-side events storage
 */
class ServerEventRepository implements ServerEventRepositoryInterface
{
    /**
     * @param ServerEventResource $resource
     * @param ServerEventInterfaceFactory $eventFactory
     * @param CollectionFactory $collectionFactory
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly ServerEventResource $resource,
        private readonly ServerEventInterfaceFactory $eventFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(ServerEventInterface $event): ServerEventInterface
    {
        try {
            /** @var ServerEvent $event */
            $this->resource->save($event);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the Zaraz server event: %1', $e->getMessage()), $e);
        }
        return $event;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $eventId): ServerEventInterface
    {
        /** @var ServerEvent $event */
        $event = $this->eventFactory->create();
        $this->resource->load($event, $eventId);
        if (!$event->getEventId()) {
            throw new NoSuchEntityException(__('Zaraz server event "%1" does not exist.', $eventId));
        }
        return $event;
    }

    /**
     * @inheritDoc
     */
    public function getByReference(string $eventCode, string $reference): ServerEventInterface
    {
        $event = $this->collectionFactory->create()
            ->addFieldToFilter(ServerEventInterface::EVENT_CODE, $eventCode)
            ->addFieldToFilter(ServerEventInterface::REFERENCE, $reference)
            ->setPageSize(1)
            ->getFirstItem();
        if (!$event->getEventId()) {
            throw new NoSuchEntityException(
                __('Zaraz server event "%1" for "%2" does not exist.', $eventCode, $reference)
            );
        }
        return $event;
    }

    /**
     * @inheritDoc
     */
    public function add(ServerEventInterface $event): bool
    {
        /** @var ServerEvent $event */
        $row = array_intersect_key($event->getData(), array_flip([
            ServerEventInterface::EVENT_CODE,
            ServerEventInterface::REFERENCE,
            ServerEventInterface::ORDER_ID,
            ServerEventInterface::SOURCE_ID,
            ServerEventInterface::STORE_ID,
            ServerEventInterface::STATUS,
            ServerEventInterface::SCHEDULED_AT,
            ServerEventInterface::LAST_ERROR,
        ]));
        $row[ServerEventInterface::STATUS] ??= ServerEventInterface::STATUS_PENDING;
        $row[ServerEventInterface::SCHEDULED_AT] ??= $this->dateTime->gmtDate();
        try {
            return $this->resource->insertIgnore($row);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the Zaraz server event: %1', $e->getMessage()), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function getDue(int $limit, bool $ignoreSchedule = false, array $orderIds = []): array
    {
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ServerEventInterface::STATUS, ServerEventInterface::STATUS_PENDING)
            ->setOrder(ServerEventInterface::SCHEDULED_AT, 'ASC')
            ->setPageSize($limit);
        if (!$ignoreSchedule) {
            $collection->addFieldToFilter(ServerEventInterface::SCHEDULED_AT, ['lteq' => $this->dateTime->gmtDate()]);
        }
        if ($orderIds) {
            $collection->addFieldToFilter(ServerEventInterface::ORDER_ID, ['in' => $orderIds]);
        }
        return array_values($collection->getItems());
    }
}
