<?php

namespace MyApp\Catalog\Service;

use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Iblock\ElementTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Main\FileTable;
use MyApp\Catalog\Config\Assets;

class CatalogService
{
    public const GOODS_PER_PAGE  = 12;
    public const MAX_GOODS_PER_PAGE = 48;
    private const PRICE_GROUP_ID = 1;

    private const CACHE_DIR_PRODUCT = '/catalog/product/';

    private const CACHE_DIR_GOODS = '/catalog/goods/';

    private const CACHE_DIR_GOODS_COUNT = '/catalog/goods_count/';

    private const CACHE_DIR_FILTERS = '/catalog/filters/';

    // ID свойств из таблицы b_iblock_property
    public const PROP_MANUFACTURER_ID  = 25;
    private const PROP_ARTICLE_ID      = 27;
    public const PROP_SIZES_ID         = 26;
    private const PROP_MATERIAL_CODE   = 'MATERIAL';

    private static function iblockId(): int
    {
        return Assets::catalogIblockId();
    }

    // Категории первого уровня
    public static function getSections(): array
    {
        Loader::includeModule('iblock');

        $db  = Application::getConnection();
        $sql = "
        SELECT ID, NAME, CODE, PICTURE, DESCRIPTION
        FROM b_iblock_section
        WHERE IBLOCK_ID   = " . self::iblockId() . "
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
          AND IBLOCK_ID = " . self::iblockId() . "
          AND ACTIVE    = 'Y'
        LIMIT 1
    ";

        return $db->query($sql)->fetch() ?: null;
    }

