<?php
/**
 * Explain and measure heavy goods queries from CatalogService.
 *
 * Usage:
 * php8.1 explain_goods_queries.php /home/a/user/site/public_html --section=58 --page=9 --limit=12
 * php8.1 explain_goods_queries.php /home/a/user/site/public_html --section=58 --page=9 --limit=12 --manufacturer=123 --size=456
 * php8.1 explain_goods_queries.php /home/a/user/site/public_html --section=58 --article=ART-01 --price_from=1000 --price_to=5000
 */

define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);

$docRoot = isset($argv[1]) ? $argv[1] : '';
if ($docRoot === '' || !is_dir($docRoot . '/bitrix')) {
    fwrite(STDERR, "Pass valid Bitrix DOCUMENT_ROOT as first arg\n");
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = rtrim($docRoot, '/');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('iblock') || !\Bitrix\Main\Loader::includeModule('catalog')) {
    fwrite(STDERR, "Cannot load iblock/catalog modules\n");
    exit(1);
}

const IBLOCK_ID = 2;
const PRICE_GROUP_ID = 1;
const PROP_MANUFACTURER_ID = 25;
const PROP_ARTICLE_ID = 27;
const PROP_MATERIAL_ID = 26;

function parseOptions($argv)
{
    $opts = array(
        'section' => 0,
        'page' => 1,
        'limit' => 12,
        'manufacturer' => 0,
        'size' => 0,
        'article' => '',
        'price_from' => null,
        'price_to' => null,
    );

    foreach ($argv as $arg) {
        if (strpos($arg, '--section=') === 0) $opts['section'] = max(0, (int)substr($arg, 10));
        if (strpos($arg, '--page=') === 0) $opts['page'] = max(1, (int)substr($arg, 7));
        if (strpos($arg, '--limit=') === 0) $opts['limit'] = max(1, min(200, (int)substr($arg, 8)));
        if (strpos($arg, '--manufacturer=') === 0) $opts['manufacturer'] = max(0, (int)substr($arg, 15));
        if (strpos($arg, '--size=') === 0) $opts['size'] = max(0, (int)substr($arg, 7));
        if (strpos($arg, '--article=') === 0) $opts['article'] = trim((string)substr($arg, 10));
        if (strpos($arg, '--price_from=') === 0) $opts['price_from'] = (float)substr($arg, 13);
        if (strpos($arg, '--price_to=') === 0) $opts['price_to'] = (float)substr($arg, 11);
    }

    return $opts;
}

function getSectionBounds(\Bitrix\Main\DB\Connection $db, $sectionId)
{
    $sql = "
        SELECT LEFT_MARGIN, RIGHT_MARGIN, NAME
        FROM b_iblock_section
        WHERE ID = " . (int)$sectionId . "
          AND IBLOCK_ID = " . IBLOCK_ID . "
          AND ACTIVE = 'Y'
        LIMIT 1
    ";

    $row = $db->query($sql)->fetch();
    return $row ?: null;
}

function getSectionIdsByBounds(\Bitrix\Main\DB\Connection $db, $leftMargin, $rightMargin)
{
    $sql = "
        SELECT ID
        FROM b_iblock_section
        WHERE IBLOCK_ID = " . IBLOCK_ID . "
          AND ACTIVE = 'Y'
          AND LEFT_MARGIN >= " . (int)$leftMargin . "
          AND RIGHT_MARGIN <= " . (int)$rightMargin . "
    ";

    $res = $db->query($sql);
    $ids = array();
    while ($row = $res->fetch()) {
        $ids[] = (int)$row['ID'];
    }

    return $ids;
}

