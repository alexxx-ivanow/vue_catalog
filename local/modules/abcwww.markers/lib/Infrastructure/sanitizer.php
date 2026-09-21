<?php

namespace Abcwww\Markers;

class Sanitizer
{
    public static function color(?string $value, string $fallback): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
            return strtoupper($value);
        }

        return $fallback;
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
