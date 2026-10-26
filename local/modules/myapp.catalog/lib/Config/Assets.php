<?php

namespace MyApp\Catalog\Config;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;

final class Assets
{
    public const MODULE_ID = 'myapp.catalog';

    public const OPT_USE_BITRIX_VUE = 'use_bitrix_vue';

    public const OPT_CATALOG_IBLOCK_ID = 'catalog_iblock_id';

    public const OPT_AUTO_LOGIN_AFTER_GUEST_ORDER = 'auto_login_after_guest_order';

    public const OPT_SHOW_CATALOG_FILTER = 'show_catalog_filter';

    public const OPT_CATALOG_PRICE_TYPE_CODE = 'catalog_price_type_code';

    public const OPT_FILTER_PROPERTY_CODES = 'catalog_filter_property_codes';

    public const OPT_CATALOG_CACHE_TTL = 'catalog_cache_ttl';

    public const DEFAULT_CATALOG_IBLOCK_ID = 2;

    public const DEFAULT_CATALOG_CACHE_TTL = 3600;

    public const DEFAULT_CATALOG_PRICE_TYPE_CODE = 'BASE';

    /** @var list<string> */
    public const DEFAULT_FILTER_PROPERTY_CODES = ['MANUFACTURER', 'SIZE', 'MATERIAL'];

    /** Базовый URL JSON API Vue-каталога (от document root, без завершающего слэша). */
    public const API_V1_BASE = '/vue_catalog/api/v1';

    /** Базовый URL ES-модулей Vue-каталога (от document root). */
    public const JS_CATALOG_BASE = '/local/modules/myapp.catalog/js/catalog/';

    public const JS_VUE_VENDOR = '/local/modules/myapp.catalog/js/vendor/vue.esm-browser.js';

    public const JS_VUE_BITRIX_BRIDGE = '/local/modules/myapp.catalog/js/vendor/vue-bitrix-bridge.js';

    /** @deprecated Используйте JS_VUE_VENDOR или vueModuleUrl() */
    public const JS_VUE = self::JS_VUE_VENDOR;

    /** Фактический режим после prepareVueForPage() (с учётом fallback). */
    private static ?bool $bitrixVueActive = null;

    public static function jsCatalogUrl(string $entry): string
    {
        return self::JS_CATALOG_BASE . ltrim($entry, '/');
    }

    public static function useBitrixVueOption(): bool
    {
        return Option::get(self::MODULE_ID, self::OPT_USE_BITRIX_VUE, 'N') === 'Y';
    }

    public static function catalogCacheTtl(): int
    {
        $ttl = (int)Option::get(
            self::MODULE_ID,
            self::OPT_CATALOG_CACHE_TTL,
            (string)self::DEFAULT_CATALOG_CACHE_TTL
        );

        return $ttl > 0 ? $ttl : self::DEFAULT_CATALOG_CACHE_TTL;
    }

    public static function catalogIblockId(): int
    {
        $iblockId = (int)Option::get(
            self::MODULE_ID,
            self::OPT_CATALOG_IBLOCK_ID,
            (string)self::DEFAULT_CATALOG_IBLOCK_ID
        );

        return $iblockId > 0 ? $iblockId : self::DEFAULT_CATALOG_IBLOCK_ID;
    }

    public static function autoLoginAfterGuestOrder(): bool
    {
        return Option::get(self::MODULE_ID, self::OPT_AUTO_LOGIN_AFTER_GUEST_ORDER, 'N') === 'Y';
    }

    public static function showCatalogFilter(): bool
    {
        return Option::get(self::MODULE_ID, self::OPT_SHOW_CATALOG_FILTER, 'Y') === 'Y';
    }

    public static function catalogPriceTypeCode(): string
    {
        $code = trim((string)Option::get(
            self::MODULE_ID,
            self::OPT_CATALOG_PRICE_TYPE_CODE,
            self::DEFAULT_CATALOG_PRICE_TYPE_CODE
        ));

        return $code !== '' ? $code : self::DEFAULT_CATALOG_PRICE_TYPE_CODE;
    }

    /**
     * Коды свойств фильтра. Пустая строка в опции — значения по умолчанию.
     * Сохранённый JSON `[]` — в фильтре только цена.
     *
     * @return list<string>
     */
    public static function filterPropertyCodes(): array
    {
        $raw = (string)Option::get(self::MODULE_ID, self::OPT_FILTER_PROPERTY_CODES, '');
        if ($raw === '') {
            return self::DEFAULT_FILTER_PROPERTY_CODES;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::DEFAULT_FILTER_PROPERTY_CODES;
        }

        return self::normalizePropertyCodes($decoded);
    }

    /**
     * @param mixed $codes
     * @return list<string>
     */
    public static function normalizePropertyCodes(mixed $codes): array
    {
        $items = is_array($codes) ? $codes : [$codes];
        $result = [];
        foreach ($items as $code) {
            $normalized = trim((string)$code);
            if ($normalized === '' || !preg_match('/^[A-Za-z0-9_]+$/', $normalized)) {
                continue;
            }
            $result[] = $normalized;
        }

        return array_values(array_unique($result));
    }

    /**
     * Готовит Vue для страницы: при включённой опции грузит ui.vue3.
     * Возвращает true, если активен режим Bitrix Vue (import map → bridge).
     */
    public static function prepareVueForPage(): bool
    {
        self::$bitrixVueActive = false;

        if (!self::useBitrixVueOption()) {
            return false;
        }

        try {
            if (!Loader::includeModule('ui')) {
                return false;
            }

            Extension::load('ui.vue3');
            self::$bitrixVueActive = true;
            return true;
        } catch (\Throwable $e) {
            self::$bitrixVueActive = false;
            return false;
        }
    }

    public static function useBitrixVue(): bool
    {
        if (self::$bitrixVueActive !== null) {
            return self::$bitrixVueActive;
        }

        return self::useBitrixVueOption();
    }

    public static function vueModuleUrl(): string
    {
        if (self::useBitrixVue()) {
            return self::JS_VUE_BITRIX_BRIDGE;
        }

        return self::JS_VUE_VENDOR;
    }
}
