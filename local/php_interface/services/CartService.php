<?php

namespace MyApp;

use \Bitrix\Main\Loader;
use \Bitrix\Main\Context;
use \Bitrix\Sale\Basket;
use \Bitrix\Sale\Fuser;
use \Bitrix\Sale\BasketItem;

class CartService
{
    public static function addProduct(int $productId, int $quantity = 1): array
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');

        $context = Context::getCurrent();
        $siteId  = $context->getSite();

        $fuserId = Fuser::getId();
        $basket  = Basket::loadItemsForFUser($fuserId, $siteId);

        $item = $basket->getExistsItem('catalog', $productId);

        if ($item) {
            $item->setField('QUANTITY', $item->getQuantity() + $quantity);
        } else {
            $item = $basket->createItem('catalog', $productId);
            $item->setFields([
                'QUANTITY' => $quantity,
                'CURRENCY' => \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
                'LID' => \Bitrix\Main\Context::getCurrent()->getSite(),
                'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
            ]);
        }

        $res = $basket->save();

        if (!$res->isSuccess()) {
            return [
                'error' => implode('; ', $res->getErrorMessages())
            ];
        }

        return [
            'success' => true,
            'basketCount' => array_sum($basket->getQuantityList()),
            'basketSum'   => (float)$basket->getPrice(),
        ];
    }
}