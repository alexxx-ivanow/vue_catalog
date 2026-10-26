<?php

namespace MyApp\Catalog\Event;

use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;
use MyApp\Catalog\Config\Assets;
use MyApp\Catalog\Service\CatalogService;

final class CatalogCacheHandler
{
    private const MODULE_ID = 'myapp.catalog';

    private const OPT_HANDLERS_INSTALLED = 'catalog_cache_handlers_installed';

    /** @var array<int, list<int>> */
    private static array $elementSectionsBeforeUpdate = [];

    public static function ensurePersistentHandlers(): void
    {
        if (Option::get(self::MODULE_ID, self::OPT_HANDLERS_INSTALLED, 'N') === 'Y') {
            return;
        }

        self::installPersistentHandlers();
        Option::set(self::MODULE_ID, self::OPT_HANDLERS_INSTALLED, 'Y');
    }

    public static function installPersistentHandlers(): void
    {
        $manager = EventManager::getInstance();
        $class = self::class;

        $manager->registerEventHandler('iblock', 'OnBeforeIBlockElementUpdate', self::MODULE_ID, $class, 'onBeforeElementUpdate');
        $manager->registerEventHandler('iblock', 'OnBeforeIBlockElementDelete', self::MODULE_ID, $class, 'onBeforeElementUpdate');
        $manager->registerEventHandler('iblock', 'OnAfterIBlockElementAdd', self::MODULE_ID, $class, 'onAfterElementChange');
        $manager->registerEventHandler('iblock', 'OnAfterIBlockElementUpdate', self::MODULE_ID, $class, 'onAfterElementUpdate');
        $manager->registerEventHandler('iblock', 'OnAfterIBlockElementDelete', self::MODULE_ID, $class, 'onAfterElementDelete');

        $manager->registerEventHandler('iblock', 'OnAfterIBlockSectionAdd', self::MODULE_ID, $class, 'onAfterSectionChange');
        $manager->registerEventHandler('iblock', 'OnAfterIBlockSectionUpdate', self::MODULE_ID, $class, 'onAfterSectionChange');
        $manager->registerEventHandler('iblock', 'OnAfterIBlockSectionDelete', self::MODULE_ID, $class, 'onAfterSectionDelete');

        $manager->registerEventHandler('catalog', 'OnPriceAdd', self::MODULE_ID, $class, 'onPriceChange');
        $manager->registerEventHandler('catalog', 'OnPriceUpdate', self::MODULE_ID, $class, 'onPriceChange');
        $manager->registerEventHandler('catalog', 'OnPriceDelete', self::MODULE_ID, $class, 'onPriceChange');
    }

    public static function uninstallPersistentHandlers(): void
    {
        $manager = EventManager::getInstance();
        $class = self::class;

        $manager->unRegisterEventHandler('iblock', 'OnBeforeIBlockElementUpdate', self::MODULE_ID, $class, 'onBeforeElementUpdate');
        $manager->unRegisterEventHandler('iblock', 'OnBeforeIBlockElementDelete', self::MODULE_ID, $class, 'onBeforeElementUpdate');
        $manager->unRegisterEventHandler('iblock', 'OnAfterIBlockElementAdd', self::MODULE_ID, $class, 'onAfterElementChange');
        $manager->unRegisterEventHandler('iblock', 'OnAfterIBlockElementUpdate', self::MODULE_ID, $class, 'onAfterElementUpdate');
        $manager->unRegisterEventHandler('iblock', 'OnAfterIBlockElementDelete', self::MODULE_ID, $class, 'onAfterElementDelete');

        $manager->unRegisterEventHandler('iblock', 'OnAfterIBlockSectionAdd', self::MODULE_ID, $class, 'onAfterSectionChange');
        $manager->unRegisterEventHandler('iblock', 'OnAfterIBlockSectionUpdate', self::MODULE_ID, $class, 'onAfterSectionChange');
        $manager->unRegisterEventHandler('iblock', 'OnAfterIBlockSectionDelete', self::MODULE_ID, $class, 'onAfterSectionDelete');

        $manager->unRegisterEventHandler('catalog', 'OnPriceAdd', self::MODULE_ID, $class, 'onPriceChange');
        $manager->unRegisterEventHandler('catalog', 'OnPriceUpdate', self::MODULE_ID, $class, 'onPriceChange');
        $manager->unRegisterEventHandler('catalog', 'OnPriceDelete', self::MODULE_ID, $class, 'onPriceChange');

        Option::delete(self::MODULE_ID, ['name' => self::OPT_HANDLERS_INSTALLED]);
    }

