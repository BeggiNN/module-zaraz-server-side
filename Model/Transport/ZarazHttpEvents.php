<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Transport;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Laminas\Uri\UriFactory;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\ZarazServerSide\Api\TransportInterface;
use Perspective\ZarazServerSide\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Zaraz HTTP Events API: https://developers.cloudflare.com/zaraz/http-events-api/
 *
 * One batch request per store. Zaraz routes e-commerce events (__zarazEcommerce) to the tools like browser events;
 * `system.cookies` carries the tool cookies (cfz_<tool>, e.g. the GA4 client id), so the event joins the same user.
 */
class ZarazHttpEvents implements TransportInterface
{
    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param HttpPost $http
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpPost $http,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function send(array $messages, int $storeId): array
    {
        $url = $this->getUrl($storeId);
        if ($url === null) {
            return $this->all($messages, new Result(
                Result::ERROR,
                'HTTP Events API path is empty (Stores → Configuration → Cloudflare Zaraz → Server-side Events)'
            ));
        }
        $body = (string)$this->json->serialize($this->preview($messages, $storeId));
        if ($this->config->isLogPayloads($storeId)) {
            $this->logger->info('Zaraz HTTP Events API request', ['url' => $url, 'body' => $body]);
        }
        try {
            $response = $this->http->post($url, $body);
        } catch (\Exception $e) {
            $error = 'HTTP Events API request failed: ' . $e->getMessage();
            return $this->all($messages, new Result(Result::ERROR, $error));
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return $this->all($messages, new Result(Result::ERROR, $this->describe($response)));
        }
        if ($this->config->isLogPayloads($storeId)) {
            $this->logger->info('Zaraz HTTP Events API response', ['body' => mb_substr($response['body'], 0, 4000)]);
        }
        return $this->all($messages, new Result(Result::SENT));
    }

    /**
     * @inheritDoc
     */
    public function preview(array $messages, int $storeId): array
    {
        $events = [];
        foreach ($messages as $message) {
            $events[] = ['client' => $this->getClient($message->event), 'system' => $this->getSystem($message)];
        }
        return ['events' => $events];
    }

    /**
     * Track properties: payload + event name (+ e-commerce flag)
     *
     * @param EventInterface $event
     * @return array
     */
    private function getClient(EventInterface $event): array
    {
        $client = ['__zarazTrack' => $event->getName()] + $event->getPayload();
        if ($event->getMethod() === EventInterface::METHOD_ECOMMERCE) {
            $client['__zarazEcommerce'] = true;
        }
        return $client;
    }

    /**
     * System object: cookies, device, page
     *
     * @param Message $message
     * @return array
     */
    private function getSystem(Message $message): array
    {
        $client = $message->client;
        $device = array_filter([
            'ip' => $client->ip,
            'user-agent' => $client->userAgent,
            'language' => $client->language,
        ], 'strlen');
        $system = [];
        if ($client->cookies) {
            $system['cookies'] = $client->cookies;
        }
        if ($device) {
            $system['device'] = $device;
        }
        if ($client->url !== '') {
            $system['page'] = ['url' => $client->url];
        }
        return $system ?: ['page' => (object)[]];
    }

    /**
     * Endpoint URL or null when not configured
     *
     * @param int $storeId
     * @return string|null
     */
    private function getUrl(int $storeId): ?string
    {
        $path = $this->config->getZarazEndpointPath($storeId);
        if ($path === '') {
            return null;
        }
        $host = $this->config->getZarazEndpointHost($storeId);
        if ($host === '') {
            $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
            $host = (string)UriFactory::factory($baseUrl)->getHost();
        }
        return 'https://' . $host . $path;
    }

    /**
     * Error message of a failed response
     *
     * @param array $response ['status' => int, 'body' => string]
     * @return string
     */
    private function describe(array $response): string
    {
        $message = sprintf('HTTP Events API responded %d', $response['status']);
        if (in_array($response['status'], [301, 302, 303, 307, 308, 401, 403], true)) {
            $message .= ' (Cloudflare Access / WAF / bot protection blocks the endpoint path?)';
        } elseif ($response['status'] === 404) {
            $message .= ' (the path differs from Zaraz → Settings → Endpoints → HTTP Events API?)';
        }
        return $message . ': ' . mb_substr(trim(strip_tags($response['body'])), 0, 300);
    }

    /**
     * The same result for every message
     *
     * @param Message[] $messages
     * @param Result $result
     * @return Result[]
     */
    private function all(array $messages, Result $result): array
    {
        return array_map(static fn (): Result => $result, $messages);
    }
}
