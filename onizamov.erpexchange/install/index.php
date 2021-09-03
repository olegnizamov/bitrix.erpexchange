<?php
defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

if (class_exists('onizamov_erpexchange')) {
    return;
}

class onizamov_erpexchange extends CModule
{
    /** @var string */
    public $MODULE_ID;

    /** @var string */
    public $MODULE_VERSION;

    /** @var string */
    public $MODULE_VERSION_DATE;

    /** @var string */
    public $MODULE_NAME;

    /** @var string */
    public $MODULE_DESCRIPTION;

    /** @var string */
    public $MODULE_GROUP_RIGHTS;

    /** @var string */
    public $PARTNER_NAME;

    /** @var string */
    public $PARTNER_URI;

    public function __construct()
    {
        $this->MODULE_ID = 'onizamov.erpexchange';
        $this->MODULE_VERSION = '1.3.0';
        $this->MODULE_VERSION_DATE = '2020-01-17 16:23:14';
        $this->MODULE_NAME = 'КК Интеграция Б24 и 1С';
        $this->MODULE_DESCRIPTION = 'Модуль обмена между 1С и Битрикс24';
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = "KK";
        $this->PARTNER_URI = "https://olegnizamov.ru/";
    }

    public function doInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID); 
        RegisterModuleDependences("rest", "OnRestServiceBuildDescription", $this->MODULE_ID, "\Onizamov\ErpExchange\RestApi", "OnRestServiceBuildDescription");
        
    }
    
    public function doUninstall()
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
        UnRegisterModuleDependences("rest", "OnRestServiceBuildDescription", $this->MODULE_ID, "\Onizamov\ErpExchange\RestApi", "OnRestServiceBuildDescription");
    }
}