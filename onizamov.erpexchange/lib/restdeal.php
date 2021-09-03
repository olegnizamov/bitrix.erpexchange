<?php
namespace Onizamov\ErpExchange;


class RestDeal extends RestEntity
{

    public function get($query, $n, \CRestServer $server)
    {
        return parent::getList('deal');
    }

    public function save($query, $n, \CRestServer $server)
    {
        return parent::update('deal');
    }
}
