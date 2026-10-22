<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Perspective\ZarazServerSide\Model\Config;

/**
 * Server-side transports
 */
class Transport implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::TRANSPORT_ZARAZ, 'label' => __('Zaraz HTTP Events API (all Zaraz tools)')],
            ['value' => Config::TRANSPORT_GA4, 'label' => __('GA4 Measurement Protocol (GA4 only)')],
        ];
    }
}
