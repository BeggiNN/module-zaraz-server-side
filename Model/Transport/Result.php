<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Transport;

/**
 * Send result of one message
 */
class Result
{
    public const SENT = 'sent';
    /** Temporary failure: retried */
    public const ERROR = 'error';
    /** The transport cannot send the event at all (e.g. no GA4 equivalent): not retried */
    public const UNSUPPORTED = 'unsupported';

    /**
     * @param string $status
     * @param string $message
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message = '',
    ) {
    }
}
