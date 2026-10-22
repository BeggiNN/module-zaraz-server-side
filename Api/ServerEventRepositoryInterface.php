<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;

/**
 * @api
 */
interface ServerEventRepositoryInterface
{
    /**
     * Save an event
     *
     * @param ServerEventInterface $event
     * @return ServerEventInterface
     * @throws CouldNotSaveException
     */
    public function save(ServerEventInterface $event): ServerEventInterface;

    /**
     * Event by id
     *
     * @param int $eventId
     * @return ServerEventInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $eventId): ServerEventInterface;

    /**
     * Event by code and reference
     *
     * @param string $eventCode
     * @param string $reference order / credit memo increment id
     * @return ServerEventInterface
     * @throws NoSuchEntityException
     */
    public function getByReference(string $eventCode, string $reference): ServerEventInterface;

    /**
     * Add the event unless the same event code + reference exists
     *
     * @param ServerEventInterface $event
     * @return bool false when it already existed
     * @throws CouldNotSaveException
     */
    public function add(ServerEventInterface $event): bool;

    /**
     * Pending events due at the moment
     *
     * @param int $limit
     * @param bool $ignoreSchedule include pending events scheduled later
     * @param int[] $orderIds only events of these orders
     * @return ServerEventInterface[]
     */
    public function getDue(int $limit, bool $ignoreSchedule = false, array $orderIds = []): array;
}
