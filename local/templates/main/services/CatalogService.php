<?php

namespace MyApp;

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\PropertyEnumerationTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Iblock\SectionTable;
use Bitrix\Main\FileTable;
use Bitrix\Main\Entity\ExpressionField;

class CatalogService
{
    private const IBLOCK_ID      = 2;
    public const GOODS_PER_PAGE  = 12;
    private const PRICE_GROUP_ID = 1;

    // ID свойств из таблицы b_iblock_property
    public const PROP_MANUFACTURER_ID  = 1;
    private const PROP_ARTICLE_ID      = 2;
    public const PROP_MATERIAL_ID      = 3;

    // Категории первого уровня
    public static function getSections(): array
    {
        Loader::includeModule('iblock');

        $db  = Application::getConnection();
        $sql = "
        SELECT ID, NAME, CODE, PICTURE, DESCRIPTION
        FROM b_iblock_section
        WHERE IBLOCK_ID   = " . self::IBLOCK_ID . "
          AND ACTIVE       = 'Y'
          AND DEPTH_LEVEL  = 1
        ORDER BY SORT ASC, NAME ASC
    ";

        $res      = $db->query($sql);
        $sections = [];

        while ($row = $res->fetch()) {
            $row['PICTURE'] = (int)$row['PICTURE']
                ? self::getFilePath((int)$row['PICTURE'])
                : '/images/no_photo.png';
            $sections[] = $row;
        }

        return $sections;
    }

    // Получить LEFT_MARGIN / RIGHT_MARGIN секции — для выборки всех вложенных товаров
    public static function getSectionBounds(int $sectionId): ?array
    {
        $db  = Application::getConnection();
        $sql = "
        SELECT LEFT_MARGIN, RIGHT_MARGIN, NAME
        FROM b_iblock_section
        WHERE ID       = {$sectionId}
          AND IBLOCK_ID = " . self::IBLOCK_ID . "
          AND ACTIVE    = 'Y'
        LIMIT 1
    ";

        return $db->query($sql)->fetch() ?: null;
    }

    // Карточка товара
    public static function getProduct(int $id): ?array
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $result = ElementTable::getList([
            'select' => ['ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'DETAIL_PICTURE'],
            'filter' => ['=IBLOCK_ID' => self::IBLOCK_ID, '=ACTIVE' => 'Y', '=ID' => $id],
        ])->fetch();

        if (!$result) {
            return null;
        }

        $result['PRICE']          = self::getPrice($id);
        if($result['DETAIL_PICTURE']) {
            $result['DETAIL_PICTURE'] = self::getFilePath($result['DETAIL_PICTURE']);
        } else {
            $result['DETAIL_PICTURE'] = '/images/no_photo.png';
        }



        $props = self::fetchProperties([$id]);
        $p = $props[$id] ?? [];
        $result['ARTICLE']      = $p['ARTICLE']      ?? '';
        $result['MANUFACTURER'] = $p['MANUFACTURER'] ?? '';
        $result['SIZE']         = $p['MATERIAL']         ?? '';
        $section = self::getMainSectionForProduct($id);
        $result['SECTION_ID'] = (int)($section['ID'] ?? 0);
        $result['SECTION_NAME'] = (string)($section['NAME'] ?? '');

        return $result;
    }


