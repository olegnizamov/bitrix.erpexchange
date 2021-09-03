<?php

namespace Onizamov\ErpExchange;


use Onizamov\Reports\Classes\Crm\Company\Company;
use Onizamov\Reports\Classes\Crm\Company\CompanyTable;

interface IEntity
{
    /**
     * Загрузка данных из 1С
     *
     * Инициализирует объект сущности crm из структуры 1С
     *
     * @param array $data
     * @return Entity текущий объект структуры
     */
    public function fromErp(array $data);

    /**
     * Выгрузка данных в 1С
     *
     * Выгружает текущий объект сущности crm в структуру 1С
     *
     * @return array Массив данных в формате 1С
     */
    public function toErp();

    /**
     * Загрузка данных из Битрикс24
     *
     * Инициализирует объект сущности crm из структуры Битрикс24
     *
     * @param array $data
     * @return Entity текущий объект структуры
     */
    public function fromCrm(array $data);

    /**
     * Выгрузка данных в Битрикс24
     *
     * Выгружает текущий объект сущности crm в структуру Битрикс24
     *
     * @return array Массив данных в формате Битрикс24
     */
    public function toCrm();

    /**
     * Сохранение данных в Битрикс24
     *
     * Реализовывает логику сохранения структуры в базе Битрикс
     *
     * @return Entity текущий объект структуры
     */
    public function save();
}

class Entity
{
    protected $mapping = [];

    private $properties = [];

    private $config;

