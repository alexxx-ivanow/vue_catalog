<?php

namespace MyApp;

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Main\FileTable;

class CatalogService
{
    private const IBLOCK_ID      = 2;
    public const GOODS_PER_PAGE  = 12;
    private const PRICE_GROUP_ID = 1;

    // ID свойств из таблицы b_iblock_property
    public const PROP_MANUFACTURER_ID  = 25;
    private const PROP_ARTICLE_ID      = 27;
    public const PROP_SIZES_ID         = 26;
    private const PROP_MATERIAL_CODE   = 'MATERIAL';

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
        $result['SIZE']         = $p['SIZE'] ?? '';
        $result['MATERIAL']     = $p['MATERIAL'] ?? '';
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
        $measurePerf = self::shouldMeasurePerf();
        $skipCache = self::shouldSkipCache();
        $perfStartedAt = microtime(true);
        $perf = [
            'cache_hit' => false,
            'elapsed_ms' => null,
            'sql_count' => null,
            'sql_time_ms' => null,
            'stages_ms' => [],
            'count_cache_hit' => false,
        ];

        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        // Кеш — включаем sectionId и нормализованный фильтр в ключ.
        ksort($filter);
        $cacheKey = md5(serialize($filter) . '|' . $page . '|' . $limit . '|' . $sectionId);
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if (!$skipCache && $cache->initCache(3600, $cacheKey, '/catalog/goods/')) {
            $result = $cache->getVars();
            if ($measurePerf) {
                $perf['cache_hit'] = true;
                $perf['elapsed_ms'] = round((microtime(true) - $perfStartedAt) * 1000, 2);
                $result['_perf'] = $perf;
            }
            return $result;
        }
        if (!$skipCache) {
            $cache->startDataCache();
        }

        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $db = Application::getConnection();
        $tracker = null;
        if ($measurePerf && method_exists($db, 'startTracker')) {
            $tracker = $db->startTracker();
        }

        $sqlHelper = $db->getSqlHelper();
        $whereParts = [
            "e.IBLOCK_ID = " . self::IBLOCK_ID,
            "e.ACTIVE = 'Y'",
        ];
        $sectionName = '';
        $subsections = [];

        // Фильтр по секции через LEFT/RIGHT MARGIN — захватывает вложенные секции.
        if ($sectionId > 0) {
            $stageStartedAt = microtime(true);
            $bounds = self::getSectionBounds($sectionId);
            if (!$bounds) {
                $result = self::emptyResult($page, $limit, '');
                if (!$skipCache) {
                    $cache->endDataCache($result);
                }
                return $result;
            }

            $sectionName = (string)$bounds['NAME'];
            $subsections = self::getDirectSubsections($sectionId);
            $lm = (int)$bounds['LEFT_MARGIN'];
            $rm = (int)$bounds['RIGHT_MARGIN'];
            $sectionIds = self::getSectionIdsByBounds($lm, $rm);
            if (empty($sectionIds)) {
                $result = self::emptyResult($page, $limit, $sectionName, $subsections);
                if (!$skipCache) {
                    $cache->endDataCache($result);
                }
                return $result;
            }

            $sectionIdsSql = implode(',', array_map('intval', $sectionIds));
            $whereParts[] = "EXISTS (
                SELECT 1
                FROM b_iblock_section_element se
                WHERE se.IBLOCK_ELEMENT_ID = e.ID
                  AND se.IBLOCK_SECTION_ID IN ({$sectionIdsSql})
            )";
            if ($measurePerf) {
                $perf['stages_ms']['section_scope'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
            }
        }

