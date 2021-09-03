<?php

namespace Onizamov\ErpExchange;

class ListField
{

    public $iblock_id = 0;
    public $iblock_code = '';
    public $fields = [
        'ID'                => 'ID',
        'NAME'              => 'Название',
        'IBLOCK_SECTION_ID' => 'ID раздела',
        'ACTIVE'            => 'Активность',
    ];

    public $properties = [];

    public function __construct(string $code)
    {
        if (!$iblock = \CIBlock::GetList([], ['CODE' => $code, "CHECK_PERMISSIONS" => "N"])->Fetch()) {
            throw new \Exception("Не корректный код инфоблока");
        }
        $this->iblock_code = $code;
        $this->iblock_id = $iblock['ID'];
        $this->fields = $this->getFieldsInfo();
    }

    public function getFieldsInfo()
    {
        $user_fields = $this->getUserFields();

        return array_merge($this->fields, $user_fields);
    }

    public function getUserFields(bool $onlyRequired = false)
    {
        $fields = [];
        $filter = ['IBLOCK_ID' => $this->iblock_id, "ACTIVE" => "Y", "CHECK_PERMISSIONS" => "N"];
        if ($onlyRequired) {
            $filter["IS_REQUIRED"] = "Y";
        }
        $props = \CIBlockProperty::GetList([], $filter);

        while ($prop = $props->Fetch()) {
            $fields['PROPERTY_' . mb_strtoupper($prop['CODE'])] = $prop['NAME'];
            $this->properties['PROPERTY_' . mb_strtoupper($prop['CODE'])] = $prop;
        }
        return $fields;
    }

    public function listForConfig()
    {
        $result = [];

        foreach ($this->fields as $fieldId => $field) {
            $result[$fieldId] = $field;
        }
        return $result;
    }

    public function getCrmValue(string $crmCode, $erpValue)
    {
        if (array_key_exists($crmCode, $this->properties)) {
            switch ($this->properties[$crmCode]["PROPERTY_TYPE"]) {
                case "user":
                    $user = \CUser::GetByLogin($erpValue)->Fetch();
                    $value = $user['LOGIN'];

                    break;
                case "L":
                    $fieldDb = \CIBlockPropertyEnum::GetList(
                        [],
                        [
                            "IBLOCK_ID" => $this->properties[$crmCode]['IBLOCK_ID'],
                            "CODE"      => $this->properties[$crmCode]['CODE'],
                            "VALUE"     => $erpValue,
                        ]
                    )->Fetch();
                    $value = $fieldDb['ID'];
                    break;
                case "F":
                    $result = [];
                    if (is_array($erpValue)) {
                        foreach ($erpValue as $k => $vals) {
                            if (is_array($vals)) {
                                foreach ($vals as $filename => $val) {
                                    $decoded = base64_decode($val, true);
                                    if ($decoded != false) {
                                        $filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/tmp/" . $filename;
                                        if (file_exists(
                                            realpath($filepath)
                                        )) {            //Сделал для того что бы отключить проверку новых, еще не записанных файлов
                                            if (!is_writable(realpath($filepath))) {
                                                throw new \Exception("{$filepath} is not writable");
                                            }
                                        }
                                        if (file_put_contents($filepath, $decoded)) {
                                            $file = \CFile::MakeFileArray($filepath);
                                            $id = \CFile::SaveFile($file, "1c");
                                            $result[$k] = $id;
                                        }
                                    }
                                }
                            } else {
                                if ((int)$vals > 0) {
                                    $result[] = $vals;
                                }
                            }
                        }
                        $erpValue = $result;
                    } else {
                        foreach ($erpValue as $filename => $val) {
                            $decoded = base64_decode($val, true);
                            if ($decoded != false) {
                                $filepath = $_SERVER["DOCUMENT_ROOT"] . "/upload/tmp/" . $filename;

                                if (!is_writable(realpath($filepath))) {
                                    throw new \Exception("{$filepath} is not writable");
                                }
                                if (file_put_contents($filepath, $decoded)) {
                                    $file = \CFile::MakeFileArray($filepath);
                                    $id = \CFile::SaveFile($file, "1c");
                                    $erpValue = $id;
                                }
                            }
                        }
                    }
                    break;
                case "E":
                    if (!preg_match('`_NESTED$`', $crmCode)) {
                        return $erpValue;
                    }
                    $result = [];
                    $ib = \CIBlock::GetList(
                        [],
                        [
                            'ID'                => $this->properties[$crmCode]['LINK_IBLOCK_ID'],
                            "CHECK_PERMISSIONS" => "N",
                        ]
                    )->Fetch();
                    if (count($erpValue)) {
                        foreach ($erpValue as $arEntity) {
                            if (is_array($arEntity)) {
                                $e = new Element($ib['CODE']);
                                $result[] = $e->fromErp($arEntity);
                            } else {
                                $result[] = $arEntity;
                            }
                        }
                        return $result;
                    }
                    return $erpValue;
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

    public function getErpValue(string $crmCode, $crmValue)
    {
        if (array_key_exists($crmCode, $this->properties)) {
            switch ($this->properties[$crmCode]["PROPERTY_TYPE"]) {
                case "user":
                    $user = \CUser::GetByID($crmValue)->Fetch();
                    $value = $user['LOGIN'];

                    break;
                case "L":
                    $fieldDb = \CIBlockPropertyEnum::GetList(
                        [],
                        [
                            "IBLOCK_ID" => $this->properties[$crmCode]['IBLOCK_ID'],
                            "CODE"      => $this->properties[$crmCode]['CODE'],
                            "ID"        => $crmValue,
                        ]
                    )->Fetch();
                    $value = $fieldDb['VALUE'];
                    break;
                case "F":
                    if (is_array($crmValue)) {
                        foreach ($crmValue as &$val) {
                            if (!is_array($val) && (int)$val > 0) {
                                $value[] = \CFile::GetPath($val);
                            }
                        }
                    } else {
                        if ((int)$crmValue > 0) {
                            $value = \CFile::GetPath($crmValue);
                        }
                    }
                    break;
                case "E":
                    $result = [];
                    if (!preg_match('`_NESTED$`', $crmCode) || empty($crmValue)) {
                        return $crmValue;
                    }
                    if (is_array($crmValue) && count($crmValue) && $crmValue[0] instanceof Element) {
                        foreach ($crmValue as $crmv) {
                            $result[] = $crmv->toErp(false);
                        }
                        return $result;
                    }
                    $ib = \CIBlock::GetList([],
                                            [
                                                'ID'                => $this->properties[$crmCode]['LINK_IBLOCK_ID'],
                                                "CHECK_PERMISSIONS" => "N",
                                            ]
                    )->Fetch();
                    $ListField = new ListField($ib['CODE']);
                    $dbQuery = \CIBlockElement::GetList(["ID"],
                                                        [
                                                            "IBLOCK_ID"         => $ib['ID'],
                                                            "ID"                => $crmValue,
                                                            "CHECK_PERMISSIONS" => "N",
                                                        ],
                                                        false,
                                                        false,
                                                        array_merge(["*"], array_keys($ListField->getUserFields()))
                    );
                    while ($arEntity = $dbQuery->GetNext()) {
                        $e = new Element($ib['CODE']);
                        $el = [];
                        foreach ($arEntity as $key => $value) {
                            $key = preg_replace('`_VALUE$`', '', $key);

                            $el[$key] = $ListField->getCrmValue($key, $value);
                        }
                        $result[] = $e->fromCrm($el)->toErp(false);
                    }
                    $value = $result;
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
}