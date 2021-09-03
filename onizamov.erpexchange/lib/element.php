<?php

namespace Onizamov\ErpExchange;

use \Bitrix\Main\Config\Option;

class Element
{

    protected $mapping = [];
    protected $options = [];

    private $iblock_code = 0;

    private $properties = [];

    private $config;

    public function __construct(string $code)
    {
        \Bitrix\Main\Loader::includeModule("bizproc");


        $this->iblock_code = $code;

        $propertyName = mb_strtolower($code) . 'Properties';
        $options = json_decode(Option::get("onizamov_erpexchange", 'options', '[]'));
        if ($options->{$propertyName}) {
            $this->options = $options->{$propertyName};
        }

        $mapping = $this->getMapping($code);
        foreach ($mapping as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $this->mapping[mb_strtoupper($key)] = $value;
            }
        }
    }

    private function getMapping(string $name)
    {
        $propertyName = mb_strtolower($name) . 'Properties';
        $mapping = json_decode(Option::get("onizamov_erpexchange", $propertyName, '[]'), true);
        if (!$mapping) {
            throw new \Exception("Не настроен модуль обмена данными");
        }

        return $mapping;
    }

    public function __get(string $property)
    {
        if (property_exists($this, $property)) {
            return $this->{$property};
        }

        if (isset($this->properties[$property])) {
            return $this->properties[$property];
        }
        return null;
    }

    public function __set(string $property, $value)
    {
        $this->properties[$property] = $value;
    }

    public function fromErp(array $structure)
    {
        if (count($this->mapping) && count($structure)) {
            $mapping = array_flip($this->mapping);
            $CrmField = new ListField($this->iblock_code);
            foreach ($mapping as $erpKey => $crmKey) {
                $key = $CrmField->getCrmValue($crmKey, $structure[$erpKey]);
                if ($key !== false) {
                    $this->{$crmKey} = $key;
                }
            }
        }
        return $this;
    }

    public function toErp(bool $skipNull = true)
    {
        $result = [];
        $CrmField = new ListField($this->iblock_code);
        if (count($this->mapping)) {
            foreach ($this->mapping as $sourceKey => $destKey) {
                if ($skipNull) {
                    if ($this->{$sourceKey} || $this->$sourceKey !== null) {
                        $result[$destKey] = $CrmField->getErpValue($sourceKey, $this->{$sourceKey});
                    }
                } else {
                    if ($this->mapping[$sourceKey]) {
                        $result[$destKey] = $CrmField->getErpValue($sourceKey, $this->{$sourceKey});
                    }
                }
            }
        }
        return $result;
    }

    public function fromCrm(array $structure)
    {
        if (count($this->mapping) && count($structure)) {
            foreach ($structure as $sourceKey => $sourceValue) {
                if (property_exists($this, $sourceKey) || isset($this->mapping[$sourceKey])) {
                    $this->{$sourceKey} = $sourceValue;
                }
            }
        }
        return $this;
    }

    public function toCrm(bool $skipNull = true)
    {
        $result = [];
        if (count($this->mapping)) {
            foreach (array_keys($this->mapping) as $sourceKey) {
                if ($skipNull) {
                    if ($this->{$sourceKey} || $this->$sourceKey !== null) {
                        $result[$sourceKey] = $this->{$sourceKey};
                    }
                } else {
                    if ($this->mapping[$sourceKey]) {
                        $result[$sourceKey] = $this->{$sourceKey};
                    }
                }
            }
        }
        return $result;
    }

    public function save()
    {
        $data = $this->toCrm();
        global $DB;

//TODO logger
//        if(!$data['NAME']){
//            $data['NAME'] = '-';
//        }
//        $pathToLog = '/erpexchange.log.txt';
//        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
//        $log = date('d.m.Y H:i:s') . ' - ' . $url . ' - Запрос: ' . json_encode($data) . "\n";
//        file_put_contents($_SERVER["DOCUMENT_ROOT"] . $pathToLog, $log, FILE_APPEND);
//

        $primaryKey = array_flip($this->mapping)[$this->options->primaryKey];
        if (!isset($data[$primaryKey])) {
            throw new \Exception("Не указан первичный ключ", 402);
        }

        $ListField = new ListField($this->iblock_code);
        $requiredFields = $ListField->getUserFields(true);

        foreach ($requiredFields as $field => $name) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \Exception("Не указано обязательное значение для поля: {$name}", 402);
            }
        }

        $data['IBLOCK_ID'] = $ListField->iblock_id;
        if (!empty($data[$primaryKey])) {
            $el = \CIBlockElement::GetList(
                ["ID"],
                ['IBLOCK_ID' => $ListField->iblock_id, $primaryKey => $data[$primaryKey]],
                false,
                false,
                ["ID"]
            )->Fetch();
            if ($el) {
                $id = $el['ID'];
            }
        }


        $CIBlockElement = new \CIBlockElement;
        if (!$id) {
            Logger::log($_SERVER['SCRIPT_URL'], 'создание записи', json_encode($data, JSON_UNESCAPED_UNICODE));
            $id = $CIBlockElement->Add($data);

            if (!$id) {
                throw new \Exception($CIBlockElement->LAST_ERROR, 500);
            }
            $execType = \CBPDocumentEventType::Create;
        } else {
            Logger::log(
                $_SERVER['SCRIPT_URL'],
                'обновление записи',
                json_encode(['ID' => $id, 'DATA' => $data], JSON_UNESCAPED_UNICODE)
            );

            if (!$CIBlockElement->Update($id, $data)) {
                throw new \Exception($CIBlockElement->LAST_ERROR, 500);
            }
            $execType = \CBPDocumentEventType::Edit;
        }