    public function __construct()
    {
        \Bitrix\Main\Loader::includeModule("bizproc");

        if (!$this instanceof IEntity) {
            throw new \Exception("Entity interface is not implemented");
        }

        $this->config = new Config($this);

        foreach ($this->config->mapping as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $this->mapping[$key] = $value;
            }
        }
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
            $reflect = new \ReflectionClass($this);
            $class = $reflect->getShortName();
            $CrmField = new CrmField($class);
            foreach ($mapping as $erpKey => $crmKey) {
                $this->{$crmKey} = $CrmField->getCrmValue($crmKey, $structure[$erpKey]);
            }
        }
        return $this;
    }

    public function toErp(bool $skipNull = true)
    {
        $result = [];
        $reflect = new \ReflectionClass($this);
        $class = $reflect->getShortName();
        $CrmField = new CrmField($class);
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
        $primaryKey = array_flip($this->mapping)[$this->config->options->primaryKey];
        if (empty($data[$primaryKey])) {
            if ((get_class($this) == 'Onizamov\ErpExchange\Company') && ($data['requisiteType'] == 1)) {
                /**
                 *Если в параметре входящего запроса "ТипОрганизации" указано "Юридическое лицо",
                 * то считывать полученные в запросе ИНН и КПП и выполнять поиск имеющихся в CRM компаний по их связке.
                 * В случае нахождения такой записи в справочнике компаний перезаписывать все передаваемые в запросе
                 * данные и в качестве ответа на запрос возвращать БитриксИд найденной компании;
                 * Если по связке ИНН-КПП компании не найдено, то не видоизменять логику модуля,
                 * т.е. создавать новую карточку компании и возвращать её БитриксИд.
                 */
                $requisiteObj = \Bitrix\Crm\RequisiteTable::query()
                    ->setSelect(
                        [
                            'ENTITY_ID',
                            'ID',
                        ]
                    )->where('RQ_INN', $data['inn'])
                    ->where('RQ_KPP', $data['kpp'])
                    ->where('ENTITY_TYPE_ID', \CCrmOwnerType::Company)->fetchObject();

                if (!empty($requisiteObj)) {
                    $id = $requisiteObj->getEntityId();
                }
            } elseif ((get_class($this) == 'Onizamov\ErpExchange\Company') && ($data['requisiteType'] == 3)) {
                /**
                 *Если в параметре входящего запроса "ТипОрганизации" = "Физическое лицо",
                 * то считывать полученный в запросе телефон и выполнять поиск имеющихся в CRM компаний по полю PHONE.
                 * Для оптимизации поиска следует унифицировать телефонный номер: у
                 * бирать обнаруженный пробелы или знаки "-" + обрезать номер на 10 символов справа.
                 * Т.е. номер +79998500655 приводить к виду 9998500655:
                 * Если запись компании по номеру телефона найдена, то перезаписывать все передаваемые в запросе
                 * данные и в качестве ответ на запрос возвращать БитриксИд найденной компании;
                 * Если по номеру телефона такой компании не найдено, то не видоизменять логику модуля,
                 * т.е. создавать новую карточку компании и возвращать её БитриксИд.
                 */
                $phone = substr($data['phone']['WORK'], -10);
                if (!empty($phone)) {
                    $requisiteObj = \Bitrix\Crm\FieldMultiTable::query()
                        ->setSelect(
                            [
                                'ELEMENT_ID',
                                'ID',
                            ]
                        )
                        ->where('TYPE_ID', 'PHONE')
                        ->where('ENTITY_ID', 'COMPANY')
                        ->whereLike('VALUE', '%' . $phone)->fetchObject();

                    if (!empty($requisiteObj)) {
                        $id = $requisiteObj->getElementId();
                    }
                }
            } elseif ((get_class($this) == 'Onizamov\ErpExchange\Company') && ($data['requisiteType'] == 2)) {
                /**
                 * Обработка дублей для ИП. Проверку ИП нужно делать по ИНН + названию карточки контагента.
                 */
                $requisiteObj = \Bitrix\Crm\RequisiteTable::query()
                    ->setSelect(
                        [
                            'ENTITY_ID',
                            'ID',
                        ]
                    )
                    ->where('RQ_INN', $data['inn'])
                    ->where('NAME', $data['TITLE'])
                    ->where('ENTITY_TYPE_ID', \CCrmOwnerType::Company)->fetchObject();

                if (!empty($requisiteObj)) {
                    $id = $requisiteObj->getEntityId();
                }
            }
        }


        if (empty($id)) {
            if (!isset($data[$primaryKey])) {
                throw new \Exception("Не указан первичный ключ", 1);
            }

            if (!empty($data[$primaryKey])) {
                if (method_exists($this->config->db, 'GetListEx')) {
                    $entity = $this->config->db::GetListEx(
                        ["ID"],
                        [$primaryKey => $data[$primaryKey]],
                        false,
                        false,
                        ["ID"]
                    )->Fetch();
                } else {
                    $entity = $this->config->db::GetList(
                        ["ID"],
                        [$primaryKey => $data[$primaryKey]],
                        false,
                        false,
                        ["ID"]
                    )->Fetch();
                }

                if ($entity) {
                    $id = $entity['ID'];
                }
            }
        }


        if (!$id) {
            Logger::log($_SERVER['SCRIPT_URL'], 'создание записи', json_encode($data, JSON_UNESCAPED_UNICODE));
            $id = $this->config->db->Add($data);

            if (!$id) {
                throw new \Exception($this->config->db->LAST_ERROR, 1);
            }
            $execType = \CBPDocumentEventType::Create;
        } else {
            Logger::log(
                $_SERVER['SCRIPT_URL'],
                'обновление записи',
                json_encode(['ID' => $id, 'DATA' => $data], JSON_UNESCAPED_UNICODE)
            );

            if (!$this->config->db->Update($id, $data)) {
                throw new \Exception($this->config->db->LAST_ERROR, 1);
            }
            $execType = \CBPDocumentEventType::Edit;
        }

        $this->ID = (string)$id;

        $className = explode("\\", get_class($this));
        $name = $className[count($className) - 1];
        $templates = \CBPWorkflowTemplateLoader::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            [
                "DOCUMENT_TYPE" => ['crm', 'CCrmDocument' . $name, mb_strtoupper($name)],
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
            $wi = $runtime->CreateWorkflow(
                $template['ID'],
                ['crm', 'CCrmDocument' . $name, mb_strtoupper($name) . '_' . $id],
                []
            );
            $wi->Start();
        }


        return $this;
    }
}
