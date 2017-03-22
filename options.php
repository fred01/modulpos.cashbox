<?php

defined('MODULE_NAME') or define('MODULE_NAME', 'modulpos.cashbox');
CModule::IncludeModule(MODULE_NAME);
CModule::IncludeModule("sale");

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Text\HtmlFilter;
use Modulpos\Cashbox\CashboxModul;


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
            $res = CashboxModul::createAssociation($retailpoint_id, $login, $password);
            if ($res['success'] === TRUE) {
                Option::set(MODULE_NAME, 'associated_login', $res['data']['associated_login']); 
                Option::set(MODULE_NAME, 'associated_password', $res['data']['associated_password']);
                CAdminMessage::showMessage(array("MESSAGE" => Loc::getMessage("MODULPOS_ASSOCIATION_CREATED"),"TYPE" => "OK"));
            } else {
                CAdminMessage::showMessage(array("MESSAGE" => Loc::getMessage("MODULPOS_ERROR_CREATING_ASSOC").':'.$res['error'],"TYPE" => "ERROR"));
            }
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
    <?  if (!CashboxModul::isAssociated()):  ?>

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
                   /><br/>
            <span><?=Loc::getMessage("MODULPOS_CASHBOX_WHERE_IS_RETAIL_POINT_ID") ?></span>
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

    <? else: ?>
    <span><?=Loc::getMessage("MODULPOS_ASSOCIATED_SUCCESSFULY") ?></span>
    <?php
    $tabControl->buttons();
    ?>    
            <input type="submit"
                name="restore"
                value="<?=Loc::getMessage("MODULPOS_DELETE_ASSOCIATION") ?>"
                title="<?=Loc::getMessage("MODULPOS_DELETE_ASSOCIATION") ?>"
                class="adm-btn-save"
                />
            
   
    <? endif; ?>

</form>
