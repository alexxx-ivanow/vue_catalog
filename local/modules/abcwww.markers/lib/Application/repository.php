<?php

namespace Abcwww\Markers;

use Bitrix\Main\Loader;

class Repository
{
    public static function getLeafSections(int $iblockId): array
    {
        if ($iblockId <= 0 || !Loader::includeModule('iblock')) {
            return [];
        }

        $result = [];
        $res = \CIBlockSection::GetList(
            ['LEFT_MARGIN' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y', 'GLOBAL_ACTIVE' => 'Y'],
            false,
            ['ID', 'NAME', 'LEFT_MARGIN', 'RIGHT_MARGIN']
        );
        while ($row = $res->Fetch()) {
            $isLeaf = ((int)$row['RIGHT_MARGIN'] - (int)$row['LEFT_MARGIN']) === 1;
            if ($isLeaf) {
                $result[(int)$row['ID']] = '[' . (int)$row['ID'] . '] ' . $row['NAME'];
            }
        }

        return $result;
    }

    public static function getScheme(
        int $iblockId,
        int $sectionId,
        string $sortBy1 = 'NAME',
        string $sortOrder1 = 'ASC',
        string $sortBy2 = 'ID',
        string $sortOrder2 = 'DESC',
        string $moreButtonPropertyCode = '',
        string $buttonTextPropertyCode = 'ABCWWW_BUTTON_TEXT',
        bool $showTooltip = false
    ): ?array
    {
        if ($iblockId <= 0 || $sectionId <= 0 || !Loader::includeModule('iblock')) {
            return null;
        }

        $section = \CIBlockSection::GetList([], ['IBLOCK_ID' => $iblockId, 'ID' => $sectionId], false, [
            'ID', 'NAME', 'IBLOCK_ID', 'UF_MARKER_IMAGE', 'UF_MARKER_MAIN_COLOR', 'UF_MARKER_HOVER_COLOR',
            'UF_MARKER_ICON', 'UF_MARKER_ICON_PC', 'UF_MARKER_ICON_MB'
        ])->Fetch();

        if (!$section) {
            return null;
        }

        $sectionUfs = self::getSectionUfValues($iblockId, (int)$section['ID']);
        $mainColorRaw = $sectionUfs['UF_MARKER_MAIN_COLOR'] ?? ($section['UF_MARKER_MAIN_COLOR'] ?? '');
        $hoverColorRaw = $sectionUfs['UF_MARKER_HOVER_COLOR'] ?? ($section['UF_MARKER_HOVER_COLOR'] ?? '');
        $iconRaw = $sectionUfs['UF_MARKER_ICON'] ?? ($section['UF_MARKER_ICON'] ?? 0);
        $iconPcRaw = $sectionUfs['UF_MARKER_ICON_PC'] ?? ($section['UF_MARKER_ICON_PC'] ?? 24);
        $iconMbRaw = $sectionUfs['UF_MARKER_ICON_MB'] ?? ($section['UF_MARKER_ICON_MB'] ?? 18);

        $mainColor = Sanitizer::color((string)$mainColorRaw, '#1E88E5');
        $scheme = [
            'id' => (int)$section['ID'],
            'name' => (string)$section['NAME'],
            'iblockType' => self::getIblockType($iblockId),
            'image' => self::filePath((int)($section['UF_MARKER_IMAGE'] ?? 0)),
            'mainColor' => $mainColor,
            'activeColor' => Sanitizer::color((string)$hoverColorRaw, '#FF5722'),
            'icon' => self::filePath((int)$iconRaw),
            'iconPc' => max(8, (int)$iconPcRaw),
            'iconMobile' => max(8, (int)$iconMbRaw),
            'showTooltipAlways' => $showTooltip,
            'snippet' => '<div data-wnfgr="markers" data-id="' . (int)$section['ID'] . '" data-iblock-id="' . (int)$iblockId . '"></div>',
        ];

        $scheme['markers'] = self::getMarkers(
            $iblockId,
            $sectionId,
            $scheme,
            $sortBy1,
            $sortOrder1,
            $sortBy2,
            $sortOrder2,
            $moreButtonPropertyCode,
            $buttonTextPropertyCode,
            $showTooltip
        );
        return $scheme;
    }

    public static function createMarker(int $iblockId, int $sectionId, string $name, float $x, float $y): array
    {
        $el = new \CIBlockElement();
        $fields = [
            'IBLOCK_ID' => $iblockId,
            'IBLOCK_SECTION_ID' => $sectionId,
            'NAME' => $name !== '' ? $name : 'Новая метка',
            'ACTIVE' => 'Y',
            'UF_MARKER_X' => Sanitizer::percent($x),
            'UF_MARKER_Y' => Sanitizer::percent($y),
        ];

        $id = (int)$el->Add($fields);
        if ($id <= 0) {
            return ['success' => false, 'error' => (string)$el->LAST_ERROR];
        }

        self::saveMarkerCoordinates($id, $iblockId, $x, $y);
        return ['success' => true, 'id' => $id];
    }

    public static function updateMarker(int $elementId, array $fields): array
    {
        $elementId = (int)$elementId;
        if ($elementId <= 0) {
            return ['success' => false, 'error' => 'Invalid marker ID'];
        }

        $el = new \CIBlockElement();
        $update = [];
        if (isset($fields['NAME'])) {
            $name = trim((string)$fields['NAME']);
            if ($name !== '') {
                $update['NAME'] = $name;
            }
        }
        if (array_key_exists('PREVIEW_TEXT', $fields)) {
            $update['PREVIEW_TEXT'] = (string)$fields['PREVIEW_TEXT'];
            $update['PREVIEW_TEXT_TYPE'] = 'html';
        }
        if (!empty($update) && !$el->Update($elementId, $update)) {
            return ['success' => false, 'error' => (string)$el->LAST_ERROR];
        }

        $uf = [];
        $hasX = isset($fields['UF_MARKER_X']);
        $hasY = isset($fields['UF_MARKER_Y']);
        if ($hasX || $hasY) {
            $elementIblockId = self::getElementIblockId($elementId);
            if ($elementIblockId > 0) {
                $x = $hasX ? (float)$fields['UF_MARKER_X'] : null;
                $y = $hasY ? (float)$fields['UF_MARKER_Y'] : null;
                self::saveMarkerCoordinates($elementId, $elementIblockId, $x, $y);
            }
        }

        return ['success' => true];
    }

    public static function getMarkerForEditor(int $elementId): ?array
    {
        $elementId = (int)$elementId;
        if ($elementId <= 0) {
            return null;
        }

        $row = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, [
            'ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME', 'PREVIEW_TEXT',
            'UF_MARKER_X', 'UF_MARKER_Y'
        ])->Fetch();
        if (!$row) {
            return null;
        }

        $iblockId = (int)$row['IBLOCK_ID'];
        $ufValues = self::getElementUfValues($iblockId, $elementId);

        return [
            'id' => $elementId,
            'name' => (string)$row['NAME'],
            'x' => self::resolveMarkerCoordinate($iblockId, $elementId, 'ABCWWW_MARKER_X', $ufValues['UF_MARKER_X'] ?? ($row['UF_MARKER_X'] ?? 0)),
            'y' => self::resolveMarkerCoordinate($iblockId, $elementId, 'ABCWWW_MARKER_Y', $ufValues['UF_MARKER_Y'] ?? ($row['UF_MARKER_Y'] ?? 0)),
            'previewText' => (string)($row['PREVIEW_TEXT'] ?? ''),
            'iblockId' => $iblockId,
            'sectionId' => (int)$row['IBLOCK_SECTION_ID'],
            'iblockType' => self::getIblockType($iblockId),
        ];
    }

