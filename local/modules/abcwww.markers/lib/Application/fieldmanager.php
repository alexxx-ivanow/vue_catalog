<?php

namespace Abcwww\Markers;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class FieldManager
{
    private static $colorUserTypeRuntimeRegistered = false;

    private const SECTION_FIELDS = [
        'UF_MARKER_IMAGE',
        'UF_MARKER_MAIN_COLOR',
        'UF_MARKER_HOVER_COLOR',
        'UF_MARKER_ICON',
        'UF_MARKER_ICON_PC',
        'UF_MARKER_ICON_MB',
    ];

    private const OBSOLETE_SECTION_FIELDS = [
        'UF_MARKER_ACTIVE_COLOR',
        'UF_MARKER_MAIN_COLOR_DATE',
    ];

    private const ELEMENT_FIELDS = [
        'UF_MARKER_X',
        'UF_MARKER_Y',
        'UF_MARKER_COLOR',
        'UF_MARKER_OPENED',
        'UF_MARKER_BTN_COLOR',
    ];

    private const OBSOLETE_ELEMENT_FIELDS = [
        'UF_MARKER_MODAL_ONLY',
        'UF_MARKER_HIDE_MORE',
        'UF_MARKER_URL',
        'UF_MARKER_BTN_TEXT',
        'UF_MARKER_HOVER_COLOR',
    ];

    /**
     * @return array{created:string[],updated:string[],errors:string[]}
     */
    public static function ensureForIblock(int $iblockId): array
    {
        $report = ['created' => [], 'updated' => [], 'errors' => []];

        if ($iblockId <= 0 || !class_exists('\CUserTypeEntity')) {
            $report['errors'][] = 'Invalid iblock or CUserTypeEntity unavailable';
            return $report;
        }

        self::registerColorUserTypeRuntime();

        $sectionEntity = 'IBLOCK_' . $iblockId . '_SECTION';
        $elementEntity = 'IBLOCK_' . $iblockId . '_ELEMENT';

        // Сначала создаём/обновляем нужные поля, и только потом чистим obsolete.
        // Иначе при ошибке создания раздел остаётся без UF.
        self::mergeReport($report, self::ensureField($sectionEntity, 'UF_MARKER_IMAGE', 'file', self::msg('ABCWWW_MARKERS_UF_SECTION_IMAGE', 'Background image')));
        self::mergeReport($report, self::ensureField($sectionEntity, 'UF_MARKER_MAIN_COLOR', ColorUserType::USER_TYPE_ID, self::msg('ABCWWW_MARKERS_UF_SECTION_COLOR', 'Main color')));
        self::mergeReport($report, self::ensureField($sectionEntity, 'UF_MARKER_HOVER_COLOR', ColorUserType::USER_TYPE_ID, self::msg('ABCWWW_MARKERS_UF_SECTION_HOVER_COLOR', 'Hover color')));
        self::mergeReport($report, self::ensureField($sectionEntity, 'UF_MARKER_ICON', 'file', self::msg('ABCWWW_MARKERS_UF_SECTION_ICON', 'Marker icon (SVG)')));
        self::mergeReport($report, self::ensureField($sectionEntity, 'UF_MARKER_ICON_PC', 'integer', self::msg('ABCWWW_MARKERS_UF_SECTION_ICON_PC', 'Desktop icon size')));
        self::mergeReport($report, self::ensureField($sectionEntity, 'UF_MARKER_ICON_MB', 'integer', self::msg('ABCWWW_MARKERS_UF_SECTION_ICON_MB', 'Mobile icon size')));

        self::mergeReport($report, self::ensureField($elementEntity, 'UF_MARKER_X', 'double', self::msg('ABCWWW_MARKERS_UF_ELEMENT_X', 'Coordinate X, %')));
        self::mergeReport($report, self::ensureField($elementEntity, 'UF_MARKER_Y', 'double', self::msg('ABCWWW_MARKERS_UF_ELEMENT_Y', 'Coordinate Y, %')));
        self::mergeReport($report, self::ensureField($elementEntity, 'UF_MARKER_COLOR', 'string', self::msg('ABCWWW_MARKERS_UF_ELEMENT_COLOR', 'Marker color')));
        self::mergeReport($report, self::ensureField($elementEntity, 'UF_MARKER_OPENED', 'boolean', self::msg('ABCWWW_MARKERS_UF_ELEMENT_OPENED', 'Open by default')));
        self::mergeReport($report, self::ensureField($elementEntity, 'UF_MARKER_BTN_COLOR', 'string', self::msg('ABCWWW_MARKERS_UF_ELEMENT_BTN_COLOR', 'Product button color')));

        self::removeFieldsByNames($sectionEntity, self::OBSOLETE_SECTION_FIELDS);
        self::removeFieldsByNames($elementEntity, self::OBSOLETE_ELEMENT_FIELDS);

        return $report;
    }

