<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class abcwww_markers extends CModule
{
    private const SERVICE_IBLOCK_TYPE = 'abcwww_markers';
    private const SERVICE_IBLOCK_CODE = 'abcwww_markers';
    private const OPT_IBLOCK_ID = 'IBLOCK_ID';
    private const OPT_IBLOCK_CREATED = 'SERVICE_IBLOCK_CREATED';
    private const OPT_IBLOCK_TYPE_CREATED = 'SERVICE_IBLOCK_TYPE_CREATED';

    public $MODULE_ID = 'abcwww.markers';
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
        $this->MODULE_NAME = Loc::getMessage('ABCWWW_MARKERS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ABCWWW_MARKERS_MODULE_DESC');
        $this->PARTNER_NAME = 'Abcwww';
        $this->PARTNER_URI = 'https://example.com';
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installEvents();
        $this->installIblock();
        $this->installFiles();
        $serviceIblockId = $this->findServiceIblockId();
        if ($serviceIblockId > 0) {
            $this->ensureServiceIblockUf($serviceIblockId);
            $this->ensureServiceIblockProperties($serviceIblockId);
        }
    }

    public function DoUninstall()
    {
        $this->unInstallIblock();
        $this->unInstallEvents();
        $this->unInstallFiles();
        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function installFiles()
    {
        CopyDirFiles(
            __DIR__ . '/components',
            $_SERVER['DOCUMENT_ROOT'] . '/local/components',
            true,
            true
        );
        CopyDirFiles(
            __DIR__ . '/js/' . $this->MODULE_ID,
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID,
            true,
            true
        );
        CopyDirFiles(
            __DIR__ . '/css/' . $this->MODULE_ID,
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID,
            true,
            true
        );
        CopyDirFiles(
            __DIR__ . '/tools',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/tools/' . $this->MODULE_ID,
            true,
            true
        );
        CopyDirFiles(
            __DIR__ . '/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin',
            true,
            true
        );
    }

    public function unInstallFiles()
    {
        DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID);
        DeleteDirFilesEx('/bitrix/css/' . $this->MODULE_ID);
        DeleteDirFilesEx('/bitrix/tools/' . $this->MODULE_ID);
        DeleteDirFilesEx('/local/components/abcwww/markers');
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/abcwww_markers_editor.php');
    }

    public function installEvents()
    {
        RegisterModuleDependences('main', 'OnProlog', $this->MODULE_ID, 'Abcwww\Markers\AdminInjector', 'onProlog');
        RegisterModuleDependences('main', 'OnEpilog', $this->MODULE_ID, 'Abcwww\Markers\PublicInjector', 'onEpilog');
        RegisterModuleDependences('main', 'OnUserTypeBuildList', $this->MODULE_ID, 'Abcwww\Markers\ColorUserType', 'GetUserTypeDescription');
        RegisterModuleDependences('iblock', 'OnAfterIBlockElementAdd', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onElementChanged');
        RegisterModuleDependences('iblock', 'OnAfterIBlockElementUpdate', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onElementChanged');
        RegisterModuleDependences('iblock', 'OnAfterIBlockElementDelete', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onElementChanged');
        RegisterModuleDependences('iblock', 'OnAfterIBlockSectionUpdate', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onSectionChanged');
        RegisterModuleDependences('iblock', 'OnAfterIBlockSectionDelete', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onSectionChanged');
    }

    public function unInstallEvents()
    {
        UnRegisterModuleDependences('main', 'OnProlog', $this->MODULE_ID, 'Abcwww\Markers\AdminInjector', 'onProlog');
        UnRegisterModuleDependences('main', 'OnEpilog', $this->MODULE_ID, 'Abcwww\Markers\PublicInjector', 'onEpilog');
        UnRegisterModuleDependences('main', 'OnUserTypeBuildList', $this->MODULE_ID, 'Abcwww\Markers\ColorUserType', 'GetUserTypeDescription');
        UnRegisterModuleDependences('iblock', 'OnAfterIBlockElementAdd', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onElementChanged');
        UnRegisterModuleDependences('iblock', 'OnAfterIBlockElementUpdate', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onElementChanged');
        UnRegisterModuleDependences('iblock', 'OnAfterIBlockElementDelete', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onElementChanged');
        UnRegisterModuleDependences('iblock', 'OnAfterIBlockSectionUpdate', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onSectionChanged');
        UnRegisterModuleDependences('iblock', 'OnAfterIBlockSectionDelete', $this->MODULE_ID, 'Abcwww\Markers\CacheManager', 'onSectionChanged');
    }

    private function installIblock(): void
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return;
        }

        $typeCreated = false;
        $iblockType = new CIBlockType();
        $typeRes = CIBlockType::GetByID(self::SERVICE_IBLOCK_TYPE);
        if (!$typeRes || !$typeRes->Fetch()) {
            $typeCreated = (bool)$iblockType->Add([
                'ID' => self::SERVICE_IBLOCK_TYPE,
                'SECTIONS' => 'Y',
                'IN_RSS' => 'N',
                'SORT' => 500,
                'LANG' => [
                    'ru' => [
                        'NAME' => 'Интерактивные метки',
                        'SECTION_NAME' => 'Схемы',
                        'ELEMENT_NAME' => 'Метки',
                    ],
                    'en' => [
                        'NAME' => 'Interactive markers',
                        'SECTION_NAME' => 'Schemes',
                        'ELEMENT_NAME' => 'Markers',
                    ],
                ],
            ]);
        }

        $iblockRes = CIBlock::GetList([], [
            'TYPE' => self::SERVICE_IBLOCK_TYPE,
            '=CODE' => self::SERVICE_IBLOCK_CODE,
            'CHECK_PERMISSIONS' => 'N',
        ]);
        $existing = $iblockRes ? $iblockRes->Fetch() : false;

        if ($existing && (int)$existing['ID'] > 0) {
            $existingId = (int)$existing['ID'];
            Option::set($this->MODULE_ID, self::OPT_IBLOCK_ID, (string)(int)$existing['ID']);
            Option::set($this->MODULE_ID, self::OPT_IBLOCK_CREATED, 'N');
            Option::set($this->MODULE_ID, self::OPT_IBLOCK_TYPE_CREATED, $typeCreated ? 'Y' : 'N');
            $this->ensureServiceIblockUf($existingId);
            $this->ensureServiceIblockProperties($existingId);
            return;
        }

        $sites = [];
        $rsSites = CSite::GetList('sort', 'asc');
        while ($site = $rsSites->Fetch()) {
            $sites[] = $site['LID'];
        }
        if (empty($sites)) {
            $sites[] = 's1';
        }

        $ib = new CIBlock();
        $id = (int)$ib->Add([
            'ACTIVE' => 'Y',
            'NAME' => 'Интерактивные схемы',
            'CODE' => self::SERVICE_IBLOCK_CODE,
            'IBLOCK_TYPE_ID' => self::SERVICE_IBLOCK_TYPE,
            'SITE_ID' => $sites,
            'SORT' => 500,
            'GROUP_ID' => ['2' => 'R'],
            'VERSION' => 2,
            'INDEX_ELEMENT' => 'N',
            'INDEX_SECTION' => 'N',
            'SECTION_PROPERTY' => 'Y',
            'RIGHTS_MODE' => 'S',
        ]);

        if ($id > 0) {
            Option::set($this->MODULE_ID, self::OPT_IBLOCK_ID, (string)$id);
            Option::set($this->MODULE_ID, self::OPT_IBLOCK_CREATED, 'Y');
            Option::set($this->MODULE_ID, self::OPT_IBLOCK_TYPE_CREATED, $typeCreated ? 'Y' : 'N');
            $this->ensureServiceIblockUf($id);
            $this->ensureServiceIblockProperties($id);
            return;
        }

        CEventLog::Add([
            'SEVERITY' => 'ERROR',
            'AUDIT_TYPE_ID' => 'ABCWWW_MARKERS_INSTALL_IBLOCK_ERROR',
            'MODULE_ID' => $this->MODULE_ID,
            'DESCRIPTION' => 'Unable to create service iblock: ' . (string)$ib->LAST_ERROR,
        ]);
    }

    private function unInstallIblock(): void
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return;
        }

        $iblockId = (int)Option::get($this->MODULE_ID, self::OPT_IBLOCK_ID, '0');
        if ($iblockId <= 0) {
            $iblockId = $this->findServiceIblockId();
        }

        $this->removeServiceIblockUf($iblockId);

        if ($iblockId > 0) {
            CIBlock::Delete($iblockId);
        }

        $res = CIBlock::GetList([], ['TYPE' => self::SERVICE_IBLOCK_TYPE, 'CHECK_PERMISSIONS' => 'N']);
        $hasOtherIblocks = $res && $res->Fetch();
        if (!$hasOtherIblocks) {
            $type = new CIBlockType();
            $type->Delete(self::SERVICE_IBLOCK_TYPE);
        }
    }

    private function ensureServiceIblockUf(int $iblockId): void
    {
        if ($iblockId <= 0) {
            return;
        }

        if ($this->loadFieldManagerClass()) {
            \Abcwww\Markers\FieldManager::registerColorUserTypeRuntime();
            $report = \Abcwww\Markers\FieldManager::ensureForIblock($iblockId);
            if (!empty($report['errors'])) {
                CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ABCWWW_MARKERS_INSTALL_UF_ERROR',
                    'MODULE_ID' => $this->MODULE_ID,
                    'DESCRIPTION' => 'UF ensure errors: ' . implode('; ', $report['errors']),
                ]);
            }
            return;
        }

        CEventLog::Add([
            'SEVERITY' => 'WARNING',
            'AUDIT_TYPE_ID' => 'ABCWWW_MARKERS_INSTALL_UF_WARN',
            'MODULE_ID' => $this->MODULE_ID,
            'DESCRIPTION' => 'Unable to load Abcwww\\Markers\\FieldManager, using fallback installer for UF fields',
        ]);
        $this->ensureServiceIblockUfFallback($iblockId);
    }

    private function removeServiceIblockUf(int $iblockId): void
    {
        if ($iblockId <= 0) {
            return;
        }

        if (!$this->loadFieldManagerClass()) {
            return;
        }

        \Abcwww\Markers\FieldManager::removeForIblock($iblockId);
    }

    private function ensureServiceIblockProperties(int $iblockId): void
    {
        if ($iblockId <= 0 || !class_exists('\CIBlockProperty')) {
            return;
        }

        $this->ensureTextProperty($iblockId, 'ABCWWW_MARKER_X', 'Координата X');
        $this->ensureTextProperty($iblockId, 'ABCWWW_MARKER_Y', 'Координата Y');
        $this->ensureTextProperty($iblockId, 'ABCWWW_BUTTON_TEXT', 'Текст кнопки');
    }

    private function ensureTextProperty(int $iblockId, string $code, string $name): void
    {
        $res = \CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'ID' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'CODE' => $code]
        );
        if ($res && $res->Fetch()) {
            return;
        }

        $property = new \CIBlockProperty();
        $ok = $property->Add([
            'IBLOCK_ID' => $iblockId,
            'NAME' => $name,
            'ACTIVE' => 'Y',
            'SORT' => '500',
            'CODE' => $code,
            'PROPERTY_TYPE' => 'S',
            'MULTIPLE' => 'N',
            'IS_REQUIRED' => 'N',
        ]);

        if (!$ok) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ABCWWW_MARKERS_INSTALL_PROPERTY_ERROR',
                'MODULE_ID' => $this->MODULE_ID,
                'DESCRIPTION' => 'Unable to create property ' . $code . ': ' . (string)$property->LAST_ERROR,
            ]);
        }
    }

    private function findServiceIblockId(): int
    {
        $iblockRes = CIBlock::GetList([], [
            'TYPE' => self::SERVICE_IBLOCK_TYPE,
            '=CODE' => self::SERVICE_IBLOCK_CODE,
            'CHECK_PERMISSIONS' => 'N',
        ]);
        $row = $iblockRes ? $iblockRes->Fetch() : false;
        return is_array($row) ? (int)($row['ID'] ?? 0) : 0;
    }

    private function ensureServiceIblockUfFallback(int $iblockId): void
    {
        if ($iblockId <= 0 || !class_exists('\CUserTypeEntity')) {
            return;
        }

        // Fallback без ColorUserType — только штатные типы Битрикса.
        $sectionEntity = 'IBLOCK_' . $iblockId . '_SECTION';
        $elementEntity = 'IBLOCK_' . $iblockId . '_ELEMENT';

        $this->ensureUfField($sectionEntity, 'UF_MARKER_IMAGE', 'file', 'Background image');
        $this->ensureUfField($sectionEntity, 'UF_MARKER_MAIN_COLOR', 'string', 'Main color');
        $this->ensureUfField($sectionEntity, 'UF_MARKER_HOVER_COLOR', 'string', 'Hover color');
        $this->ensureUfField($sectionEntity, 'UF_MARKER_ICON', 'file', 'Marker icon (SVG)');
        $this->ensureUfField($sectionEntity, 'UF_MARKER_ICON_PC', 'integer', 'Desktop icon size');
        $this->ensureUfField($sectionEntity, 'UF_MARKER_ICON_MB', 'integer', 'Mobile icon size');

        $this->ensureUfField($elementEntity, 'UF_MARKER_X', 'double', 'Coordinate X, %');
        $this->ensureUfField($elementEntity, 'UF_MARKER_Y', 'double', 'Coordinate Y, %');
        $this->ensureUfField($elementEntity, 'UF_MARKER_COLOR', 'string', 'Marker color');
        $this->ensureUfField($elementEntity, 'UF_MARKER_OPENED', 'boolean', 'Open by default');
        $this->ensureUfField($elementEntity, 'UF_MARKER_BTN_COLOR', 'string', 'Product button color');
    }

    private function ensureUfField(string $entityId, string $fieldName, string $userType, string $label): void
    {
        $res = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId, 'FIELD_NAME' => $fieldName]);
        if ($res && $res->Fetch()) {
            return;
        }

        $fields = [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => $userType,
            'XML_ID' => $fieldName,
            'SORT' => 500,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => $this->buildUfSettingsByType($userType),
            'EDIT_FORM_LABEL' => ['ru' => $label, 'en' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label, 'en' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label, 'en' => $label],
        ];

        $ob = new \CUserTypeEntity();
        $fieldId = (int)$ob->Add($fields);
        if ($fieldId <= 0) {
            CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ABCWWW_MARKERS_INSTALL_UF_ERROR',
                'MODULE_ID' => $this->MODULE_ID,
                'DESCRIPTION' => 'Unable to create UF field ' . $fieldName . ' for ' . $entityId . ': ' . (string)$ob->LAST_ERROR,
            ]);
        }
    }

    private function buildUfSettingsByType(string $userType): array
    {
        if ($userType === 'boolean') {
            return ['DEFAULT_VALUE' => 0, 'DISPLAY' => 'CHECKBOX'];
        }
        if ($userType === 'integer') {
            return ['DEFAULT_VALUE' => 0, 'MIN_VALUE' => 0];
        }
        if ($userType === 'double') {
            return ['DEFAULT_VALUE' => 0, 'PRECISION' => 2];
        }
        if ($userType === 'file') {
            return ['SIZE' => 20, 'LIST_WIDTH' => 0, 'LIST_HEIGHT' => 0, 'MAX_SHOW_SIZE' => 0, 'MAX_ALLOWED_SIZE' => 0, 'EXTENSIONS' => 'jpg,jpeg,png,webp,svg'];
        }
        if ($userType === 'string') {
            return [
                'DEFAULT_VALUE' => '',
                'SIZE' => 20,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 0,
                'REGEXP' => '',
            ];
        }
        return [];
    }

    private function loadFieldManagerClass(): bool
    {
        if (class_exists('\Abcwww\Markers\FieldManager')) {
            return true;
        }

        Loader::includeModule($this->MODULE_ID);
        if (class_exists('\Abcwww\Markers\FieldManager')) {
            return true;
        }

        $moduleRoot = dirname(__DIR__);
        $includeFile = $moduleRoot . '/include.php';
        if (is_file($includeFile)) {
            require_once $includeFile;
        }
        if (class_exists('\Abcwww\Markers\FieldManager')) {
            return true;
        }

        $fieldManagerFile = $moduleRoot . '/lib/Application/fieldmanager.php';
        if (is_file($fieldManagerFile)) {
            require_once $fieldManagerFile;
        }

        return class_exists('\Abcwww\Markers\FieldManager');
    }
}
