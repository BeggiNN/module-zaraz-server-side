<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\Controller\Order;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Perspective\ZarazServerSide\Api\BrowserDeliveryInterface;
use Perspective\ZarazServerSide\Model\SuccessOrders;

/**
 * POST perspective_zaraz_server/order/delivered (form key): the success page delivered Order Completed to Zaraz.
 * Accepted only for an order of the visitor's checkout session.
 */
class Delivered implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param SuccessOrders $successOrders
     * @param BrowserDeliveryInterface $browserDelivery
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly SuccessOrders $successOrders,
        private readonly BrowserDeliveryInterface $browserDelivery,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(): Json
    {
        $incrementId = trim((string)$this->request->getParam('order'));
        $known = $incrementId !== '' && $this->successOrders->isSessionOrder($incrementId);

        return $this->jsonFactory->create()->setData([
            'delivered' => $known && $this->browserDelivery->markDelivered($incrementId),
        ]);
    }
}
