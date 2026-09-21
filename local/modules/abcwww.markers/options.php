<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Abcwww\Markers\FieldManager;
use Abcwww\Markers\Options;

if (!$USER->IsAdmin()) {
    return;
}

Loc::loadMessages(__FILE__);

$defaults = Options::defaults();
$moduleId = Options::MODULE_ID;
$ensureReport = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $iblockId = max(0, (int)($_POST[Options::IBLOCK_ID] ?? 0));
    Option::set($moduleId, Options::IBLOCK_ID, (string)$iblockId);
    Option::set($moduleId, Options::ENABLE_SNIPPET_AUTO_INIT, isset($_POST[Options::ENABLE_SNIPPET_AUTO_INIT]) ? 'Y' : 'N');

    if (isset($_POST['ensure_fields']) && $iblockId > 0) {
        $ensureReport = FieldManager::ensureForIblock($iblockId);
    }
}

$values = [];
foreach ($defaults as $k => $v) {
    $values[$k] = Option::get($moduleId, $k, $v);
}

$aTabs = [
    [
        'DIV'   => 'edit1',
        'TAB'   => 'Настройки модуля',
        'TITLE' => 'Настройки'
    ]
];

$tabControl = new CAdminTabControl(
    'tabControl',
    $aTabs
);

$tabControl->Begin();

if (is_array($ensureReport)) {
    $created = !empty($ensureReport['created']) ? implode(', ', $ensureReport['created']) : '—';
    $updated = !empty($ensureReport['updated']) ? implode(', ', $ensureReport['updated']) : '—';
    $errors = !empty($ensureReport['errors']) ? implode('; ', $ensureReport['errors']) : '';
    if ($errors !== '') {
        CAdminMessage::ShowMessage([
            'MESSAGE' => 'UF-поля обновлены с ошибками',
            'DETAILS' => 'Созданы: ' . htmlspecialcharsbx($created) . '<br>Обновлены: ' . htmlspecialcharsbx($updated) . '<br>Ошибки: ' . htmlspecialcharsbx($errors),
            'TYPE' => 'ERROR',
            'HTML' => true,
        ]);
    } else {
        CAdminMessage::ShowNote('UF-поля созданы/обновлены. Созданы: ' . $created . '. Обновлены: ' . $updated . '.');
    }
}

?>
<form method="post" action="<?= $APPLICATION->GetCurPage()?>?mid=<?= htmlspecialcharsbx($mid) ?>&lang=<?= LANGUAGE_ID ?>">
    <?
    $tabControl->BeginNextTab();
    ?>
    <?= bitrix_sessid_post() ?>
    
        <tr class="heading">
            <td colspan="2">Общие</td>
        </tr>
        <tr>
            <td valign="top" width="50%" class="adm-detail-content-cell-l">
                <label for="ABCWWW_MARKERS_IBLOCK_ID"><b><?= Loc::getMessage('ABCWWW_MARKERS_OPT_IBLOCK') ?></b></label>
            </td>
            <td valign="top" nowrap width="50%" class="adm-detail-content-cell-r">
                <input type="number" min="0" id="ABCWWW_MARKERS_IBLOCK_ID" name="<?= Options::IBLOCK_ID ?>" value="<?= htmlspecialcharsbx($values[Options::IBLOCK_ID]) ?>">
            </td>
        </tr>
        <tr>
            <td valign="top" width="50%" class="adm-detail-content-cell-l">
                <label for="ABCWWW_MARKERS_SNIPPET_INIT"><?= Loc::getMessage('ABCWWW_MARKERS_OPT_SNIPPET_INIT') ?></label>
            </td>
            <td valign="top" nowrap width="50%" class="adm-detail-content-cell-r">
                <input type="checkbox" class="adm-designed-checkbox" id="ABCWWW_MARKERS_SNIPPET_INIT" name="<?= Options::ENABLE_SNIPPET_AUTO_INIT ?>" value="Y" <?= $values[Options::ENABLE_SNIPPET_AUTO_INIT] === 'Y' ? 'checked' : '' ?>>
                <label class="adm-designed-checkbox-label" for="ABCWWW_MARKERS_SNIPPET_INIT" title=""></label>
            </td>
        </tr>
        <tr>
            <td valign="top" width="50%" class="adm-detail-content-cell-l">
                <?= Loc::getMessage('ABCWWW_MARKERS_OPT_FIELDS') ?>
            </td>
            <td valign="top" nowrap width="50%" class="adm-detail-content-cell-r">
                <button class="adm-btn" type="submit" name="ensure_fields" value="Y"><?= Loc::getMessage('ABCWWW_MARKERS_OPT_FIELDS_BTN') ?></button>
            </td>
        </tr>
        
    <?
    $tabControl->Buttons();
    ?>
   
    <input type="submit" class="adm-btn-save" value="<?= Loc::getMessage('MAIN_SAVE') ?>">
    <?
    $tabControl->End();
    ?>
</form>
<style>
    input[type="number"] {
        background: #fff;
        border: 1px solid;
        border-color: #87919c #959ea9 #9ea7b1 #959ea9;
        border-radius: 4px;
        color: #000;
        box-shadow: 0 1px 0 0 rgba(255, 255, 255, 0.3), inset 0 2px 2px -1px rgba(180, 188, 191, 0.7);
        display: inline-block;
        min-width: 240px;
        outline: none;
        vertical-align: middle;
        -webkit-font-smoothing: antialiased;
        font-size: 13px;
        height: 25px;
        padding: 0 5px;
        margin: 0;
    }
</style>