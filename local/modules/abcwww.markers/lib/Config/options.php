<?php

namespace Abcwww\Markers;

class Options
{
    public const MODULE_ID = 'abcwww.markers';
    public const IBLOCK_ID = 'IBLOCK_ID';
    public const ENABLE_SNIPPET_AUTO_INIT = 'ENABLE_SNIPPET_AUTO_INIT';
    public const SERVICE_IBLOCK_CREATED = 'SERVICE_IBLOCK_CREATED';
    public const SERVICE_IBLOCK_TYPE_CREATED = 'SERVICE_IBLOCK_TYPE_CREATED';

    public static function defaults(): array
    {
        return [
            self::IBLOCK_ID => '0',
            self::ENABLE_SNIPPET_AUTO_INIT => 'Y',
            self::SERVICE_IBLOCK_CREATED => 'N',
            self::SERVICE_IBLOCK_TYPE_CREATED => 'N',
        ];
    }
}
