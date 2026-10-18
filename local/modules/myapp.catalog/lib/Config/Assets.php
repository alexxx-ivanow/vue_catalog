<?php

namespace MyApp\Catalog\Config;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\UI\Extension;

final class Assets
{
    public const MODULE_ID = 'myapp.catalog';

    public const OPT_USE_BITRIX_VUE = 'use_bitrix_vue';

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
