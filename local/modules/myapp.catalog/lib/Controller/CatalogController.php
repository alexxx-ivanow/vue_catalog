<?php

namespace MyApp\Catalog\Controller;

use Bitrix\Main\Context;
use MyApp\Catalog\Config\Templates;
use MyApp\Catalog\Service\CatalogService;

class CatalogController extends BasePageController
{
    public function configureActions()
    {
        return [
            'index' => [
                'prefilters' => [],
            ],
            'section' => [
                'prefilters' => [],
            ],
        ];
    }

    public function indexAction()
    {
        $initialState = [
            'pageTitle'   => 'Каталог new',
            'pageType' => 'catalog',
            'contentView' => Templates::viewPath('catalog/index.php'),
            'data_sections'    => CatalogService::getSections(),
            'data_section'    => [],
            'data_product'    => [],
            'jsEntry' => 'app-catalog.js',
        ];

        return $this->renderPage($initialState);
    }

    public function sectionAction(int $id)
    {
        $request = Context::getCurrent()->getRequest();

        $manufacturers = $this->normalizeIntList(
            $request->get('manufacturer') ?? $request->get('manufacturers')
        );
        $sizes = $this->normalizeIntList(
            $request->get('size') ?? $request->get('sizes')
        );
        $materials = $this->normalizeStringList(
            $request->get('material') ?? $request->get('materials')
        );

        $priceFrom = $request->get('price_from') !== null && $request->get('price_from') !== ''
            ? (float)$request->get('price_from')
            : null;
        $priceTo = $request->get('price_to') !== null && $request->get('price_to') !== ''
            ? (float)$request->get('price_to')
            : null;

        $filters = CatalogService::getFilters($id);
        $minPrice = (float)($filters['price']['min'] ?? 0);
        $maxPrice = (float)($filters['price']['max'] ?? 0);

        if ($priceFrom === null || $priceFrom <= 0) {
            $priceFrom = $minPrice;
        } else {
            $priceFrom = max($minPrice, min($priceFrom, $maxPrice));
        }

        if ($priceTo === null || $priceTo <= 0) {
            $priceTo = $maxPrice;
        } else {
            $priceTo = max($minPrice, min($priceTo, $maxPrice));
        }

        $filter = [
            'article'       => '',
            'price_from'    => $priceFrom > $minPrice ? $priceFrom : null,
            'price_to'      => $priceTo < $maxPrice ? $priceTo : null,
            'sizes'         => $sizes,
            'manufacturers' => $manufacturers,
            'materials'     => $materials,
        ];

        $page = max(1, (int)$request->get('page') ?: 1);
        $limit = CatalogService::normalizeGoodsLimit($request->get('limit'));

        $goods = CatalogService::getGoods($filter, $page, $limit, $id);

        $initialState = [
            'pageTitle'   => 'Каталог new',
            'pageType' => 'section',
            'sectionId' => $id,
            'contentView' => Templates::viewPath('catalog/section.php'),
            'data_sections'    => [],
            'data_section'    => $goods,
            'data_product'    => [],
            'sectionPagination' => [
                'page'  => (int)($goods['page'] ?? 1),
                'pages' => (int)($goods['pages'] ?? 1),
                'limit' => (int)($goods['limit'] ?? $limit),
                'total' => (int)($goods['total'] ?? 0),
            ],
            'sectionFilter' => $filters,
            'jsEntry' => 'app-catalog-section.js',
        ];

        return $this->renderPage($initialState);
    }

    private function normalizeIntList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($items as $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $intValue = (int)$item;
            if ($intValue > 0) {
                $result[] = $intValue;
            }
        }

        return array_values(array_unique($result));
    }

    private function normalizeStringList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }
            $str = trim((string)$item);
            if ($str !== '') {
                $result[] = $str;
            }
        }

        return array_values(array_unique($result));
    }
}