    public static function deleteMarker(int $elementId): array
    {
        if ((int)$elementId <= 0) {
            return ['success' => false, 'error' => 'Invalid marker ID'];
        }
        $ok = \CIBlockElement::Delete((int)$elementId);
        return ['success' => $ok];
    }

    private static function getMarkers(
        int $iblockId,
        int $sectionId,
        array $scheme,
        string $sortBy1 = 'NAME',
        string $sortOrder1 = 'ASC',
        string $sortBy2 = 'ID',
        string $sortOrder2 = 'DESC',
        string $moreButtonPropertyCode = '',
        string $buttonTextPropertyCode = 'ABCWWW_BUTTON_TEXT',
        bool $showTooltip = false
    ): array
    {
        $markers = [];
        $allowedFields = ['NAME', 'ID', 'SORT'];
        $allowedOrders = ['ASC', 'DESC'];
        $sortBy1 = in_array(strtoupper($sortBy1), $allowedFields, true) ? strtoupper($sortBy1) : 'NAME';
        $sortOrder1 = in_array(strtoupper($sortOrder1), $allowedOrders, true) ? strtoupper($sortOrder1) : 'ASC';
        $sortBy2 = in_array(strtoupper($sortBy2), $allowedFields, true) ? strtoupper($sortBy2) : 'ID';
        $sortOrder2 = in_array(strtoupper($sortOrder2), $allowedOrders, true) ? strtoupper($sortOrder2) : 'DESC';
        $res = \CIBlockElement::GetList(
            [$sortBy1 => $sortOrder1, $sortBy2 => $sortOrder2],
            ['IBLOCK_ID' => $iblockId, 'SECTION_ID' => $sectionId, 'ACTIVE' => 'Y', 'INCLUDE_SUBSECTIONS' => 'N'],
            false,
            false,
            [
                'ID', 'IBLOCK_ID', 'NAME', 'CODE', 'PREVIEW_TEXT', 'PREVIEW_PICTURE',
                'UF_MARKER_X', 'UF_MARKER_Y', 'UF_MARKER_COLOR',
                'UF_MARKER_OPENED', 'UF_MARKER_BTN_COLOR'
            ]
        );

        while ($row = $res->Fetch()) {
            $ufValues = self::getElementUfValues($iblockId, (int)$row['ID']);
            $markerX = self::resolveMarkerCoordinate($iblockId, (int)$row['ID'], 'ABCWWW_MARKER_X', $ufValues['UF_MARKER_X'] ?? ($row['UF_MARKER_X'] ?? 0));
            $markerY = self::resolveMarkerCoordinate($iblockId, (int)$row['ID'], 'ABCWWW_MARKER_Y', $ufValues['UF_MARKER_Y'] ?? ($row['UF_MARKER_Y'] ?? 0));
            $markerColor = $ufValues['UF_MARKER_COLOR'] ?? ($row['UF_MARKER_COLOR'] ?? '');
            $opened = $ufValues['UF_MARKER_OPENED'] ?? ($row['UF_MARKER_OPENED'] ?? 0);
            $btnColorRaw = $ufValues['UF_MARKER_BTN_COLOR'] ?? ($row['UF_MARKER_BTN_COLOR'] ?? '');
            $moreContent = self::resolveMoreButtonContent($iblockId, (int)$row['ID'], (string)($row['CODE'] ?? ''), $moreButtonPropertyCode);
            $buttonText = self::resolveButtonText($iblockId, (int)$row['ID'], $buttonTextPropertyCode);

            $previewText = Sanitizer::html($row['PREVIEW_TEXT'] ?? '');
            $showMoreButton = $moreContent !== '';

            $markers[] = [
                'id' => (int)$row['ID'],
                'name' => (string)$row['NAME'],
                'x' => Sanitizer::percent($markerX),
                'y' => Sanitizer::percent($markerY),
                'opened' => $showTooltip || (int)$opened === 1,
                'color' => Sanitizer::color((string)$markerColor, $scheme['mainColor']),
                'hoverColor' => $scheme['activeColor'],
                'previewImage' => self::filePath((int)($row['PREVIEW_PICTURE'] ?? 0)),
                'previewText' => $previewText,
                'showMoreButton' => $showMoreButton,
                'buttonText' => $buttonText,
                'buttonColor' => Sanitizer::color((string)$btnColorRaw, $scheme['mainColor']),
                'productUrl' => $moreContent,
                'editUrl' => self::buildElementEditUrl($iblockId, $sectionId, (int)$row['ID'], (string)($scheme['iblockType'] ?? '')),
            ];
        }

        return $markers;
    }

