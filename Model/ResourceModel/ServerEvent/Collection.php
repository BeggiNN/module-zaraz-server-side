<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent as ServerEventResource;
use Perspective\ZarazServerSide\Model\ServerEvent;

/**
 * Server-side event rows
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'event_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(ServerEvent::class, ServerEventResource::class);
    }
}
