<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Abcwww\Markers\Options;
use Abcwww\Markers\Repository;

if (!Loader::includeModule('abcwww.markers') || !Loader::includeModule('iblock')) {
    return;
}

$defaultIblock = (int)Option::get(Options::MODULE_ID, Options::IBLOCK_ID, Options::defaults()[Options::IBLOCK_ID]);
$iblockTypes = Repository::getIblockTypes();

$iblockTypeId = trim((string)($arCurrentValues['IBLOCK_TYPE_ID'] ?? ''));
$iblockId = isset($arCurrentValues['IBLOCK_ID']) ? (int)$arCurrentValues['IBLOCK_ID'] : 0;

if ($iblockId <= 0 && $defaultIblock > 0) {
    $iblockId = $defaultIblock;
}

if ($iblockTypeId === '' && $iblockId > 0) {
    $iblockTypeId = (string)\CIBlock::GetArrayByID($iblockId, 'IBLOCK_TYPE_ID');
}

$iblocks = $iblockTypeId !== '' ? Repository::getIblocksByType($iblockTypeId) : [];
if ($iblockId > 0 && !isset($iblocks[$iblockId])) {
    // выбранный инфоблок не из текущего типа — сбрасываем
    $iblockId = 0;
}

$leafSections = $iblockId > 0 ? Repository::getLeafSections($iblockId) : [];
$propertyCodes = $iblockId > 0 ? Repository::getElementPropertyCodes($iblockId) : [];

$arComponentParameters = [
    'GROUPS' => [
        'DATA_SOURCE' => [
            'NAME' => 'Источник данных',
        ],
        'VISUAL' => [
            'NAME' => 'Внешний вид',
        ],
    ],
    'PARAMETERS' => [
        'IBLOCK_TYPE_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'Тип инфоблока',
            'TYPE' => 'LIST',
            'VALUES' => $iblockTypes,
            'DEFAULT' => $iblockTypeId,
            'REFRESH' => 'Y',
        ],
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'Инфоблок',
            'TYPE' => 'LIST',
            'VALUES' => $iblocks,
            'DEFAULT' => $iblockId > 0 ? $iblockId : '',
            'REFRESH' => 'Y',
        ],
        'SECTION_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'Доступные интерактивные карты',
            'TYPE' => 'LIST',
            'VALUES' => $leafSections,
            'DEFAULT' => '',
            'REFRESH' => 'Y',
        ],
        'SORT_BY1' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Первое поле сортировки',
            'TYPE' => 'LIST',
            'VALUES' => ['NAME' => 'NAME', 'ID' => 'ID', 'SORT' => 'SORT'],
            'DEFAULT' => 'NAME',
        ],
        'SORT_ORDER1' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Первое направление сортировки',
            'TYPE' => 'LIST',
            'VALUES' => ['ASC' => 'ASC', 'DESC' => 'DESC'],
            'DEFAULT' => 'ASC',
        ],
        'SORT_BY2' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Второе поле сортировки',
            'TYPE' => 'LIST',
            'VALUES' => ['ID' => 'ID', 'NAME' => 'NAME', 'SORT' => 'SORT'],
            'DEFAULT' => 'ID',
        ],
        'SORT_ORDER2' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Второе направление сортировки',
            'TYPE' => 'LIST',
            'VALUES' => ['ASC' => 'ASC', 'DESC' => 'DESC'],
            'DEFAULT' => 'DESC',
        ],
        'MORE_BUTTON_PROPERTY_CODE' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Код свойства со ссылкой для кнопки "Подробнее"',
            'TYPE' => 'LIST',
            'VALUES' => array_merge(['' => '(не выбрано)'], $propertyCodes),
            'DEFAULT' => '',
            'ADDITIONAL_VALUES' => 'Y',
        ],
        'BUTTON_TEXT_PROPERTY_CODE' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Код свойства с текстом кнопки',
            'TYPE' => 'LIST',
            'VALUES' => array_merge(['' => '(не выбрано)'], $propertyCodes),
            'DEFAULT' => 'ABCWWW_BUTTON_TEXT',
            'ADDITIONAL_VALUES' => 'Y',
        ],
        'PROPERTY_CODES' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Дополнительные свойства элементов (CODE)',
            'TYPE' => 'LIST',
            'VALUES' => $propertyCodes,
            'MULTIPLE' => 'Y',
            'SIZE' => 8,
            'DEFAULT' => [],
            'ADDITIONAL_VALUES' => 'Y',
        ],
        'SHOW_TOOLTIP' => [
            'PARENT' => 'VISUAL',
            'NAME' => 'Показывать тултипы сразу открытыми',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
        ],
        'TOOLTIP_ON_HOVER' => [
            'PARENT' => 'VISUAL',
            'NAME' => 'Показывать тултип по наведению (hover)',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
        ],
        'USE_MODAL' => [
            'PARENT' => 'VISUAL',
            'NAME' => 'Открывать модальное окно вместо тултипа',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'N',
        ],
        'CACHE_TYPE' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => 'Тип кеширования',
            'TYPE' => 'LIST',
            'VALUES' => ['A' => 'Авто', 'Y' => 'Кешировать', 'N' => 'Не кешировать'],
            'DEFAULT' => 'A',
        ],
        'CACHE_TIME' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => 'Время кеша',
            'TYPE' => 'STRING',
            'DEFAULT' => '3600000',
        ],
    ],
];
