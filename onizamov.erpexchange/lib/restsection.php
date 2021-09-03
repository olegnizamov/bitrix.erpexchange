<?php

namespace Onizamov\ErpExchange;

use \Bitrix\Rest\RestException;

class RestSection
{

    public $iblock_id = 0;
    public $iblock_code = '';


    private $mapping = [
        "Ид"                 => 'ID',
        "Название"           => 'NAME',
        "Описание"           => 'DESCRIPTION',
        "ВнешнийКод"         => 'XML_ID',
        "СимвольныйКод"      => 'CODE',
        "Родитель"           => 'IBLOCK_SECTION_ID',
        "УровеньВложенности" => 'DEPTH_LEVEL',
    ];

    public function __construct(string $code)
    {
        if (!$iblock = \CIBlock::GetList([], ['CODE' => $code, "CHECK_PERMISSIONS" => "N"])->Fetch()) {
            throw new \Exception("Не корректный код инфоблока");
        }
        $this->iblock_code = $code;
        $this->iblock_id = $iblock['ID'];
    }

    public function get($query, $n, \CRestServer $server)
    {
        Logger::log($_SERVER['SCRIPT_URL'], 'получение записей сущности', json_encode($_GET, JSON_UNESCAPED_UNICODE));
        $dbQuery = \CIBlockSection::GetTreeList(["IBLOCK_ID" => $this->iblock_id, "CHECK_PERMISSIONS" => "N"]);
        while ($arEntity = $dbQuery->GetNext()) {
            $fields = [];
            foreach ($this->mapping as $key => $value) {
                $fields[$key] = $arEntity[$value];
            }
            $result[] = $fields;
        }
        return $result;
    }

    public function save($query, $n, \CRestServer $server)
    {
        try {
            $json = new Json(file_get_contents('php://input'));
        } catch (\Throwable $th) {
            throw new RestException(
                $th->getMessage(),
                'WRONG_JON',
                \CRestServer::STATUS_WRONG_REQUEST
            );
        }


        $result = [];
        foreach ($json->document['result'] as $arEntity) {
            $data = [];
            foreach (array_flip($this->mapping) as $key => $value) {
                $data[$key] = $arEntity[$value];
            }

            $el = \CIBlockSection::GetList(
                ["ID"],
                [
                    'IBLOCK_ID' => $this->iblock_id,
                    ["LOGIC" => "OR", ["ID" => $data['ID'], "XML_ID" => $data['XML_ID']]],
                ],
                false,
                false,
                ["ID"]
            )->Fetch();

            $CIBlockSection = new \CIBlockSection;
            if (!$el) {
                Logger::log($_SERVER['SCRIPT_URL'], 'создание записи', json_encode($data, JSON_UNESCAPED_UNICODE));
                $id = $CIBlockSection->Add($data);

                if (!$id) {
                    throw new \Exception($CIBlockSection->LAST_ERROR, 500);
                }
            } else {
                Logger::log(
                    $_SERVER['SCRIPT_URL'],
                    'обновление записи',
                    json_encode(['ID' => $el['ID'], 'DATA' => $data], JSON_UNESCAPED_UNICODE)
                );
                if (!$CIBlockSection->Update($el['ID'], $data)) {
                    throw new \Exception($CIBlockSection->LAST_ERROR, 500);
                }
            }

            $result[] = $arEntity;
        }


        return $result;
    }
}
