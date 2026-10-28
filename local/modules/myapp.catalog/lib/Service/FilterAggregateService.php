<?php

namespace MyApp\Catalog\Service;

use Bitrix\Main\Application;
use MyApp\Catalog\Install\FilterAggregateSchema;

final class FilterAggregateService
{
    /**
     * @return array{payload: array, config_hash: string, is_stale: bool}|null
     */
    public static function read(int $sectionId): ?array
    {
        $sectionId = max(0, $sectionId);
        FilterAggregateSchema::ensureTable();

        $connection = Application::getConnection();
        $table = FilterAggregateSchema::tableName();
        $row = $connection->query("
            SELECT CONFIG_HASH, PAYLOAD_JSON, IS_STALE
            FROM {$table}
            WHERE SECTION_ID = {$sectionId}
            LIMIT 1
        ")->fetch();

        if (!$row) {
            return null;
        }

        $payload = json_decode((string)($row['PAYLOAD_JSON'] ?? ''), true);
        if (!is_array($payload)) {
            return null;
        }

        return [
            'payload' => $payload,
            'config_hash' => (string)($row['CONFIG_HASH'] ?? ''),
            'is_stale' => ((string)($row['IS_STALE'] ?? 'N')) === 'Y',
        ];
    }

    /**
     * @param array{enabled?: bool, properties?: array, price?: array} $payload
     */
    public static function write(int $sectionId, array $payload, string $configHash, bool $isStale = false): void
    {
        $sectionId = max(0, $sectionId);
        FilterAggregateSchema::ensureTable();

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $table = FilterAggregateSchema::tableName();
        $json = (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stale = $isStale ? 'Y' : 'N';
        $now = date('Y-m-d H:i:s');

        $connection->queryExecute("
            INSERT INTO {$table} (SECTION_ID, CONFIG_HASH, PAYLOAD_JSON, IS_STALE, UPDATED_AT)
            VALUES (
                {$sectionId},
                '" . $helper->forSql($configHash) . "',
                '" . $helper->forSql($json) . "',
                '{$stale}',
                '" . $helper->forSql($now) . "'
            )
            ON DUPLICATE KEY UPDATE
                CONFIG_HASH = VALUES(CONFIG_HASH),
                PAYLOAD_JSON = VALUES(PAYLOAD_JSON),
                IS_STALE = VALUES(IS_STALE),
                UPDATED_AT = VALUES(UPDATED_AT)
        ");
    }

    public static function markStaleForSectionPath(int $sectionId): void
    {
        $sectionId = (int)$sectionId;
        if ($sectionId <= 0) {
            return;
        }

        FilterAggregateSchema::ensureTable();

        foreach (CatalogService::getSectionPathIds($sectionId) as $pathSectionId) {
            self::markStale($pathSectionId);
        }
    }

    public static function markStale(int $sectionId): void
    {
        $sectionId = max(0, $sectionId);
        FilterAggregateSchema::ensureTable();

        $connection = Application::getConnection();
        $table = FilterAggregateSchema::tableName();
        $connection->queryExecute("
            UPDATE {$table}
            SET IS_STALE = 'Y'
            WHERE SECTION_ID = {$sectionId}
        ");
    }

    public static function clearAll(): void
    {
        FilterAggregateSchema::ensureTable();

        $connection = Application::getConnection();
        $table = FilterAggregateSchema::tableName();
        $connection->queryExecute("TRUNCATE TABLE {$table}");
    }

    public static function rebuildAndPersist(int $sectionId): void
    {
        $sectionId = max(0, $sectionId);
        $payload = CatalogService::buildFiltersPayload($sectionId);
        self::write($sectionId, $payload, CatalogService::filtersConfigHash(), false);
    }

    public static function scheduleRebuild(int $sectionId): void
    {
        $sectionId = (int)$sectionId;
        if ($sectionId < 0) {
            return;
        }

        try {
            $application = Application::getInstance();
            if (method_exists($application, 'addBackgroundJob')) {
                $application->addBackgroundJob(
                    [self::class, 'rebuildAndPersist'],
                    [$sectionId]
                );

                return;
            }
        } catch (\Throwable $e) {
        }

        self::rebuildAndPersist($sectionId);
    }

    /**
     * Прогрев snapshot (агент / ручной вызов после импорта).
     */
    public static function warmSection(int $sectionId): void
    {
        self::rebuildAndPersist($sectionId);
    }
}
