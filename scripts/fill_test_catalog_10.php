<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

/**
 * Usage:
 * php8.1 fill_test_catalog_10.php /home/a/alexxx6c/ek.proff-site.ru/public_html
 */

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
$TOTAL_ITEMS = 10;

// Локальный fallback, если удаленная картинка не скачалась
$LOCAL_FALLBACK_FILE = $_SERVER['DOCUMENT_ROOT'] . '/upload/no_photo.jpg';

$tree = array(
    'Тестовая категория 1' => array('Тестовая подкатегория 1.1', 'Тестовая подкатегория 1.2'),
    'Тестовая категория 2' => array('Тестовая подкатегория 2.1', 'Тестовая подкатегория 2.2'),
);

$manufacturers = array('IKEA', 'Hoff', 'Leroy Merlin', 'Mebelion', 'Askona');
$sizes = array('60x40x200', '80x45x210', '90x50x220', '120x60x230');
$materials = array('ЛДСП', 'МДФ', 'Массив', 'Металл');
$images = array(
    'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1200&q=80.jpg',
    'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80.jpg',
    'https://images.unsplash.com/photo-1484101403633-562f891dc89a?auto=format&fit=crop&w=1200&q=80.jpg',
    'https://images.unsplash.com/photo-1493666438817-866a91353ca9?auto=format&fit=crop&w=1200&q=80.jpg'
);

function makeCode($name, $suffix)
{
    $code = CUtil::translit($name, 'ru', array(
        'replace_space' => '-',
        'replace_other' => '-',
        'change_case' => 'L',
    ));
    $code = trim($code, '-');
    if ($code === '') {
        $code = 'item';
    }
    return $code . '-' . $suffix;
}

function makeImageFileArray($url, $namePrefix, $fallbackLocalPath)
{
    // 1) Пытаемся скачать удаленный файл вручную
    $tmpFile = tempnam(sys_get_temp_dir(), 'bx_img_');
    if ($tmpFile) {
        $ctx = stream_context_create(array(
            'http' => array(
                'timeout' => 20,
                'follow_location' => 1,
                'user_agent' => 'BitrixTestSeeder/1.0',
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        ));

        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false && strlen($data) > 512) {
            file_put_contents($tmpFile, $data);

            $file = CFile::MakeFileArray($tmpFile);
            if (is_array($file) && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
                $file['name'] = $namePrefix . '-' . mt_rand(1000, 9999) . '.jpg';
                $file['type'] = 'image/jpeg';
                $file['MODULE_ID'] = 'iblock';
                return $file;
            }
        }

        @unlink($tmpFile);
    }

    // 2) Fallback на локальную картинку
    if (is_file($fallbackLocalPath)) {
        $file = CFile::MakeFileArray($fallbackLocalPath);
        if (is_array($file) && !empty($file['tmp_name'])) {
            $ext = pathinfo($fallbackLocalPath, PATHINFO_EXTENSION);
            if ($ext === '') {
                $ext = 'jpg';
            }
            $file['name'] = $namePrefix . '-fallback-' . mt_rand(1000, 9999) . '.' . $ext;
            $file['MODULE_ID'] = 'iblock';
            return $file;
        }
    }

    return false;
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
    while ($row = $res->Fetch()) {
        $existing[$row['VALUE']] = (int)$row['ID'];
    }

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

        if (!$enumId) {
            throw new Exception("Cannot create enum {$value}");
        }

        $map[$value] = (int)$enumId;
    }

    return $map;
}

