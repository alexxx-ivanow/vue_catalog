<?php
/**
 * Safe Bitrix catalog bulk importer (IBLOCK_ID=2).
 *
 * Examples:
 * php8.1 import_100k.php /home/a/alexxx6c/ek.proff-site.ru/public_html --limit=1000
 * php8.1 import_100k.php /home/a/alexxx6c/ek.proff-site.ru/public_html --continue --limit=1000
 * php8.1 import_100k.php /home/a/alexxx6c/ek.proff-site.ru/public_html --auto --total=100000 --limit=1000 --pause=2
 */

define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);
define('DisableEventsCheck', true);

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);
ini_set('memory_limit', '1024M');

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

$IBLOCK_ID = 2;
$PRICE_TYPE_ID = 1;
$CURRENCY = 'RUB';

// Place local images here in advance for stable import performance.
$IMAGE_PATHS = array(
    '/upload/test-catalog/1.jpg',
    '/upload/test-catalog/2.jpg',
    '/upload/test-catalog/3.jpg',
    '/upload/test-catalog/4.jpg',
    '/upload/no_photo.jpg',
);

$tree = array(
    'Тестовая категория 1' => array('Тестовая подкатегория 1.1', 'Тестовая подкатегория 1.2'),
    'Тестовая категория 2' => array('Тестовая подкатегория 2.1', 'Тестовая подкатегория 2.2'),
    'Тестовая категория 3' => array('Тестовая подкатегория 3.1', 'Тестовая подкатегория 3.2'),
);

$manufacturers = array('IKEA', 'Hoff', 'Leroy Merlin', 'Mebelion', 'Askona', 'Mr.Doors');
$sizes = array('60x40x200', '80x45x210', '90x50x220', '120x60x230', '140x60x240');
$materials = array('ЛДСП', 'МДФ', 'Массив', 'Металл', 'Шпон');

function parseOptions($argv)
{
    $opts = array(
        'limit' => 1000,
        'offset' => 0,
        'total' => 100000,
        'pause' => 2,
        'continue' => false,
        'auto' => false,
        'state' => __DIR__ . '/import_100k_state.json',
    );

    foreach ($argv as $arg) {
        if (strpos($arg, '--limit=') === 0) $opts['limit'] = max(1, (int)substr($arg, 8));
        if (strpos($arg, '--offset=') === 0) $opts['offset'] = max(0, (int)substr($arg, 9));
        if (strpos($arg, '--total=') === 0) $opts['total'] = max(1, (int)substr($arg, 8));
        if (strpos($arg, '--pause=') === 0) $opts['pause'] = max(0, (int)substr($arg, 8));
        if (strpos($arg, '--state=') === 0) $opts['state'] = substr($arg, 8);
        if ($arg === '--continue') $opts['continue'] = true;
        if ($arg === '--auto') $opts['auto'] = true;
    }

    return $opts;
}

function readState($stateFile)
{
    if (!is_file($stateFile)) {
        return array('offset' => 0, 'created_total' => 0, 'last_run' => null);
    }
    $raw = @file_get_contents($stateFile);
    if (!$raw) {
        return array('offset' => 0, 'created_total' => 0, 'last_run' => null);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return array('offset' => 0, 'created_total' => 0, 'last_run' => null);
    }
    return $data;
}

