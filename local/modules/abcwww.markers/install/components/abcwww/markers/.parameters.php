<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Abcwww\Markers\Options;
use Abcwww\Markers\Repository;

if (!Loader::includeModule('abcwww.markers')) {
    return;
}

$defaultIblock = (int)Option::get(Options::MODULE_ID, Options::IBLOCK_ID, Options::defaults()[Options::IBLOCK_ID]);
$iblockId = isset($arCurrentValues['IBLOCK_ID']) ? (int)$arCurrentValues['IBLOCK_ID'] : $defaultIblock;

$leafSections = [];
if ($iblockId > 0) {
    $leafSections = Repository::getLeafSections($iblockId);
}

$arComponentParameters = [
    'PARAMETERS' => [
        'IBLOCK_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'IBLOCK_ID',
            'TYPE' => 'STRING',
            'DEFAULT' => $defaultIblock,
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
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'BUTTON_TEXT_PROPERTY_CODE' => [
            'PARENT' => 'DATA_SOURCE',
            'NAME' => 'Код свойства с текстом кнопки',
            'TYPE' => 'STRING',
            'DEFAULT' => 'ABCWWW_BUTTON_TEXT',
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
        'CACHE_TYPE' => ['PARENT' => 'CACHE_SETTINGS', 'NAME' => 'Тип кеширования', 'TYPE' => 'LIST', 'VALUES' => ['A' => 'Авто', 'Y' => 'Кешировать', 'N' => 'Не кешировать'], 'DEFAULT' => 'A'],
        'CACHE_TIME' => ['PARENT' => 'CACHE_SETTINGS', 'NAME' => 'Время кеша', 'TYPE' => 'STRING', 'DEFAULT' => '3600000'],
    ],
];
