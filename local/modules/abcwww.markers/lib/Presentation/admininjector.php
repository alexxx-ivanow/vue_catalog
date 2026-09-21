<?php

namespace Abcwww\Markers;

use Bitrix\Main\Context;
use Bitrix\Main\Page\Asset;

class AdminInjector
{
    public static function onProlog(): void
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return;
        }

        $request = Context::getCurrent()->getRequest();
        $scriptName = (string)$request->getRequestedPage();
        $baseName = strtolower(basename($scriptName));
        if (!in_array($baseName, ['iblock_section_edit.php', 'cat_iblock_section_edit.php'], true)) {
            return;
        }

        $iblockId = (int)($request->get('IBLOCK_ID') ?? 0);
        $sectionId = (int)($request->get('ID') ?? 0);
        if ($sectionId <= 0) {
            $sectionId = (int)($request->get('find_section_section') ?? 0);
        }
        if ($iblockId <= 0 && $sectionId > 0 && class_exists('\CIBlockSection')) {
            $section = \CIBlockSection::GetByID($sectionId)->Fetch();
            if (is_array($section) && !empty($section['IBLOCK_ID'])) {
                $iblockId = (int)$section['IBLOCK_ID'];
            }
        }
        if ($iblockId <= 0 || $sectionId <= 0) {
            return;
        }

        $asset = Asset::getInstance();
        $asset->addString('<script>window.AbcwwwMarkersAdmin={iblockId:' . $iblockId . ',sectionId:' . $sectionId . '};</script>');
        $asset->addJs('/bitrix/js/' . Options::MODULE_ID . '/admin-inject.js');
    }
}
