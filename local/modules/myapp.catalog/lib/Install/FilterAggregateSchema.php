<?php

namespace MyApp\Catalog\Install;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

final class FilterAggregateSchema
{
    private const MODULE_ID = 'myapp.catalog';

    private const OPT_TABLE_READY = 'filter_aggregate_table_ready';

    private const OPT_INDEXES_READY = 'filter_performance_indexes_ready';

    public static function ensureTable(): void
    {
        if (Option::get(self::MODULE_ID, self::OPT_TABLE_READY, 'N') === 'Y') {
            return;
        }

        $connection = Application::getConnection();
        $tableName = self::tableName();
        if (!$connection->isTableExists($tableName)) {
            $connection->queryExecute("
                CREATE TABLE {$tableName} (
                    SECTION_ID INT NOT NULL,
                    CONFIG_HASH VARCHAR(32) NOT NULL,
                    PAYLOAD_JSON LONGTEXT NOT NULL,
                    IS_STALE CHAR(1) NOT NULL DEFAULT 'N',
                    UPDATED_AT DATETIME NOT NULL,
                    PRIMARY KEY (SECTION_ID),
                    INDEX ix_myapp_filter_agg_stale (IS_STALE)
                )
            ");
        }

        Option::set(self::MODULE_ID, self::OPT_TABLE_READY, 'Y');
    }

    public static function ensurePerformanceIndexes(): void
    {
        if (Option::get(self::MODULE_ID, self::OPT_INDEXES_READY, 'N') === 'Y') {
            return;
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();

        self::createIndexIfMissing(
            $connection,
            'b_iblock_section_element',
            'ix_myapp_cat_sect_elem',
            'IBLOCK_SECTION_ID, IBLOCK_ELEMENT_ID'
        );
        self::createIndexIfMissing(
            $connection,
            'b_iblock_element_property',
            'ix_myapp_cat_elem_prop',
            'IBLOCK_PROPERTY_ID, IBLOCK_ELEMENT_ID, VALUE_ENUM'
        );
        self::createIndexIfMissing(
            $connection,
            'b_catalog_price',
            'ix_myapp_cat_price_group_product',
            'CATALOG_GROUP_ID, PRODUCT_ID'
        );

        Option::set(self::MODULE_ID, self::OPT_INDEXES_READY, 'Y');
    }

    public static function tableName(): string
    {
        return 'myapp_catalog_filter_aggregate';
    }

    public static function resetInstallFlags(): void
    {
        Option::delete(self::MODULE_ID, ['name' => self::OPT_TABLE_READY]);
        Option::delete(self::MODULE_ID, ['name' => self::OPT_INDEXES_READY]);
    }

    private static function createIndexIfMissing(
        \Bitrix\Main\DB\Connection $connection,
        string $table,
        string $indexName,
        string $columns
    ): void {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: $table;
        $indexName = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName) ?: $indexName;

        $exists = $connection->query("
            SHOW INDEX FROM `{$table}` WHERE Key_name = '{$indexName}'
        ")->fetch();

        if ($exists) {
            return;
        }

        try {
            $connection->queryExecute("CREATE INDEX {$indexName} ON {$table} ({$columns})");
        } catch (\Throwable $e) {
            // Индекс мог быть создан параллельно или имя колонок отличается на кастомной БД.
        }
    }
}
