<?php

namespace Onizamov\ErpExchange;

class CrmField
{

    public $entityName = '';

    public $class = '';

    public $fields = [];

    public $userFields = [];

    public $enumFields = [];


    public function __construct(string $entityName)
    {
        $className = "\CCrm" . ucfirst($entityName);

        if (!class_exists($className)) {
            throw new \Exception("Не корректное название сущности");
        }

        $this->entityName = $entityName;
        $this->class = new $className();
        $this->fields = $this->class::getFieldsInfo();
        $this->userFields = self::getUserFields();
        $this->enumFields = self::getFieldsEnum();
    }

    public function listForConfig()
    {
        $result = [];

        $fields = array_keys($this->fields);
        foreach ($fields as $fieldId) {
            $caption = $this->class::GetFieldCaption($fieldId);
            if ($caption) {
                $result[$fieldId] = $caption;
            }
        }

        foreach ($this->userFields as $fieldId => $field) {
            $result[$fieldId] = $field['EDIT_FORM_LABEL'];
        }

        return $result;
    }

    private function getUserFields()
    {
        global $USER_FIELD_MANAGER;
        $CCrmFields = new \CCrmFields($USER_FIELD_MANAGER, mb_strtoupper('CRM_' . $this->entityName));
        $fields = $CCrmFields->GetFields();

        return $fields;
    }

    private function getFieldsEnum()
    {
        $fieldsEnum = [];

        //получаем значение списков
        $fieldDb = \CUserFieldEnum::GetList();
        while ($field = $fieldDb->Fetch()) {
            $fields[$field['USER_FIELD_ID']][$field['VALUE']] = $field['ID'];
        }

        $arFields = self::getUserFields();
        foreach ($arFields as $key => $value) {
            if ($value['USER_TYPE_ID'] == 'enumeration') {
                $fieldsEnum[$key] =  $fields[$value['ID']];
            }
        }

        return $fieldsEnum;
    }

    private function getStatusList($entity)
    {
        $statusList = [];
        $res = \CCrmStatus::GetList(array('SORT' => 'ASC'), ["ENTITY_ID" => $entity]);
        while ($ar = $res->Fetch()) {
            $statusList[$ar["STATUS_ID"]] = $ar["NAME"];
        }

        return $statusList;
    }

    public function getErpValue(string $crmCode, $crmValue)
    {


        if (array_key_exists($crmCode, $this->userFields)) {

            switch ($this->userFields[$crmCode]['USER_TYPE_ID']) {
                case "enumeration":
                    if ($this->userFields[$crmCode]['MULTIPLE'] == "Y") {
                        $value = '';
                        foreach ($crmValue as $item) {

                            $value .= array_flip($this->enumFields[$crmCode])[$item] . ";";
                        }
                    } else {

                        $value = array_flip($this->enumFields[$crmCode])[$crmValue];
                    }

                    break;

                case "address":
                    if ($crmValue) {
                        $value = $crmValue;
                    } else {
                        $value = '';
                    }

                    break;
                case "user":
                case "employee":

                    if ($crmValue) {

                        $user = \CUser::GetByID($crmValue)->Fetch();
                        $value = [
                            "Ид" => $user['ID'],
                            "Имя" => $user['NAME'],
                            "Фамилия" => $user['LAST_NAME'],
                            "Отчество" => $user['SECOND_NAME'],
                            "ВнешнийИд" => $user['XML_ID'],
                            "Логин" => $user['LOGIN'],
                            "E_mail" => $user['EMAIL'],
                        ];
                    } else {
                        $value = $crmValue;
                    }

                    break;
                default:

                    if (is_string($crmValue)) {
                        $value = str_replace("&quot;", "\"", $crmValue);
                    } else {
                        $value = $crmValue;
                    }
                    break;
            }
        } elseif (array_key_exists($crmCode, $this->fields)) {

            switch ($this->fields[$crmCode]["TYPE"]) {
                case "crm_status":
                    $statusList = self::getStatusList($this->fields[$crmCode]["CRM_STATUS_TYPE"]);
                    $value = $statusList[$crmValue];

                    break;

                case "user":
                case "employee":
                    if ($crmValue) {

                        $user = \CUser::GetByID($crmValue)->Fetch();
                        $value = [
                            "Ид" => $user['ID'],
                            "Имя" => $user['NAME'],
                            "Фамилия" => $user['LAST_NAME'],
                            "Отчество" => $user['SECOND_NAME'],
                            "ВнешнийИд" => $user['XML_ID'],
                            "Логин" => $user['LOGIN'],
                            "E_mail" => $user['EMAIL'],
                        ];
                    } else {
                        $value = $crmValue;
                    }

                    break;

                default:
                    if (is_string($crmValue)) {
                        $value = str_replace("&quot;", "\"", $crmValue);
                    } else {
                        $value = $crmValue;
                    }
                    break;
            }
        } else {
            if (is_string($crmValue)) {
                $value = str_replace("&quot;", "\"", $crmValue);
            } else {
                $value = $crmValue;
            }
        }

        return $value;
    }

    public function getCrmValue(string $crmCode, $erpValue)
    {
        if (array_key_exists($crmCode, $this->userFields)) {

            switch ($this->userFields[$crmCode]['USER_TYPE_ID']) {
                case "enumeration":
                    if ($this->userFields[$crmCode]['MULTIPLE'] == "Y") {
                        $value = [];
                        $arVal = explode(';', $erpValue);
                        foreach ($arVal as $item) {

                            if ($item) {
                                $value[] = $this->enumFields[$crmCode][$item];;
                            }
                        }
                    } else {

                        $value = $this->enumFields[$crmCode][$erpValue];
                    }

                    break;
                case "user":
                case "employee":
                    //TODO:Добавить поддержку других идентификаторов
                    if ($erpValue["Ид"]) {
                        $user = \CUser::GetByID($erpValue["Ид"])->Fetch();
                        $value = [
                            "Ид" => $user['ID'],
                            "Имя" => $user['NAME'],
                            "Фамилия" => $user['LAST_NAME'],
                            "Отчество" => $user['SECOND_NAME'],
                            "ВнешнийИд" => $user['XML_ID'],
                            "Логин" => $user['LOGIN'],
                            "E_mail" => $user['EMAIL'],
                        ];
                    } else {
                        $value = $erpValue;
                    }

                    break;

                default:

                    $value = $erpValue;
                    break;
            }
        } elseif (array_key_exists($crmCode, $this->fields)) {

            switch ($this->fields[$crmCode]["TYPE"]) {
                case "crm_status":
                    $statusList = self::getStatusList($this->fields[$crmCode]["CRM_STATUS_TYPE"]);
                    $value = array_flip($statusList)[$erpValue];

                    break;

                case "user":
                case "employee":
                    //TODO:Добавить поддержку других идентификаторов
                    if ($erpValue["Ид"]) {
                        $user = \CUser::GetByID($erpValue["Ид"])->Fetch();
                        $value = [
                            "Ид" => $user['ID'],
                            "Имя" => $user['NAME'],
                            "Фамилия" => $user['LAST_NAME'],
                            "Отчество" => $user['SECOND_NAME'],
                            "ВнешнийИд" => $user['XML_ID'],
                            "Логин" => $user['LOGIN'],
                            "E_mail" => $user['EMAIL'],
                        ];
                    } else {
                        $value = $erpValue;
                    }

                    break;

                default:

                    $value = $erpValue;
                    break;
            }
        } else {
            $value = $erpValue;
        }

        return $value;
    }
}
