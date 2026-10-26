<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class myapp_catalog extends CModule
{
    private const OPT_TEMPLATES_INSTALLED = 'TEMPLATES_INSTALLED';

    private const ROUTES_FILE = 'vue_catalog.php';

    public $MODULE_ID = 'myapp.catalog';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('MYAPP_CATALOG_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MYAPP_CATALOG_MODULE_DESC');
        $this->PARTNER_NAME = 'MyApp';
        $this->PARTNER_URI = 'https://example.com';
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installTemplates();
        $this->installRoutes();
        $this->installEvents();
    }

    public function DoUninstall()
    {
        $this->uninstallEvents();
        $this->uninstallRoutes();
        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function installEvents(): void
    {
        if (!\Bitrix\Main\Loader::includeModule($this->MODULE_ID)) {
            return;
        }

        \MyApp\Catalog\Event\CatalogCacheHandler::installPersistentHandlers();
        Option::set($this->MODULE_ID, 'catalog_cache_handlers_installed', 'Y');
    }

    public function uninstallEvents(): void
    {
        if (!\Bitrix\Main\Loader::includeModule($this->MODULE_ID)) {
            \Bitrix\Main\Loader::registerAutoLoadClasses($this->MODULE_ID, [
                'MyApp\\Catalog\\Event\\CatalogCacheHandler' => 'lib/Event/CatalogCacheHandler.php',
            ]);
        }

        if (class_exists(\MyApp\Catalog\Event\CatalogCacheHandler::class)) {
            \MyApp\Catalog\Event\CatalogCacheHandler::uninstallPersistentHandlers();
        }
    }

    public function installTemplates(): void
    {
        $documentRoot = (string)$_SERVER['DOCUMENT_ROOT'];
        $sourceRoot = __DIR__ . '/templates';
        $targetRoot = $documentRoot . '/local/templates';

        if (!is_dir($sourceRoot)) {
            return;
        }

        foreach (['assets', 'main'] as $dirName) {
            $from = $sourceRoot . '/' . $dirName;
            if (!is_dir($from)) {
                continue;
            }

            $to = $targetRoot . '/' . $dirName;
            if (!is_dir($to)) {
                CheckDirPath($to . '/');
            }

            CopyDirFiles($from, $to, true, true);
        }

        Option::set($this->MODULE_ID, self::OPT_TEMPLATES_INSTALLED, 'Y');
    }

    public function installRoutes(): void
    {
        $documentRoot = (string)$_SERVER['DOCUMENT_ROOT'];
        $source = __DIR__ . '/routes/' . self::ROUTES_FILE;
        $targetDir = $documentRoot . '/local/routes';
        $target = $targetDir . '/' . self::ROUTES_FILE;

        if (!is_file($source)) {
            return;
        }

        if (!is_dir($targetDir)) {
            CheckDirPath($targetDir . '/');
        }

        copy($source, $target);
        $this->registerRoutingConfig($documentRoot);
    }

    public function uninstallRoutes(): void
    {
        $documentRoot = (string)$_SERVER['DOCUMENT_ROOT'];
        $routesFile = $documentRoot . '/local/routes/' . self::ROUTES_FILE;

        if (is_file($routesFile)) {
            @unlink($routesFile);
        }

        $settingsPath = $documentRoot . '/local/.settings_extra.php';
        if (is_file($settingsPath)) {
            @unlink($settingsPath);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($settingsPath, true);
            }
        }
    }

    private function registerRoutingConfig(string $documentRoot): void
    {
        $settingsPath = $documentRoot . '/local/.settings_extra.php';
        $settings = [];

        if (is_file($settingsPath)) {
            $loaded = include $settingsPath;
            if (is_array($loaded)) {
                $settings = $loaded;
            }
        }

        $routing = $settings['routing']['value'] ?? [];
        $config = $routing['config'] ?? [];
        if (!is_array($config)) {
            $config = [];
        }

        if (in_array(self::ROUTES_FILE, $config, true)) {
            return;
        }

        $config[] = self::ROUTES_FILE;
        $settings['routing'] = [
            'value' => [
                'config' => array_values($config),
            ],
            'readonly' => false,
        ];

        $export = var_export($settings, true);
        $content = "<?php\n\nreturn {$export};\n";

        if (@file_put_contents($settingsPath, $content) === false) {
            return;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($settingsPath, true);
        }
    }
}
