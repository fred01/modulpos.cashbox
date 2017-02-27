<?php
defined('MODULE_NAME') or define('MODULE_NAME', 'modulpos.cashbox');
CModule::IncludeModule(MODULE_NAME);

$arClasses = array(
  'Modulpos\Cashbox\EventHandler'=>'lib/eventhandlers.php',
  'Modulpos\Cashbox\CashboxModul'=>'lib/cashboxmodul.php',
);

CModule::AddAutoloadClasses(MODULE_NAME, $arClasses);

?>