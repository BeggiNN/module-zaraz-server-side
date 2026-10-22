<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\ResourceModel;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Perspective\ZarazServerSide\Api\Data\ServerEventInterface;

/**
 * perspective_zaraz_server_event
 */
class ServerEvent extends AbstractDb
{
    public const TABLE = 'perspective_zaraz_server_event';

    /**
     * @var string
     */
    protected $connectionName = 'sales';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(self::TABLE, ServerEventInterface::EVENT_ID);
    }

    /**
     * INSERT IGNORE on the (event_code, reference) unique key
     *
     * @param array $row
     * @return bool false when the row already existed
     */
    public function insertIgnore(array $row): bool
    {
        return $this->getConnection()->insertArray(
            $this->getMainTable(),
            array_keys($row),
            [array_values($row)],
            AdapterInterface::INSERT_IGNORE
        ) > 0;
    }

    /**
     * Pending Order Completed of the order → delivered
     *
     * @param string $eventCode
     * @param string $reference
     * @return bool
     */
    public function markDelivered(string $eventCode, string $reference): bool
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            [ServerEventInterface::STATUS => ServerEventInterface::STATUS_DELIVERED],
            [
                ServerEventInterface::EVENT_CODE . ' = ?' => $eventCode,
                ServerEventInterface::REFERENCE . ' = ?' => $reference,
                ServerEventInterface::STATUS . ' = ?' => ServerEventInterface::STATUS_PENDING,
            ]
        ) > 0;
    }

    /**
     * Delete processed rows created before the date
     *
     * @param string $before UTC "Y-m-d H:i:s"
     * @return int
     */
    public function deleteProcessed(string $before): int
    {
        return $this->getConnection()->delete(
            $this->getMainTable(),
            [
                ServerEventInterface::STATUS . ' <> ?' => ServerEventInterface::STATUS_PENDING,
                ServerEventInterface::CREATED_AT . ' < ?' => $before,
            ]
        );
    }
}
