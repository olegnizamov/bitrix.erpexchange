<?php

namespace Onizamov\ErpExchange;

use \Bitrix\Crm\EntityRequisite;

class Company extends Entity implements IEntity
{
    protected $mapping = [
        'phone'         => 'Телефон',
        'email'         => 'E_mail',
        'site'          => 'Сайт',
        'messenger'     => 'Мессенджер',
        'inn'           => 'ИНН',
        'kpp'           => 'КПП',
        'ogrn'          => 'ОГРН',
        'okpo'          => 'ОКПО',
        'account'       => 'ГлавныйБухгалтер',
        'director'      => 'ГенеральныйДиректор',
        'shortName'     => 'КраткоеНаименованиеОрганизации',
        'fullName'      => 'ПолноеНаименованиеОрганизации',
        'requisiteType' => 'ТипРеквизитов',

        'bankName'     => 'НаименованиеБанка',
        'bankbik'      => 'БИК',
        'bankRSchet'   => 'РасчетныйСчет',
        'bankKorSchet' => 'КорСчет',
        'bankCurrency' => 'Валюта',
        'bankAddress'  => 'АдресБанка',
        'bankSwift'    => 'Свифт',
        'bankComment'  => 'КомментарийБанка',

        'factAdr' => 'ФактическийАдрес',
        'yurAdr'  => 'ЮридическийАдрес',
    ];

    protected $addressMapping = [
        "ADDRESS_1"   => "УлицаДомКорпус",
        "ADDRESS_2"   => "КвартираОфис",
        "CITY"        => "Город",
        "REGION"      => "Район",
        "PROVINCE"    => "Область",
        "POSTAL_CODE" => "ПочтовыйИндекс",
        "COUNTRY"     => "Страна",
    ];

    public function fromCrm(array $data)
    {
        parent::fromCrm($data);

        $this->loadRequesite();

        $this->loadMultiFields();

        return $this;
    }

    public function save()
    {
        parent::save();

        $this->updateRequisite();

        $this->updateMultiFields();

        return $this;
    }

