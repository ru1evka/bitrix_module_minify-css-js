<?php
namespace Advantika\Optimazer;
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main\Application;
use \Bitrix\Main\Localization\Loc;

$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();

Loc::loadMessages(__FILE__);

$siteId = $request->get('site_id');


if (!$siteId)
{
	$site = \CSite::GetList($by = 'sort', $order = 'asc', [
		'DEFAULT' => 'Y',
	])->Fetch();
	if (!$site)
	{
		$site = \CSite::GetList($by = 'sort', $order = 'asc', [
			'ACTIVE' => 'Y',
		])->Fetch();
	}
	if (!$site)
	{
		$site = \CSite::GetList($by = 'sort', $order = 'asc')->Fetch();
	}
}
else
{
	$site = \CSite::GetByID($siteId)->Fetch();
}
if (empty($site['LID']))
{
	\CAdminMessage::ShowMessage(
		Loc::getMessage('ADVANTIKA_OPTIMAZER_SITE_NOT_DEFINED')
	);
	return;
}

$currentOptions = Options($site['LID']);

if (check_bitrix_sessid() && $request->isPost())
{

    if ($request->getPost('save') != '')
    {
        $data = $request->getPostList();
        OptionsUpdate($data, $site['LID']);
        \CAdminMessage::ShowNote(
            Loc::getMessage('ADVANTIKA_OPTIMAZER_SETTINGS_SAVED')
        );
    } else if($request->getPost('minification') != ''){
        minify($site['LID']);
    }
}

$title = '[' . $site['LID'] . '] ' .  $site['NAME'];
if ($request->get('advantika_optimazer_action') == 'optimazer')
{
	$description = Loc::getMessage('ADVANTIKA_OPTIMAZER_TITLE_REDIRECTS');
}
else
{
	$description = Loc::getMessage('ADVANTIKA_OPTIMAZER_OPTIONS_TITLE');
}
$aTabs[] = array(
    'DIV' => 'edit1',
	'TAB' => $title,
	'TITLE' => $description
);
$APPLICATION->SetAdditionalCSS(SITE_DIR."/bitrix/modules/advantika.optimazer/assets/css/style.css");
$tabControl = new \CAdminTabControl("tabControl", $aTabs);
$tabControl->begin();

?>

<form method="post" action="">
	<?= bitrix_sessid_post() ?>

	<? $tabControl->beginNextTab() ?>

	<? if ($request->get('advantika_optimazer_action') == 'optimazer') { ?>
        <tr class="heading ">
            <td class="adm-detail-content-cell-l adm-detail-title gray-section_down" colspan="2">
                <label>
                    <?= Loc::getMessage("ADVANTIKA_OPTIMAZER_OPTIONS_TITLE_ALL") ?>
                </label>
            </td>
        </tr>
	    <tr>
	    	<td class="adm-detail-content-cell-l" width="50%">
	    		<label>
	    			<?= Loc::getMessage("ADVANTIKA_OPTIMAZER_CSS_TITLE") ?>
	    		</label>
	    	</td>
	    	<td class="adm-detail-content-cell-r" width="50%">
	    		<input name="active_minify_css" value="Y" type="checkbox"
	    			<?= $currentOptions["active_minify_css"] == "Y"? "checked" : "" ?>>
	    	</td>
	    </tr>
        <tr>
	    	<td class="adm-detail-content-cell-l" width="50%">
	    		<label>
	    			<?= Loc::getMessage("ADVANTIKA_OPTIMAZER_JS_TITLE") ?>
	    		</label>
	    	</td>
	    	<td class="adm-detail-content-cell-r" width="50%">
	    		<input name="active_minify_js" value="Y" type="checkbox"
	    			<?= $currentOptions["active_minify_js"] == "Y"? "checked" : "" ?>>
	    	</td>
	    </tr>
    <?} else { ?>
        <tr class="heading ">
            <td class="adm-detail-content-cell-l adm-detail-title gray-section_down" colspan="2">
                <label>
                    <?= Loc::getMessage("ADVANTIKA_OPTIMAZER_OPTIONS_TITLE_ALL") ?>
                </label>
            </td>
        </tr>
        <tr>
            <td class="adm-detail-content-cell-l" width="50%">
                <label>
                    <?= Loc::getMessage("ADVANTIKA_OPTIMAZER_CSS_TITLE") ?>
                </label>
            </td>
            <td class="adm-detail-content-cell-r" width="50%">
                <input name="active_minify_css" value="Y" type="checkbox"
                    <?= $currentOptions["active_minify_css"] == "Y"? "checked" : "" ?>>
            </td>
        </tr>
        <tr>
            <td class="adm-detail-content-cell-l" width="50%">
                <label>
                    <?= Loc::getMessage("ADVANTIKA_OPTIMAZER_JS_TITLE") ?>
                </label>
            </td>
            <td class="adm-detail-content-cell-r" width="50%">
                <input name="active_minify_js" value="Y" type="checkbox"
                    <?= $currentOptions["active_minify_js"] == "Y"? "checked" : "" ?>>
            </td>
        </tr>
    <? }
    $tabControl->buttons();?>
    <input class="adm-btn-save" type="submit" name="save"
           value="<?= Loc::getMessage("ADVANTIKA_OPTIMAZER_SAVE_SETTINGS") ?>">
    <input class="adm-btn-save" type="submit" name="minification"
           value="<?= Loc::getMessage("ADVANTIKA_OPTIMAZER_MINiFY") ?>">
    <?$tabControl->end();?>
</form>

<script>

BX.ready(function () {
	"use strict";
	// autoappend rows
	function makeAutoAppend($table) {
		function bindEvents($row) {
			for (let $input of $row.querySelectorAll('input[type="text"]')) {
				$input.addEventListener("change", function (event) {
					let $tr = event.target.closest("tr");
					let $trLast = $table.rows[$table.rows.length - 1];
					if ($tr != $trLast) {
						return;
					}
					$table.insertRow(-1);
					$trLast = $table.rows[$table.rows.length - 1];
					$trLast.innerHTML = $tr.innerHTML;
					let idx = parseInt($tr.getAttribute("data-idx")) + 1;
					$trLast.setAttribute("data-idx", idx);
					for (let $input of $trLast.querySelectorAll("input,select")) {
						let name = $input.getAttribute("name");
						if (name) {
							$input.setAttribute("name", name.replace(/([a-zA-Z0-9])\[\d+\]/, "$1[" + idx + "]"));
						}
					}
					bindEvents($trLast);
				});
			}
		}
		for (let $row of document.querySelectorAll(".js-table-autoappendrows tr")) {
			bindEvents($row);
		}
	}
	for (let $table of document.querySelectorAll(".js-table-autoappendrows")) {
		makeAutoAppend($table);
	}
});

</script>
