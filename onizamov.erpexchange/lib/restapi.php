<?php

namespace Onizamov\ErpExchange;

class RestApi
{
   public static function OnRestServiceBuildDescription()
   {
      $rules = [
         'erpexchange' => [
            'erp.exchange.company.get' => [
               'callback' => [new RestCompany, 'get']
            ],
            'erp.exchange.contact.get' => [
               'callback' => [new RestContact, 'get']
            ],
            'erp.exchange.deal.get' => [
               'callback' => [new RestDeal, 'get']
            ],
            'erp.exchange.company.save' => [
               'callback' => [new RestCompany, 'save']
            ],
            'erp.exchange.contact.save' => [
               'callback' => [new RestContact, 'save']
            ],
            'erp.exchange.deal.save' => [
               'callback' => [new RestDeal, 'save']
            ],
            'erp.exchange.invoice.get' => [
                'callback' => [new RestInvoice, 'get']
            ],
            'erp.exchange.invoice.save' => [
               'callback' => [new RestInvoice, 'save']
           ],
         ]
      ];

      $iblocks = \CIBlock::GetList([], ['TYPE'=>['lists', 'CRM_PRODUCT_CATALOG'], "CHECK_PERMISSIONS" => "N"]);

      while($ib = $iblocks->Fetch()){
         if(!empty($ib['CODE'])){
            $rules['erpexchange']['erp.exchange.'.$ib['CODE'].'.get'] = [
               'callback' => [new RestList($ib['CODE']), 'get']
            ];
            $rules['erpexchange']['erp.exchange.'.$ib['CODE'].'.save'] = [
               'callback' => [new RestList($ib['CODE']), 'save']
            ];

            $rules['erpexchange']['erp.exchange.section.'.$ib['CODE'].'.get'] = [
               'callback' => [new RestSection($ib['CODE']), 'get']
            ];
            $rules['erpexchange']['erp.exchange.section.'.$ib['CODE'].'.save'] = [
               'callback' => [new RestSection($ib['CODE']), 'save']
            ];
         }
      }
      return $rules;
   }
}
