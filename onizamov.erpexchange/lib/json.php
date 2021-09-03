<?php
namespace Onizamov\ErpExchange;

class Json {

    public $document = [];

    public function __construct(string $document) {
        $this->document = json_decode($document, true);
        if(json_last_error() != JSON_ERROR_NONE){
            throw new \Exception("Не корректный JSON документ", 1);   
        }
    } 
}