function writeState($stateFile, $offset, $createdTotal)
{
    $payload = array(
        'offset' => (int)$offset,
        'created_total' => (int)$createdTotal,
        'last_run' => date('c'),
    );
    @file_put_contents($stateFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function makeCode($name, $suffix)
{
    $code = CUtil::translit($name, 'ru', array(
        'replace_space' => '-',
        'replace_other' => '-',
        'change_case' => 'L',
    ));
    $code = trim($code, '-');
    if ($code === '') $code = 'item';
    return $code . '-' . $suffix;
}

function localImageFileArray($relativePath, $namePrefix)
{
    $absolute = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $relativePath;
    if (!is_file($absolute)) {
        return false;
    }

    $file = CFile::MakeFileArray($absolute);
    if (!is_array($file) || empty($file['tmp_name'])) {
        return false;
    }

    $ext = pathinfo($absolute, PATHINFO_EXTENSION);
    if ($ext === '') $ext = 'jpg';
    $file['name'] = $namePrefix . '-' . mt_rand(1000, 9999) . '.' . $ext;
    $file['MODULE_ID'] = 'iblock';
    return $file;
}

function ensureProperty($iblockId, $code, $name, $type)
{
    $res = CIBlockProperty::GetList(array(), array('IBLOCK_ID' => $iblockId, 'CODE' => $code));
    if ($row = $res->Fetch()) {
        return (int)$row['ID'];
    }

    $property = new CIBlockProperty();
    $id = $property->Add(array(
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'SORT' => 500,
        'CODE' => $code,
        'PROPERTY_TYPE' => $type,
        'MULTIPLE' => 'N',
        'IS_REQUIRED' => 'N',
        'FILTRABLE' => 'Y',
    ));
    if (!$id) {
        throw new Exception("Cannot create property {$code}: " . $property->LAST_ERROR);
    }
    return (int)$id;
}

function ensureEnumValues($propertyId, $values)
{
    $map = array();
    $existing = array();

    $res = CIBlockPropertyEnum::GetList(array('SORT' => 'ASC'), array('PROPERTY_ID' => $propertyId));
    while ($row = $res->Fetch()) $existing[$row['VALUE']] = (int)$row['ID'];

    $enum = new CIBlockPropertyEnum();
    foreach ($values as $index => $value) {
        if (isset($existing[$value])) {
            $map[$value] = $existing[$value];
            continue;
        }
        $enumId = $enum->Add(array(
            'PROPERTY_ID' => $propertyId,
            'VALUE' => $value,
            'DEF' => 'N',
            'SORT' => 100 + ($index * 10),
        ));
        if (!$enumId) throw new Exception("Cannot create enum {$value}");
        $map[$value] = (int)$enumId;
    }
    return $map;
}

function ensureSection($iblockId, $name, $parentId, $imagePath)
{
    $res = CIBlockSection::GetList(array(), array(
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
        'SECTION_ID' => $parentId > 0 ? $parentId : false,
        'INCLUDE_SUBSECTIONS' => 'N',
    ), false, array('ID'));

    if ($row = $res->Fetch()) return (int)$row['ID'];

    $picture = localImageFileArray($imagePath, 'section-picture');
    $detailPicture = localImageFileArray($imagePath, 'section-detail');

    $section = new CIBlockSection();
    $id = $section->Add(array(
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => $parentId > 0 ? $parentId : false,
        'NAME' => $name,
        'CODE' => makeCode($name, time() . '-' . mt_rand(100, 999)),
        'ACTIVE' => 'Y',
        'SORT' => 500,
        'PICTURE' => $picture ? $picture : false,
        'DETAIL_PICTURE' => $detailPicture ? $detailPicture : false,
    ));

    if (!$id) throw new Exception("Cannot create section {$name}: " . $section->LAST_ERROR);
    return (int)$id;
}

function ensureCatalogProduct($productId)
{
    if (CCatalogProduct::GetByID($productId)) return;
    CCatalogProduct::Add(array(
        'ID' => $productId,
        'QUANTITY' => 100,
        'CAN_BUY_ZERO' => 'Y',
        'NEGATIVE_AMOUNT_TRACE' => 'N',
    ));
}

function setPrice($productId, $priceTypeId, $price, $currency)
{
    $price = round($price, 2);
    $res = CPrice::GetList(array(), array(
        'PRODUCT_ID' => $productId,
        'CATALOG_GROUP_ID' => $priceTypeId,
    ));

    if ($row = $res->Fetch()) {
        CPrice::Update((int)$row['ID'], array(
            'PRODUCT_ID' => $productId,
            'CATALOG_GROUP_ID' => $priceTypeId,
            'PRICE' => $price,
            'CURRENCY' => $currency,
        ));
        return;
    }

    CPrice::Add(array(
        'PRODUCT_ID' => $productId,
        'CATALOG_GROUP_ID' => $priceTypeId,
        'PRICE' => $price,
        'CURRENCY' => $currency,
    ));
}

function bootstrapCatalogMeta($IBLOCK_ID, $tree, $IMAGE_PATHS, $manufacturers, $sizes)
{
    $manufacturerPropId = ensureProperty($IBLOCK_ID, 'CML2_MANUFACTURER', 'Производитель', 'L');
    $sizesPropId = ensureProperty($IBLOCK_ID, 'SIZES', 'Размеры', 'L');
    ensureProperty($IBLOCK_ID, 'CML2_ARTICLE', 'Артикул', 'S');
    ensureProperty($IBLOCK_ID, 'MATERIAL', 'Материал', 'S');

    $manufacturerEnumMap = ensureEnumValues($manufacturerPropId, $manufacturers);
    $sizesEnumMap = ensureEnumValues($sizesPropId, $sizes);

    $subSectionIds = array();
    foreach ($tree as $parent => $children) {
        $parentImage = $IMAGE_PATHS[array_rand($IMAGE_PATHS)];
        $parentId = ensureSection($IBLOCK_ID, $parent, 0, $parentImage);
        foreach ($children as $child) {
            $childImage = $IMAGE_PATHS[array_rand($IMAGE_PATHS)];
            $subSectionIds[] = ensureSection($IBLOCK_ID, $child, $parentId, $childImage);
        }
    }

    return array($manufacturerEnumMap, $sizesEnumMap, $subSectionIds);
}

function importBatch($offset, $limit, $IBLOCK_ID, $PRICE_TYPE_ID, $CURRENCY, $subSectionIds, $manufacturers, $sizes, $materials, $manufacturerEnumMap, $sizesEnumMap, $IMAGE_PATHS)
{
    $element = new CIBlockElement();
    $created = 0;

    for ($i = 0; $i < $limit; $i++) {
        $globalIndex = $offset + $i + 1;
        $subId = $subSectionIds[array_rand($subSectionIds)];
        $manufacturer = $manufacturers[array_rand($manufacturers)];
        $size = $sizes[array_rand($sizes)];
        $material = $materials[array_rand($materials)];
        $imagePath = $IMAGE_PATHS[array_rand($IMAGE_PATHS)];
        $article = 'ART-' . date('ymd') . '-' . str_pad((string)$globalIndex, 6, '0', STR_PAD_LEFT);

        $name = "Тестовый товар {$globalIndex}";
        $preview = "Краткое описание товара {$globalIndex}";
        $detail = '<p><strong>Описание:</strong> Практичный тестовый товар для демонстрации каталога: нейтральный дизайн, аккуратная отделка и универсальный формат использования.</p>'
            . '<p>Подходит для дома и офиса, легко сочетается с другими элементами интерьера, не требует сложного ухода и сохраняет презентабельный вид при ежедневной эксплуатации.</p>';

        $detailPicture = localImageFileArray($imagePath, 'product-detail');
        $previewPicture = localImageFileArray($imagePath, 'product-preview');

        $itemId = $element->Add(array(
            'IBLOCK_ID' => $IBLOCK_ID,
            'IBLOCK_SECTION_ID' => $subId,
            'ACTIVE' => 'Y',
            'NAME' => $name,
            'CODE' => makeCode($name, date('His') . '-' . $globalIndex),
            'PREVIEW_TEXT' => $preview,
            'DETAIL_TEXT' => $detail,
            'DETAIL_TEXT_TYPE' => 'html',
            'DETAIL_PICTURE' => $detailPicture ? $detailPicture : false,
            'PREVIEW_PICTURE' => $previewPicture ? $previewPicture : false,
        ));

        if (!$itemId) {
            fwrite(STDERR, "Create product failed '{$name}': {$element->LAST_ERROR}\n");
            continue;
        }

        CIBlockElement::SetPropertyValuesEx((int)$itemId, $IBLOCK_ID, array(
            'CML2_MANUFACTURER' => isset($manufacturerEnumMap[$manufacturer]) ? $manufacturerEnumMap[$manufacturer] : null,
            'SIZES' => isset($sizesEnumMap[$size]) ? $sizesEnumMap[$size] : null,
            'CML2_ARTICLE' => $article,
            'MATERIAL' => $material,
        ));

        ensureCatalogProduct((int)$itemId);
        $price = mt_rand(10000, 15000000) / 100;
        setPrice((int)$itemId, $PRICE_TYPE_ID, $price, $CURRENCY);

        $created++;
        if ($created % 100 === 0) {
            echo "Created in this batch: {$created}\n";
        }
    }

    return $created;
}

$opts = parseOptions($argv);
$state = readState($opts['state']);

if ($opts['continue']) {
    $opts['offset'] = (int)($state['offset'] ?? 0);
}

try {
    list($manufacturerEnumMap, $sizesEnumMap, $subSectionIds) = bootstrapCatalogMeta(
        $IBLOCK_ID,
        $tree,
        $IMAGE_PATHS,
        $manufacturers,
        $sizes
    );
} catch (Exception $e) {
    fwrite(STDERR, "Bootstrap error: " . $e->getMessage() . "\n");
    exit(1);
}

$createdTotal = (int)($state['created_total'] ?? 0);
$offset = (int)$opts['offset'];
$limit = (int)$opts['limit'];
$total = (int)$opts['total'];
$pause = (int)$opts['pause'];

do {
    if ($offset >= $total) {
        echo "Target reached: offset {$offset} >= total {$total}\n";
        break;
    }

    $batchLimit = min($limit, $total - $offset);
    $batchStart = microtime(true);
    echo "Start batch: offset={$offset}, limit={$batchLimit}\n";

    $created = importBatch(
        $offset,
        $batchLimit,
        $IBLOCK_ID,
        $PRICE_TYPE_ID,
        $CURRENCY,
        $subSectionIds,
        $manufacturers,
        $sizes,
        $materials,
        $manufacturerEnumMap,
        $sizesEnumMap,
        $IMAGE_PATHS
    );

    $offset += $batchLimit;
    $createdTotal += $created;
    writeState($opts['state'], $offset, $createdTotal);

    $elapsed = round(microtime(true) - $batchStart, 2);
    echo "Batch done: created={$created}, new_offset={$offset}, elapsed={$elapsed}s\n";
    echo "State saved: {$opts['state']}\n";

    if ($opts['auto'] && $offset < $total && $pause > 0) {
        echo "Sleep {$pause}s before next batch...\n";
        sleep($pause);
    }
} while ($opts['auto']);

echo "Import finished. Created total in state: {$createdTotal}\n";
