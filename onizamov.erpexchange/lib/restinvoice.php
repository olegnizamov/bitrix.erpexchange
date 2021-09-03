<?php
namespace Onizamov\ErpExchange;


class RestInvoice extends RestEntity
{

    public function get($query, $n, \CRestServer $server)
    {
        return parent::getList('invoice');
    }

    public function save($query, $n, \CRestServer $server)
    {
        return parent::update('invoice');
    }
}
