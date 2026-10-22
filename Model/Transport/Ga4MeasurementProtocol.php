<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Model\Transport;

use Magento\Framework\Serialize\Serializer\Json;
use Perspective\ZarazServerSide\Api\TransportInterface;
use Perspective\ZarazServerSide\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * GA4 Measurement Protocol (GA4 only): https://developers.google.com/analytics/devguides/collection/protocol/ga4
 *
 * Fallback when the Zaraz HTTP Events API cannot be used. The client id comes from the Zaraz GA4 cookie, so
 * the purchase joins the same GA4 user; Meta / TikTok do not get the event with this transport.
 */
class Ga4MeasurementProtocol implements TransportInterface
{
    private const URL = 'https://www.google-analytics.com/mp/collect';
    private const DEBUG_URL = 'https://www.google-analytics.com/debug/mp/collect';
    /** GA4 accepts timestamp_micros up to 72 hours back */
    private const MAX_AGE = 72 * 3600 - 600;

    /**
     * @param Config $config
     * @param Ga4Mapper $mapper
     * @param Ga4ClientId $clientId
     * @param HttpPost $http
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly Ga4Mapper $mapper,
        private readonly Ga4ClientId $clientId,
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
        $measurementId = $this->config->getGa4MeasurementId($storeId);
        $secret = $this->config->getGa4ApiSecret($storeId);
        if ($measurementId === '' || $secret === '') {
            return array_map(
                static fn (): Result => new Result(Result::ERROR, 'GA4 Measurement ID or API secret is empty'),
                $messages
            );
        }
        $debug = $this->config->isGa4Debug($storeId);
        $url = ($debug ? self::DEBUG_URL : self::URL)
            . '?' . http_build_query(['measurement_id' => $measurementId, 'api_secret' => $secret]);

        $results = [];
        foreach ($messages as $key => $message) {
            $body = $this->buildBody($message);
            $results[$key] = $body === null
                ? new Result(
                    Result::UNSUPPORTED,
                    sprintf('"%s" has no GA4 Measurement Protocol event', $message->event->getName())
                )
                : $this->post($url, $body, $debug, $storeId);
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function preview(array $messages, int $storeId): array
    {
        $bodies = [];
        foreach ($messages as $message) {
            $bodies[] = $this->buildBody($message);
        }
        return $bodies;
    }

    /**
     * Request body of one message, or null when GA4 has no equivalent event
     *
     * @param Message $message
     * @return array|null
     */
    public function buildBody(Message $message): ?array
    {
        $event = $this->mapper->map($message->event);
        if ($event === null) {
            return null;
        }
        $cookies = $message->client->cookies;
        $sessionId = $this->clientId->getSessionId($cookies);
        if ($sessionId !== null) {
            $event['params']['session_id'] = $sessionId;
        }
        $event['params']['engagement_time_msec'] = 1;

        $body = [
            'client_id' => $this->clientId->getClientId($cookies)
                ?? 'perspective.' . sprintf('%u', crc32($message->event->getId() ?? $message->event->getName())),
        ];
        if ($message->timestamp > time() - self::MAX_AGE) {
            $body['timestamp_micros'] = $message->timestamp * 1000000;
        }
        $body['events'] = [$event];
        return $body;
    }

    /**
     * POST one event
     *
     * @param string $url
     * @param array $body
     * @param bool $debug
     * @param int $storeId
     * @return Result
     */
    private function post(string $url, array $body, bool $debug, int $storeId): Result
    {
        $json = (string)$this->json->serialize($body);
        if ($this->config->isLogPayloads($storeId)) {
            $this->logger->info('GA4 Measurement Protocol request', ['body' => $json, 'debug' => $debug]);
        }
        try {
            $response = $this->http->post($url, $json);
        } catch (\Exception $e) {
            return new Result(Result::ERROR, 'GA4 Measurement Protocol request failed: ' . $e->getMessage());
        }
        if ($response['status'] < 200 || $response['status'] >= 300) {
            return new Result(Result::ERROR, sprintf(
                'GA4 Measurement Protocol responded %d: %s',
                $response['status'],
                mb_substr($response['body'], 0, 300)
            ));
        }
        if ($debug) {
            $messages = json_decode($response['body'], true)['validationMessages'] ?? [];
            if ($messages) {
                return new Result(Result::ERROR, 'GA4 validation: ' . json_encode($messages, JSON_UNESCAPED_SLASHES));
            }
        }
        return new Result(Result::SENT);
    }
}
