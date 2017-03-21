<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class modulpos_cashbox extends CModule
{
    var $MODULE_ID = 'modulpos.cashbox';

    public function __construct()
    {
        $arModuleVersion = array();
        
        include __DIR__ . '/version.php';

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }
        
        $this->MODULE_ID = 'modulpos.cashbox';
        $this->MODULE_NAME = Loc::getMessage('MODULPOS_CASHBOX_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MODULPOS_CASHBOX_MODULE_DESCRIPTION');
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = Loc::getMessage('MODULPOS_CASHBOX_MODULE_PARTNER_NAME');
        $this->PARTNER_URI = 'http://modulpos.ru';
    }

    function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);

        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler("sale", "OnGetCustomCashboxHandlers", $this->MODULE_ID, '\Modulpos\Cashbox\EventHandler', 'OnGetCashboxHandler');
        $eventManager->registerEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\Modulpos\Cashbox\EventHandler', 'OnSaveOrder');
        CopyDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/modulpos.cashbox/install/tools", $_SERVER["DOCUMENT_ROOT"]."/bitrix/tools", true, true);
    }

    function DoUninstall()
    {
        DeleteDirFiles($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/modulpos.cashbox/install/tools", $_SERVER["DOCUMENT_ROOT"]."/bitrix/tools");
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler("sale", "OnGetCustomCashboxHandlers", $this->MODULE_ID, '\Modulpos\Cashbox\EventHandler', 'OnGetCashboxHandler');
        $eventManager->unRegisterEventHandler('sale', 'OnSaleOrderSaved', $this->MODULE_ID, '\Modulpos\Cashbox\EventHandler', 'OnSaveOrder');

        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