    // Карточка товара
    public static function getProduct(int $id): ?array
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }

        $skipCache = self::shouldSkipCache();
        $cacheKey = 'product_' . $id;
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if (!$skipCache && $cache->initCache(Assets::catalogCacheTtl(), $cacheKey, self::CACHE_DIR_PRODUCT)) {
            $cached = $cache->getVars();
            return is_array($cached) && array_key_exists('product', $cached)
                ? $cached['product']
                : null;
        }
        $productCacheStarted = !$skipCache && $cache->startDataCache();

        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $result = ElementTable::getList([
            'select' => ['ID', 'NAME', 'CODE', 'DETAIL_TEXT', 'DETAIL_PICTURE'],
            'filter' => ['=IBLOCK_ID' => self::iblockId(), '=ACTIVE' => 'Y', '=ID' => $id],
        ])->fetch();

        if (!$result) {
            if (!$skipCache) {
                self::commitTaggedDataCache(
                    $cache,
                    self::CACHE_DIR_PRODUCT,
                    self::cacheTagsForProduct($id),
                    ['product' => null],
                    $productCacheStarted
                );
            }
            return null;
        }

        $result['PRICE']          = self::getPrice($id);
        if ($result['DETAIL_PICTURE']) {
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

        if (!$skipCache) {
            self::commitTaggedDataCache(
                $cache,
                self::CACHE_DIR_PRODUCT,
                self::cacheTagsForProduct($id),
                ['product' => $result],
                $productCacheStarted
            );
        }

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
        $perf = self::createPerfBag();

        $page = max(1, $page);
        $limit = self::normalizeGoodsLimit($limit);

        // Кеш — включаем sectionId и нормализованный фильтр в ключ.
        ksort($filter);
        $cacheKey = md5(
            serialize($filter) . '|' . $page . '|' . $limit . '|' . $sectionId
            . '|' . (Assets::showCatalogFilter() ? '1' : '0')
            . '|' . Assets::catalogPriceTypeCode()
            . '|' . implode(',', Assets::filterPropertyCodes())
        );
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if (!$skipCache && $cache->initCache(Assets::catalogCacheTtl(), $cacheKey, self::CACHE_DIR_GOODS)) {
            $result = $cache->getVars();
            if ($measurePerf) {
                $perf['cache_hit'] = true;
                self::finalizePerf($perf, $perfStartedAt);
                $result['_perf'] = $perf;
            }
            return $result;
        }
        $goodsCacheStarted = !$skipCache && $cache->startDataCache();

        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $db = Application::getConnection();

        $sqlHelper = $db->getSqlHelper();
        $whereParts = [
            "e.IBLOCK_ID = " . self::iblockId(),
            "e.ACTIVE = 'Y'",
        ];
        $joinParts = [];
        $needsDistinct = false;
        $sectionName = '';
        $subsections = [];
        $sectionIds = [];
        $sectionIdsSql = '';

        // Фильтр по секции через LEFT/RIGHT MARGIN — захватывает вложенные секции.
        if ($sectionId > 0) {
            $stageStartedAt = microtime(true);

            $t = microtime(true);
            $bounds = self::getSectionBounds($sectionId);
            if ($measurePerf) {
                self::perfAddQuery($perf, 'section_bounds', (microtime(true) - $t) * 1000);
            }
            if (!$bounds) {
                $result = self::emptyResult($page, $limit, '');
                if (!$skipCache) {
                    self::commitTaggedDataCache(
                        $cache,
                        self::CACHE_DIR_GOODS,
                        self::cacheTagsForGoodsListing($sectionId, []),
                        $result,
                        $goodsCacheStarted
                    );
                }
                if ($measurePerf) {
                    self::finalizePerf($perf, $perfStartedAt);
                    $result['_perf'] = $perf;
                }
                return $result;
            }

            $sectionName = (string)$bounds['NAME'];

            $t = microtime(true);
            $subsections = self::getDirectSubsections($sectionId);
            if ($measurePerf) {
                self::perfAddQuery($perf, 'section_subsections', (microtime(true) - $t) * 1000, null, count($subsections));
            }

            $lm = (int)$bounds['LEFT_MARGIN'];
            $rm = (int)$bounds['RIGHT_MARGIN'];

            $t = microtime(true);
            $sectionIds = self::getSectionIdsByBounds($lm, $rm);
            if ($measurePerf) {
                self::perfAddQuery($perf, 'section_ids_by_bounds', (microtime(true) - $t) * 1000, null, count($sectionIds));
            }
            if (empty($sectionIds)) {
                $result = self::emptyResult($page, $limit, $sectionName, $subsections);
                if (!$skipCache) {
                    self::commitTaggedDataCache(
                        $cache,
                        self::CACHE_DIR_GOODS,
                        self::cacheTagsForGoodsListing($sectionId, []),
                        $result,
                        $goodsCacheStarted
                    );
                }
                if ($measurePerf) {
                    self::finalizePerf($perf, $perfStartedAt);
                    $result['_perf'] = $perf;
                }
                return $result;
            }

            $sectionIdsSql = implode(',', array_map('intval', $sectionIds));
            // JOIN по секциям быстрее коррелированного EXISTS; DISTINCT нужен из‑за пересечений.
            $joinParts[] = "INNER JOIN b_iblock_section_element se_scope
                ON se_scope.IBLOCK_ELEMENT_ID = e.ID
               AND se_scope.IBLOCK_SECTION_ID IN ({$sectionIdsSql})";
            $needsDistinct = true;
            if ($measurePerf) {
                $perf['stages_ms']['section_scope'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
                $perf['meta']['section_ids_count'] = count($sectionIds);
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

        $selectedProperties = [];
        $hasPriceFrom = false;
        $hasPriceTo = false;
        if (Assets::showCatalogFilter()) {
            $rawProperties = is_array($filter['properties'] ?? null) ? $filter['properties'] : [];
            $selectedProperties = self::normalizeSelectedProperties($rawProperties);
            foreach ($selectedProperties as $index => $selected) {
                $alias = 'p_filter_' . $index;
                $propertyId = (int)$selected['def']['ID'];
                if ($selected['def']['valueKind'] === 'enum') {
                    $idsSql = implode(',', array_map('intval', $selected['values']));
                    $valueSql = "{$alias}.VALUE_ENUM IN ({$idsSql})";
                } else {
                    $valuesSql = implode(',', array_map(
                        static fn(string $value): string => "'" . $sqlHelper->forSql($value) . "'",
                        $selected['values']
                    ));
                    $valueSql = "{$alias}.VALUE IN ({$valuesSql})";
                }
                $joinParts[] = "INNER JOIN b_iblock_element_property {$alias}
                    ON {$alias}.IBLOCK_ELEMENT_ID = e.ID
                   AND {$alias}.IBLOCK_PROPERTY_ID = {$propertyId}
                   AND {$valueSql}";
                $needsDistinct = true;
            }

            $hasPriceFrom = isset($filter['price_from']) && $filter['price_from'] !== '' && $filter['price_from'] !== null;
            $hasPriceTo = isset($filter['price_to']) && $filter['price_to'] !== '' && $filter['price_to'] !== null;
            if ($hasPriceFrom || $hasPriceTo) {
                $priceFrom = $hasPriceFrom ? (float)$filter['price_from'] : null;
                $priceTo = $hasPriceTo ? (float)$filter['price_to'] : null;

                $priceJoin = "INNER JOIN b_catalog_price cp
                    ON cp.PRODUCT_ID = e.ID
                   AND cp.CATALOG_GROUP_ID = " . self::filterPriceGroupId();
                if ($priceFrom !== null) {
                    $priceJoin .= " AND cp.PRICE >= {$priceFrom}";
                }
                if ($priceTo !== null) {
                    $priceJoin .= " AND cp.PRICE <= {$priceTo}";
                }
                $joinParts[] = $priceJoin;
            }
        }

        $hasExtraFilters = $article !== ''
            || !empty($selectedProperties)
            || $hasPriceFrom
            || $hasPriceTo;

        $useSectionFastPath = ($sectionId > 0 && $sectionIdsSql !== '' && !$hasExtraFilters);

        $perfForQueries = null;
        if ($measurePerf) {
            $perfForQueries = &$perf;
        }

        $stageStartedAt = microtime(true);
        $scopedIds = null;
        if ($useSectionFastPath) {
            // Один проход по секции даёт и total, и порядок: раньше COUNT и ORDER BY
            // дважды делали один и тот же JOIN по всем товарам секции.
            $scopeSql = "
                SELECT e.ID
                FROM b_iblock_section_element se
                INNER JOIN b_iblock_element e
                    ON e.ID = se.IBLOCK_ELEMENT_ID
                WHERE se.IBLOCK_SECTION_ID IN ({$sectionIdsSql})
                  AND e.IBLOCK_ID = " . self::iblockId() . "
                  AND e.ACTIVE = 'Y'
                ORDER BY e.SORT ASC, e.ID DESC
            ";
            $scopeRes = self::queryWithPerf($db, $scopeSql, $perfForQueries, 'goods_section_scope');
            $seen = [];
            while ($row = $scopeRes->fetchRaw()) {
                $seen[(int)$row['ID']] = true;
            }
            $scopedIds = array_keys($seen);
            unset($seen);
            $total = count($scopedIds);
            if ($measurePerf) {
                $perf['meta']['count_mode'] = 'section_scope';
            }
        } else {
            $where = implode(' AND ', $whereParts);
            $joins = !empty($joinParts) ? "\n" . implode("\n", $joinParts) : '';
            $fromSql = "FROM b_iblock_element e{$joins}";
            $countCacheKey = md5($fromSql . '|' . $where . '|' . ($needsDistinct ? '1' : '0'));
            $totalInfo = self::getTotalCountCached(
                $db,
                $fromSql,
                $where,
                $needsDistinct,
                $countCacheKey,
                $skipCache,
                $sectionId,
                $perfForQueries
            );
            $total = (int)$totalInfo['total'];
            if ($measurePerf) {
                $perf['count_cache_hit'] = (bool)$totalInfo['cache_hit'];
                $perf['meta']['count_mode'] = 'full';
            }
        }
        if ($measurePerf) {
            $perf['stages_ms']['count'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
        }
        $pages = (int)ceil($total / $limit);
        $page = max(1, min($page, $pages ?: 1));
        $offset = ($page - 1) * $limit;

        $stageStartedAt = microtime(true);
        $items = [];
        $itemIds = [];
        $pictureIds = [];

        if ($scopedIds !== null) {
            $pageIds = array_slice($scopedIds, $offset, $limit);
            if (!empty($pageIds)) {
                $idsList = implode(',', $pageIds);
                $detailsSql = "
                    SELECT e.ID, e.NAME, e.CODE, e.PREVIEW_TEXT, e.DETAIL_PICTURE
                    FROM b_iblock_element e
                    WHERE e.ID IN ({$idsList})
                ";
                $detailsRes = self::queryWithPerf($db, $detailsSql, $perfForQueries, 'goods_list_details');
                $byId = [];
                while ($row = $detailsRes->fetch()) {
                    $byId[(int)$row['ID']] = $row;
                }
                foreach ($pageIds as $pageId) {
                    if (!isset($byId[$pageId])) {
                        continue;
                    }
                    $row = $byId[$pageId];
                    $pictureId = (int)$row['DETAIL_PICTURE'];
                    $itemIds[] = $pageId;
                    if ($pictureId > 0) {
                        $pictureIds[$pictureId] = $pictureId;
                    }
                    $items[] = $row;
                }
            }
        } else {
            $where = implode(' AND ', $whereParts);
            $joins = !empty($joinParts) ? "\n" . implode("\n", $joinParts) : '';
            $fromSql = "FROM b_iblock_element e{$joins}";
            $groupBy = $needsDistinct
                ? 'GROUP BY e.ID, e.NAME, e.CODE, e.PREVIEW_TEXT, e.DETAIL_PICTURE, e.SORT'
                : '';
            $sql = "
                SELECT e.ID, e.NAME, e.CODE, e.PREVIEW_TEXT, e.DETAIL_PICTURE
                {$fromSql}
                WHERE {$where}
                {$groupBy}
                ORDER BY e.SORT ASC, e.ID DESC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $res = self::queryWithPerf($db, $sql, $perfForQueries, 'goods_list');
            while ($row = $res->fetch()) {
                $itemId = (int)$row['ID'];
                $pictureId = (int)$row['DETAIL_PICTURE'];
                $itemIds[] = $itemId;
                if ($pictureId > 0) {
                    $pictureIds[$pictureId] = $pictureId;
                }
                $items[] = $row;
            }
        }
        if ($measurePerf) {
            $perf['stages_ms']['list'] = round((microtime(true) - $stageStartedAt) * 1000, 2);
            $perf['meta']['items_count'] = count($items);
        }

        $stageStartedAt = microtime(true);
        $pictureMap = self::getFilePaths(array_values($pictureIds));
        if ($measurePerf) {
            $filesMs = (microtime(true) - $stageStartedAt) * 1000;
            $perf['stages_ms']['files'] = round($filesMs, 2);
            self::perfAddQuery($perf, 'files_paths', $filesMs, null, count($pictureIds));
        }

        $stageStartedAt = microtime(true);
        $prices = self::getPrices($itemIds);
        if ($measurePerf) {
            $pricesMs = (microtime(true) - $stageStartedAt) * 1000;
            $perf['stages_ms']['prices'] = round($pricesMs, 2);
            self::perfAddQuery($perf, 'prices', $pricesMs, null, count($itemIds));
        }

        // Свойства (артикул/производитель/…) на карточке списка не нужны — не тянем их.
        foreach ($items as &$item) {
            $id = (int)$item['ID'];
            $detailPictureId = (int)$item['DETAIL_PICTURE'];

            $item['DETAIL_PICTURE'] = $detailPictureId > 0
                ? ($pictureMap[$detailPictureId] ?? '/images/no_photo.png')
                : '/images/no_photo.png';
            $item['PRICE'] = $prices[$id] ?? 0.0;
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
            self::finalizePerf($perf, $perfStartedAt);
            $result['_perf'] = $perf;
            $result['perf'] = $perf;
        }

        if (!$skipCache) {
            $cachePayload = $result;
            unset($cachePayload['_perf'], $cachePayload['perf']);
            self::commitTaggedDataCache(
                $cache,
                self::CACHE_DIR_GOODS,
                self::cacheTagsForGoodsListing($sectionId, $items),
                $cachePayload,
                $goodsCacheStarted
            );
        }

        return $result;
    }

    public static function normalizeGoodsLimit(mixed $limit): int
    {
        $intLimit = (int)$limit;
        if ($intLimit <= 0) {
            $intLimit = self::GOODS_PER_PAGE;
        }

        return max(1, min(self::MAX_GOODS_PER_PAGE, $intLimit));
    }

    // резервный метод, пригодится при импорте товаров
    public static function clearCache(): void
    {
        \Bitrix\Main\Data\Cache::clearCache(false, self::CACHE_DIR_GOODS);
        \Bitrix\Main\Data\Cache::clearCache(false, self::CACHE_DIR_GOODS_COUNT);
        \Bitrix\Main\Data\Cache::clearCache(false, self::CACHE_DIR_FILTERS);
        \Bitrix\Main\Data\Cache::clearCache(false, self::CACHE_DIR_PRODUCT);
    }

    /**
     * @param list<int> $extraSectionIds разделы до сохранения (перенос товара)
     * @param bool $invalidateSectionScopes сброс кеша фильтра/счётчика по цепочке разделов
     */
    public static function invalidateByElement(
        int $elementId,
        array $extraSectionIds = [],
        bool $invalidateSectionScopes = false
    ): void {
        $elementId = (int)$elementId;
        if ($elementId <= 0) {
            return;
        }

        $taggedCache = Application::getInstance()->getTaggedCache();
        $taggedCache->clearByTag(self::tagForElement($elementId));

        if (!$invalidateSectionScopes) {
            return;
        }

        $sectionIds = array_unique(array_merge(
            self::getElementSectionIds($elementId),
            array_map('intval', $extraSectionIds)
        ));

        foreach ($sectionIds as $sectionId) {
            if ($sectionId > 0) {
                self::clearSectionPathTags($sectionId);
            }
        }
    }

    public static function invalidateBySection(int $sectionId, bool $includeDescendants = false): void
    {
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            return;
        }

        self::clearSectionPathTags($sectionId);

        if (!$includeDescendants) {
            return;
        }

        $taggedCache = Application::getInstance()->getTaggedCache();
        foreach (self::getSectionDescendantIds($sectionId) as $childId) {
            $taggedCache->clearByTag(self::tagForSection($childId));
        }
    }

    /**
     * @return list<int>
     */
    public static function getElementSectionIds(int $elementId): array
    {
        $elementId = (int)$elementId;
        if ($elementId <= 0) {
            return [];
        }

        Loader::includeModule('iblock');

        $db = Application::getConnection();
        $sql = "
            SELECT se.IBLOCK_SECTION_ID
            FROM b_iblock_section_element se
            INNER JOIN b_iblock_section s ON s.ID = se.IBLOCK_SECTION_ID
            WHERE se.IBLOCK_ELEMENT_ID = {$elementId}
              AND s.IBLOCK_ID = " . self::iblockId() . "
        ";
        $res = $db->query($sql);
        $ids = [];
        while ($row = $res->fetch()) {
            $ids[] = (int)$row['IBLOCK_SECTION_ID'];
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @return list<string>
     */
    private static function cacheTagsForProduct(int $elementId): array
    {
        return [self::tagForElement($elementId)];
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private static function cacheTagsForGoodsListing(int $sectionId, array $items): array
    {
        $tags = self::cacheTagsForSectionScope($sectionId);
        foreach ($items as $item) {
            $id = (int)($item['ID'] ?? 0);
            if ($id > 0) {
                $tags[] = self::tagForElement($id);
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return list<string>
     */
    private static function cacheTagsForSectionScope(int $sectionId): array
    {
        $sectionId = (int)$sectionId;
        if ($sectionId > 0) {
            return [self::tagForSection($sectionId)];
        }

        return [self::tagForIblock()];
    }

    private static function tagForElement(int $elementId): string
    {
        return 'iblock_element_' . (int)$elementId;
    }

    private static function tagForSection(int $sectionId): string
    {
        return 'iblock_section_' . (int)$sectionId;
    }

    private static function tagForIblock(): string
    {
        return 'iblock_id_' . self::iblockId();
    }

    private static function clearSectionPathTags(int $sectionId): void
    {
        $taggedCache = Application::getInstance()->getTaggedCache();
        foreach (self::getSectionPath($sectionId) as $item) {
            $taggedCache->clearByTag(self::tagForSection((int)$item['ID']));
        }
    }

    /**
     * @return list<int>
     */
    private static function getSectionDescendantIds(int $sectionId): array
    {
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            return [];
        }

        $bounds = self::getSectionBounds($sectionId);
        if (!$bounds) {
            return [];
        }

        $db = Application::getConnection();
        $left = (int)$bounds['LEFT_MARGIN'];
        $right = (int)$bounds['RIGHT_MARGIN'];
        $sql = "
            SELECT ID
            FROM b_iblock_section
            WHERE IBLOCK_ID = " . self::iblockId() . "
              AND LEFT_MARGIN > {$left}
              AND RIGHT_MARGIN < {$right}
        ";
        $res = $db->query($sql);
        $ids = [];
        while ($row = $res->fetch()) {
            $ids[] = (int)$row['ID'];
        }

        return $ids;
    }

    /**
     * @param list<string> $tags
     */
    private static function commitTaggedDataCache(
        \Bitrix\Main\Data\Cache $cache,
        string $dir,
        array $tags,
        mixed $data,
        bool $started
    ): void {
        if (!$started) {
            return;
        }

        $taggedCache = Application::getInstance()->getTaggedCache();
        $taggedCache->startTagCache($dir);
        foreach (array_unique($tags) as $tag) {
            $tag = trim((string)$tag);
            if ($tag !== '') {
                $taggedCache->registerTag($tag);
            }
        }
        $taggedCache->endTagCache();
        $cache->endDataCache($data);
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
        $measurePerf = self::shouldMeasurePerf();
        $skipCache = self::shouldSkipCache();
        $perfStartedAt = microtime(true);
        $perf = self::createPerfBag();

        if (!Assets::showCatalogFilter()) {
            $result = [
                'enabled' => false,
                'properties' => [],
                'price' => ['min' => 0.0, 'max' => 0.0],
            ];
            if ($measurePerf) {
                self::finalizePerf($perf, $perfStartedAt);
                $result['_perf'] = $perf;
            }
            return $result;
        }

        Loader::includeModule('iblock');
        Loader::includeModule('catalog');

        $cacheKey = 'section_' . max(0, $sectionId)
            . '_g' . self::filterPriceGroupId()
            . '_' . md5(implode("\n", Assets::filterPropertyCodes()));
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        if (!$skipCache && $cache->initCache(Assets::catalogCacheTtl(), $cacheKey, self::CACHE_DIR_FILTERS)) {
            $result = $cache->getVars();
            if ($measurePerf) {
                $perf['cache_hit'] = true;
                self::finalizePerf($perf, $perfStartedAt);
                $result['_perf'] = $perf;
            }
            return $result;
        }
        $filtersCacheStarted = !$skipCache && $cache->startDataCache();
        $filtersCacheTags = self::cacheTagsForSectionScope($sectionId);

        $db = Application::getConnection();
        $iblockId = self::iblockId();
        $scopeFrom = "FROM b_iblock_element e";
        $scopeWhere = "e.IBLOCK_ID = {$iblockId} AND e.ACTIVE = 'Y'";
        $perfForQueries = null;
        if ($measurePerf) {
            $perfForQueries = &$perf;
        }

        if ($sectionId > 0) {
            $t = microtime(true);
            $bounds = self::getSectionBounds($sectionId);
            if ($measurePerf) {
                self::perfAddQuery($perf, 'section_bounds', (microtime(true) - $t) * 1000);
            }
            if (!$bounds) {
                $result = self::emptyFiltersPayload();
                if (!$skipCache) {
                    self::commitTaggedDataCache($cache, self::CACHE_DIR_FILTERS, $filtersCacheTags, $result, $filtersCacheStarted);
                }
                if ($measurePerf) {
                    self::finalizePerf($perf, $perfStartedAt);
                    $result['_perf'] = $perf;
                }
                return $result;
            }

            $t = microtime(true);
            $sectionIds = self::getSectionIdsByBounds(
                (int)$bounds['LEFT_MARGIN'],
                (int)$bounds['RIGHT_MARGIN']
            );
            if ($measurePerf) {
                self::perfAddQuery($perf, 'section_ids_by_bounds', (microtime(true) - $t) * 1000, null, count($sectionIds));
            }
            if (empty($sectionIds)) {
                $result = self::emptyFiltersPayload();
                if (!$skipCache) {
                    self::commitTaggedDataCache($cache, self::CACHE_DIR_FILTERS, $filtersCacheTags, $result, $filtersCacheStarted);
                }
                if ($measurePerf) {
                    self::finalizePerf($perf, $perfStartedAt);
                    $result['_perf'] = $perf;
                }
                return $result;
            }

            $sectionIdsSql = implode(',', array_map('intval', $sectionIds));
            $scopeFrom = "FROM b_iblock_section_element se
                INNER JOIN b_iblock_element e
                    ON e.ID = se.IBLOCK_ELEMENT_ID";
            $scopeWhere = "se.IBLOCK_SECTION_ID IN ({$sectionIdsSql})
                AND e.IBLOCK_ID = {$iblockId}
                AND e.ACTIVE = 'Y'";
            if ($measurePerf) {
                $perf['meta']['section_ids_count'] = count($sectionIds);
            }
        }

        $definitions = self::filterPropertyDefinitions();
        $properties = [];
        if (!empty($definitions)) {
            $defsById = [];
            foreach ($definitions as $definition) {
                $defsById[(int)$definition['ID']] = $definition;
            }
            $propIdsSql = implode(',', array_map('intval', array_keys($defsById)));

            $propsSql = "
                SELECT p.IBLOCK_PROPERTY_ID AS PROP_ID, p.VALUE_ENUM, p.VALUE
                {$scopeFrom}
                INNER JOIN b_iblock_element_property p
                    ON p.IBLOCK_ELEMENT_ID = e.ID
                   AND p.IBLOCK_PROPERTY_ID IN ({$propIdsSql})
                WHERE {$scopeWhere}
                GROUP BY p.IBLOCK_PROPERTY_ID, p.VALUE_ENUM, p.VALUE
            ";
            $res = self::queryWithPerf($db, $propsSql, $perfForQueries, 'filters_props');
            $enumIdsByProp = [];
            $stringValuesByProp = [];
            while ($row = $res->fetch()) {
                $propId = (int)$row['PROP_ID'];
                $definition = $defsById[$propId] ?? null;
                if ($definition === null) {
                    continue;
                }
                if ($definition['valueKind'] === 'enum') {
                    $enumId = (int)$row['VALUE_ENUM'];
                    if ($enumId > 0) {
                        $enumIdsByProp[$propId][$enumId] = true;
                    }
                    continue;
                }
                $value = trim((string)$row['VALUE']);
                if ($value !== '') {
                    $stringValuesByProp[$propId][$value] = true;
                }
            }

            $enumItemsByProp = [];
            $enumIds = [];
            foreach ($enumIdsByProp as $ids) {
                foreach (array_keys($ids) as $enumId) {
                    $enumIds[$enumId] = true;
                }
            }
            if (!empty($enumIds)) {
                $enumSql = "
                    SELECT ID, PROPERTY_ID, VALUE
                    FROM b_iblock_property_enum
                    WHERE ID IN (" . implode(',', array_keys($enumIds)) . ")
                    ORDER BY SORT ASC, VALUE ASC
                ";
                $res = self::queryWithPerf($db, $enumSql, $perfForQueries, 'filters_enum_values');
                while ($row = $res->fetch()) {
                    $propertyId = (int)$row['PROPERTY_ID'];
                    $enumItemsByProp[$propertyId][] = [
                        'id' => (int)$row['ID'],
                        'value' => (string)$row['VALUE'],
                    ];
                }
            }

            foreach ($definitions as $definition) {
                $propertyId = (int)$definition['ID'];
                if ($definition['valueKind'] === 'enum') {
                    $values = $enumItemsByProp[$propertyId] ?? [];
                } else {
                    $list = array_keys($stringValuesByProp[$propertyId] ?? []);
                    sort($list, SORT_STRING);
                    $values = array_map(
                        static fn(string $value): array => ['value' => $value],
                        $list
                    );
                }
                if ($values === []) {
                    continue;
                }
                $properties[] = [
                    'code' => $definition['CODE'],
                    'name' => $definition['NAME'],
                    'valueKind' => $definition['valueKind'],
                    'values' => $values,
                ];
            }
        }

        $priceSql = "
            SELECT MIN(cp.PRICE) as MIN_PRICE, MAX(cp.PRICE) as MAX_PRICE
            {$scopeFrom}
            INNER JOIN b_catalog_price cp
                ON cp.PRODUCT_ID = e.ID
               AND cp.CATALOG_GROUP_ID = " . self::filterPriceGroupId() . "
            WHERE {$scopeWhere}
        ";
        $row = self::queryWithPerf($db, $priceSql, $perfForQueries, 'filters_price_minmax')->fetch();

        $minPrice = (int)floor((float)($row['MIN_PRICE'] ?? 0));
        $maxPrice = (int)ceil((float)($row['MAX_PRICE'] ?? 100000));
        if ($maxPrice < $minPrice) {
            $maxPrice = $minPrice;
        }

        $result = [
            'enabled' => true,
            'properties' => $properties,
            'price' => ['min' => $minPrice, 'max' => $maxPrice],
        ];

        if ($measurePerf) {
            self::finalizePerf($perf, $perfStartedAt);
            $result['_perf'] = $perf;
            $result['perf'] = $perf;
        }

        if (!$skipCache) {
            $cachePayload = $result;
            unset($cachePayload['_perf'], $cachePayload['perf']);
            self::commitTaggedDataCache($cache, self::CACHE_DIR_FILTERS, $filtersCacheTags, $cachePayload, $filtersCacheStarted);
        }

        return $result;
    }

    public static function getBreadcrumbs(string $pageType, int $sectionId = 0, int $productId = 0): array
    {
        if ($pageType === 'cart') {
            return [
                ['title' => 'Главная', 'url' => '/'],
                ['title' => 'Корзина', 'url' => null],
            ];
        }

        if ($pageType === 'checkout') {
            return [
                ['title' => 'Главная', 'url' => '/'],
                ['title' => 'Корзина', 'url' => '/cart/'],
                ['title' => 'Оформление заказа', 'url' => null],
            ];
        }

        if ($pageType === 'order_success') {
            return [
                ['title' => 'Главная', 'url' => '/'],
                ['title' => 'Корзина', 'url' => '/cart/'],
                ['title' => 'Заказ оформлен', 'url' => null],
            ];
        }

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

    /**
     * @return array{enabled: bool, properties: array, price: array{min: float, max: float}}
     */
    private static function emptyFiltersPayload(): array
    {
        return [
            'enabled' => true,
            'properties' => [],
            'price' => ['min' => 0.0, 'max' => 0.0],
        ];
    }

    private static function filterPriceGroupId(): int
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $code = Assets::catalogPriceTypeCode();
        $db = Application::getConnection();
        $safeCode = $db->getSqlHelper()->forSql($code);
        $row = $db->query("
            SELECT ID
            FROM b_catalog_group
            WHERE NAME = '{$safeCode}'
            LIMIT 1
        ")->fetch();
        $id = (int)($row['ID'] ?? 0);
        $resolved = $id > 0 ? $id : self::PRICE_GROUP_ID;

        return $resolved;
    }

    /**
     * @return list<array{ID: int, CODE: string, NAME: string, valueKind: string}>
     */
    private static function filterPropertyDefinitions(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $codes = Assets::filterPropertyCodes();
        if ($codes === []) {
            return $cache = [];
        }

        $db = Application::getConnection();
        $sqlHelper = $db->getSqlHelper();
        $inSql = implode(',', array_map(
            static fn(string $code): string => "'" . $sqlHelper->forSql($code) . "'",
            $codes
        ));
        $res = $db->query("
            SELECT ID, CODE, NAME, PROPERTY_TYPE
            FROM b_iblock_property
            WHERE IBLOCK_ID = " . self::iblockId() . "
              AND CODE IN ({$inSql})
        ");
        $byCode = [];
        while ($row = $res->fetch()) {
            $code = (string)$row['CODE'];
            $byCode[strtoupper($code)] = [
                'ID' => (int)$row['ID'],
                'CODE' => $code,
                'NAME' => (string)$row['NAME'],
                'valueKind' => ((string)$row['PROPERTY_TYPE'] === 'L') ? 'enum' : 'string',
            ];
        }

        $ordered = [];
        foreach ($codes as $code) {
            $key = strtoupper($code);
            if (isset($byCode[$key])) {
                $ordered[] = $byCode[$key];
            }
        }

        return $cache = $ordered;
    }

    /**
     * @param array<string, mixed> $raw
     * @return list<array{def: array, values: array}>
     */
    private static function normalizeSelectedProperties(array $raw): array
    {
        $selected = [];
        foreach (self::filterPropertyDefinitions() as $definition) {
            $values = null;
            foreach ($raw as $code => $list) {
                if (strcasecmp((string)$code, $definition['CODE']) === 0) {
                    $values = $list;
                    break;
                }
            }
            if (!is_array($values) || $values === []) {
                continue;
            }
            if ($definition['valueKind'] === 'enum') {
                $normalized = self::normalizeIntFilterList($values);
            } else {
                $normalized = self::normalizeStringFilterList($values);
            }
            if ($normalized === []) {
                continue;
            }
            $selected[] = [
                'def' => $definition,
                'values' => $normalized,
            ];
        }

        return $selected;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function parseRequestProperties(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $code => $values) {
            $code = trim((string)$code);
            if ($code === '' || !preg_match('/^[A-Za-z0-9_]+$/', $code)) {
                continue;
            }
            $items = is_array($values) ? $values : [$values];
            $clean = [];
            foreach ($items as $item) {
                if (is_array($item)) {
                    foreach ($item as $nested) {
                        $str = trim((string)$nested);
                        if ($str !== '') {
                            $clean[] = $str;
                        }
                    }
                    continue;
                }
                $str = trim((string)$item);
                if ($str !== '') {
                    $clean[] = $str;
                }
            }
            if ($clean !== []) {
                $result[$code] = array_values(array_unique($clean));
            }
        }

        return $result;
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
            WHERE IBLOCK_ID = " . self::iblockId() . "
              AND CODE = '{$safeCode}'
            LIMIT 1
        ";
        $row = $db->query($sql)->fetch();
        $cache[$code] = (int)($row['ID'] ?? 0);

        return $cache[$code];
    }

    private static function getTotalCountCached(
        \Bitrix\Main\DB\Connection $db,
        string $fromSql,
        string $where,
        bool $needsDistinct,
        string $countCacheKey,
        bool $skipCache,
        int $sectionId,
        ?array &$perf = null
    ): array {
        $countCache = \Bitrix\Main\Data\Cache::createInstance();
        if (!$skipCache && $countCache->initCache(Assets::catalogCacheTtl(), $countCacheKey, self::CACHE_DIR_GOODS_COUNT)) {
            $cached = $countCache->getVars();
            if ($perf !== null) {
                self::perfAddQuery($perf, 'goods_count_cache_hit', 0.0);
            }
            return [
                'total' => (int)($cached['total'] ?? 0),
                'cache_hit' => true,
            ];
        }

        // Подзапрос + COUNT(*) часто дешевле COUNT(DISTINCT) по широкому JOIN.
        if ($needsDistinct) {
            $countSql = "
                SELECT COUNT(*) as CNT
                FROM (
                    SELECT e.ID
                    {$fromSql}
                    WHERE {$where}
                    GROUP BY e.ID
                ) counted
            ";
        } else {
            $countSql = "
                SELECT COUNT(1) as CNT
                {$fromSql}
                WHERE {$where}
            ";
        }
        $row = self::queryWithPerf($db, $countSql, $perf, 'goods_count')->fetch();
        $total = (int)($row['CNT'] ?? 0);

        if (!$skipCache) {
            $countCacheStarted = $countCache->startDataCache();
            self::commitTaggedDataCache(
                $countCache,
                self::CACHE_DIR_GOODS_COUNT,
                self::cacheTagsForSectionScope($sectionId),
                ['total' => $total],
                $countCacheStarted
            );
        }

        return [
            'total' => $total,
            'cache_hit' => false,
        ];
    }

    private static function createPerfBag(): array
    {
        return [
            'cache_hit' => false,
            'elapsed_ms' => null,
            'sql_count' => 0,
            'sql_time_ms' => 0.0,
            'php_overhead_ms' => null,
            'stages_ms' => [],
            'count_cache_hit' => false,
            'queries' => [],
            'slowest' => null,
            'meta' => [],
        ];
    }

    private static function queryWithPerf(
        \Bitrix\Main\DB\Connection $db,
        string $sql,
        ?array &$perf,
        string $label
    ) {
        if ($perf === null) {
            return $db->query($sql);
        }

        $startedAt = microtime(true);
        $res = $db->query($sql);
        $ms = (microtime(true) - $startedAt) * 1000;
        self::perfAddQuery($perf, $label, $ms, $sql);

        return $res;
    }

    private static function perfAddQuery(
        array &$perf,
        string $label,
        float $ms,
        ?string $sql = null,
        ?int $rows = null
    ): void {
        $entry = [
            'label' => $label,
            'ms' => round($ms, 2),
        ];
        if ($rows !== null) {
            $entry['rows'] = $rows;
        }
        if ($sql !== null && $sql !== '') {
            $entry['sql'] = self::normalizeSqlForPerf($sql);
        }
        $perf['queries'][] = $entry;
    }

    private static function normalizeSqlForPerf(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
        if (strlen($normalized) > 4000) {
            return substr($normalized, 0, 4000) . '…';
        }

        return $normalized;
    }

    private static function finalizePerf(array &$perf, float $startedAt): void
    {
        $perf['elapsed_ms'] = round((microtime(true) - $startedAt) * 1000, 2);
        $perf['sql_count'] = count($perf['queries']);

        $sqlTime = 0.0;
        $slowest = null;
        foreach ($perf['queries'] as $query) {
            $ms = (float)($query['ms'] ?? 0);
            $sqlTime += $ms;
            if ($slowest === null || $ms > (float)$slowest['ms']) {
                $slowest = [
                    'label' => $query['label'] ?? '',
                    'ms' => $ms,
                ];
            }
        }

        $perf['sql_time_ms'] = round($sqlTime, 2);
        $perf['php_overhead_ms'] = round(max(0, (float)$perf['elapsed_ms'] - $sqlTime), 2);
        $perf['slowest'] = $slowest;

        usort($perf['queries'], static function (array $a, array $b): int {
            return ((float)($b['ms'] ?? 0) <=> (float)($a['ms'] ?? 0));
        });
    }

    private static function getSectionIdsByBounds(int $leftMargin, int $rightMargin): array
    {
        $db = Application::getConnection();
        $sql = "
            SELECT ID
            FROM b_iblock_section
            WHERE IBLOCK_ID = " . self::iblockId() . "
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

    public static function shouldSkipCache(): bool
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
          AND s.IBLOCK_ID = " . self::iblockId() . "
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
            WHERE IBLOCK_ID = " . self::iblockId() . "
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
            WHERE parent.IBLOCK_ID = " . self::iblockId() . "
              AND parent.ACTIVE = 'Y'
              AND current_section.IBLOCK_ID = " . self::iblockId() . "
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
