<?php

namespace Abcwww\Markers;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;

class PublicInjector
{
    public static function onEpilog(): void
    {
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }

        $snippetAutoinit = Option::get(
            Options::MODULE_ID,
            Options::ENABLE_SNIPPET_AUTO_INIT,
            Options::defaults()[Options::ENABLE_SNIPPET_AUTO_INIT]
        ) === 'Y';
        if (!$snippetAutoinit) {
            return;
        }

        $asset = Asset::getInstance();
        $asset->addCss('/bitrix/css/' . Options::MODULE_ID . '/markers.css');
        $asset->addJs('/bitrix/js/' . Options::MODULE_ID . '/markers.js');
        $iblockId = (int)Option::get(Options::MODULE_ID, Options::IBLOCK_ID, Options::defaults()[Options::IBLOCK_ID]);
        $asset->addString(
            '<script>window.AbcwwwMarkersDefaultIblock=' . $iblockId . ';</script>'
        );
    }
}
