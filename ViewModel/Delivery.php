<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\ZarazServerSide\ViewModel;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Event\DeliveryId;
use Perspective\Zaraz\Model\Event\EventCode;
use Perspective\ZarazServerSide\Model\Scheduler;
use Perspective\ZarazServerSide\Model\SuccessOrders;

/**
 * Success page (not cached): config of the delivery report for the server fallback
 */
class Delivery implements ArgumentInterface
{
    /**
     * @param SuccessOrders $successOrders
     * @param Scheduler $scheduler
     * @param StoreManagerInterface $storeManager
     * @param DeliveryId $deliveryId
     * @param UrlInterface $urlBuilder
     * @param FormKey $formKey
     * @param Json $json
     */
    public function __construct(
        private readonly SuccessOrders $successOrders,
        private readonly Scheduler $scheduler,
        private readonly StoreManagerInterface $storeManager,
        private readonly DeliveryId $deliveryId,
        private readonly UrlInterface $urlBuilder,
        private readonly FormKey $formKey,
        private readonly Json $json,
    ) {
    }

    /**
     * Config JSON, or empty string when the fallback is off
     *
     * @return string
     */
    public function getConfigJson(): string
    {
        $incrementId = $this->successOrders->getLastOrderIncrementId();
        $storeId = (int)$this->storeManager->getStore()->getId();
        if ($incrementId === '' || !$this->scheduler->isActive(EventCode::ORDER_COMPLETED, $storeId)) {
            return '';
        }
        return (string)$this->json->serialize([
            'url' => $this->urlBuilder->getUrl('perspective_zaraz_server/order/delivered', ['_secure' => true]),
            'order' => $incrementId,
            'eventId' => $this->deliveryId->forOrder($incrementId),
            'formKey' => $this->formKey->getFormKey(),
        ]);
    }
}
