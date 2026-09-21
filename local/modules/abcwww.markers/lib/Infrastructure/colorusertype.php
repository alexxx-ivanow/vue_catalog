<?php

namespace Abcwww\Markers;

/**
 * UF-тип "Color picker" на базе строки (#RRGGBB).
 * Обязателен GetDBColumnType — без него CUserTypeEntity::Add падает с
 * "Ошибка добавления UF_... для объекта ...".
 */
class ColorUserType
{
    public const USER_TYPE_ID = 'abcwww_color';
    private const DEFAULT_COLOR = '#1E88E5';

    public static function GetUserTypeDescription(): array
    {
        $baseType = 'string';
        if (class_exists('\CUserTypeManager') && defined('CUserTypeManager::BASE_TYPE_STRING')) {
            $baseType = \CUserTypeManager::BASE_TYPE_STRING;
        }

        return [
            'USER_TYPE_ID' => self::USER_TYPE_ID,
            'CLASS_NAME' => __CLASS__,
            'DESCRIPTION' => 'Color picker',
            'BASE_TYPE' => $baseType,
        ];
    }

    /**
     * @param array $userField
     */
    public static function GetDBColumnType($userField): string
    {
        global $DB;
        $type = is_object($DB) ? strtolower((string)$DB->type) : 'mysql';

        switch ($type) {
            case 'oracle':
                return 'varchar2(50 char)';
            case 'mssql':
                return 'varchar(50)';
            default:
                return 'varchar(50)';
        }
    }

    /**
     * @param array $userField
     */
    public static function PrepareSettings($userField): array
    {
        $default = self::normalizeColor((string)($userField['SETTINGS']['DEFAULT_VALUE'] ?? ''));
        return [
            'DEFAULT_VALUE' => $default !== '' ? $default : self::DEFAULT_COLOR,
            'SIZE' => 10,
            'ROWS' => 1,
            'MIN_LENGTH' => 0,
            'MAX_LENGTH' => 7,
            'REGEXP' => '',
        ];
    }

    /**
     * @param bool|array $arHtmlControl
     */
    public static function GetSettingsHTML($userField = false, $arHtmlControl = [], $bVarsFromForm = false): string
    {
        if ($bVarsFromForm) {
            $value = self::normalizeColor((string)($GLOBALS[$arHtmlControl['NAME']]['DEFAULT_VALUE'] ?? ''));
        } elseif (is_array($userField)) {
            $value = self::normalizeColor((string)($userField['SETTINGS']['DEFAULT_VALUE'] ?? ''));
        } else {
            $value = self::DEFAULT_COLOR;
        }

        if ($value === '') {
            $value = self::DEFAULT_COLOR;
        }

        $name = htmlspecialcharsbx((string)($arHtmlControl['NAME'] ?? 'SETTINGS') . '[DEFAULT_VALUE]');

        return
            '<tr><td>Значение по умолчанию:</td><td>' .
            '<input type="color" value="' . htmlspecialcharsbx($value) . '" ' .
            'oninput="this.nextElementSibling.value=this.value;"> ' .
            '<input type="text" name="' . $name . '" value="' . htmlspecialcharsbx($value) . '" size="10" maxlength="7">' .
            '</td></tr>';
    }

    /**
     * @param array $userField
     * @param array $htmlControl
     */
    public static function GetEditFormHTML($userField, $htmlControl): string
    {
        $value = self::normalizeColor((string)($htmlControl['VALUE'] ?? ''));
        if ($value === '') {
            $value = self::normalizeColor((string)($userField['SETTINGS']['DEFAULT_VALUE'] ?? self::DEFAULT_COLOR));
        }
        if ($value === '') {
            $value = self::DEFAULT_COLOR;
        }

        $inputName = (string)($htmlControl['NAME'] ?? '');
        $inputId = preg_replace('/[^a-z0-9_:-]+/i', '_', $inputName);
        $inputId = $inputId !== '' ? $inputId : 'abcwww_color_' . mt_rand(1000, 9999);

        return
            '<span style="display:inline-flex;align-items:center;gap:8px;">' .
                '<input type="color" value="' . htmlspecialcharsbx($value) . '" ' .
                    'oninput="document.getElementById(\'' . htmlspecialcharsbx($inputId) . '\').value=this.value;">' .
                '<input type="text" id="' . htmlspecialcharsbx($inputId) . '" name="' . htmlspecialcharsbx($inputName) . '" ' .
                    'value="' . htmlspecialcharsbx($value) . '" size="10" maxlength="7" ' .
                    'oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)){this.previousElementSibling.value=this.value;}">' .
            '</span>';
    }

    /**
     * @param array $userField
     * @param array $htmlControl
     */
    public static function GetAdminListViewHTML($userField, $htmlControl): string
    {
        $value = self::normalizeColor((string)($htmlControl['VALUE'] ?? ''));
        if ($value === '') {
            return '&nbsp;';
        }

        return
            '<span style="display:inline-block;width:12px;height:12px;border:1px solid #c5cdd3;vertical-align:middle;margin-right:6px;background:' .
            htmlspecialcharsbx($value) .
            ';"></span>' .
            htmlspecialcharsbx($value);
    }

    /**
     * @param array $userField
     * @param array $htmlControl
     */
    public static function GetAdminListEditHTML($userField, $htmlControl): string
    {
        return self::GetEditFormHTML($userField, $htmlControl);
    }

    /**
     * @param array $userField
     * @param array $htmlControl
     */
    public static function GetFilterHTML($userField, $htmlControl): string
    {
        $value = htmlspecialcharsbx((string)($htmlControl['VALUE'] ?? ''));
        $name = htmlspecialcharsbx((string)($htmlControl['NAME'] ?? ''));
        return '<input type="text" name="' . $name . '" value="' . $value . '" size="10">';
    }

    /**
     * @param array $userField
     * @param mixed $value
     */
    public static function CheckFields($userField, $value): array
    {
        $errors = [];
        $raw = trim((string)$value);
        if ($raw === '') {
            return $errors;
        }

        if (self::normalizeColor($raw) === '') {
            $label = (string)($userField['EDIT_FORM_LABEL'] ?? $userField['FIELD_NAME'] ?? 'color');
            $errors[] = [
                'id' => (string)($userField['FIELD_NAME'] ?? 'COLOR'),
                'text' => 'Некорректный цвет в поле "' . $label . '". Ожидается #RRGGBB.',
            ];
        }

        return $errors;
    }

    /**
     * @param array $userField
     * @param mixed $value
     */
    public static function OnBeforeSave($userField, $value): string
    {
        return self::normalizeColor((string)$value);
    }

    private static function normalizeColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $m)) {
            return '';
        }

        $hex = strtoupper($m[1]);
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . $hex;
    }
}
