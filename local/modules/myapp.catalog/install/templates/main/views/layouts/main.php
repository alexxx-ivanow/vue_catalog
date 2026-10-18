<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$pageTitle = $pageTitle ?? 'Каталог';
$pageType = $pageType ?? 'catalog';
$sectionId = (int)($sectionId ?? 0);
$contentView = $contentView ?? '';
$data_sections = is_array($data_sections ?? null) ? $data_sections : [];
$data_section = is_array($data_section ?? null) ? $data_section : [];
$data_product = is_array($data_product ?? null) ? $data_product : [];
$data_order = is_array($data_order ?? null) ? $data_order : [];
$sectionPagination = is_array($sectionPagination ?? null) ? $sectionPagination : [];
$sectionFilter = is_array($sectionFilter ?? null) ? $sectionFilter : [];
$jsBase = rtrim((string)($jsBase ?? '/local/modules/myapp.catalog/js/catalog/'), '/') . '/';
$vueModuleUrl = (string)($vueModuleUrl ?? '/local/modules/myapp.catalog/js/vendor/vue.esm-browser.js');
$useBitrixVue = !empty($useBitrixVue);
$bitrixAssetJs = (string)($bitrixAssetJs ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">

    <link rel="stylesheet" href="/local/templates/assets/css/style.css">

    <title><?= htmlspecialcharsbx($pageTitle) ?></title>

    <?php if ($useBitrixVue && $bitrixAssetJs !== ''): ?>
        <?= $bitrixAssetJs ?>
    <?php endif; ?>

    <!-- Import Map: позволяет писать import 'vue' без npm -->
    <script type="importmap">
    {
        "imports": {
            "vue": <?= json_encode($vueModuleUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
        }
    }
    </script>
</head>
<body>

<header>
    <div class="top_menu">
        <div><a href="/"> Главная</a></div>
        <div><a href="/catalog/"> Каталог</a></div>
        <div><a href="/cart/"> Корзина</a></div>
    </div>
    <h2 style="text-align:center;">Шапка сайта</h2>
</header>

<main>
    <div class="container">
        <div id="app-breadcrumbs"></div>
        <?php if (!empty($contentView)): ?>
            <?php
            $contentFile = \Bitrix\Main\Application::getDocumentRoot() . $contentView;
            if (is_file($contentFile)) {
                include $contentFile;
            }
            ?>
        <?php endif; ?>
    </div>
</main>

<footer>
    <h3 style="text-align:center;">Подвал сайта</h3>
</footer>

<!-- Передаём данные из PHP в JS -->
<script>
    window.__BX__ = {
        sessid:  '<?= bitrix_sessid() ?>',
        apiBase: '/api/v1/',
        userId:  <?= (int)$GLOBALS['USER']->GetID() ?>,
    };

    window.__PAGE_DATA__ = {
        page: {
            type: <?= json_encode($pageType, JSON_UNESCAPED_UNICODE) ?>,
            section_id: <?= $sectionId ?>,
        },
        catalog: {
            items: <?= json_encode($data_sections, JSON_UNESCAPED_UNICODE) ?>,
            total: <?= count($data_sections) ?>,
        },
        section: {
            id: <?= $sectionId ?>,
            data: <?= json_encode($data_section, JSON_UNESCAPED_UNICODE) ?>,
            total: <?= count($data_section['items'] ?? []) ?>,
            filter: <?= json_encode($sectionFilter, JSON_UNESCAPED_UNICODE) ?>,
            pagination: {
                page: <?= (int)($sectionPagination['page'] ?? 1) ?>,
                pages: <?= (int)($sectionPagination['pages'] ?? 1) ?>,
                limit: <?= (int)($sectionPagination['limit'] ?? 20) ?>,
                total: <?= (int)($sectionPagination['total'] ?? 0) ?>,
            },
        },
        product: {
            section_id: <?= $sectionId ?>,
            data: <?= json_encode($data_product, JSON_UNESCAPED_UNICODE) ?>,
        },
        order: {
            data: <?= json_encode($data_order, JSON_UNESCAPED_UNICODE) ?>,
        },
    };
</script>

<script type="module" src="<?= htmlspecialcharsbx($jsBase) ?>app-breadcrumbs.js"></script>

<!-- JS подключается в конце body, только если нужен на этой странице -->
<?php if (!empty($jsEntry)): ?>
    <script type="module" src="<?= htmlspecialcharsbx($jsBase . $jsEntry) ?>"></script>
<?php endif; ?>

</body>
</html>