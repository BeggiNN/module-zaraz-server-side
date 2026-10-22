<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model;

use Perspective\Zaraz\Model\Event\EventCode;
use Perspective\ZarazServerSide\Api\BrowserDeliveryInterface;
use Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent as ServerEventResource;

/**
 * Pending server Order Completed → delivered, so the fallback is not sent
 */
class BrowserDelivery implements BrowserDeliveryInterface
{
    /**
     * @param ServerEventResource $resource
     */
    public function __construct(
        private readonly ServerEventResource $resource,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function markDelivered(string $incrementId): bool
    {
        return $incrementId !== '' && $this->resource->markDelivered(EventCode::ORDER_COMPLETED, $incrementId);
    }
}
