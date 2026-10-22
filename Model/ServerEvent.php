<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

use Magento\Framework\Model\AbstractModel;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;
use Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent as ServerEventResource;

/**
 * Server-side event row
 */
class ServerEvent extends AbstractModel implements ServerEventInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'perspective_zaraz_server_event';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ServerEventResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getEventId(): ?int
    {
        $id = $this->getData(self::EVENT_ID);
        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritDoc
     */
    public function getEventCode(): string
    {
        return (string)$this->getData(self::EVENT_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setEventCode(string $code): self
    {
        return $this->setData(self::EVENT_CODE, $code);
    }

    /**
     * @inheritDoc
     */
    public function getReference(): string
    {
        return (string)$this->getData(self::REFERENCE);
    }

    /**
     * @inheritDoc
     */
    public function setReference(string $reference): self
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return (int)$this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getSourceId(): ?int
    {
        $id = $this->getData(self::SOURCE_ID);
        return $id === null ? null : (int)$id;
    }

    /**
     * @inheritDoc
     */
    public function setSourceId(?int $sourceId): self
    {
        return $this->setData(self::SOURCE_ID, $sourceId);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return (int)$this->getData(self::STORE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(int $storeId): self
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?: self::STATUS_PENDING);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getAttempts(): int
    {
        return (int)$this->getData(self::ATTEMPTS);
    }

    /**
     * @inheritDoc
     */
    public function setAttempts(int $attempts): self
    {
        return $this->setData(self::ATTEMPTS, $attempts);
    }

    /**
     * @inheritDoc
     */
    public function getScheduledAt(): ?string
    {
        return $this->getData(self::SCHEDULED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setScheduledAt(string $scheduledAt): self
    {
        return $this->setData(self::SCHEDULED_AT, $scheduledAt);
    }

    /**
     * @inheritDoc
     */
    public function getSentAt(): ?string
    {
        return $this->getData(self::SENT_AT);
    }

    /**
     * @inheritDoc
     */
    public function setSentAt(?string $sentAt): self
    {
        return $this->setData(self::SENT_AT, $sentAt);
    }

    /**
     * @inheritDoc
     */
    public function getLastError(): ?string
    {
        return $this->getData(self::LAST_ERROR);
    }

    /**
     * @inheritDoc
     */
    public function setLastError(?string $error): self
    {
        return $this->setData(self::LAST_ERROR, $error === null ? null : mb_substr($error, 0, 2000));
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }
}
