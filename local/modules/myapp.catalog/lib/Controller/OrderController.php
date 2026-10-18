<?php

namespace MyApp\Catalog\Controller;

use MyApp\Catalog\Config\Templates;

class OrderController extends BasePageController
{
    public function configureActions()
    {
        return [
            'index' => [
                'prefilters' => [],
            ],
        ];
    }

    public function indexAction()
    {
        $initialState = [
            'pageTitle'   => 'Оформление заказа',
            'pageType'    => 'checkout',
            'sectionId'   => 0,
            'contentView' => Templates::viewPath('order/index.php'),
            'data_sections' => [],
            'data_section'  => [],
            'data_product'  => [],
            'jsEntry'     => 'app-checkout.js',
        ];

        return $this->renderPage($initialState);
    }
}
