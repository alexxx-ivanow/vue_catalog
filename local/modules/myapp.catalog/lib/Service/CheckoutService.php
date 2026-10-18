<?php

namespace MyApp\Catalog\Service;

use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Order;
use Bitrix\Sale\PaySystem\BaseServiceHandler;
use Bitrix\Sale\PaySystem\Manager as PaySystemManager;
use Bitrix\Sale\PersonType;
use MyApp\Catalog\Checkout\DeliveryUiResolver;

class CheckoutService
{
    public static function init(): array
    {
        self::ensureModules();

        $cart = CartService::getCart();
        if (empty($cart['items'])) {
            return [
                'success' => false,
                'code' => 'EMPTY_BASKET',
                'error' => 'Корзина пуста',
            ];
        }

        $personTypes = self::getPersonTypes();
        $defaultPersonTypeId = (int)($personTypes[0]['id'] ?? 0);

        return [
            'success' => true,
            'personTypes' => $personTypes,
            'selection' => [
                'personTypeId' => $defaultPersonTypeId,
                'locationCode' => '',
                'locationLabel' => '',
                'deliveryId' => 0,
                'paySystemId' => 0,
                'properties' => [],
                'deliveryExtra' => [],
            ],
            'basket' => $cart,
        ];
    }

    public static function searchLocations(string $query, int $limit = 10): array
    {
        self::ensureModules();
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return ['items' => []];
        }

        $limit = max(1, min(20, $limit));
        $items = self::searchLocationsViaLocationTable($query, $limit);

        if (empty($items)) {
            $items = self::searchLocationsViaLegacyApi($query, $limit);
        }

