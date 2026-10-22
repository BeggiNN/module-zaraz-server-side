<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Event;

/**
 * Server-only event codes added to Perspective\Zaraz\Model\Event\BuilderPool
 */
class EventCode
{
    public const ORDER_REFUNDED = 'order_refunded';
    public const ORDER_CANCELLED = 'order_cancelled';
}