function buildWhere(\Bitrix\Main\DB\Connection $db, $opts)
{
    $sqlHelper = $db->getSqlHelper();
    $whereParts = array(
        "e.IBLOCK_ID = " . IBLOCK_ID,
        "e.ACTIVE = 'Y'",
    );

    if ((int)$opts['section'] > 0) {
        $bounds = getSectionBounds($db, (int)$opts['section']);
        if (!$bounds) {
            throw new RuntimeException('Section not found or inactive');
        }

        $sectionIds = getSectionIdsByBounds($db, (int)$bounds['LEFT_MARGIN'], (int)$bounds['RIGHT_MARGIN']);
        if (empty($sectionIds)) {
            throw new RuntimeException('No nested sections for selected section');
        }

        $sectionIdsSql = implode(',', array_map('intval', $sectionIds));
        $whereParts[] = "EXISTS (
            SELECT 1
            FROM b_iblock_section_element se
            WHERE se.IBLOCK_ELEMENT_ID = e.ID
              AND se.IBLOCK_SECTION_ID IN ({$sectionIdsSql})
        )";
    }

    if ($opts['article'] !== '') {
        $articleEscaped = $sqlHelper->forSql($opts['article']);
        $whereParts[] = "EXISTS (
            SELECT 1
            FROM b_iblock_element_property p_article
            WHERE p_article.IBLOCK_ELEMENT_ID = e.ID
              AND p_article.IBLOCK_PROPERTY_ID = " . PROP_ARTICLE_ID . "
              AND p_article.VALUE LIKE '%{$articleEscaped}%'
        )";
    }

    if ((int)$opts['manufacturer'] > 0) {
        $whereParts[] = "EXISTS (
            SELECT 1
            FROM b_iblock_element_property p_manufacturer
            WHERE p_manufacturer.IBLOCK_ELEMENT_ID = e.ID
              AND p_manufacturer.IBLOCK_PROPERTY_ID = " . PROP_MANUFACTURER_ID . "
              AND p_manufacturer.VALUE_ENUM = " . (int)$opts['manufacturer'] . "
        )";
    }

    if ((int)$opts['size'] > 0) {
        $whereParts[] = "EXISTS (
            SELECT 1
            FROM b_iblock_element_property p_size
            WHERE p_size.IBLOCK_ELEMENT_ID = e.ID
              AND p_size.IBLOCK_PROPERTY_ID = " . PROP_MATERIAL_ID . "
              AND p_size.VALUE_ENUM = " . (int)$opts['size'] . "
        )";
    }

    if ($opts['price_from'] !== null || $opts['price_to'] !== null) {
        $priceWhere = "cp.PRODUCT_ID = e.ID AND cp.CATALOG_GROUP_ID = " . PRICE_GROUP_ID;
        if ($opts['price_from'] !== null) {
            $priceWhere .= " AND cp.PRICE >= " . (float)$opts['price_from'];
        }
        if ($opts['price_to'] !== null) {
            $priceWhere .= " AND cp.PRICE <= " . (float)$opts['price_to'];
        }

        $whereParts[] = "EXISTS (
            SELECT 1
            FROM b_catalog_price cp
            WHERE {$priceWhere}
        )";
    }

    return implode(' AND ', $whereParts);
}

function runTimed(\Bitrix\Main\DB\Connection $db, $sql)
{
    $startedAt = microtime(true);
    $res = $db->query($sql);
    $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);
    return array($res, $elapsedMs);
}

function printExplain(\Bitrix\Main\DB\Connection $db, $queryTitle, $sql)
{
    echo "\n==== {$queryTitle} ====\n";
    echo $sql . "\n";

    try {
        $explainSql = "EXPLAIN FORMAT=JSON " . $sql;
        list($res, $ms) = runTimed($db, $explainSql);
        $row = $res->fetch();
        echo "EXPLAIN FORMAT=JSON time: {$ms} ms\n";
        if ($row && isset($row['EXPLAIN'])) {
            echo $row['EXPLAIN'] . "\n";
        } else {
            echo "No JSON explain payload\n";
        }
    } catch (\Throwable $e) {
        echo "EXPLAIN FORMAT=JSON failed: " . $e->getMessage() . "\n";
        echo "Fallback to plain EXPLAIN:\n";
        list($res, $ms) = runTimed($db, "EXPLAIN " . $sql);
        echo "EXPLAIN time: {$ms} ms\n";
        while ($row = $res->fetch()) {
            echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
    }
}

function runAndPrintStats(\Bitrix\Main\DB\Connection $db, $queryTitle, $sql)
{
    list($res, $ms) = runTimed($db, $sql);
    $rows = 0;
    while ($res->fetch()) {
        $rows++;
    }
    echo "{$queryTitle} execution: {$ms} ms, rows: {$rows}\n";
}

$opts = parseOptions($argv);
$db = \Bitrix\Main\Application::getConnection();

try {
    $where = buildWhere($db, $opts);
} catch (\Throwable $e) {
    fwrite(STDERR, "Cannot build WHERE: " . $e->getMessage() . "\n");
    exit(1);
}

$page = (int)$opts['page'];
$limit = (int)$opts['limit'];
$offset = ($page - 1) * $limit;

$countSql = "
    SELECT COUNT(1) AS CNT
    FROM b_iblock_element e
    WHERE {$where}
";

$listSql = "
    SELECT e.ID, e.NAME, e.CODE, e.PREVIEW_TEXT, e.DETAIL_PICTURE
    FROM b_iblock_element e
    WHERE {$where}
    ORDER BY e.SORT ASC, e.ID DESC
    LIMIT {$limit} OFFSET {$offset}
";

echo "Options: " . json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
echo "WHERE hash: " . md5($where) . "\n";

printExplain($db, 'COUNT query', $countSql);
printExplain($db, 'LIST query', $listSql);

echo "\n---- Timing (real execution) ----\n";
runAndPrintStats($db, 'COUNT', $countSql);
runAndPrintStats($db, 'LIST', $listSql);