    // Список товаров с фильтром и пагинацией
    public static function getGoods(
        array $filter    = [],
        int   $page      = 1,
        int   $limit     = self::GOODS_PER_PAGE,
        int   $sectionId = 0
    ): array {

        // Кеш — включаем sectionId в ключ
        $cacheKey = md5(serialize($filter) . $page . $limit . $sectionId);
        $cache    = \Bitrix\Main\Data\Cache::createInstance();

        if ($cache->initCache(3600, $cacheKey, '/catalog/goods/')) {
            return $cache->getVars();
        }

        $cache->startDataCache();

        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $filteredIds = null;

        // Фильтр по артикулу — прямой SQL по b_iblock_element_property
        if (!empty($filter['article'])) {
            $ids = self::getIdsByStringProp(
                self::PROP_ARTICLE_ID,
                $filter['article']
            );
            if (empty($ids)) {
                $cache->abortDataCache(); // не кешируем пустой результат фильтрации
                return self::emptyResult($page, $limit);
            }
            $filteredIds = $ids;
        }

        // Фильтр по производителю — по VALUE_ENUM
        if (!empty($filter['manufacturer'])) {
            $ids = self::getIdsByEnumProp(
                self::PROP_MANUFACTURER_ID,
                (int)$filter['manufacturer']
            );
            if (empty($ids)) {
                $cache->abortDataCache();
                return self::emptyResult($page, $limit);
            }
            $filteredIds = self::intersect($filteredIds, $ids);
            if (empty($filteredIds)) {
                $cache->abortDataCache();
                return self::emptyResult($page, $limit);
            }
        }

        // Фильтр по размеру — по VALUE_ENUM
        if (!empty($filter['size'])) {
            $ids = self::getIdsByEnumProp(
                self::PROP_MATERIAL_ID,
                (int)$filter['size']
            );
            if (empty($ids)) {
                $cache->abortDataCache();
                return self::emptyResult($page, $limit);
            }
            $filteredIds = self::intersect($filteredIds, $ids);
            if (empty($filteredIds)) {
                $cache->abortDataCache();
                return self::emptyResult($page, $limit);
            }
        }

        // Фильтр по цене — через PriceTable D7
        if (!empty($filter['price_from']) || !empty($filter['price_to'])) {
            $priceFilter = ['=CATALOG_GROUP_ID' => self::PRICE_GROUP_ID];
            if (!empty($filter['price_from'])) $priceFilter['>=PRICE'] = (float)$filter['price_from'];
            if (!empty($filter['price_to']))   $priceFilter['<=PRICE'] = (float)$filter['price_to'];

            $res      = PriceTable::getList(['select' => ['PRODUCT_ID'], 'filter' => $priceFilter]);
            $priceIds = [];
            while ($row = $res->fetch()) $priceIds[] = (int)$row['PRODUCT_ID'];

            if (empty($priceIds)) {
                $cache->abortDataCache();
                return self::emptyResult($page, $limit);
            }
            $filteredIds = self::intersect($filteredIds, $priceIds);
            if (empty($filteredIds)) {
                $cache->abortDataCache();
                return self::emptyResult($page, $limit);
            }
        }

        $db    = Application::getConnection();
        $where = "e.IBLOCK_ID = " . self::IBLOCK_ID . " AND e.ACTIVE = 'Y'";

        // Фильтр по секции через LEFT/RIGHT MARGIN — захватывает все вложенные
        if ($sectionId > 0) {
            $bounds = self::getSectionBounds($sectionId);
            if (!$bounds) {
                $cache->abortDataCache();
                return self::emptyResult($page, $limit);
            }
            $lm = (int)$bounds['LEFT_MARGIN'];
            $rm = (int)$bounds['RIGHT_MARGIN'];

            // Джоин с b_iblock_section_element и b_iblock_section
            $sectionJoin = "
            JOIN b_iblock_section_element se ON se.IBLOCK_ELEMENT_ID = e.ID
            JOIN b_iblock_section          s  ON s.ID = se.IBLOCK_SECTION_ID
                AND s.LEFT_MARGIN  >= {$lm}
                AND s.RIGHT_MARGIN <= {$rm}
        ";
        } else {
            $sectionJoin = '';
        }

        if ($filteredIds !== null) {
            $ids    = array_map('intval', $filteredIds);
            $where .= " AND e.ID IN (" . implode(',', $ids) . ")";
        }

        // COUNT с учётом секции
        $countSql = "
        SELECT COUNT(DISTINCT e.ID) as CNT
        FROM b_iblock_element e
        {$sectionJoin}
        WHERE {$where}
    ";
        $total  = (int)$db->query($countSql)->fetch()['CNT'];
        $pages  = (int)ceil($total / $limit);
        $page   = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $limit;

        // Выборка страницы
        $sql = "
        SELECT DISTINCT e.ID, e.NAME, e.CODE, e.PREVIEW_TEXT, e.DETAIL_PICTURE, s.NAME as SECTION_NAME
        FROM b_iblock_element e
        {$sectionJoin}
        WHERE {$where}
        ORDER BY e.SORT ASC, e.ID DESC
        LIMIT {$limit} OFFSET {$offset}
    ";

        $res     = $db->query($sql);
        $items   = [];
        $itemIds = [];

        while ($row = $res->fetch()) {
            $row['DETAIL_PICTURE'] = (int)$row['DETAIL_PICTURE']
                ? self::getFilePath((int)$row['DETAIL_PICTURE'])
                : '/images/no_photo.png';
            $items[]   = $row;
            $itemIds[] = (int)$row['ID'];
            $sectionName = $row['SECTION_NAME'];
        }

        $prices = self::getPrices($itemIds);
        foreach ($items as &$item) {
            $item['PRICE'] = $prices[(int)$item['ID']] ?? 0.0;
        }
        unset($item);

        if (!empty($itemIds)) {
            $props = self::fetchProperties($itemIds);
            foreach ($items as &$item) {
                $p = $props[(int)$item['ID']] ?? [];
                $item['ARTICLE']      = $p['CML2_ARTICLE']      ?? '';
                $item['MANUFACTURER'] = $p['CML2_MANUFACTURER'] ?? '';
                $item['SIZE']         = $p['SIZES']     ?? '';
            }
            unset($item);
        }

        $result = [
            'items' => $items,
            'total' => $total,
            'pages' => $pages,
            'page'  => $page,
            'limit' => $limit,
            'sectionName' => $sectionName,
        ];

        $cache->endDataCache($result);

        return $result;
    }

