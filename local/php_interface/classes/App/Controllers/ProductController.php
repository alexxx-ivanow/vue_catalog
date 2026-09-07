<?php

namespace MyApp;

use Bitrix\Main\Application;
use MyApp\CatalogService;

class ProductController extends BasePageController
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
        $product = CatalogService::getProduct($id);
        if (!$product) {
            // 404
            \CHTTP::SetStatus('404 Not Found');
            include Application::getDocumentRoot() . '/404.php';
            return null;
        }

        $initialState = [
            'pageTitle'   => 'Товар - ' . $product['NAME'],
            'pageType' => 'product',
            'sectionId' => (int)($product['SECTION_ID'] ?? 0),
            'contentView' => '/local/templates/main/views/catalog/product.php',
            'data_sections'    => [],
            'data_section'    => [],
            'data_product'    => [
                'ID' => (int)$product['ID'],
            ],
            'jsEntry' => 'app-product.js', // если страница без Vue - jsEntry не нужен
        ];

        return $this->renderPage($initialState);
    }
}