<?php

namespace Onizamov\ErpExchange;

use \Bitrix\Rest\RestException;

class RestEntity
{

    protected function getList(string $entityName)
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
        $result = [];
        $entityName = ucfirst(mb_strtolower($entityName));

        $crmEntity = "\CCrm" . $entityName;
        $entity = "\\Onizamov\\ErpExchange\\" . $entityName;
        $arFilter = [">DATE_MODIFY" => $from->format("d.m.Y H:i:s")];
        if (method_exists($crmEntity, 'GetListEx')) {
            $dbQuery = $crmEntity::GetListEx(["ID"], $arFilter, false, false, ["*", "UF_*"]);
        } else {
            $dbQuery = $crmEntity::GetList(["ID"], $arFilter, false, false, ["*", "UF_*"]);
        }
        while ($arEntity = $dbQuery->GetNext()) {
            $e = new $entity;
            $result[] = $e->fromCrm($arEntity)->toErp(false);
        }

        return $result;
    }

    protected function update(string $entityName)
    {
        define('1C_UPDATE_PROCESS', true);
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
        $entity = "\\Onizamov\\ErpExchange\\" . ucfirst(mb_strtolower($entityName));
        foreach ($json->document['result'] as $arEntity) {
            $e = new $entity;
            if ($e->fromErp($arEntity)->save()) {
                $result[] = $e->toErp();
            }
        }

        return $result;
    }
}
