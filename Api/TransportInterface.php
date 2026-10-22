<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Api;

use Perspective\ZarazServerSide\Model\Transport\Message;
use Perspective\ZarazServerSide\Model\Transport\Result;

/**
 * Delivery of server-side events (Zaraz HTTP Events API, GA4 Measurement Protocol, ...), registered in TransportPool
 *
 * @api
 */
interface TransportInterface
{
    /**
     * Send events of one store; a result per message, keyed like $messages
     *
     * @param Message[] $messages
     * @param int $storeId
     * @return Result[]
     */
    public function send(array $messages, int $storeId): array;

    /**
     * Request bodies that send() would post, for --dry-run
     *
     * @param Message[] $messages
     * @param int $storeId
     * @return array
     */
    public function preview(array $messages, int $storeId): array;
}