    private static function updateMarkerUfs(int $elementId, array $fields): void
    {
        global $USER_FIELD_MANAGER;
        $elRes = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['ID', 'IBLOCK_ID'])->Fetch();
        if (!$elRes) {
            return;
        }
        $entityId = 'IBLOCK_' . (int)$elRes['IBLOCK_ID'] . '_ELEMENT';
        $USER_FIELD_MANAGER->Update($entityId, $elementId, $fields);
    }

    private static function saveMarkerCoordinates(int $elementId, int $iblockId, ?float $x, ?float $y): void
    {
        $uf = [];
        if ($x !== null) {
            $uf['UF_MARKER_X'] = Sanitizer::percent($x);
        }
        if ($y !== null) {
            $uf['UF_MARKER_Y'] = Sanitizer::percent($y);
        }
        if (!empty($uf)) {
            self::updateMarkerUfs($elementId, $uf);
        }

        self::ensureCoordinateProperties($iblockId);
        $props = [];
        if ($x !== null) {
            $props['ABCWWW_MARKER_X'] = (string)Sanitizer::percent($x);
        }
        if ($y !== null) {
            $props['ABCWWW_MARKER_Y'] = (string)Sanitizer::percent($y);
        }
        if (!empty($props)) {
            \CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, $props);
        }
    }

    private static function resolveMarkerCoordinate(int $iblockId, int $elementId, string $propertyCode, mixed $ufFallback): float
    {
        $value = self::getElementPropertyValueByCode($iblockId, $elementId, $propertyCode);
        if ($value !== '') {
            return Sanitizer::percent($value);
        }

        return Sanitizer::percent($ufFallback);
    }

    private static function getElementIblockId(int $elementId): int
    {
        if ($elementId <= 0) {
            return 0;
        }
        $row = \CIBlockElement::GetList([], ['ID' => $elementId], false, false, ['ID', 'IBLOCK_ID'])->Fetch();
        return is_array($row) ? (int)($row['IBLOCK_ID'] ?? 0) : 0;
    }

    private static function ensureCoordinateProperties(int $iblockId): void
    {
        static $checked = [];
        if ($iblockId <= 0 || isset($checked[$iblockId]) || !class_exists('\CIBlockProperty')) {
            return;
        }

        self::ensureTextProperty($iblockId, 'ABCWWW_MARKER_X', 'Координата X');
        self::ensureTextProperty($iblockId, 'ABCWWW_MARKER_Y', 'Координата Y');
        $checked[$iblockId] = true;
    }

    private static function ensureTextProperty(int $iblockId, string $code, string $name): void
    {
        $res = \CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'CODE' => $code]
        );
        if ($res && $res->Fetch()) {
            return;
        }

        $property = new \CIBlockProperty();
        $property->Add([
            'IBLOCK_ID' => $iblockId,
            'NAME' => $name,
            'ACTIVE' => 'Y',
            'SORT' => '500',
            'CODE' => $code,
            'PROPERTY_TYPE' => 'S',
            'MULTIPLE' => 'N',
            'IS_REQUIRED' => 'N',
        ]);
    }

    private static function getElementUfValues(int $iblockId, int $elementId): array
    {
        static $cache = [];
        if ($iblockId <= 0 || $elementId <= 0) {
            return [];
        }

        $cacheKey = $iblockId . ':' . $elementId;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        global $USER_FIELD_MANAGER;
        if (!is_object($USER_FIELD_MANAGER)) {
            $cache[$cacheKey] = [];
            return $cache[$cacheKey];
        }

        $entityId = 'IBLOCK_' . $iblockId . '_ELEMENT';
        $fields = (array)$USER_FIELD_MANAGER->GetUserFields($entityId, $elementId, LANGUAGE_ID);
        $result = [];
        foreach ($fields as $name => $meta) {
            $result[$name] = $meta['VALUE'] ?? null;
        }

        $cache[$cacheKey] = $result;
        return $result;
    }

    private static function filePath(int $fileId): string
    {
        return $fileId > 0 ? (string)\CFile::GetPath($fileId) : '';
    }

    private static function getIblockType(int $iblockId): string
    {
        static $cache = [];
        if ($iblockId <= 0) {
            return '';
        }
        if (isset($cache[$iblockId])) {
            return $cache[$iblockId];
        }

        $value = (string)\CIBlock::GetArrayByID($iblockId, 'IBLOCK_TYPE_ID');
        $cache[$iblockId] = $value;
        return $value;
    }

    private static function buildElementEditUrl(int $iblockId, int $sectionId, int $elementId, string $iblockType): string
    {
        return '/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=' . $iblockId
            . '&type=' . rawurlencode($iblockType)
            . '&ID=' . $elementId
            . '&lang=ru&find_section_section=' . $sectionId;
    }

    private static function getSectionUfValues(int $iblockId, int $sectionId): array
    {
        static $cache = [];
        if ($iblockId <= 0 || $sectionId <= 0) {
            return [];
        }
        $cacheKey = $iblockId . ':' . $sectionId;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        global $USER_FIELD_MANAGER;
        if (!is_object($USER_FIELD_MANAGER)) {
            $cache[$cacheKey] = [];
            return $cache[$cacheKey];
        }

        $entityId = 'IBLOCK_' . $iblockId . '_SECTION';
        $fields = (array)$USER_FIELD_MANAGER->GetUserFields($entityId, $sectionId, LANGUAGE_ID);
        $result = [];
        foreach ($fields as $name => $meta) {
            $result[$name] = $meta['VALUE'] ?? null;
        }
        $cache[$cacheKey] = $result;
        return $result;
    }

    private static function getElementPropertyValueByCode(int $iblockId, int $elementId, string $propertyCode): string
    {
        static $cache = [];
        $propertyCode = trim((string)$propertyCode);
        if ($iblockId <= 0 || $elementId <= 0 || $propertyCode === '') {
            return '';
        }

        $cacheKey = $iblockId . ':' . $elementId . ':' . strtoupper($propertyCode);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $res = \CIBlockElement::GetProperty($iblockId, $elementId, ['sort' => 'asc'], ['CODE' => $propertyCode]);
        $value = '';
        if ($prop = $res->Fetch()) {
            $value = trim((string)($prop['VALUE'] ?? ''));
        }
        $cache[$cacheKey] = $value;
        return $value;
    }

    private static function resolveMoreButtonContent(
        int $iblockId,
        int $elementId,
        string $elementCode,
        string $moreButtonPropertyCode
    ): string {
        $propertyCode = trim((string)$moreButtonPropertyCode);
        if ($propertyCode === '') {
            return trim((string)$elementCode);
        }

        return self::getElementPropertyValueByCode($iblockId, $elementId, $propertyCode);
    }

    private static function resolveButtonText(
        int $iblockId,
        int $elementId,
        string $buttonTextPropertyCode
    ): string {
        $propertyCode = trim((string)$buttonTextPropertyCode);
        if ($propertyCode === '') {
            return 'Подробнее';
        }

        $value = self::getElementPropertyValueByCode($iblockId, $elementId, $propertyCode);
        return $value !== '' ? $value : 'Подробнее';
    }
}
