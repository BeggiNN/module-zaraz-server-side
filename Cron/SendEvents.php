<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Cron;

use Perspective\ZarazServerSide\Model\Processor;

/**
 * Every minute: send due server-side events
 */
class SendEvents
{
    private const LIMIT = 200;

    /**
     * @param Processor $processor
     */
    public function __construct(
        private readonly Processor $processor,
    ) {
    }

    /**
     * Send due events
     *
     * @return void
     */
    public function execute(): void
    {
        $this->processor->process(self::LIMIT);
    }
}
