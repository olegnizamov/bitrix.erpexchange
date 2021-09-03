<?php
namespace Onizamov\ErpExchange;


class RestContact extends RestEntity
{

    public function get($query, $n, \CRestServer $server)
    {
        return parent::getList('contact');
    }

    public function save($query, $n, \CRestServer $server)
    {
        return parent::update('contact');
    }
}
