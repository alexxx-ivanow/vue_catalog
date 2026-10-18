<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class myapp_catalog extends CModule
{
    private const OPT_TEMPLATES_INSTALLED = 'TEMPLATES_INSTALLED';

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
    }

    public function DoUninstall()
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
        Option::delete($this->MODULE_ID);
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
}
