<?php
namespace Onizamov\ErpExchange;


class RestCompany extends RestEntity
{

    public function get($query, $n, \CRestServer $server)
    {
        return parent::getList('company');
    }

    public function save($query, $n, \CRestServer $server)
    {
        return parent::update('company');
    }
}
