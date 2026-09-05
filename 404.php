<?

use Bitrix\Main\Localization\Loc;

include_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/urlrewrite.php');

CHTTP::SetStatus("404 Not Found");
@define("ERROR_404","Y");

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->SetPageProperty("NOT_SHOW_NAV_CHAIN", "Y");

$APPLICATION->SetTitle(Loc::GetMessage('TITLE_404'));?>
<div class="container">
    <div class="no-page">
        <div class="no-page__head">404</div>
            <p><?=Loc::GetMessage('TITLE_404');?></p>
            <a class="btn" href="<?=SITE_DIR?>"><?=Loc::GetMessage('DESCRIPTION_404');?></a>
    </div>
</div>
<?$APPLICATION->SetPageProperty('HIDE_H1', 'Y');?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>