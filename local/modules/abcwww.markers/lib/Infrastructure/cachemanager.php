<?php

namespace Abcwww\Markers;

use Bitrix\Main\Data\Cache;

class CacheManager
{
    public static function onElementChanged(array $fields = []): void
    {
        self::clear();
    }

    public static function onSectionChanged(array $fields = []): void
    {
        self::clear();
    }

    public static function clear(): void
    {
        Cache::clearCache(true, '/abcwww_markers/');
        if (class_exists('\CBitrixComponent')) {
            \CBitrixComponent::clearComponentCache('abcwww:markers');
        }
    }
}
