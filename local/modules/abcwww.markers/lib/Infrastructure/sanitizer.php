<?php

namespace Abcwww\Markers;

class Sanitizer
{
    public static function color(?string $value, string $fallback): string
    {
        $normalized = self::normalizeColor($value);
        return $normalized !== '' ? $normalized : $fallback;
    }

    /** Возвращает нормализованный цвет или пустую строку (без fallback). */
    public static function normalizeColor(?string $value): string
    {
        $value = trim((string)$value);
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

    public static function html(?string $html): string
    {
        $html = (string)$html;
        if ($html === '') {
            return '';
        }

        if (class_exists('\CBXSanitizer')) {
            $sanitizer = new \CBXSanitizer();
            $sanitizer->SetLevel(\CBXSanitizer::SECURE_LEVEL_LOW);
            return (string)$sanitizer->SanitizeHtml($html);
        }

        return strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><img><a><span><div><h1><h2><h3><h4><h5><h6>');
    }

    public static function percent(mixed $value): float
    {
        $num = round((float)$value, 2);
        return max(0, min(100, $num));
    }
}