        return ['items' => $items];
    }

    public static function calculate(array $selection): array
    {
        self::ensureModules();

        $cart = CartService::getCart();
        if (empty($cart['items'])) {
            return [
                'success' => false,
                'code' => 'EMPTY_BASKET',
                'error' => 'Корзина пуста',
            ];
        }

        $selection = self::normalizeSelection($selection);
        $cartTotals = is_array($cart['totals'] ?? null) ? $cart['totals'] : [];
        $resolved = self::buildCalculatedCheckoutState($selection, $cartTotals);
        if ($resolved === null) {
            return [
                'success' => false,
                'error' => 'Не удалось подготовить заказ',
            ];
        }

        return [
            'success' => true,
            'selection' => $resolved['selection'],
            'deliveries' => $resolved['deliveries'],
            'paySystems' => $resolved['paySystems'],
            'properties' => $resolved['properties'],
            'basket' => $cart,
            'totals' => $resolved['totals'],
        ];
    }

    public static function submit(array $selection): array
    {
        self::ensureModules();

        $cart = CartService::getCart();
        if (empty($cart['items'])) {
            return [
                'success' => false,
                'code' => 'EMPTY_BASKET',
                'error' => 'Корзина пуста',
            ];
        }

        $personTypeId = (int)($selection['personTypeId'] ?? 0);
        $locationCode = trim((string)($selection['locationCode'] ?? ''));
        $deliveryId = (int)($selection['deliveryId'] ?? 0);
        $paySystemId = (int)($selection['paySystemId'] ?? 0);

        if ($personTypeId <= 0 || $locationCode === '' || $deliveryId <= 0 || $paySystemId <= 0) {
            return [
                'success' => false,
                'error' => 'Заполните тип покупателя, местоположение, доставку и оплату',
            ];
        }

        $order = self::buildDraftOrder($selection, true);
        if (!$order) {
            return [
                'success' => false,
                'error' => 'Не удалось создать заказ',
            ];
        }

        self::applyProperties($order, $selection);
        self::applyDeliveryExtra($order, $selection);

        $order->doFinalAction(true);
        self::applyProperties($order, $selection);

        $propertyErrors = self::validateRequiredProperties($order, $selection);
        if (!empty($propertyErrors)) {
            return [
                'success' => false,
                'error' => implode('; ', $propertyErrors),
                'propertyErrors' => $propertyErrors,
            ];
        }

        $saveResult = $order->save();
        if (!$saveResult->isSuccess()) {
            return [
                'success' => false,
                'error' => implode('; ', $saveResult->getErrorMessages()),
            ];
        }

        $order->doFinalAction(true);

        $orderId = (int)$order->getId();
        $accountNumber = (string)$order->getField('ACCOUNT_NUMBER');

        $paymentInfo = self::resolvePaymentRedirect($order);
        $successUrl = '/order/success/' . $orderId . '/';

        return [
            'success' => true,
            'orderId' => $orderId,
            'accountNumber' => $accountNumber,
            'redirectUrl' => $paymentInfo['redirectUrl'],
            'successUrl' => $successUrl,
            'isOnlinePayment' => $paymentInfo['isOnline'],
        ];
    }

    public static function getOrderSuccessInfo(int $orderId): ?array
    {
        self::ensureModules();
        if ($orderId <= 0) {
            return null;
        }

        $order = Order::load($orderId);
        if (!$order) {
            return null;
        }

        if (!self::canViewOrder($order)) {
            return null;
        }

        return [
            'id' => $orderId,
            'accountNumber' => (string)$order->getField('ACCOUNT_NUMBER'),
            'price' => (float)$order->getPrice(),
            'currency' => (string)$order->getCurrency(),
            'dateInsert' => (string)$order->getField('DATE_INSERT'),
        ];
    }

    public static function getPersonTypes(): array
    {
        self::ensureModules();
        $siteId = Context::getCurrent()->getSite();
        $result = [];

        $types = PersonType::load($siteId);
        foreach ($types as $type) {
            if ($type['ACTIVE'] !== 'Y') {
                continue;
            }
            $result[] = [
                'id' => (int)$type['ID'],
                'name' => (string)$type['NAME'],
                'sort' => (int)$type['SORT'],
            ];
        }

        usort($result, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $result;
    }

    /**
     * @return array{
     *   selection: array,
     *   deliveries: array,
     *   paySystems: array,
     *   properties: array,
     *   totals: array
     * }|null
     */
    private static function buildCalculatedCheckoutState(array $selection, array $cartTotals = []): ?array
    {
        $order = self::buildDraftOrder($selection);
        if (!$order) {
            return null;
        }

        $order->doFinalAction(true);

        $deliveries = self::getDeliveries($order, (int)$selection['deliveryId']);
        $deliveryId = self::resolveSelectedId((int)$selection['deliveryId'], $deliveries);
        $selection['deliveryId'] = $deliveryId;

        if ($deliveryId > 0 && $deliveryId !== self::getSelectedDeliveryId($order)) {
            $order = self::buildDraftOrder($selection);
            if (!$order) {
                return null;
            }
            $order->doFinalAction(true);
            $deliveries = self::getDeliveries($order, $deliveryId);
        }

        $paySystems = [];
        $paySystemId = 0;
        $properties = [];

        if ($deliveryId > 0) {
            $paySystems = self::getPaySystems($order, (int)$selection['paySystemId']);
            $paySystemId = self::resolveSelectedId((int)$selection['paySystemId'], $paySystems);
            $selection['paySystemId'] = $paySystemId;

            if ($paySystemId > 0) {
                $order = self::buildDraftOrder($selection);
                if (!$order) {
                    return null;
                }
                $order->doFinalAction(true);
                self::applyProperties($order, $selection);
                $deliveries = self::getDeliveries($order, $deliveryId);
                $paySystems = self::getPaySystems($order, $paySystemId);
                $properties = self::getOrderProperties($order);
            }
        }

        $totals = self::calculateTotals($order, $cartTotals);

        $deliveries = self::markSelectedInList($deliveries, $deliveryId);
        $paySystems = self::markSelectedInList($paySystems, $paySystemId);

        return [
            'selection' => self::normalizeSelection($selection),
            'deliveries' => $deliveries,
            'paySystems' => $paySystems,
            'properties' => $properties,
            'totals' => $totals,
        ];
    }

    private static function resolveSelectedId(int $selectedId, array $items): int
    {
        if ($selectedId > 0) {
            foreach ($items as $item) {
                if ((int)($item['id'] ?? 0) === $selectedId) {
                    return $selectedId;
                }
            }
        }

        if (!empty($items[0]['id'])) {
            return (int)$items[0]['id'];
        }

        return 0;
    }

    private static function markSelectedInList(array $items, int $selectedId): array
    {
        foreach ($items as &$item) {
            $item['selected'] = (int)($item['id'] ?? 0) === $selectedId;
        }
        unset($item);

        return $items;
    }

    private static function buildDraftOrder(array $selection, bool $forSave = false): ?Order
    {
        global $USER;

        $siteId = Context::getCurrent()->getSite();
        $userId = is_object($USER) && $USER->IsAuthorized() ? (int)$USER->GetID() : 0;
        $personTypeId = (int)($selection['personTypeId'] ?? 0);

        if ($personTypeId <= 0) {
            $types = self::getPersonTypes();
            $personTypeId = (int)($types[0]['id'] ?? 0);
        }
        if ($personTypeId <= 0) {
            return null;
        }

        $fuserId = Fuser::getId();
        $basket = Basket::loadItemsForFUser($fuserId, $siteId);
        if ($basket->isEmpty()) {
            return null;
        }

        $order = Order::create($siteId, $userId);
        $order->setPersonTypeId($personTypeId);
        $order->setField('CURRENCY', self::resolveBasketCurrency($basket));

        $orderBasket = $basket->createClone();
        $setBasketResult = $order->setBasket($orderBasket);
        if (!$setBasketResult->isSuccess()) {
            return null;
        }

        self::applyLocation($order, (string)($selection['locationCode'] ?? ''));
        self::applyProperties($order, $selection);

        $deliveryId = (int)($selection['deliveryId'] ?? 0);
        if ($deliveryId > 0) {
            self::setDelivery($order, $deliveryId);
        }

        $paySystemId = (int)($selection['paySystemId'] ?? 0);
        if ($paySystemId > 0) {
            self::setPaySystem($order, $paySystemId);
        }

        if ($forSave) {
            $order->setField('USER_DESCRIPTION', (string)($selection['comment'] ?? ''));
        }

        return $order;
    }

    private static function applyLocation(Order $order, string $locationCode): void
    {
        if ($locationCode === '') {
            return;
        }

        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $property) {
            if ($property->isUtil()) {
                continue;
            }
            $prop = self::normalizeOrderPropertyMeta($property->getProperty());
            if (!$prop) {
                continue;
            }
            if (self::isLocationPropertyMeta($prop)) {
                $property->setValue($locationCode);
                return;
            }
        }

        $locationProp = $propertyCollection->getDeliveryLocation();
        if ($locationProp) {
            $locationProp->setValue($locationCode);
        }
    }

    private static function applyProperties(Order $order, array $selection): void
    {
        $values = is_array($selection['properties'] ?? null) ? $selection['properties'] : [];
        if (empty($values)) {
            return;
        }

        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $propertyValue) {
            if ($propertyValue->isUtil()) {
                continue;
            }
            $prop = self::normalizeOrderPropertyMeta($propertyValue->getProperty());
            if (!$prop) {
                continue;
            }
            if (self::isLocationPropertyMeta($prop)) {
                continue;
            }

            $value = self::resolveSubmittedPropertyValue($values, (string)$prop['CODE'], (int)$prop['ID']);
            if ($value === '') {
                continue;
            }

            $propertyValue->setValue($value);
            if (($prop['IS_ZIP'] ?? '') === 'Y') {
                $zipProperty = $propertyCollection->getDeliveryLocationZip();
                if ($zipProperty) {
                    $zipProperty->setValue($value);
                }
            }
        }
    }

    private static function applyDeliveryExtra(Order $order, array $selection): void
    {
        $extra = is_array($selection['deliveryExtra'] ?? null) ? $selection['deliveryExtra'] : [];
        if (empty($extra)) {
            return;
        }

        $shipment = self::getActiveShipment($order);
        if (!$shipment) {
            return;
        }

        if (!empty($extra['deliveryPoint'])) {
            $shipment->setField('COMMENTS', (string)$extra['deliveryPoint']);
        }
        if (!empty($extra['pickupPointId'])) {
            $shipment->setField('TRACKING_NUMBER', (string)$extra['pickupPointId']);
        }
    }

    private static function setDelivery(Order $order, int $deliveryId): void
    {
        $service = DeliveryManager::getObjectById($deliveryId);
        if (!$service) {
            return;
        }

        $shipment = self::getActiveShipment($order, true);
        if (!$shipment) {
            return;
        }

        $shipment->setField('DELIVERY_ID', $deliveryId);
        $shipment->setField('DELIVERY_NAME', $service->getName());
        $shipment->setField('CURRENCY', $order->getCurrency());
        self::applyDeliveryCalculationToShipment($shipment, $service);
    }

    private static function applyDeliveryCalculationToShipment($shipment, $service): void
    {
        $calcResult = $service->calculate($shipment);
        if (!$calcResult->isSuccess()) {
            return;
        }

        $price = (float)$calcResult->getPrice();
        $shipment->setField('BASE_PRICE_DELIVERY', $price);
        $shipment->setField('PRICE_DELIVERY', $price);
        $shipment->setField('CUSTOM_PRICE_DELIVERY', 'N');
    }

    private static function setPaySystem(Order $order, int $paySystemId): void
    {
        $paymentCollection = $order->getPaymentCollection();
        $payment = null;

        foreach ($paymentCollection as $existingPayment) {
            $payment = $existingPayment;
            break;
        }

        if (!$payment) {
            $payment = $paymentCollection->createItem();
        }

        $service = PaySystemManager::getObjectById($paySystemId);
        $payment->setField('PAY_SYSTEM_ID', $paySystemId);
        $payment->setField('PAY_SYSTEM_NAME', $service ? $service->getField('NAME') : '');
        $payment->setField('SUM', $order->getPrice());
        $payment->setField('CURRENCY', $order->getCurrency());
    }

    private static function getDeliveries(Order $order, int $selectedId = 0): array
    {
        $locationCode = trim((string)self::getOrderLocationCode($order));
        if ($locationCode === '') {
            return [];
        }

        $shipment = self::getActiveShipment($order, true);
        if (!$shipment) {
            return [];
        }

        $list = [];
        $services = DeliveryManager::getRestrictedObjectsList($shipment);
        foreach ($services as $deliveryId => $service) {
            $calculationData = [];
            $price = 0.0;
            $calcResult = $service->calculate($shipment);
            if ($calcResult->isSuccess()) {
                $price = (float)$calcResult->getPrice();
                $calculationData = $calcResult->getData();
            }

            $list[] = [
                'id' => (int)$deliveryId,
                'name' => (string)$service->getName(),
                'description' => (string)$service->getDescription(),
                'price' => $price,
                'currency' => (string)$order->getCurrency(),
                'selected' => (int)$deliveryId === $selectedId,
                'extra' => DeliveryUiResolver::resolve($service, is_array($calculationData) ? $calculationData : []),
            ];
        }

        return $list;
    }

    private static function getPaySystems(Order $order, int $selectedId = 0): array
    {
        $deliveryId = self::getSelectedDeliveryId($order);
        if ($deliveryId <= 0) {
            return [];
        }

        $paymentCollection = $order->getPaymentCollection();
        $payment = null;
        foreach ($paymentCollection as $existingPayment) {
            $payment = $existingPayment;
            break;
        }
        if (!$payment) {
            $payment = $paymentCollection->createItem();
        }
        $payment->setField('SUM', $order->getPrice());
        $payment->setField('CURRENCY', $order->getCurrency());

        $list = [];
        $services = PaySystemManager::getListWithRestrictions($payment);
        foreach ($services as $service) {
            $meta = self::normalizePaySystemMeta($service);
            if (!$meta) {
                continue;
            }
            $id = (int)$meta['ID'];
            $list[] = [
                'id' => $id,
                'name' => (string)$meta['NAME'],
                'description' => (string)($meta['DESCRIPTION'] ?? ''),
                'selected' => $id === $selectedId,
            ];
        }

        return $list;
    }

    private static function getOrderProperties(Order $order): array
    {
        $locationCode = trim((string)self::getOrderLocationCode($order));
        $deliveryId = self::getSelectedDeliveryId($order);
        $paySystemId = 0;
        foreach ($order->getPaymentCollection() as $payment) {
            $paySystemId = (int)$payment->getPaymentSystemId();
            break;
        }

        if ($locationCode === '' || $deliveryId <= 0 || $paySystemId <= 0) {
            return [];
        }

        $result = [];
        $propertyCollection = $order->getPropertyCollection();

        foreach ($propertyCollection as $propertyValue) {
            if ($propertyValue->isUtil()) {
                continue;
            }
            $prop = self::normalizeOrderPropertyMeta($propertyValue->getProperty());
            if (!$prop) {
                continue;
            }
            if (self::isLocationPropertyMeta($prop)) {
                continue;
            }
            if (!$propertyValue->isRequired() && !$propertyValue->getPropertyId()) {
                continue;
            }

            if (method_exists($propertyValue, 'isEnabled') && !$propertyValue->isEnabled()) {
                continue;
            }

            $options = [];
            if ((string)$prop['TYPE'] === 'ENUM') {
                $propId = (int)$prop['ID'];
                if ($propId > 0) {
                    $enumRes = \CSaleOrderPropsVariant::GetList(
                        ['SORT' => 'ASC'],
                        ['ORDER_PROPS_ID' => $propId]
                    );
                    while ($enum = $enumRes->Fetch()) {
                        $options[] = [
                            'value' => (string)$enum['VALUE'],
                            'name' => (string)$enum['NAME'],
                        ];
                    }
                }
            }

            $result[] = [
                'id' => (int)$prop['ID'],
                'code' => (string)$prop['CODE'],
                'name' => (string)$prop['NAME'],
                'type' => (string)$prop['TYPE'],
                'required' => $propertyValue->isRequired(),
                'value' => self::stringifyPropertyValue($propertyValue->getValue()),
                'options' => $options,
            ];
        }

        return $result;
    }

    private static function validateRequiredProperties(Order $order, array $selection): array
    {
        $errors = [];
        $values = is_array($selection['properties'] ?? null) ? $selection['properties'] : [];

        foreach (self::getOrderProperties($order) as $property) {
            if (empty($property['required'])) {
                continue;
            }
            $value = self::resolveSubmittedPropertyValue($values, (string)$property['code'], (int)$property['id']);
            if ($value === '') {
                $value = self::stringifyPropertyValue($property['value'] ?? '');
            }
            if ($value === '') {
                $errors[] = 'Заполните поле: ' . $property['name'];
            }
        }

        return $errors;
    }

    private static function calculateTotals(Order $order, array $cartTotals = []): array
    {
        $basketPrice = (float)$order->getBasket()->getPrice();
        $deliveryPrice = (float)$order->getDeliveryPrice();
        if ($deliveryPrice <= 0) {
            $deliveryPrice = self::getCalculatedDeliveryPriceForOrder($order);
        }

        $orderDiscount = max(0, (float)$order->getDiscountPrice());
        $displayed = self::resolveDisplayedItemTotals($basketPrice, $orderDiscount, $cartTotals);

        $total = (float)$order->getPrice();
        $discountNotInBasketPrice = $displayed['discount'] > 0
            && abs($basketPrice - $displayed['itemsSum']) < 0.05;

        if ($discountNotInBasketPrice) {
            $total = $displayed['payable'] + $deliveryPrice;
        } elseif ($total <= 0 || ($deliveryPrice > 0 && $total + 0.01 < $basketPrice + $deliveryPrice - $orderDiscount)) {
            $total = max(0, $displayed['payable'] + $deliveryPrice);
        }

        return [
            'itemsSum' => round($displayed['itemsSum'], 2),
            'delivery' => round($deliveryPrice, 2),
            'discount' => round($displayed['discount'], 2),
            'total' => round(max(0, $total), 2),
            'currency' => (string)$order->getCurrency(),
        ];
    }

    /**
     * Сумма товаров и скидка в том же виде, что в корзине:
     * itemsSum — цена до скидки, discount — возможная скидка, payable — товары к оплате.
     */
    private static function resolveDisplayedItemTotals(float $basketPrice, float $orderDiscount, array $cartTotals): array
    {
        $itemsSum = round((float)($cartTotals['itemsSum'] ?? 0), 2);
        $payable = round((float)($cartTotals['total'] ?? 0), 2);
        $discount = round(max(0, (float)($cartTotals['discount'] ?? 0)), 2);

        if ($itemsSum <= 0) {
            $itemsSum = round($basketPrice, 2);
            $discount = round($orderDiscount, 2);
            $payable = round(max(0, $itemsSum - $discount), 2);
        }

        if ($orderDiscount > $discount + 0.009 && abs($basketPrice - $itemsSum) < 0.05) {
            $discount = round($orderDiscount, 2);
            $payable = round(max(0, $itemsSum - $discount), 2);
        } elseif ($basketPrice > 0 && $basketPrice + 0.009 < $payable) {
            $payable = round($basketPrice, 2);
            $discount = round(max($discount, $itemsSum - $payable), 2);
        }

        return [
            'itemsSum' => $itemsSum,
            'discount' => $discount,
            'payable' => $payable,
        ];
    }

    private static function getCalculatedDeliveryPriceForOrder(Order $order): float
    {
        $deliveryId = self::getSelectedDeliveryId($order);
        if ($deliveryId <= 0) {
            return 0.0;
        }

        $service = DeliveryManager::getObjectById($deliveryId);
        $shipment = self::getActiveShipment($order, false);
        if (!$service || !$shipment) {
            return 0.0;
        }

        $calcResult = $service->calculate($shipment);
        if (!$calcResult->isSuccess()) {
            return 0.0;
        }

        return (float)$calcResult->getPrice();
    }

    private static function resolvePaymentRedirect(Order $order): array
    {
        $request = Context::getCurrent()->getRequest();
        $redirectUrl = null;
        $isOnline = false;

        foreach ($order->getPaymentCollection() as $payment) {
            if ($payment->isPaid()) {
                continue;
            }

            $service = PaySystemManager::getObjectById((int)$payment->getPaymentSystemId());
            if (!$service) {
                continue;
            }

            if ($service->isCash()) {
                continue;
            }

            $isOnline = true;
            $initResult = $service->initiatePay($payment, $request, BaseServiceHandler::REDIRECT);
            if ($initResult->isSuccess()) {
                $redirectUrl = (string)$initResult->getPaymentUrl();
            }
            break;
        }

        return [
            'redirectUrl' => $redirectUrl ?: null,
            'isOnline' => $isOnline,
        ];
    }

    private static function canViewOrder(Order $order): bool
    {
        global $USER;

        $orderUserId = (int)$order->getUserId();
        if ($orderUserId > 0 && is_object($USER) && $USER->IsAuthorized()) {
            return (int)$USER->GetID() === $orderUserId;
        }

        if ($orderUserId <= 0) {
            return true;
        }

        return false;
    }

    private static function getSelectedDeliveryId(Order $order): int
    {
        $shipment = self::getActiveShipment($order, false);

        return $shipment ? (int)$shipment->getDeliveryId() : 0;
    }

    private static function getActiveShipment(Order $order, bool $create = false)
    {
        $collection = $order->getShipmentCollection();
        foreach ($collection as $shipment) {
            if (!$shipment->isSystem()) {
                return $shipment;
            }
        }

        if (!$create) {
            return null;
        }

        $shipment = $collection->createItem();
        $shipment->setField('CURRENCY', $order->getCurrency());

        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        foreach ($order->getBasket() as $basketItem) {
            $shipmentItem = $shipmentItemCollection->createItem($basketItem);
            $shipmentItem->setQuantity($basketItem->getQuantity());
        }

        return $shipment;
    }

    private static function getOrderLocationCode(Order $order): string
    {
        $propertyCollection = $order->getPropertyCollection();
        $locationProp = $propertyCollection->getDeliveryLocation();
        if ($locationProp) {
            return (string)$locationProp->getValue();
        }

        foreach ($propertyCollection as $property) {
            $prop = self::normalizeOrderPropertyMeta($property->getProperty());
            if ($prop && self::isLocationPropertyMeta($prop)) {
                return (string)$property->getValue();
            }
        }

        return '';
    }

    private static function normalizeSelection(array $selection): array
    {
        return [
            'personTypeId' => (int)($selection['personTypeId'] ?? 0),
            'locationCode' => (string)($selection['locationCode'] ?? ''),
            'locationLabel' => (string)($selection['locationLabel'] ?? ''),
            'deliveryId' => (int)($selection['deliveryId'] ?? 0),
            'paySystemId' => (int)($selection['paySystemId'] ?? 0),
            'properties' => is_array($selection['properties'] ?? null) ? $selection['properties'] : [],
            'deliveryExtra' => is_array($selection['deliveryExtra'] ?? null) ? $selection['deliveryExtra'] : [],
        ];
    }

    /**
     * @param mixed $propertyMeta
     * @return array{ID:int,CODE:string,NAME:string,TYPE:string,IS_LOCATION:string,IS_ZIP:string}|null
     */
    private static function normalizeOrderPropertyMeta(mixed $propertyMeta): ?array
    {
        if (is_array($propertyMeta)) {
            return [
                'ID' => (int)($propertyMeta['ID'] ?? 0),
                'CODE' => (string)($propertyMeta['CODE'] ?? ''),
                'NAME' => (string)($propertyMeta['NAME'] ?? ''),
                'TYPE' => (string)($propertyMeta['TYPE'] ?? ''),
                'IS_LOCATION' => (string)($propertyMeta['IS_LOCATION'] ?? ''),
                'IS_ZIP' => (string)($propertyMeta['IS_ZIP'] ?? ''),
            ];
        }

        if (is_object($propertyMeta)) {
            if (method_exists($propertyMeta, 'getType') && method_exists($propertyMeta, 'getField')) {
                return [
                    'ID' => (int)$propertyMeta->getField('ID'),
                    'CODE' => (string)$propertyMeta->getField('CODE'),
                    'NAME' => (string)$propertyMeta->getField('NAME'),
                    'TYPE' => (string)$propertyMeta->getType(),
                    'IS_LOCATION' => (string)$propertyMeta->getField('IS_LOCATION'),
                    'IS_ZIP' => (string)$propertyMeta->getField('IS_ZIP'),
                ];
            }
        }

        return null;
    }

    private static function resolveSubmittedPropertyValue(array $values, string $code, int $id): string
    {
        $candidates = [];
        if ($code !== '' && array_key_exists($code, $values)) {
            $candidates[] = $values[$code];
        }
        if ($id > 0 && array_key_exists((string)$id, $values)) {
            $candidates[] = $values[(string)$id];
        }
        if ($id > 0 && array_key_exists($id, $values)) {
            $candidates[] = $values[$id];
        }

        foreach ($candidates as $candidate) {
            $stringValue = self::stringifyPropertyValue($candidate);
            if ($stringValue !== '') {
                return $stringValue;
            }
        }

        return '';
    }

    private static function stringifyPropertyValue(mixed $value): string
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $stringValue = self::stringifyPropertyValue($item);
                if ($stringValue !== '') {
                    return $stringValue;
                }
            }

            return '';
        }

        if (is_bool($value) || is_scalar($value) || $value === null) {
            return trim((string)$value);
        }

        return '';
    }

    private static function isLocationPropertyMeta(array $propertyMeta): bool
    {
        return ($propertyMeta['TYPE'] ?? '') === 'LOCATION'
            || ($propertyMeta['IS_LOCATION'] ?? '') === 'Y';
    }

    /**
     * @param mixed $service
     * @return array{ID:int,NAME:string,DESCRIPTION:string}|null
     */
    private static function normalizePaySystemMeta(mixed $service): ?array
    {
        if (is_array($service)) {
            return [
                'ID' => (int)($service['ID'] ?? 0),
                'NAME' => (string)($service['NAME'] ?? ''),
                'DESCRIPTION' => (string)($service['DESCRIPTION'] ?? ''),
            ];
        }

        if (is_object($service) && method_exists($service, 'getField')) {
            return [
                'ID' => (int)$service->getField('ID'),
                'NAME' => (string)$service->getField('NAME'),
                'DESCRIPTION' => (string)$service->getField('DESCRIPTION'),
            ];
        }

        return null;
    }

    private static function searchLocationsViaLocationTable(string $query, int $limit): array
    {
        if (!class_exists('\Bitrix\Sale\Location\LocationTable')) {
            return [];
        }

        $langId = defined('LANGUAGE_ID') ? (string)LANGUAGE_ID : 'ru';
        $items = [];

        try {
            $res = \Bitrix\Sale\Location\LocationTable::getList([
                'select' => [
                    'ID',
                    'CODE',
                    'LOC_NAME' => 'NAME.NAME',
                ],
                'filter' => [
                    '=NAME.LANGUAGE_ID' => $langId,
                    '%NAME.NAME' => $query,
                ],
                'order' => ['LOC_NAME' => 'ASC'],
                'limit' => $limit,
            ]);

            while ($row = $res->fetch()) {
                $displayName = trim((string)($row['LOC_NAME'] ?? ''));
                if ($displayName === '') {
                    continue;
                }
                $items[] = [
                    'id' => (int)$row['ID'],
                    'code' => (string)($row['CODE'] ?? ''),
                    'displayName' => $displayName,
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $items;
    }

    private static function searchLocationsViaLegacyApi(string $query, int $limit): array
    {
        $items = [];

        if (!class_exists('\CSaleLocation')) {
            return [];
        }

        $res = \CSaleLocation::GetList(
            ['SORT' => 'ASC', 'NAME_LANG' => 'ASC'],
            ['LNAME' => $query . '%'],
            false,
            ['nTopCount' => $limit],
            ['ID', 'CODE', 'CITY_NAME', 'REGION_NAME', 'COUNTRY_NAME']
        );

        while ($row = $res->Fetch()) {
            $parts = array_filter([
                $row['CITY_NAME'] ?? '',
                $row['REGION_NAME'] ?? '',
                $row['COUNTRY_NAME'] ?? '',
            ]);
            $items[] = [
                'id' => (int)$row['ID'],
                'code' => (string)($row['CODE'] ?? ''),
                'displayName' => implode(', ', $parts),
            ];
        }

        return $items;
    }

    private static function resolveBasketCurrency(Basket $basket): string
    {
        foreach ($basket as $basketItem) {
            $currency = (string)$basketItem->getField('CURRENCY');
            if ($currency !== '') {
                return $currency;
            }
        }

        return (string)\Bitrix\Currency\CurrencyManager::getBaseCurrency();
    }

    private static function ensureModules(): void
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');
        Loader::includeModule('currency');
    }
}
