<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Test\Integration;

use Perspective\ZarazServerSide\Api\TransportInterface;
use Perspective\ZarazServerSide\Model\Transport\Result;

/**
 * Records messages instead of sending them
 */
class FakeTransport implements TransportInterface
{
    /**
     * @var array[] messages of each send() call
     */
    public array $calls = [];

    /**
     * @param Result $result returned for every message
     */
    public function __construct(
        public Result $result = new Result(Result::SENT),
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(array $messages, int $storeId): array
    {
        $this->calls[] = $messages;
        return array_map(fn (): Result => $this->result, $messages);
    }

    /**
     * @inheritDoc
     */
    public function preview(array $messages, int $storeId): array
    {
        return [];
    }
}