    // резервный метод, пригодится при импорте товаров
    public static function clearCache(): void
    {
        \Bitrix\Main\Data\Cache::clearCache(false, '/catalog/goods/');
    }

    // получаем цены набора товаров одним запросом
    private static function getPrices(array $productIds): array
    {
        if (empty($productIds)) return [];

        $res = PriceTable::getList([
            'select' => ['PRODUCT_ID', 'PRICE'],
            'filter' => [
                '@PRODUCT_ID'      => $productIds,
                '=CATALOG_GROUP_ID' => self::PRICE_GROUP_ID,
            ],
        ]);

        $prices = [];
        while ($row = $res->fetch()) {
            $prices[(int)$row['PRODUCT_ID']] = (float)$row['PRICE'];
        }

        return $prices;
    }

    // Значения для фильтра
    public static function getFilters(int $sectionId = 0): array
    {
        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $db = Application::getConnection();

        // Если секция — получаем ID товаров только из неё
        $elementIdsWhere = '';
        if ($sectionId > 0) {
            $bounds = self::getSectionBounds($sectionId);
            if ($bounds) {
                $lm = (int)$bounds['LEFT_MARGIN'];
                $rm = (int)$bounds['RIGHT_MARGIN'];

                $elementIdsWhere = "AND p.IBLOCK_ELEMENT_ID IN (
                SELECT DISTINCT se.IBLOCK_ELEMENT_ID
                FROM b_iblock_section_element se
                JOIN b_iblock_section s ON s.ID = se.IBLOCK_SECTION_ID
                WHERE s.LEFT_MARGIN  >= {$lm}
                  AND s.RIGHT_MARGIN <= {$rm}
            )";
            }
        }