function ensureSection($iblockId, $name, $parentId, $imageUrl, $fallbackLocalPath)
{
    $res = CIBlockSection::GetList(array(), array(
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
        'SECTION_ID' => $parentId > 0 ? $parentId : false,
        'INCLUDE_SUBSECTIONS' => 'N',
    ), false, array('ID', 'PICTURE', 'DETAIL_PICTURE'));

    if ($row = $res->Fetch()) {
        $update = array();

        if (empty($row['PICTURE'])) {
            $picture = makeImageFileArray($imageUrl, 'section-picture', $fallbackLocalPath);
            if ($picture) {
                $update['PICTURE'] = $picture;
            }
        }

        if (empty($row['DETAIL_PICTURE'])) {
            $detailPicture = makeImageFileArray($imageUrl, 'section-detail', $fallbackLocalPath);
            if ($detailPicture) {
                $update['DETAIL_PICTURE'] = $detailPicture;
            }
        }

        if (!empty($update)) {
            $section = new CIBlockSection();
            if (!$section->Update((int)$row['ID'], $update)) {
                fwrite(STDERR, "Section update warning ({$name}): {$section->LAST_ERROR}\n");
            }
        }

        return (int)$row['ID'];
    }

    $sectionPicture = makeImageFileArray($imageUrl, 'section-picture', $fallbackLocalPath);
    $sectionDetailPicture = makeImageFileArray($imageUrl, 'section-detail', $fallbackLocalPath);

    $section = new CIBlockSection();
    $id = $section->Add(array(
        'IBLOCK_ID' => $iblockId,
        'IBLOCK_SECTION_ID' => $parentId > 0 ? $parentId : false,
        'NAME' => $name,
        'CODE' => makeCode($name, time() . '-' . mt_rand(100, 999)),
        'ACTIVE' => 'Y',
        'SORT' => 500,
        'PICTURE' => $sectionPicture ? $sectionPicture : false,
        'DETAIL_PICTURE' => $sectionDetailPicture ? $sectionDetailPicture : false,
    ));

    if (!$id) {
        throw new Exception("Cannot create section {$name}: " . $section->LAST_ERROR);
    }

    return (int)$id;
}

function ensureCatalogProduct($productId)
{
    if (CCatalogProduct::GetByID($productId)) {
        return;
    }

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

// ---- Bootstrap свойств
try {
    $manufacturerPropId = ensureProperty($IBLOCK_ID, 'CML2_MANUFACTURER', 'Производитель', 'L');
    $sizesPropId = ensureProperty($IBLOCK_ID, 'SIZES', 'Размеры', 'L');
    ensureProperty($IBLOCK_ID, 'CML2_ARTICLE', 'Артикул', 'S');
    ensureProperty($IBLOCK_ID, 'MATERIAL', 'Материал', 'S');

    $manufacturerEnumMap = ensureEnumValues($manufacturerPropId, $manufacturers);
    $sizesEnumMap = ensureEnumValues($sizesPropId, $sizes);
} catch (Exception $e) {
    fwrite(STDERR, "Bootstrap error: " . $e->getMessage() . "\n");
    exit(1);
}

// ---- Разделы / подразделы
$subSectionIds = array();
foreach ($tree as $parent => $children) {
    $parentImage = $images[array_rand($images)];
    $parentId = ensureSection($IBLOCK_ID, $parent, 0, $parentImage, $LOCAL_FALLBACK_FILE);

    foreach ($children as $child) {
        $childImage = $images[array_rand($images)];
        $subSectionIds[] = ensureSection($IBLOCK_ID, $child, $parentId, $childImage, $LOCAL_FALLBACK_FILE);
    }
}

$element = new CIBlockElement();
$created = 0;

// ---- Товары
for ($i = 1; $i <= $TOTAL_ITEMS; $i++) {
    $subId = $subSectionIds[array_rand($subSectionIds)];
    $manufacturer = $manufacturers[array_rand($manufacturers)];
    $size = $sizes[array_rand($sizes)];
    $material = $materials[array_rand($materials)];
    $image = $images[array_rand($images)];
    $article = 'ART-' . date('ymd') . '-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);

    $name = "Тестовый товар {$i}";
    $preview = "Краткое описание товара {$i}";
    $detail = '<p><strong>Описание:</strong> Практичный тестовый товар для демонстрации каталога: аккуратная геометрия, универсальный внешний вид и нейтральная отделка.</p>'
        . '<p>Подходит для квартиры и офиса, легко комбинируется с другой мебелью, не требует сложного ухода и сохраняет аккуратный вид при ежедневном использовании.</p>';

    $detailPicture = makeImageFileArray($image, 'product-detail', $LOCAL_FALLBACK_FILE);
    $previewPicture = makeImageFileArray($image, 'product-preview', $LOCAL_FALLBACK_FILE);

    if (!$detailPicture) {
        fwrite(STDERR, "WARN: DETAIL_PICTURE not prepared for {$name}\n");
    }

    $itemId = $element->Add(array(
        'IBLOCK_ID' => $IBLOCK_ID,
        'IBLOCK_SECTION_ID' => $subId,
        'ACTIVE' => 'Y',
        'NAME' => $name,
        'CODE' => makeCode($name, date('His') . '-' . mt_rand(100, 999)),
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

    $price = mt_rand(10000, 15000000) / 100; // 100..150000
    setPrice((int)$itemId, $PRICE_TYPE_ID, $price, $CURRENCY);

    $created++;
    echo "OK {$itemId}: {$name}, {$price} RUB\n";
}

echo "Done. Created: {$created}\n";