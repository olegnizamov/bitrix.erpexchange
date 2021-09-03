<?php

namespace Onizamov\ErpExchange;

use \Bitrix\Rest\RestException;

class RestList
{

    public $iblock_id = 0;
    public $iblock_code = '';

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
        if (!isset($_GET['from'])) {
            throw new RestException(
                'Не передан обязательный параметр from',
                'WRONG_FROM',
                \CRestServer::STATUS_WRONG_REQUEST
            );
        }

        $from = new \DateTime($_GET['from']);

        $ListField = new ListField($this->iblock_code);

        $dbQuery = \CIBlockElement::GetList(
            ["ID"],
            [
                "IBLOCK_ID"         => $this->iblock_id,
                "DATE_MODIFY_FROM"  => $from->format("d.m.Y H:i:s"),
                "CHECK_PERMISSIONS" => "N",
            ],
            false,
            false,
            array_merge(["*"], array_keys($ListField->getFieldsInfo()))
//TODO Код старый                           array_merge(["*"], array_keys($ListField->getUserFields()))
        );
        while ($arEntity = $dbQuery->GetNext()) {
            $e = new Element($this->iblock_code);
            $el = [];
            foreach ($arEntity as $key => $value) {
                $key = preg_replace('`_VALUE$`', '', $key);

                $el[$key] = $ListField->getCrmValue($key, $value);
            }
            $result[] = $e->fromCrm($el)->toErp(false);
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
            $e = new Element($this->iblock_code);
            if ($e->fromErp($arEntity)->save()) {
                $result[] = $e->toErp();
            }
        }

        return $result;
    }
}
