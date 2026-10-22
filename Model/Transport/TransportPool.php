<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Transport;

use Magento\Framework\Exception\LocalizedException;
use Perspective\ZarazServerSide\Api\TransportInterface;

/**
 * Transports by code (di.xml); the code is selected in "Server-side events → Transport"
 */
class TransportPool
{
    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(
        private readonly array $transports = [],
    ) {
    }

    /**
     * Transport by code
     *
     * @param string $code
     * @return TransportInterface
     * @throws LocalizedException
     */
    public function get(string $code): TransportInterface
    {
        if (!isset($this->transports[$code])) {
            throw new LocalizedException(__('Unknown Zaraz server transport "%1".', $code));
        }
        return $this->transports[$code];
    }
}