        $article = trim((string)($filter['article'] ?? ''));
        if ($article !== '') {
            $articleEscaped = $sqlHelper->forSql($article);
            $whereParts[] = "EXISTS (
                SELECT 1
                FROM b_iblock_element_property p_article
                WHERE p_article.IBLOCK_ELEMENT_ID = e.ID
                  AND p_article.IBLOCK_PROPERTY_ID = " . self::PROP_ARTICLE_ID . "
                  AND p_article.VALUE LIKE '%{$articleEscaped}%'
            )";
        }

        $manufacturers = self::normalizeIntFilterList($filter['manufacturers'] ?? ($filter['manufacturer'] ?? []));
        if (!empty($manufacturers)) {
            $manufacturerIds = implode(',', array_map('intval', $manufacturers));
            $whereParts[] = "EXISTS (
                SELECT 1
                FROM b_iblock_element_property p_manufacturer
                WHERE p_manufacturer.IBLOCK_ELEMENT_ID = e.ID
                  AND p_manufacturer.IBLOCK_PROPERTY_ID = " . self::PROP_MANUFACTURER_ID . "
                  AND p_manufacturer.VALUE_ENUM IN ({$manufacturerIds})
            )";
        }

        $sizes = self::normalizeIntFilterList($filter['sizes'] ?? ($filter['size'] ?? []));
        if (!empty($sizes)) {
            $sizeIds = implode(',', array_map('intval', $sizes));
            $whereParts[] = "EXISTS (
                SELECT 1
                FROM b_iblock_element_property p_size
                WHERE p_size.IBLOCK_ELEMENT_ID = e.ID
                  AND p_size.IBLOCK_PROPERTY_ID = " . self::PROP_SIZES_ID . "
                  AND p_size.VALUE_ENUM IN ({$sizeIds})
            )";
        }

        $materials = self::normalizeStringFilterList($filter['materials'] ?? ($filter['material'] ?? []));
        if (!empty($materials)) {
            $materialPropertyId = self::getPropertyIdByCode(self::PROP_MATERIAL_CODE);
            if ($materialPropertyId > 0) {
                $materialValues = array_map(
                    static fn(string $value): string => "'" . $sqlHelper->forSql($value) . "'",
                    $materials
                );
                $materialValuesSql = implode(',', $materialValues);
                $whereParts[] = "EXISTS (
                    SELECT 1
                    FROM b_iblock_element_property p_material
                    WHERE p_material.IBLOCK_ELEMENT_ID = e.ID
                      AND p_material.IBLOCK_PROPERTY_ID = {$materialPropertyId}
                      AND p_material.VALUE IN ({$materialValuesSql})
                )";
            }
        }

        $hasPriceFrom = isset($filter['price_from']) && $filter['price_from'] !== '' && $filter['price_from'] !== null;
        $hasPriceTo = isset($filter['price_to']) && $filter['price_to'] !== '' && $filter['price_to'] !== null;
        if ($hasPriceFrom || $hasPriceTo) {
            $priceFrom = $hasPriceFrom ? (float)$filter['price_from'] : null;
            $priceTo = $hasPriceTo ? (float)$filter['price_to'] : null;

            $priceWhere = "cp.PRODUCT_ID = e.ID AND cp.CATALOG_GROUP_ID = " . self::PRICE_GROUP_ID;
            if ($priceFrom !== null) {
                $priceWhere .= " AND cp.PRICE >= {$priceFrom}";
            }
            if ($priceTo !== null) {
                $priceWhere .= " AND cp.PRICE <= {$priceTo}";
            }

            $whereParts[] = "EXISTS (
                SELECT 1
                FROM b_catalog_price cp
                WHERE {$priceWhere}
            )";
        }

        $where = implode(' AND ', $whereParts);

        $stageStartedAt = microtime(true);
        $countCacheKey = md5($where);
        $totalInfo = self::getTotalCountCached($db, $where, $countCacheKey, $skipCache);
        $total = (int)$totalInfo['total'];
        if ($measurePerf) {
            $perf['count_cache_hit'] = (bool)$totalInfo['cache_hit'];
            $perf['stages_ms']['count'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
        }
        $pages = (int)ceil($total / $limit);
        $page = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $limit;

        $stageStartedAt = microtime(true);
        $sql = "
            SELECT e.ID, e.NAME, e.CODE, e.PREVIEW_TEXT, e.DETAIL_PICTURE
            FROM b_iblock_element e
            WHERE {$where}
            ORDER BY e.SORT ASC, e.ID DESC
            LIMIT {$limit} OFFSET {$offset}
        ";

        $res = $db->query($sql);
        $items = [];
        $itemIds = [];

        $pictureIds = [];
        while ($row = $res->fetch()) {
            $itemId = (int)$row['ID'];
            $pictureId = (int)$row['DETAIL_PICTURE'];
            $itemIds[] = $itemId;
            if ($pictureId > 0) {
                $pictureIds[$pictureId] = $pictureId;
            }
            $items[] = $row;
        }
        if ($measurePerf) {
            $perf['stages_ms']['list'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
        }

        $stageStartedAt = microtime(true);
        $pictureMap = self::getFilePaths(array_values($pictureIds));
        if ($measurePerf) {
            $perf['stages_ms']['files'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
        }

        $stageStartedAt = microtime(true);
        $prices = self::getPrices($itemIds);
        if ($measurePerf) {
            $perf['stages_ms']['prices'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
        }

        $stageStartedAt = microtime(true);
        $props = !empty($itemIds) ? self::fetchProperties($itemIds) : [];
        if ($measurePerf) {
            $perf['stages_ms']['props'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
        }

        foreach ($items as &$item) {
            $id = (int)$item['ID'];
            $detailPictureId = (int)$item['DETAIL_PICTURE'];
            $p = $props[$id] ?? [];

            $item['DETAIL_PICTURE'] = $detailPictureId > 0
                ? ($pictureMap[$detailPictureId] ?? '/images/no_photo.png')
                : '/images/no_photo.png';
            $item['PRICE'] = $prices[$id] ?? 0.0;
            $item['ARTICLE'] = $p['ARTICLE'] ?? '';
            $item['MANUFACTURER'] = $p['MANUFACTURER'] ?? '';
            $item['SIZE'] = $p['SIZE'] ?? '';
            $item['MATERIAL'] = $p['MATERIAL'] ?? '';
        }
        unset($item);

        $result = [
            'items' => $items,
            'total' => $total,
            'pages' => $pages,
            'page'  => $page,
            'limit' => $limit,
            'sectionName' => $sectionName,
            'subsections' => $subsections,
        ];

        if ($measurePerf) {
            if ($tracker !== null && method_exists($db, 'stopTracker')) {
                $db->stopTracker();
            }

            if ($tracker !== null && method_exists($tracker, 'getQueries')) {
                $queries = $tracker->getQueries();
                $sqlCount = is_array($queries) ? count($queries) : 0;
                $sqlTime = 0.0;

                if (is_array($queries)) {
                    foreach ($queries as $query) {
                        if (is_object($query) && method_exists($query, 'getTime')) {
                            $sqlTime += (float)$query->getTime();
                        } elseif (is_array($query) && isset($query['time'])) {
                            $sqlTime += (float)$query['time'];
                        }
                    }
                }

                $perf['sql_count'] = $sqlCount;
                $perf['sql_time_ms'] = round($sqlTime * 1000, 2);
            }

            $perf['elapsed_ms'] = round((microtime(true) - $perfStartedAt) * 1000, 2);
            $result['_perf'] = $perf;
        }

        if (!$skipCache) {
            $cache->endDataCache($result);
        }

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
        $bounds = null;
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
        WHERE pe.PROPERTY_ID = " . self::PROP_SIZES_ID . "
          AND EXISTS (
              SELECT 1
              FROM b_iblock_element_property p
              WHERE p.IBLOCK_PROPERTY_ID = " . self::PROP_SIZES_ID . "
                AND p.VALUE_ENUM = pe.ID
                {$elementIdsWhere}
          )
        ORDER BY pe.SORT ASC
    ");
        while ($row = $res->fetch()) {
            $sizes[] = ['ID' => $row['ID'], 'VALUE' => $row['VALUE']];
        }

        // Мин/макс цена — только по товарам секции
        if ($sectionId > 0 && !empty($elementIdsWhere) && !empty($bounds)) {
            $lm = (int)$bounds['LEFT_MARGIN'];
            $rm = (int)$bounds['RIGHT_MARGIN'];

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

        $materials = [];
        $materialPropertyId = self::getPropertyIdByCode(self::PROP_MATERIAL_CODE);
        if ($materialPropertyId > 0) {
            $res = $db->query("
                SELECT DISTINCT p.VALUE
                FROM b_iblock_element_property p
                WHERE p.IBLOCK_PROPERTY_ID = {$materialPropertyId}
                  AND p.VALUE IS NOT NULL
                  AND p.VALUE <> ''
                  {$elementIdsWhere}
                ORDER BY p.VALUE ASC
            ");
            while ($row = $res->fetch()) {
                $materials[] = ['VALUE' => (string)$row['VALUE']];
            }
        }

        return [
            'manufacturers' => $manufacturers,
            'sizes'         => $sizes,
            'materials'     => $materials,
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
            $sectionPath = self::getSectionPath($sectionId);
            $lastIndex = count($sectionPath) - 1;
            foreach ($sectionPath as $index => $section) {
                $breadcrumbs[] = [
                    'title' => $section['NAME'],
                    'url' => $index === $lastIndex ? null : '/catalog/section/' . (int)$section['ID'],
                ];
            }
        }

        if ($pageType === 'product' && $productId > 0) {
            $product = self::getProduct($productId);
            if ($product) {
                if (!empty($product['SECTION_ID'])) {
                    $sectionPath = self::getSectionPath((int)$product['SECTION_ID']);
                    foreach ($sectionPath as $section) {
                        $breadcrumbs[] = [
                            'title' => $section['NAME'],
                            'url' => '/catalog/section/' . (int)$section['ID'],
                        ];
                    }
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
        $materialPropertyId = self::getPropertyIdByCode(self::PROP_MATERIAL_CODE);

        $propIdsRaw = [
            self::PROP_ARTICLE_ID,
            self::PROP_MANUFACTURER_ID,
            self::PROP_SIZES_ID,
        ];
        if ($materialPropertyId > 0) {
            $propIdsRaw[] = $materialPropertyId;
        }
        $propIds = implode(',', array_map('intval', $propIdsRaw));

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

            $key = null;
            if ($propId === self::PROP_ARTICLE_ID) {
                $key = 'ARTICLE';
            } elseif ($propId === self::PROP_MANUFACTURER_ID) {
                $key = 'MANUFACTURER';
            } elseif ($propId === self::PROP_SIZES_ID) {
                $key = 'SIZE';
            } elseif ($materialPropertyId > 0 && $propId === $materialPropertyId) {
                $key = 'MATERIAL';
            }

            if ($key !== null) {
                $result[$elemId][$key] = $row['DISPLAY_VALUE'];
            }
        }

        return $result;
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

    private static function getFilePaths(array $fileIds): array
    {
        if (empty($fileIds)) return [];

        $res = FileTable::getList([
            'select' => ['ID', 'SUBDIR', 'FILE_NAME'],
            'filter' => ['@ID' => $fileIds],
        ]);

        $map = [];
        while ($file = $res->fetch()) {
            $map[(int)$file['ID']] = '/upload/' . $file['SUBDIR'] . '/' . $file['FILE_NAME'];
        }

        return $map;
    }

    private static function normalizeIntFilterList(mixed $value): array
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

    private static function normalizeStringFilterList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }
            $strValue = trim((string)$item);
            if ($strValue !== '') {
                $result[] = $strValue;
            }
        }

        return array_values(array_unique($result));
    }

    private static function getPropertyIdByCode(string $code): int
    {
        static $cache = [];
        if (array_key_exists($code, $cache)) {
            return $cache[$code];
        }

        $db = Application::getConnection();
        $safeCode = $db->getSqlHelper()->forSql($code);
        $sql = "
            SELECT ID
            FROM b_iblock_property
            WHERE IBLOCK_ID = " . self::IBLOCK_ID . "
              AND CODE = '{$safeCode}'
            LIMIT 1
        ";
        $row = $db->query($sql)->fetch();
        $cache[$code] = (int)($row['ID'] ?? 0);

        return $cache[$code];
    }

    private static function getTotalCountCached(
        \Bitrix\Main\DB\Connection $db,
        string $where,
        string $countCacheKey,
        bool $skipCache
    ): array {
        $countCache = \Bitrix\Main\Data\Cache::createInstance();
        if (!$skipCache && $countCache->initCache(3600, $countCacheKey, '/catalog/goods_count/')) {
            $cached = $countCache->getVars();
            return [
                'total' => (int)($cached['total'] ?? 0),
                'cache_hit' => true,
            ];
        }

        $countSql = "
            SELECT COUNT(1) as CNT
            FROM b_iblock_element e
            WHERE {$where}
        ";
        $total = (int)$db->query($countSql)->fetch()['CNT'];

        if (!$skipCache) {
            $countCache->startDataCache();
            $countCache->endDataCache(['total' => $total]);
        }

        return [
            'total' => $total,
            'cache_hit' => false,
        ];
    }

    private static function getSectionIdsByBounds(int $leftMargin, int $rightMargin): array
    {
        $db = Application::getConnection();
        $sql = "
            SELECT ID
            FROM b_iblock_section
            WHERE IBLOCK_ID = " . self::IBLOCK_ID . "
              AND ACTIVE = 'Y'
              AND LEFT_MARGIN >= {$leftMargin}
              AND RIGHT_MARGIN <= {$rightMargin}
        ";

        $res = $db->query($sql);
        $ids = [];
        while ($row = $res->fetch()) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    private static function emptyResult(int $page, int $limit, string $sectionName = '', array $subsections = []): array
    {
        return [
            'items' => [],
            'total' => 0,
            'pages' => 0,
            'page'  => $page,
            'limit' => $limit,
            'sectionName' => $sectionName,
            'subsections' => $subsections,
        ];
    }

    private static function shouldMeasurePerf(): bool
    {
        try {
            $request = \Bitrix\Main\Context::getCurrent()->getRequest();
            $perf = $request->get('perf');
            return $perf === '1' || $perf === 1 || $perf === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function shouldSkipCache(): bool
    {
        try {
            $request = \Bitrix\Main\Context::getCurrent()->getRequest();
            $skipCache = $request->get('skip_cache');
            return $skipCache === '1' || $skipCache === 1 || $skipCache === true;
        } catch (\Throwable $e) {
            return false;
        }
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

    private static function getDirectSubsections(int $sectionId): array
    {
        $db = Application::getConnection();
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            return [];
        }

        $sql = "
            SELECT ID, NAME
            FROM b_iblock_section
            WHERE IBLOCK_ID = " . self::IBLOCK_ID . "
              AND ACTIVE = 'Y'
              AND IBLOCK_SECTION_ID = {$sectionId}
            ORDER BY SORT ASC, NAME ASC
        ";

        $res = $db->query($sql);
        $result = [];
        while ($row = $res->fetch()) {
            $childId = (int)$row['ID'];
            $result[] = [
                'ID' => $childId,
                'NAME' => (string)$row['NAME'],
                'URL' => '/catalog/section/' . $childId,
            ];
        }

        return $result;
    }

    private static function getSectionPath(int $sectionId): array
    {
        $db = Application::getConnection();
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            return [];
        }

        $sql = "
            SELECT parent.ID, parent.NAME
            FROM b_iblock_section parent
            JOIN b_iblock_section current_section ON current_section.ID = {$sectionId}
            WHERE parent.IBLOCK_ID = " . self::IBLOCK_ID . "
              AND parent.ACTIVE = 'Y'
              AND current_section.IBLOCK_ID = " . self::IBLOCK_ID . "
              AND current_section.ACTIVE = 'Y'
              AND parent.LEFT_MARGIN <= current_section.LEFT_MARGIN
              AND parent.RIGHT_MARGIN >= current_section.RIGHT_MARGIN
            ORDER BY parent.LEFT_MARGIN ASC
        ";

        $res = $db->query($sql);
        $path = [];
        while ($row = $res->fetch()) {
            $path[] = [
                'ID' => (int)$row['ID'],
                'NAME' => (string)$row['NAME'],
            ];
        }

        return $path;
    }
}
