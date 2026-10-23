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
$apiBase = (string)($apiBase ?? '/vue_catalog/api/v1/');
$jsBase = rtrim((string)($jsBase ?? '/local/modules/myapp.catalog/js/catalog/'), '/') . '/';
$vueModuleUrl = (string)($vueModuleUrl ?? '/local/modules/myapp.catalog/js/vendor/vue.esm-browser.js');
$useBitrixVue = !empty($useBitrixVue);
$bitrixAssetJs = (string)($bitrixAssetJs ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

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

    <?php if ($useBitrixVue && $bitrixAssetJs !== ''): ?>
        <?= $bitrixAssetStrings ?>        
    <?php endif; ?>
</head>
<body class="site-body">

<header class="site-header">
    <div class="site-header__inner container">
        <a href="/" class="site-header__logo">
            <img src="/local/templates/assets/img/svg/logo.svg" alt="<?= htmlspecialcharsbx($pageTitle) ?>" width="180" height="48">
        </a>
        <nav class="site-nav" aria-label="Основное меню">
            <a href="/" class="site-nav__link">Главная</a>
            <a href="/catalog/" class="site-nav__link">Каталог</a>
            <div id="app-cart-nav"></div>
        </nav>
    </div>
</header>

<main class="site-main">
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

<footer class="site-footer">
    <div class="site-footer__inner container">
        <p class="site-footer__copy">© <?= date('Y') ?> Интернет-магазин. Все права защищены.</p>
    </div>
</footer>

<!-- Передаём данные из PHP в JS -->
<script>
    window.__BX__ = {
        sessid:  '<?= bitrix_sessid() ?>',
        apiBase: <?= json_encode(rtrim($apiBase, '/') . '/', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
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
<script type="module" src="<?= htmlspecialcharsbx($jsBase) ?>app-cart-nav.js"></script>

<!-- JS подключается в конце body, только если нужен на этой странице -->
<?php if (!empty($jsEntry)): ?>
    <script type="module" src="<?= htmlspecialcharsbx($jsBase . $jsEntry) ?>"></script>
<?php endif; ?>

</body>
</html>