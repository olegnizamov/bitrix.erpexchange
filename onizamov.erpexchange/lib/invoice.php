<?php

namespace Onizamov\ErpExchange;

class Invoice extends Entity implements IEntity
{
    protected $mapping = [
        'products' => 'Товары',
        'UF_CONTACT_ID' => 'ИдКонтакта',
        'UF_COMPANY_ID' => 'ИдКомпании',
        'UF_MYCOMPANY_ID' => 'ИдСобственнойКомпании',
    ];

    protected $productMapping = [
        'PRODUCT_NAME' => 'Наименование',
        'PRODUCT_ID' => 'ИдТовара',
        'PRICE' => 'Цена',
        'QUANTITY' => 'Количество',
        'MEASURE_NAME' => 'ЕдИзмерения',
        'DISCOUNT_PRICE' => 'СуммаСкидки',
        'VAT_RATE' => 'СтавкаНалога',
        'VAT_INCLUDED' => 'ВключаяНалог',
    ];

    public function fromCrm(array $data)
    {
        parent::fromCrm($data);

        $this->loadProducts();
        $this->loadCounterparties();

        return $this;
    }

    private function loadProducts()
    {
        $ProductRows = \CCrmInvoice::GetProductRows($this->ID);

        $products = [];
        if ($ProductRows) {
            foreach ($ProductRows as $arProduct) {
                $product = [];
                foreach ($arProduct as $label => $value) {
                    if (in_array($label, array_keys($this->productMapping))) {
                        $product[$this->productMapping[$label]] = $value;
                    }
                }
                $products[] = $product;
            }
        }

        $this->products = $products;
    }

    private function loadCounterparties()
    {
        $arFields = \CCrmInvoice::GetList(["ID"], ["ID" => $this->ID], false, false, ["*"])->Fetch();

        $this->contactId = $arFields["UF_CONTACT_ID"];
        $this->companyId = $arFields["UF_COMPANY_ID"];
        $this->myCompanyId = $arFields["UF_MYCOMPANY_ID"];
    }

    public function save()
    {
        parent::save();

        $this->updateProducts();

        return $this;
    }

    private function updateProducts()
    {

        $products = [];
        if (is_array($this->products)){
            foreach ($this->products as $product) {
                $fields = [];
                foreach ($this->productMapping as $label) {
                    if (in_array($label, array_keys($product))){
                        $fields[array_flip($this->productMapping)[$label]] = $product[$label];
                    }
                }

                $products[] = $fields;
            }
        }

        if ($products){
            $arFields = \CCrmInvoice::GetList(["ID"], ["ID" => $this->ID], false, false, ["*"])->Fetch();
            $arFields['INVOICE_PROPERTIES'] = [""];
            $arFields['PRODUCT_ROWS'] = $products;

            $invoice = new \CCrmInvoice(false);
            $success = $invoice->Update($this->ID, $arFields);
            if (!$success) {
                throw new \Exception($invoice->LAST_ERROR, 1);
            }
        }

    }
}
