<?php

namespace Onizamov\ErpExchange;

class Contact extends Entity implements IEntity
{
    protected $mapping = [
        'phone' => 'Телефон',
        'email' => 'E_mail',
    ];

    public function fromCrm(array $data)
    {
        parent::fromCrm($data);

        $this->loadMultiFields();

        return $this;
    }

    public function save()
    {
        parent::save();

        $this->updateMultiFields();

        return $this;
    }

    private function updateMultiFields()
    {
        $CCrmFieldMulti = new \CCrmFieldMulti();

        $multiFields = [
            'phone' => 'PHONE',
            'email' => 'EMAIL',
        ];


        foreach ($multiFields as $multiField => $typeId) {
            if ($this->$multiField || $this->$multiField !== null) {
                $fields = \CCrmFieldMulti::GetList(
                    ['ID' => 'ASC'],
                    [
                        'ENTITY_ID'         => 'CONTACT',
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
                                'ENTITY_ID'  => 'CONTACT',
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
     * Дополняет объект компании телефонами, email и т.п. из CRM
     *
     * @return void
     */
    private function loadMultiFields()
    {
        $fields = \CCrmFieldMulti::GetList(
            ['ID' => 'ASC'],
            [
                'ENTITY_ID'         => 'CONTACT',
                'CHECK_PERMISSIONS' => 'N',
                'ELEMENT_ID'        => $this->ID,
                'TYPE_ID'           => ['PHONE', 'EMAIL'],
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
    }
}
