<?php

use Bitrix\Main\Loader;
use Abcwww\Markers\Repository;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

class AbcwwwMarkersComponent extends CBitrixComponent
{
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
                (string)($this->arParams['SHOW_TOOLTIP'] ?? 'N') === 'Y'
            );
            if (!$scheme) {
                $this->abortResultCache();
                return;
            }

            $scheme['tooltipOnHover'] = (string)($this->arParams['TOOLTIP_ON_HOVER'] ?? 'N') === 'Y';

            $this->arResult = [
                'IBLOCK_ID' => $iblockId,
                'SECTION_ID' => $sectionId,
                'SCHEME' => $scheme,
            ];
            $this->includeComponentTemplate();
        }
    }
}
