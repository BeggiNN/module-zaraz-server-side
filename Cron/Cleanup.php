<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Perspective\ZarazServerSide\Model\ClientContext\Storage;
use Perspective\ZarazServerSide\Model\Config;
use Perspective\ZarazServerSide\Model\ResourceModel\ServerEvent as ServerEventResource;

/**
 * Daily: delete processed events and the client context (IP, user agent, cookies) older than "Keep data, days"
 */
class Cleanup
{
    /**
     * @param Config $config
     * @param ServerEventResource $eventResource
     * @param ResourceConnection $resource
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly Config $config,
        private readonly ServerEventResource $eventResource,
        private readonly ResourceConnection $resource,
        private readonly DateTime $dateTime,
    ) {
    }

    /**
     * Delete old processed events and client context
     *
     * @return void
     */
    public function execute(): void
    {
        $before = $this->dateTime->gmtDate(null, time() - $this->config->getRetentionDays() * 86400);
        $this->eventResource->deleteProcessed($before);

        foreach (['sales_order' => ['sales', 'created_at'], 'quote' => ['checkout', 'updated_at']] as $table => $info) {
            [$connectionName, $dateField] = $info;
            $this->resource->getConnection($connectionName)->update(
                $this->resource->getTableName($table, $connectionName),
                [Storage::FIELD => null],
                [Storage::FIELD . ' IS NOT NULL', $dateField . ' < ?' => $before]
            );
        }
    }
}