    public static function removeForIblock(int $iblockId): void
    {
        if ($iblockId <= 0 || !class_exists('\CUserTypeEntity')) {
            return;
        }

        $sectionEntity = 'IBLOCK_' . $iblockId . '_SECTION';
        $elementEntity = 'IBLOCK_' . $iblockId . '_ELEMENT';

        self::removeFieldsByNames($sectionEntity, self::SECTION_FIELDS);
        self::removeFieldsByNames($elementEntity, self::ELEMENT_FIELDS);
        self::removeFieldsByNames($sectionEntity, self::OBSOLETE_SECTION_FIELDS);
        self::removeFieldsByNames($elementEntity, self::OBSOLETE_ELEMENT_FIELDS);
    }

    /**
     * @return array{created:string[],updated:string[],errors:string[]}
     */
    private static function ensureField(string $entityId, string $fieldName, string $userType, string $label): array
    {
        $report = ['created' => [], 'updated' => [], 'errors' => []];
        $effectiveUserType = self::resolveUserType($userType);

        $existing = self::findField($entityId, $fieldName);
        if ($existing) {
            $existingUserType = (string)($existing['USER_TYPE_ID'] ?? '');
            if ($existingUserType === $effectiveUserType) {
                return $report;
            }

            // CUserTypeEntity::Update не умеет надёжно менять USER_TYPE_ID.
            // Пересоздаём поле (для цвета значения — hex-строки, потеря минимальна).
            $id = (int)($existing['ID'] ?? 0);
            if ($id > 0) {
                $ob = new \CUserTypeEntity();
                if (!$ob->Delete($id)) {
                    $report['errors'][] = $fieldName . ': cannot delete old type ' . $existingUserType;
                    return $report;
                }
            }
        }

        $fields = [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => $effectiveUserType,
            'XML_ID' => $fieldName,
            'SORT' => 500,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => self::buildSettingsByType($effectiveUserType),
            'EDIT_FORM_LABEL' => ['ru' => $label, 'en' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label, 'en' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label, 'en' => $label],
        ];

        $ob = new \CUserTypeEntity();
        $fieldId = (int)$ob->Add($fields);
        if ($fieldId <= 0) {
            $error = trim((string)$ob->LAST_ERROR);
            if ($error === '') {
                $error = 'Add failed';
            }

            // Если кастомный тип всё ещё недоступен/ломан — пробуем string, чтобы UF не пропали.
            if ($effectiveUserType === ColorUserType::USER_TYPE_ID) {
                $fields['USER_TYPE_ID'] = 'string';
                $fields['SETTINGS'] = self::buildSettingsByType('string');
                $fallbackId = (int)$ob->Add($fields);
                if ($fallbackId > 0) {
                    $report['created'][] = $fieldName . ' (string fallback)';
                    $report['errors'][] = $fieldName . ': abcwww_color failed (' . $error . '), created as string';
                    return $report;
                }
            }

            $report['errors'][] = $fieldName . ': ' . $error;
            self::logError('Unable to create UF field ' . $fieldName . ' for ' . $entityId . ': ' . $error);
            return $report;
        }

        if ($existing) {
            $report['updated'][] = $fieldName;
        } else {
            $report['created'][] = $fieldName;
        }

        return $report;
    }

