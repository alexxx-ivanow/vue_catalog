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

        $section = CatalogService::getGoods([], $page, $limit, $id);

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
            'jsEntry' => 'app-catalog-section.js', // если страница без Vue - jsEntry не нужен
        ];

        return $this->renderPage($initialState);
    }
}