    private function updateRequisite()
    {
        $requisite = new EntityRequisite();

        if ($this->requisiteType) {
            $req = $requisite->getList(
                ['filter' => ['ENTITY_ID' => $this->ID, 'ENTITY_TYPE_ID' => \CCrmOwnerType::Company]]
            )->fetch();
            if ($req) {
                $requisite->delete($req['ID']);
            }

            $fields = $req;

            $fields['ENTITY_ID'] = $this->ID;
            $fields['ENTITY_TYPE_ID'] = \CCrmOwnerType::Company;

            if ($this->fullName || $this->fullName !== null) {
                $fields['NAME'] = $this->fullName;
            }

            $fields['NAME'] = $this->TITLE ?: '-';

            if ($this->inn || $this->inn !== null) {
                $fields['RQ_INN'] = $this->inn;
            }

            if ($this->kpp || $this->kpp !== null) {
                $fields['RQ_KPP'] = $this->kpp;
            }

            if ($this->shortName || $this->shortName !== null) {
                $fields['RQ_COMPANY_NAME'] = $this->shortName;
            }

            if ($this->fullName || $this->fullName !== null) {
                $fields['RQ_COMPANY_FULL_NAME'] = $this->fullName;
            }

            if ($this->ogrn || $this->ogrn !== null) {
                $fields['RQ_OGRN'] = $this->ogrn;
            }
            if ($this->okpo || $this->okpo !== null) {
                $fields['RQ_OKPO'] = $this->okpo;
            }
            if ($this->director || $this->director !== null) {
                $fields['RQ_DIRECTOR'] = $this->director;
            }
            if ($this->account || $this->account !== null) {
                $fields['RQ_ACCOUNTANT'] = $this->account;
            }

            $addresses = [];
            $addressMap = array_flip($this->addressMapping);
            if ($this->factAdr) {
                $address = [];
                foreach ($this->factAdr as $code => $value) {
                    if (in_array($code, array_keys($addressMap))) {
                        $address[$addressMap[$code]] = $value;
                    }
                }
                $addresses["1"] = $address;
            }

            if ($this->yurAdr) {
                $address = [];
                foreach ($this->yurAdr as $code => $value) {
                    if (in_array($code, array_keys($addressMap))) {
                        $address[$addressMap[$code]] = $value;
                    }
                }
                $addresses["6"] = $address;
            }


            if ($addresses) {
                $fields['RQ_ADDR'] = $addresses;
            }

            $fields['ADDRESS_ONLY'] = 'N';
            $fields['PRESET_ID'] = $this->requisiteType;
            $reqRes = $requisite->add($fields);
            if (!$reqRes->isSuccess()) {
                throw new \Exception(implode(";", $reqRes->getErrorMessages()), 402);
            } else {
                $bank = new \Bitrix\Crm\EntityBankDetail();
                $bankRes = $bank->getList(['filter' => ['ENTITY_ID' => $req['ID']]])->fetch();

                if ($bankRes) {
                    $bank->delete($bankRes['ID']);
                }

                $bankFields = [];

                if ($this->bankName || $this->bankName !== null) {
                    $bankFields['RQ_BANK_NAME'] = $this->bankName;
                    $bankFields['NAME'] = $this->bankName;
                    $bankFields['ENTITY_TYPE_ID'] = 8;
                    $bankFields['ENTITY_ID'] = $reqRes->getId();

                    if ($this->bankbik || $this->bankbik !== null) {
                        $bankFields['RQ_BIK'] = $this->bankbik;
                    }
                    if ($this->bankRSchet || $this->bankRSchet !== null) {
                        $bankFields['RQ_ACC_NUM'] = $this->bankRSchet;
                    }
                    if ($this->bankKorSchet || $this->bankKorSchet !== null) {
                        $bankFields['RQ_COR_ACC_NUM'] = $this->bankKorSchet;
                    }
                    if ($this->bankCurrency || $this->bankCurrency !== null) {
                        $bankFields['RQ_ACC_CURRENCY'] = $this->bankCurrency;
                    }
                    if ($this->bankAddress || $this->bankAddress !== null) {
                        $bankFields['RQ_BANK_ADDR'] = $this->bankAddress;
                    }
                    if ($this->bankSwift || $this->bankSwift !== null) {
                        $bankFields['RQ_SWIFT'] = $this->bankSwift;
                    }
                    if ($this->bankComment || $this->bankComment !== null) {
                        $bankFields['COMMENTS'] = $this->bankComment;
                    }
                }

                if ($bankFields) {
                    $bankResult = $bank->add($bankFields);
                    if (!$bankResult->isSuccess()) {
                        throw new \Exception(implode(";", $bankResult->getErrorMessages()), 402);
                    }
                }
            }
        } else {
            throw new \Exception("Не передан обязательный параметр " . $this->mapping['requisiteType'], 402);
        }
    }

    private function updateMultiFields()
    {
        $CCrmFieldMulti = new \CCrmFieldMulti();

        $multiFields = [
            'phone'     => 'PHONE',
            'email'     => 'EMAIL',
            'site'      => 'WEB',
            'messenger' => 'IM',
        ];

        foreach ($multiFields as $multiField => $typeId) {
            if ($this->$multiField || $this->$multiField !== null) {
                $fields = \CCrmFieldMulti::GetList(
                    ['ID' => 'ASC'],
                    [
                        'ENTITY_ID'         => 'COMPANY',
                        'CHECK_PERMISSIONS' => 'N',
                        'ELEMENT_ID'        => $this->ID,
                        'TYPE_ID'           => $typeId,
                    ]
                );

                while ($field = $fields->fetch()) {
                    $CCrmFieldMulti->delete($field["ID"]);
                }

                foreach ($this->$multiField as $type => $values) {
                    $fieldArr = explode(';', $values);
                    foreach ($fieldArr as $item) {
                        if ($item) {
                            $fields = [
                                'ENTITY_ID'  => 'COMPANY',
                                'ELEMENT_ID' => $this->ID,
                                'TYPE_ID'    => $typeId,
                                'VALUE_TYPE' => $type,
                                'VALUE'      => $item,
                            ];

                            $CCrmFieldMulti->add($fields);
                        }
                    }
                }
            }
        }
    }