    private static function findField(string $entityId, string $fieldName): ?array
    {
        $res = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $fieldName,
        ]);
        $row = $res ? $res->Fetch() : false;
        return is_array($row) ? $row : null;
    }

    private static function resolveUserType(string $requestedType): string
    {
        if ($requestedType !== ColorUserType::USER_TYPE_ID) {
            return $requestedType;
        }

        return self::isUserTypeAvailable($requestedType) ? $requestedType : 'string';
    }

    public static function registerColorUserTypeRuntime(): void
    {
        if (!self::$colorUserTypeRuntimeRegistered) {
            self::$colorUserTypeRuntimeRegistered = true;

            if (function_exists('AddEventHandlerCompatible')) {
                AddEventHandlerCompatible(
                    'main',
                    'OnUserTypeBuildList',
                    ['Abcwww\\Markers\\ColorUserType', 'GetUserTypeDescription']
                );
            } elseif (class_exists('\Bitrix\Main\EventManager')) {
                \Bitrix\Main\EventManager::getInstance()->addEventHandler(
                    'main',
                    'OnUserTypeBuildList',
                    ['Abcwww\\Markers\\ColorUserType', 'GetUserTypeDescription']
                );
            }
        }

        self::resetUserTypeCache();
    }

    private static function resetUserTypeCache(): void
    {
        global $USER_FIELD_MANAGER;
        if (!is_object($USER_FIELD_MANAGER)) {
            return;
        }

        // CUserTypeManager кэширует типы после первого GetUserType().
        // Без сброса новый обработчик в текущем запросе не увидят.
        try {
            $ref = new \ReflectionObject($USER_FIELD_MANAGER);
            if ($ref->hasProperty('arUserTypes')) {
                $prop = $ref->getProperty('arUserTypes');
                $prop->setAccessible(true);
                $prop->setValue($USER_FIELD_MANAGER, null);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private static function isUserTypeAvailable(string $userType): bool
    {
        global $USER_FIELD_MANAGER;
        if (!is_object($USER_FIELD_MANAGER) || !method_exists($USER_FIELD_MANAGER, 'GetUserType')) {
            return false;
        }

        $description = $USER_FIELD_MANAGER->GetUserType($userType);
        return is_array($description) && !empty($description['CLASS_NAME']);
    }

    private static function buildSettingsByType(string $userType): array
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
            return [
                'SIZE' => 20,
                'LIST_WIDTH' => 0,
                'LIST_HEIGHT' => 0,
                'MAX_SHOW_SIZE' => 0,
                'MAX_ALLOWED_SIZE' => 0,
                'EXTENSIONS' => 'jpg,jpeg,png,webp,svg',
            ];
        }
        if ($userType === ColorUserType::USER_TYPE_ID) {
            return [
                'DEFAULT_VALUE' => '#1E88E5',
                'SIZE' => 10,
                'ROWS' => 1,
                'MIN_LENGTH' => 0,
                'MAX_LENGTH' => 7,
                'REGEXP' => '',
            ];
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

    /**
     * Важно: CUserTypeEntity::GetList НЕ поддерживает фильтр @FIELD_NAME (IN).
     * Старый код с '@FIELD_NAME' => $names мог вернуть ВСЕ UF сущности и удалить их.
     */
    private static function removeFieldsByNames(string $entityId, array $fieldNames): void
    {
        if ($entityId === '' || empty($fieldNames)) {
            return;
        }

        $wanted = [];
        foreach ($fieldNames as $name) {
            $name = (string)$name;
            if ($name !== '') {
                $wanted[$name] = true;
            }
        }
        if (empty($wanted)) {
            return;
        }

        $res = \CUserTypeEntity::GetList([], ['ENTITY_ID' => $entityId]);
        if (!$res) {
            return;
        }

        $ob = new \CUserTypeEntity();
        while ($row = $res->Fetch()) {
            $name = (string)($row['FIELD_NAME'] ?? '');
            if (!isset($wanted[$name])) {
                continue;
            }
            $id = (int)($row['ID'] ?? 0);
            if ($id > 0) {
                $ob->Delete($id);
            }
        }
    }

    private static function mergeReport(array &$target, array $part): void
    {
        foreach (['created', 'updated', 'errors'] as $key) {
            if (!empty($part[$key]) && is_array($part[$key])) {
                $target[$key] = array_merge($target[$key], $part[$key]);
            }
        }
    }

    private static function logError(string $description): void
    {
        if (!class_exists('\CEventLog')) {
            return;
        }

        \CEventLog::Add([
            'SEVERITY' => 'ERROR',
            'AUDIT_TYPE_ID' => 'ABCWWW_MARKERS_UF_ERROR',
            'MODULE_ID' => 'abcwww.markers',
            'DESCRIPTION' => $description,
        ]);
    }

    private static function msg(string $code, string $fallback): string
    {
        $value = Loc::getMessage($code);
        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
