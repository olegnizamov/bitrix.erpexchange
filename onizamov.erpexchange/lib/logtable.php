<?php

namespace Onizamov\Erpexchange;

use Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField,
    Bitrix\Main\ORM\Fields\TextField,
    Bitrix\Main\ORM\Fields\DatetimeField,
    Bitrix\Main\ORM\Fields\StringField;


/**
 * Class LogTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> date_create date_time
 * <li> url string
 * <li> request_type string
 * <li> request text
 * </ul>
 * @package Onizamov\Erpexchange
 **/
class LogTable extends DataManager
{
    /**
     * Возвращает название таблицы.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'erpexchange_log';
    }

    /**
     * Возвращает карту сущности.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new IntegerField(
                'id',
                [
                    'primary'      => true,
                    'autocomplete' => true,
                    'title'        => 'id',
                ]
            ),
            new DatetimeField(
                'UF_DATE_CREATE',
                [
                    'required' => true,
                    'title'    => 'UF_DATE_CREATE',
                ]
            ),

            new StringField(
                'UF_URL',
                [
                    'required' => true,
                    'title'    => 'UF_URL',
                ]
            ),
            new StringField(
                'UF_REQUEST_TYPE',
                [
                    'required' => true,
                    'title'    => 'UF_REQUEST_TYPE',
                ]
            ),
            new TextField(
                'UF_REQUEST',
                [
                    'title' => 'UF_REQUEST',
                ]
            ),
        ];
    }
}