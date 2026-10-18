<?php

namespace MyApp\Catalog\Controller;

use MyApp\Catalog\Config\Templates;
use MyApp\Catalog\Service\CheckoutService;

class OrderSuccessController extends BasePageController
{
    public function configureActions()
    {
        return [
            'index' => [
                'prefilters' => [],
            ],
        ];
    }

    public function indexAction(int $id)
    {
        $orderInfo = CheckoutService::getOrderSuccessInfo($id);
        if (!$orderInfo) {
            \CHTTP::SetStatus('404 Not Found');
            include \Bitrix\Main\Application::getDocumentRoot() . '/404.php';
            return null;
        }

        $initialState = [
            'pageTitle'   => 'Заказ оформлен',
            'pageType'    => 'order_success',
            'sectionId'   => 0,
            'contentView' => Templates::viewPath('order/success.php'),
            'data_sections' => [],
            'data_section'  => [],
            'data_product'  => [],
            'data_order'    => $orderInfo,
            'jsEntry'     => 'app-order-success.js',
        ];

        return $this->renderPage($initialState);
    }
}
