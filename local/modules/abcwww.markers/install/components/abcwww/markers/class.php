<?php

use Bitrix\Main\Loader;
use Abcwww\Markers\Repository;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class AbcwwwMarkersComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        $arParams['IBLOCK_TYPE_ID'] = trim((string)($arParams['IBLOCK_TYPE_ID'] ?? ''));
        $arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
        $arParams['SECTION_ID'] = (int)($arParams['SECTION_ID'] ?? 0);
        $arParams['PROPERTY_CODES'] = $this->normalizePropertyCodes($arParams['PROPERTY_CODES'] ?? []);
        return $arParams;
    }

    public function executeComponent()
    {
        if (!Loader::includeModule('abcwww.markers')) {
            ShowError('Module abcwww.markers is not installed');
            return;
        }

        $iblockId = (int)($this->arParams['IBLOCK_ID'] ?? 0);
        $sectionId = (int)($this->arParams['SECTION_ID'] ?? 0);
        if ($iblockId <= 0 || $sectionId <= 0) {
            return;
        }

        $propertyCodes = $this->normalizePropertyCodes($this->arParams['PROPERTY_CODES'] ?? []);

        $cacheKey = [
            $iblockId,
            $sectionId,
            (string)($this->arParams['SORT_BY1'] ?? 'NAME'),
            (string)($this->arParams['SORT_ORDER1'] ?? 'ASC'),
            (string)($this->arParams['SORT_BY2'] ?? 'ID'),
            (string)($this->arParams['SORT_ORDER2'] ?? 'DESC'),
            (string)($this->arParams['MORE_BUTTON_PROPERTY_CODE'] ?? ''),
            (string)($this->arParams['BUTTON_TEXT_PROPERTY_CODE'] ?? 'ABCWWW_BUTTON_TEXT'),
            (string)($this->arParams['SHOW_TOOLTIP'] ?? 'N'),
            (string)($this->arParams['TOOLTIP_ON_HOVER'] ?? 'N'),
            (string)($this->arParams['USE_MODAL'] ?? 'N'),
            implode(',', $propertyCodes),
            $this->arParams['CACHE_TIME'] ?? 3600000,
        ];

        if ($this->startResultCache(false, $cacheKey, '/abcwww_markers/')) {
            $scheme = Repository::getScheme(
                $iblockId,
                $sectionId,
                (string)($this->arParams['SORT_BY1'] ?? 'NAME'),
                (string)($this->arParams['SORT_ORDER1'] ?? 'ASC'),
                (string)($this->arParams['SORT_BY2'] ?? 'ID'),
                (string)($this->arParams['SORT_ORDER2'] ?? 'DESC'),
                (string)($this->arParams['MORE_BUTTON_PROPERTY_CODE'] ?? ''),
                (string)($this->arParams['BUTTON_TEXT_PROPERTY_CODE'] ?? 'ABCWWW_BUTTON_TEXT'),
                (string)($this->arParams['SHOW_TOOLTIP'] ?? 'N') === 'Y',
                $propertyCodes
            );
            if (!$scheme) {
                $this->abortResultCache();
                return;
            }

            $scheme['tooltipOnHover'] = (string)($this->arParams['TOOLTIP_ON_HOVER'] ?? 'N') === 'Y';
            $scheme['useModal'] = (string)($this->arParams['USE_MODAL'] ?? 'N') === 'Y';
            if ($scheme['useModal']) {
                $scheme['showTooltipAlways'] = false;
                foreach ($scheme['markers'] as &$marker) {
                    $marker['opened'] = false;
                }
                unset($marker);
            }

            $this->arResult = [
                'IBLOCK_TYPE_ID' => (string)($this->arParams['IBLOCK_TYPE_ID'] ?? ''),
                'IBLOCK_ID' => $iblockId,
                'SECTION_ID' => $sectionId,
                'SCHEME' => $scheme,
            ];
            $this->includeComponentTemplate();
        }
    }

    /** @param mixed $codes */
    private function normalizePropertyCodes($codes): array
    {
        if (!is_array($codes)) {
            $codes = [$codes];
        }

        $result = [];
        foreach ($codes as $code) {
            $code = trim((string)$code);
            if ($code === '' || isset($result[$code])) {
                continue;
            }
            $result[$code] = $code;
        }

        return array_values($result);
    }
}
