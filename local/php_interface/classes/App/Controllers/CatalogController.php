<?php

namespace MyApp;

use Bitrix\Main\Application;

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
        $sections = CatalogService::getSections();

        $initialState = [
            'pageTitle'   => 'Каталог new',
            'pageType' => 'catalog',
            'contentView' => '/local/templates/main/views/catalog/index.php',
            'data_sections'    => $sections,
            'data_section'    => [],
            'data_product'    => [],
            'jsEntry' => 'app-catalog.js', // если страница без Vue - jsEntry не нужен
        ];

        return $this->renderPage($initialState);
    }

    public function sectionAction(int $id)
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $page = max(1, (int)$request->getQuery('page'));
        $limit = max(1, min(48, (int)$request->getQuery('limit') ?: 12));
        $query = $request->getQueryList()->toArray();

        $sectionFilter = [
            'price_from' => isset($query['price_from']) && $query['price_from'] !== '' ? (float)$query['price_from'] : null,
            'price_to' => isset($query['price_to']) && $query['price_to'] !== '' ? (float)$query['price_to'] : null,
            'manufacturers' => $this->normalizeIntList($query['manufacturers'] ?? ($query['manufacturers[]'] ?? [])),
            'sizes' => $this->normalizeIntList($query['sizes'] ?? ($query['sizes[]'] ?? [])),
            'materials' => $this->normalizeStringList($query['materials'] ?? ($query['materials[]'] ?? [])),
        ];

        $section = CatalogService::getGoods($sectionFilter, $page, $limit, $id);

        $sectionPagination = [
            'page' => (int)($section['page'] ?? 1),
            'pages' => (int)($section['pages'] ?? 1),
            'limit' => (int)($section['limit'] ?? $limit),
            'total' => (int)($section['total'] ?? 0),
        ];

        $initialState = [
            'pageTitle'   => 'Каталог new',
            'pageType' => 'section',
            'sectionId' => $id,
            'contentView' => '/local/templates/main/views/catalog/section.php',
            'data_sections'    => [],
            'data_section'    => $section,
            'data_product'    => [],
            'sectionPagination' => $sectionPagination,
            'sectionFilter' => $sectionFilter,
            'jsEntry' => 'app-catalog-section.js', // если страница без Vue - jsEntry не нужен
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