        // Производители — только те что есть в секции
        $manufacturers = [];
        $res = $db->query("
        SELECT DISTINCT pe.ID, pe.VALUE
        FROM b_iblock_property_enum pe
        WHERE pe.PROPERTY_ID = " . self::PROP_MANUFACTURER_ID . "
          AND EXISTS (
              SELECT 1
              FROM b_iblock_element_property p
              WHERE p.IBLOCK_PROPERTY_ID = " . self::PROP_MANUFACTURER_ID . "
                AND p.VALUE_ENUM = pe.ID
                {$elementIdsWhere}
          )
        ORDER BY pe.SORT ASC
    ");
        while ($row = $res->fetch()) {
            $manufacturers[] = ['ID' => $row['ID'], 'VALUE' => $row['VALUE']];
        }

        // Размеры — только те что есть в секции
        $sizes = [];
        $res = $db->query("
        SELECT DISTINCT pe.ID, pe.VALUE
        FROM b_iblock_property_enum pe
        WHERE pe.PROPERTY_ID = " . self::PROP_MATERIAL_ID . "
          AND EXISTS (
              SELECT 1
              FROM b_iblock_element_property p
              WHERE p.IBLOCK_PROPERTY_ID = " . self::PROP_MATERIAL_ID . "
                AND p.VALUE_ENUM = pe.ID
                {$elementIdsWhere}
          )
        ORDER BY pe.SORT ASC
    ");
        while ($row = $res->fetch()) {
            $sizes[] = ['ID' => $row['ID'], 'VALUE' => $row['VALUE']];
        }

        // Мин/макс цена — только по товарам секции
        if ($sectionId > 0 && !empty($elementIdsWhere)) {
            $bounds  = self::getSectionBounds($sectionId);
            $lm      = (int)$bounds['LEFT_MARGIN'];
            $rm      = (int)$bounds['RIGHT_MARGIN'];

            $row = $db->query("
            SELECT MIN(cp.PRICE) as MIN_PRICE, MAX(cp.PRICE) as MAX_PRICE
            FROM b_catalog_price cp
            WHERE cp.CATALOG_GROUP_ID = " . self::PRICE_GROUP_ID . "
              AND cp.PRODUCT_ID IN (
                  SELECT DISTINCT se.IBLOCK_ELEMENT_ID
                  FROM b_iblock_section_element se
                  JOIN b_iblock_section s ON s.ID = se.IBLOCK_SECTION_ID
                  WHERE s.LEFT_MARGIN  >= {$lm}
                    AND s.RIGHT_MARGIN <= {$rm}
              )
        ")->fetch();
        } else {
            $row = $db->query("
            SELECT MIN(PRICE) as MIN_PRICE, MAX(PRICE) as MAX_PRICE
            FROM b_catalog_price
            WHERE CATALOG_GROUP_ID = " . self::PRICE_GROUP_ID . "
        ")->fetch();
        }

        $minPrice = (float)($row['MIN_PRICE'] ?? 0);
        $maxPrice = (float)($row['MAX_PRICE'] ?? 100000);

        return [
            'manufacturers' => $manufacturers,
            'sizes'         => $sizes,
            'price'         => ['min' => $minPrice, 'max' => $maxPrice],
        ];
    }

    public static function getBreadcrumbs(string $pageType, int $sectionId = 0, int $productId = 0): array
    {
        $breadcrumbs = [
            ['title' => 'Главная', 'url' => '/'],
            ['title' => 'Каталог', 'url' => '/catalog/'],
        ];

        if ($pageType === 'section' && $sectionId > 0) {
            $section = self::getSectionBounds($sectionId);
            if (!empty($section['NAME'])) {
                $breadcrumbs[] = ['title' => $section['NAME'], 'url' => null];
            }
        }

        if ($pageType === 'product' && $productId > 0) {
            $product = self::getProduct($productId);
            if ($product) {
                if (!empty($product['SECTION_ID']) && !empty($product['SECTION_NAME'])) {
                    $breadcrumbs[] = [
                        'title' => $product['SECTION_NAME'],
                        'url' => '/catalog/section/' . (int)$product['SECTION_ID'],
                    ];
                }
                $breadcrumbs[] = ['title' => $product['NAME'], 'url' => null];
            }
        }

        if ($pageType === 'catalog') {
            $breadcrumbs[1]['url'] = null;
        }

        return $breadcrumbs;
    }


    // Вспомогательные методы

    /**
     * Свойства нескольких элементов одним SQL-запросом.
     * Без джоинов — прямое обращение к b_iblock_element_property.
     * Возвращает [elementId => ['ARTICLE' => ..., 'MANUFACTURER' => ..., 'SIZE' => ...]]
     */
    private static function fetchProperties(array $elementIds): array
    {
        if (empty($elementIds)) return [];

        $db  = Application::getConnection();
        $ids = implode(',', array_map('intval', $elementIds));

        $propIds = implode(',', [
            self::PROP_ARTICLE_ID,
            self::PROP_MANUFACTURER_ID,
            self::PROP_MATERIAL_ID,
        ]);

        // Один запрос — все нужные свойства для всех элементов страницы
        $sql = "
            SELECT
                p.IBLOCK_ELEMENT_ID,
                p.IBLOCK_PROPERTY_ID,
                p.VALUE,
                p.VALUE_ENUM,
                COALESCE(pe.VALUE, p.VALUE) AS DISPLAY_VALUE
            FROM b_iblock_element_property p
            LEFT JOIN b_iblock_property_enum pe
                ON pe.ID = p.VALUE_ENUM
            WHERE p.IBLOCK_ELEMENT_ID IN ({$ids})
              AND p.IBLOCK_PROPERTY_ID IN ({$propIds})
        ";

        $res    = $db->query($sql);
        $result = [];

        while ($row = $res->fetch()) {
            $elemId = (int)$row['IBLOCK_ELEMENT_ID'];
            $propId = (int)$row['IBLOCK_PROPERTY_ID'];

            $key = match ($propId) {
                self::PROP_ARTICLE_ID      => 'ARTICLE',
                self::PROP_MANUFACTURER_ID => 'MANUFACTURER',
                self::PROP_MATERIAL_ID         => 'MATERIAL',
                default                    => null,
            };

            if ($key !== null) {
                $result[$elemId][$key] = $row['DISPLAY_VALUE'];
            }
        }

        return $result;
    }

    /**
     * ID элементов по строковому свойству (поиск подстроки).
     * Прямой SQL — без ORM-джоинов.
     */
    private static function getIdsByStringProp(int $propId, string $value): array
    {
        $db    = Application::getConnection();
        $value = $db->getSqlHelper()->forSql($value);

        $sql = "
            SELECT IBLOCK_ELEMENT_ID
            FROM b_iblock_element_property
            WHERE IBLOCK_PROPERTY_ID = {$propId}
              AND VALUE LIKE '%{$value}%'
        ";

        $res = $db->query($sql);
        $ids = [];
        while ($row = $res->fetch()) {
            $ids[] = (int)$row['IBLOCK_ELEMENT_ID'];
        }

        return $ids;
    }

    /**
     * ID элементов по enum-свойству (по ID значения из b_iblock_property_enum).
     * Прямой SQL — без ORM-джоинов.
     */
    private static function getIdsByEnumProp(int $propId, int $enumId): array
    {
        $db = Application::getConnection();

        $sql = "
            SELECT IBLOCK_ELEMENT_ID
            FROM b_iblock_element_property
            WHERE IBLOCK_PROPERTY_ID = {$propId}
              AND VALUE_ENUM = {$enumId}
        ";

        $res = $db->query($sql);
        $ids = [];
        while ($row = $res->fetch()) {
            $ids[] = (int)$row['IBLOCK_ELEMENT_ID'];
        }

        return $ids;
    }

    private static function getPrice(int $productId): float
    {
        $row = PriceTable::getList([
            'select' => ['PRICE'],
            'filter' => [
                '=PRODUCT_ID'      => $productId,
                '=CATALOG_GROUP_ID' => self::PRICE_GROUP_ID,
            ],
        ])->fetch();

        return $row ? (float)$row['PRICE'] : 0.0;
    }

    private static function getFilePath(?int $fileId): string
    {
        if (!$fileId) return '';

        $file = FileTable::getList([
            'select' => ['SUBDIR', 'FILE_NAME'],
            'filter' => ['=ID' => $fileId],
        ])->fetch();

        return $file ? '/upload/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'] : '';
    }

    private static function intersect(?array $existing, array $new): array
    {
        if ($existing === null) return $new;
        return array_values(array_intersect($existing, $new));
    }

    private static function emptyResult(int $page, int $limit): array
    {
        return [
            'items' => [],
            'total' => 0,
            'pages' => 0,
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    private static function getMainSectionForProduct(int $productId): ?array
    {
        $db = Application::getConnection();

        $sql = "
        SELECT s.ID, s.NAME
        FROM b_iblock_section_element se
        JOIN b_iblock_section s ON s.ID = se.IBLOCK_SECTION_ID
        WHERE se.IBLOCK_ELEMENT_ID = {$productId}
          AND s.IBLOCK_ID = " . self::IBLOCK_ID . "
          AND s.ACTIVE = 'Y'
        ORDER BY s.DEPTH_LEVEL DESC, s.SORT ASC
        LIMIT 1
    ";

        return $db->query($sql)->fetch() ?: null;
    }
}
