<?php

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;
use Abcwww\Markers\CacheManager;
use Abcwww\Markers\Repository;

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('abcwww.markers') || !Loader::includeModule('iblock')) {
    echo Json::encode(['success' => false, 'error' => 'Module is not available']);
    die();
}

$action = (string)($_REQUEST['action'] ?? '');

try {
    if ($action === 'getScheme') {
        $iblockId = (int)($_REQUEST['iblock_id'] ?? 0);
        $sectionId = (int)($_REQUEST['section_id'] ?? $_REQUEST['id'] ?? 0);
        $data = Repository::getScheme($iblockId, $sectionId);
        echo Json::encode(['success' => $data !== null, 'data' => $data]);
        die();
    }

    if (!check_bitrix_sessid()) {
        echo Json::encode(['success' => false, 'error' => 'Invalid sessid']);
        die();
    }

    if (!$USER->IsAdmin()) {
        echo Json::encode(['success' => false, 'error' => 'Access denied']);
        die();
    }

    if ($action === 'createMarker') {
        $result = Repository::createMarker(
            (int)($_POST['iblock_id'] ?? 0),
            (int)($_POST['section_id'] ?? 0),
            (string)($_POST['name'] ?? ''),
            (float)($_POST['x'] ?? 0),
            (float)($_POST['y'] ?? 0)
        );
        CacheManager::clear();
        echo Json::encode($result);
        die();
    }

    if ($action === 'updateMarker') {
        $updateFields = [];
        if (array_key_exists('x', $_POST)) {
            $updateFields['UF_MARKER_X'] = (float)$_POST['x'];
        }
        if (array_key_exists('y', $_POST)) {
            $updateFields['UF_MARKER_Y'] = (float)$_POST['y'];
        }
        if (array_key_exists('name', $_POST)) {
            $updateFields['NAME'] = (string)$_POST['name'];
        }
        if (array_key_exists('preview_text', $_POST)) {
            $updateFields['PREVIEW_TEXT'] = (string)$_POST['preview_text'];
        }
        $result = Repository::updateMarker((int)($_POST['id'] ?? 0), $updateFields);
        CacheManager::clear();
        echo Json::encode($result);
        die();
    }

    if ($action === 'getMarker') {
        $marker = Repository::getMarkerForEditor((int)($_REQUEST['id'] ?? 0));
        echo Json::encode(['success' => $marker !== null, 'data' => $marker]);
        die();
    }

    if ($action === 'deleteMarker') {
        $result = Repository::deleteMarker((int)($_POST['id'] ?? 0));
        CacheManager::clear();
        echo Json::encode($result);
        die();
    }

    echo Json::encode(['success' => false, 'error' => 'Unknown action']);
} catch (\Throwable $e) {
    \CEventLog::Add([
        'SEVERITY' => 'ERROR',
        'AUDIT_TYPE_ID' => 'ABCWWW_MARKERS_AJAX_ERROR',
        'MODULE_ID' => 'abcwww.markers',
        'DESCRIPTION' => $e->getMessage(),
    ]);
    echo Json::encode(['success' => false, 'error' => $e->getMessage()]);
}
