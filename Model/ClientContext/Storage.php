<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\ClientContext;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\ZarazServerSide\Model\ClientContext;

/**
 * Client context in the "perspective_zaraz_context" column of quote / sales_order
 */
class Storage
{
    public const FIELD = 'perspective_zaraz_context';

    /**
     * @param ResourceConnection $resource
     * @param Json $json
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Json $json,
    ) {
    }

    /**
     * Context stored on a quote / order model, or null
     *
     * @param DataObject $entity
     * @return ClientContext|null
     */
    public function read(DataObject $entity): ?ClientContext
    {
        $raw = (string)$entity->getData(self::FIELD);
        if ($raw === '') {
            return null;
        }
        try {
            $data = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }
        return is_array($data) ? $this->createContext($data) : null;
    }

    /**
     * Set the context on a model that is saved afterwards (order before placement)
     *
     * @param DataObject $entity
     * @param ClientContext $context
     * @return void
     */
    public function assign(DataObject $entity, ClientContext $context): void
    {
        $entity->setData(self::FIELD, $this->encode($context));
    }

    /**
     * Write the context of a saved quote with one UPDATE when it changed (no quote save / totals collection)
     *
     * @param DataObject $quote
     * @param ClientContext $context
     * @return void
     */
    public function saveToQuote(DataObject $quote, ClientContext $context): void
    {
        $value = $this->encode($context);
        if (!$quote->getId() || $quote->getData(self::FIELD) === $value) {
            return;
        }
        $this->resource->getConnection('checkout')->update(
            $this->resource->getTableName('quote', 'checkout'),
            [self::FIELD => $value],
            ['entity_id = ?' => (int)$quote->getId()]
        );
        $quote->setData(self::FIELD, $value);
    }

    /**
     * Context of stored data
     *
     * @param array $data
     * @return ClientContext
     */
    private function createContext(array $data): ClientContext
    {
        $cookies = [];
        foreach ((array)($data['cookies'] ?? []) as $name => $value) {
            if (is_scalar($value)) {
                $cookies[(string)$name] = (string)$value;
            }
        }
        return new ClientContext(
            $cookies,
            (string)($data['ip'] ?? ''),
            (string)($data['user_agent'] ?? ''),
            (string)($data['language'] ?? ''),
            (string)($data['url'] ?? ''),
        );
    }

    /**
     * JSON of a context
     *
     * @param ClientContext $context
     * @return string
     */
    private function encode(ClientContext $context): string
    {
        return (string)$this->json->serialize($context->toArray());
    }
}
