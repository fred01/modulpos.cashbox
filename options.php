<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Text\HtmlFilter;

defined('MODULE_NAME') or define('MODULE_NAME', 'modulpos.cashbox');

if (!$USER->isAdmin()) {
    $APPLICATION->authForm('Nope');
}

$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();

Loc::loadMessages($context->getServer()->getDocumentRoot()."/bitrix/modules/main/options.php");
Loc::loadMessages(__FILE__);

$tabControl = new CAdminTabControl("tabControl", array(
    array(
        "DIV" => "edit1",
        "TAB" => Loc::getMessage("MAIN_TAB_SET"),
        "TITLE" => Loc::getMessage("MAIN_TAB_TITLE_SET"),
    ),
));

if ((!empty($save) || !empty($restore)) && $request->isPost() && check_bitrix_sessid()) {
  if (!empty($restore)) {
    Option::delete(MODULE_NAME);
    CAdminMessage::showMessage(array(
      "MESSAGE" => Loc::getMessage("REFERENCES_OPTIONS_RESTORED"),
      "TYPE" => "OK",
    ));
  } else {
    if ($request->getPost('login')) {
      $login = $request->getPost('login');
      $password = $request->getPost('password');
      $retailpoint_id = $request->getPost('retailpoint_id');
      $validated = true; // TODO validate values
      if ($validated) {
        Option::set(MODULE_NAME, "login", $login);
        Option::set(MODULE_NAME, "password", $password);
        Option::set(MODULE_NAME, "retailpoint_id", $retailpoint_id);
        CAdminMessage::showMessage(array("MESSAGE" => Loc::getMessage("REFERENCES_OPTIONS_SAVED"),"TYPE" => "OK"));
      } else {
        CAdminMessage::showMessage(Loc::getMessage("REFERENCES_INVALID_VALUE"));
      }
    }
  }
}

$tabControl->begin();
?>

<form method="post" action="<?=sprintf('%s?mid=%s&lang=%s', $request->getRequestedPage(), urlencode($mid), LANGUAGE_ID)?>">
    <?php
    echo bitrix_sessid_post();
    $tabControl->beginNextTab();
    ?>
    <tr>
        <td width="40%">
            <label for="login"><?=Loc::getMessage("REFERENCES_MODULPOS_LOGIN") ?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   maxlength="50"
                   name="login"
                   value="<?=HtmlFilter::encode(Option::get(MODULE_NAME, "login", ''));?>"
                   />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="password"><?=Loc::getMessage("REFERENCES_MODULPOS_PASS") ?>:</label>
        <td width="60%">
            <input type="password"
                   size="50"
                   maxlength="50"
                   name="password"
                   value="<?=HtmlFilter::encode(Option::get(MODULE_NAME, "password", ''));?>"
                   />
        </td>
    </tr>
    <tr>
        <td width="40%">
            <label for="retailpoint_id"><?=Loc::getMessage("REFERENCES_MODULPOS_RETAILPOINT_ID") ?>:</label>
        <td width="60%">
            <input type="text"
                   size="50"
                   maxlength="50"
                   name="retailpoint_id"
                   value="<?=HtmlFilter::encode(Option::get(MODULE_NAME, 'retailpoint_id', ''));?>"
                   />
        </td>
    </tr>

    <?php
    $tabControl->buttons();
    ?>
    <input type="submit"
           name="save"
           value="<?=Loc::getMessage("MAIN_SAVE") ?>"
           title="<?=Loc::getMessage("MAIN_OPT_SAVE_TITLE") ?>"
           class="adm-btn-save"
           />
    <?php
    $tabControl->end();
    ?>
</form>
