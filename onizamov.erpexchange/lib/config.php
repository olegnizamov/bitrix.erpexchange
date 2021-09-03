<?php

namespace Onizamov\ErpExchange;

use \Bitrix\Main\Config\Option;

class Config
{

    public $mapping = [];

    public $options = [];

    public $db;

    public function __construct($object)
    {
        $className = explode("\\", get_class($object));
        $name = $className[count($className) - 1];
        $class = "\CCrm" . $name;
        $propertyName =  mb_strtolower($name) . 'Properties';

        if (!class_exists($class)) {
            throw new \Exception("Не корректно передана сущность");
        }
        $this->db = new $class;


        $options = json_decode(Option::get("onizamov_erpexchange", 'options', '[]'));

        if ($options->{$propertyName}) {
            $this->options = $options->{$propertyName};
        }

        $this->mapping = json_decode(Option::get("onizamov_erpexchange", $propertyName, '[]'), true);
        if (!$this->mapping) {
            throw new \Exception("Не настроен модуль обмена данными");
        }
    }
}