    public static function onBeforeElementUpdate(array &$fields): void
    {
        if (!self::isCatalogElementFields($fields)) {
            return;
        }

        $elementId = (int)($fields['ID'] ?? 0);
        if ($elementId <= 0) {
            return;
        }

        self::$elementSectionsBeforeUpdate[$elementId] = CatalogService::getElementSectionIds($elementId);
    }

    public static function onAfterElementChange(array &$fields): void
    {
        if (!self::isCatalogElementFields($fields)) {
            return;
        }

        $elementId = (int)($fields['ID'] ?? 0);
        if ($elementId > 0) {
            CatalogService::invalidateByElement($elementId, [], true);
        }
    }

    public static function onAfterElementUpdate(array &$fields): void
    {
        if (!self::isCatalogElementFields($fields)) {
            return;
        }

        $elementId = (int)($fields['ID'] ?? 0);
        if ($elementId <= 0) {
            return;
        }

        $oldSectionIds = self::$elementSectionsBeforeUpdate[$elementId] ?? [];
        unset(self::$elementSectionsBeforeUpdate[$elementId]);

        CatalogService::invalidateByElement($elementId, $oldSectionIds, false);
    }

    public static function onAfterElementDelete(array &$fields): void
    {
        if (!self::isCatalogElementFields($fields)) {
            return;
        }

        $elementId = (int)($fields['ID'] ?? 0);
        if ($elementId <= 0) {
            return;
        }

        $oldSectionIds = self::$elementSectionsBeforeUpdate[$elementId] ?? [];
        unset(self::$elementSectionsBeforeUpdate[$elementId]);

        CatalogService::invalidateByElement($elementId, $oldSectionIds, true);
    }

    public static function onAfterSectionChange(array &$fields): void
    {
        if (!self::isCatalogIblock(self::resolveIblockIdFromFields($fields))) {
            return;
        }

        $sectionId = (int)($fields['ID'] ?? 0);
        if ($sectionId > 0) {
            CatalogService::invalidateBySection($sectionId, false);
        }
    }

    public static function onAfterSectionDelete(array &$fields): void
    {
        if (!self::isCatalogIblock(self::resolveIblockIdFromFields($fields))) {
            return;
        }

        $sectionId = (int)($fields['ID'] ?? 0);
        if ($sectionId > 0) {
            CatalogService::invalidateBySection($sectionId, true);
        }
    }

    public static function onPriceChange(array &$fields): void
    {
        $productId = (int)($fields['PRODUCT_ID'] ?? 0);
        if ($productId <= 0) {
            return;
        }

        if (!Loader::includeModule('iblock')) {
            return;
        }

        $row = ElementTable::getList([
            'select' => ['IBLOCK_ID'],
            'filter' => ['=ID' => $productId],
            'limit' => 1,
        ])->fetch();

        if (!$row || !self::isCatalogIblock((int)$row['IBLOCK_ID'])) {
            return;
        }

        CatalogService::invalidateByElement($productId, [], true);
    }

    private static function isCatalogIblock(int $iblockId): bool
    {
        return $iblockId > 0 && $iblockId === Assets::catalogIblockId();
    }

    private static function isCatalogElementFields(array $fields): bool
    {
        return self::isCatalogIblock(self::resolveIblockIdFromFields($fields));
    }

    private static function resolveIblockIdFromFields(array $fields): int
    {
        $iblockId = (int)($fields['IBLOCK_ID'] ?? 0);
        if ($iblockId > 0) {
            return $iblockId;
        }

        $elementId = (int)($fields['ID'] ?? 0);
        if ($elementId <= 0) {
            return 0;
        }

        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        $row = ElementTable::getList([
            'select' => ['IBLOCK_ID'],
            'filter' => ['=ID' => $elementId],
            'limit' => 1,
        ])->fetch();

        return (int)($row['IBLOCK_ID'] ?? 0);
    }
}
