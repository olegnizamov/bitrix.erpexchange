<?php

namespace Onizamov\ErpExchange;


class Deal extends Entity implements IEntity
{
    protected $mapping = [
        'products' => 'Товары',
    ];

    protected $productMapping = [
        'ORIGINAL_PRODUCT_NAME' => 'Наименование',
        'PRODUCT_ID'            => 'ИдТовара',
        'PRICE'                 => 'Цена',
        'QUANTITY'              => 'Количество',
        'MEASURE_NAME'          => 'ЕдИзмерения',
        'DISCOUNT_SUM'          => 'СуммаСкидки',
        'TAX_RATE'              => 'СтавкаНалога',
        'TAX_INCLUDED'          => 'ВключаяНалог',
    ];

    public function fromCrm(array $data)
    {
        parent::fromCrm($data);

        $this->loadProducts();

        return $this;
    }

    private function loadProducts()
    {
        $ProductRows = \CCrmProductRow::GetList([], ["OWNER_ID" => $this->ID]);

        $products = [];
        while ($arProduct = $ProductRows->GetNext()) {
            $product = [];
            foreach ($arProduct as $label => $value) {
                if (in_array($label, array_keys($this->productMapping))) {
                    $product[$this->productMapping[$label]] = $value;
                }
            }
            $products[] = $product;
        }

        $this->products = $products;
    }

    public function save()
    {
        parent::save();

        $this->updateProducts();

        return $this;
    }

    private function updateProducts()
    {
        if (is_array($this->products)) {
            $ProductRows = \CCrmProductRow::GetList([], ["OWNER_ID" => $this->ID]);

            while ($arProduct = $ProductRows->GetNext()) {
                \CCrmProductRow::Delete($arProduct["ID"], false);
            }

            $products = [];
            foreach ($this->products as $product) {
                $fields = [];
                foreach ($this->productMapping as $label) {
                    if (in_array($label, array_keys($product))) {
                        $fields[array_flip($this->productMapping)[$label]] = $product[$label];
                    }
                }
                if (isset($product['Цена'])) {
                    $fields["PRICE_NETTO"] = $product['Цена'];
                    $fields["PRICE_BRUTTO"] = $product['Цена'];
                    $fields["PRICE_EXCLUSIVE"] = $product['Цена'];
                }
//                $fields["OWNER_ID"] = $this->ID;
//                $fields["OWNER_TYPE"] = "D";
                $products[] = $fields;

//                $prodRow = new \CCrmProductRow();
//                $prodRow->Add($fields, false);
            }

            if ($products) {
                \CCrmProductRow::SaveRows('D', $this->ID, $products);
            }
        }
    }
}
