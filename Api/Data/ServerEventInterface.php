<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Api\Data;

/**
 * Server-side event of an order: one row per event code and order / credit memo increment id
 */
interface ServerEventInterface
{
    public const EVENT_ID = 'event_id';
    public const EVENT_CODE = 'event_code';
    public const REFERENCE = 'reference';
    public const ORDER_ID = 'order_id';
    public const SOURCE_ID = 'source_id';
    public const STORE_ID = 'store_id';
    public const STATUS = 'status';
    public const ATTEMPTS = 'attempts';
    public const SCHEDULED_AT = 'scheduled_at';
    public const SENT_AT = 'sent_at';
    public const LAST_ERROR = 'last_error';
    public const CREATED_AT = 'created_at';

    /** Waiting for the scheduled time (and for the browser, for Order Completed) */
    public const STATUS_PENDING = 'pending';
    /** The browser delivered the event to Zaraz; the server does not send it */
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_SENT = 'sent';
    /** Max attempts reached */
    public const STATUS_FAILED = 'failed';
    /** Bot, disabled event or filtered order */
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Get event id
     *
     * @return int|null
     */
    public function getEventId(): ?int;

    /**
     * Get event code
     *
     * @return string
     */
    public function getEventCode(): string;

    /**
     * Set event code
     *
     * @param string $code
     * @return $this
     */
    public function setEventCode(string $code): self;

    /**
     * Get reference
     *
     * @return string
     */
    public function getReference(): string;

    /**
     * Set reference
     *
     * @param string $reference
     * @return $this
     */
    public function setReference(string $reference): self;

    /**
     * Get order id
     *
     * @return int
     */
    public function getOrderId(): int;

    /**
     * Set order id
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Get source id
     *
     * @return int|null
     */
    public function getSourceId(): ?int;

    /**
     * Set source id
     *
     * @param int|null $sourceId
     * @return $this
     */
    public function setSourceId(?int $sourceId): self;

    /**
     * Get store id
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set store id
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get attempts
     *
     * @return int
     */
    public function getAttempts(): int;

    /**
     * Set attempts
     *
     * @param int $attempts
     * @return $this
     */
    public function setAttempts(int $attempts): self;

    /**
     * UTC "Y-m-d H:i:s"
     *
     * @return string|null
     */
    public function getScheduledAt(): ?string;

    /**
     * Set scheduled at
     *
     * @param string $scheduledAt UTC "Y-m-d H:i:s"
     * @return $this
     */
    public function setScheduledAt(string $scheduledAt): self;

    /**
     * Get sent at
     *
     * @return string|null
     */
    public function getSentAt(): ?string;

    /**
     * Set sent at
     *
     * @param string|null $sentAt UTC "Y-m-d H:i:s"
     * @return $this
     */
    public function setSentAt(?string $sentAt): self;

    /**
     * Get last error
     *
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * Set last error
     *
     * @param string|null $error
     * @return $this
     */
    public function setLastError(?string $error): self;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}