//        foreach($data as $key => $value){
//            if(strpos( $key, 'PROPERTY_') !== false){
//                \CIBlockElement::SetPropertyValueCode($id, str_replace('PROPERTY_','', $key), $value);
//            }
//        }
        foreach ($data as $key => $value) {
            if (strpos($key, 'PROPERTY_') !== false) {
                if ($value instanceof Element) {
                    $elProps = \CIBlockElement::GetList(
                        ["ID"],
                        ['IBLOCK_ID' => $ListField->iblock_id, 'ID' => $id],
                        false,
                        false,
                        [$key]
                    )->fetch();
                    $DB->StartTransaction();
                    if (!\CIBlockElement::Delete($elProps[$key . '_VALUE'])) {
                        $transactionError = true;
                        $DB->Rollback();
                    } else {
                        $DB->Commit();
                    }
                    $pi = $value->save()->ID;
                    \CIBlockElement::SetPropertyValueCode($id, str_replace('PROPERTY_', '', $key), $pi);
                } else {
                    if (is_array($value) && count($value) && $value[0] instanceof Element) {
                        $elProps = \CIBlockElement::GetList(
                            ["ID"],
                            ['IBLOCK_ID' => $ListField->iblock_id, 'ID' => $id],
                            false,
                            false,
                            ["ID", $key]
                        )->fetch();
                        $DB->StartTransaction();
                        $transactionError = false;
                        foreach ($elProps[$key . '_VALUE'] as $elPropId) {
                            if (!\CIBlockElement::Delete($elPropId)) {
                                $transactionError = true;
                                $DB->Rollback();
                            }
                        }
                        if (!$transactionError) {
                            $DB->Commit();
                        }
                        $pi = [];
                        foreach ($value as $val) {
                            $pi[] = $val->save()->ID;
                        }
                        \CIBlockElement::SetPropertyValueCode($id, str_replace('PROPERTY_', '', $key), $pi);
                    } else {
                        \CIBlockElement::SetPropertyValueCode($id, str_replace('PROPERTY_', '', $key), $value);
                    }
                }
            }
        }

        $this->ID = (string)$id;

        $templates = \CBPWorkflowTemplateLoader::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            [
                "DOCUMENT_TYPE" => ['lists', 'Bitrix\Lists\BizprocDocumentLists', 'iblock_' . $ListField->iblock_id],
                "ACTIVE"        => "Y",
                "IS_SYSTEM"     => "N",
                'AUTO_EXECUTE'  => $execType,
            ],
            false,
            false,
            ["ID", "NAME"]
        );

        while ($template = $templates->Fetch()) {
            $runtime = \CBPRuntime::GetRuntime();
            $wi = $runtime->CreateWorkflow($template['ID'], ['lists', 'Bitrix\Lists\BizprocDocumentLists', $id], []);
            $wi->Start();
        }


        return $this;
    }
}