    /**
     * Дополняет объект компании реквизитами из CRM
     *
     * @return void
     */
    private function loadRequesite()
    {
        $requesite = new EntityRequisite();

        $req = $requesite->getList(
            ['filter' => ['ENTITY_ID' => $this->ID, 'ENTITY_TYPE_ID' => \CCrmOwnerType::Company]]
        )->fetch();
        if ($req) {
            $this->inn = $req['RQ_INN'];
            $this->kpp = $req['RQ_KPP'];
            $this->shortName = $req['RQ_COMPANY_NAME'];
            $this->fullName = $req['RQ_COMPANY_FULL_NAME'];
            $this->ogrn = $req['RQ_OGRN'];
            $this->okpo = $req['RQ_OKPO'];
            $this->director = $req['RQ_DIRECTOR'];
            $this->account = $req['RQ_ACCOUNTANT'];
            $this->requisiteType = $req['PRESET_ID'];

            /**
             * Получить банк Компании
             * @return object DB\Result
             */
            $bank = new \Bitrix\Crm\EntityBankDetail();
            $bankRes = $bank->getList(['filter' => ['ENTITY_ID' => $req['ID']]])->fetch();

            $this->bankName = $bankRes['RQ_BANK_NAME'];
            $this->bankbik = $bankRes['RQ_BIK'];
            $this->bankRSchet = $bankRes['RQ_ACC_NUM'];
            $this->bankKorSchet = $bankRes['RQ_COR_ACC_NUM'];
            $this->bankCurrency = $bankRes['RQ_ACC_CURRENCY'];
            $this->bankAddress = $bankRes['RQ_BANK_ADDR'];
            $this->bankSwift = $bankRes['RQ_SWIFT'];
            $this->bankComment = $bankRes['COMMENTS'];


            $addresses = \Bitrix\Crm\EntityRequisite::getAddresses($req['ID']);
            if ($addresses) {
                foreach ($addresses as $type => $address) {
                    $fields = [];
                    foreach ($address as $code => $value) {
                        if (in_array($code, array_keys($this->addressMapping))) {
                            $fields[$this->addressMapping[$code]] = $value;
                        }
                    }

                    switch ($type) {
                        case "1":
                            $this->factAdr = $fields;
                            break;

                        case "6":
                            $this->yurAdr = $fields;
                            break;
                    }
                }
            }
        }
    }

    /**
     * Дополняет объект компании телефонами, email и т.п. из CRM
     *
     * @return void
     */
    private function loadMultiFields()
    {
        $fields = \CCrmFieldMulti::GetList(
            ['ID' => 'ASC'],
            [
                'ENTITY_ID'         => 'COMPANY',
                'CHECK_PERMISSIONS' => 'N',
                'ELEMENT_ID'        => $this->ID,
                'TYPE_ID'           => ['PHONE', 'EMAIL', 'WEB', 'IM'],
            ]
        );

        while ($field = $fields->fetch()) {
            switch ($field['TYPE_ID']) {
                case 'PHONE':
                    $phone[$field['VALUE_TYPE']][] = $field['VALUE'];
                    break;
                case 'EMAIL':
                    $email[$field['VALUE_TYPE']][] = $field['VALUE'];
                    break;
                case 'WEB':
                    $site[$field['VALUE_TYPE']][] = $field['VALUE'];
                    break;
                case 'IM':
                    $messenger[$field['VALUE_TYPE']][] = $field['VALUE'];
                    break;
            }
        }

        foreach ($phone as $type => $phones) {
            $ph[$type] = trim(implode(';', $phones), ';');
        }
        $this->phone = $ph;

        foreach ($email as $type => $emails) {
            $em[$type] = trim(implode(';', $emails), ';');
        }
        $this->email = $em;

        foreach ($site as $type => $sites) {
            $st[$type] = trim(implode(';', $sites), ';');
        }
        $this->site = $st;

        foreach ($messenger as $type => $messengers) {
            $ms[$type] = trim(implode(';', $messengers), ';');
        }
        $this->messenger = $ms;
    }
}
