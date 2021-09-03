<?php

defined('B_PROLOG_INCLUDED') and (B_PROLOG_INCLUDED === true) or die();
defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'onizamov_erpexchange');

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;


if (!$USER->isAdmin()) {
    $APPLICATION->authForm('Nope');
}

Loader::includeModule("onizamov.erpexchange");


$app = Application::getInstance();
$context = $app->getContext();
$request = $context->getRequest();

Loc::loadMessages($context->getServer()->getDocumentRoot() . "/bitrix/modules/main/options.php");
Loc::loadMessages(__FILE__);


$CrmField = new \Onizamov\ErpExchange\CrmField('company');
$crmCompanyFields = $CrmField->listForConfig();
$CrmField = new \Onizamov\ErpExchange\CrmField('contact');
$crmContactFields = $CrmField->listForConfig();
$CrmField = new \Onizamov\ErpExchange\CrmField('deal');
$crmDealFields = $CrmField->listForConfig();
$CrmField = new \Onizamov\ErpExchange\CrmField('invoice');
$crmInvoiceFields = $CrmField->listForConfig();


$savedOptions = json_decode(Option::get(ADMIN_MODULE_NAME, 'options', '[]'), true);

$tabArray = [
    [
        "DIV"   => "company",
        "TAB"   => "Компании",
        "TITLE" => "Компании",
    ],
    [
        "DIV"   => "contact",
        "TAB"   => "Контакты",
        "TITLE" => "Контакты",
    ],
    [
        "DIV"   => "deal",
        "TAB"   => "Сделки",
        "TITLE" => "Сделки",
    ],
    [
        "DIV"   => "invoice",
        "TAB"   => "Счета",
        "TITLE" => "Счета",
    ],
];

$iblocks = CIBlock::GetList([], ['TYPE' => ['lists', 'CRM_PRODUCT_CATALOG']]);

while ($ib = $iblocks->Fetch()) {
    if (!empty($ib['CODE'])) {
        $tabArray[] = [
            "DIV"   => $ib['CODE'],
            "TAB"   => $ib['NAME'],
            "TITLE" => $ib['NAME'],
        ];
        $tabEntityFieldsVar = "crm" . ucfirst($ib['CODE']) . "Fields";
        $ListField = new \Onizamov\ErpExchange\ListField($ib['CODE']);
        $$tabEntityFieldsVar = $ListField->listForConfig();
    }
}

$tabControl = new CAdminTabControl("tabControl", $tabArray);

if (!empty($save) && $request->isPost() && check_bitrix_sessid()) {
    $error = false;

    $data = $request->getPostList();

    foreach ($data as $propName => $propValue) {
        if (is_array($data[$propName])) {
            $array = [];
            foreach ($propValue as $i) {
                $crmCode = isset($i['crm']) ? $i['crm'] : '';
                $erpCode = isset($i['erp']) ? $i['erp'] : '';

                if ($crmCode && $erpCode) {
                    $array[$crmCode] = $erpCode;
                }
            }

            $array = json_encode($array);
            Option::set(ADMIN_MODULE_NAME, $propName, $array);
        }
    }

    if ($data['options']) {
        if ($data['options']['primaryKey']) {
            foreach ($data['options']['primaryKey'] as $propName => $primaryKeyOffset) {
                $primaryKey = '';
                if (isset($data[$propName][$primaryKeyOffset]['erp']) && !empty($data[$propName][$primaryKeyOffset]['erp'])) {
                    $savedOptions[$propName]['primaryKey'] = $data[$propName][$primaryKeyOffset]['erp'];
                }
            }
        }
        Option::set(ADMIN_MODULE_NAME, 'options', json_encode($savedOptions));
    }

    if ($error) {
        CAdminMessage::showMessage('Ошибка сохранения настроек');
    } else {
        CAdminMessage::showMessage([
                                       "MESSAGE" => 'Настройки успешно сохранены',
                                       "TYPE"    => "OK",
                                   ]);
    }
}

$tabControl->begin();
$APPLICATION->AddHeadString('<script type="text/javascript" src="/bitrix/js/main/jquery/jquery-1.7.min.js"></script>');
?>
<style>
    table th,
    table .row td {
        text-align: center !important;
        white-space: nowrap;
    }

    .row input[type=text] {
        width: 100%;
    }

    .hidden {
        display: none
    }
</style>


<form method="post"
      action="<?= sprintf('%s?mid=%s&lang=%s', $request->getRequestedPage(), urlencode($mid), LANGUAGE_ID) ?>">
    <?php
    echo bitrix_sessid_post();

    foreach ($tabArray as $tab) { ?>

        <?
        $tabEntity = $tab["DIV"];
        $tabEntityProperties = $tabEntity . "Properties";
        $tabEntityFieldsVar = "crm" . ucfirst($tabEntity) . "Fields";
        $tabControl->beginNextTab();
        $savedProperties = json_decode(Option::get(ADMIN_MODULE_NAME, $tabEntityProperties, '[]'), true);


        $i = 0;
        ?>
        <tr>
            <th width="40%">
                Поле в Битрикс
            </th>

            <th width="40%">
                Название при экспорте
            </th>
            <th width="20%">
                Первичный ключ
            </th>
        </tr>
    <?php
    foreach ($savedProperties

    as $crmCode => $erpCode) : ?>
        <tr class="row">
            <td>
                <select name="<?= $tabEntityProperties ?>[<?= $i ?>][crm]">
                    <option value=""> -</option>
                    <?php
                    foreach ($$tabEntityFieldsVar as $key => $val) : ?>
                        <option value="<?= $key ?>" <?= $crmCode == $key ? "selected" : "" ?>><?= $val ?></option>
                    <?php
                    endforeach; ?>
                </select>
            </td>

            <td>
                <input type="text" name="<?= $tabEntityProperties ?>[<?= $i ?>][erp]" value="<?= $erpCode ?>"/>
            </td>
            <td>
                <input type="radio" name="options[primaryKey][<?= $tabEntityProperties ?>]"
                       value="<?= $i ?>" <?= $savedOptions[$tabEntityProperties]['primaryKey'] == $erpCode ? "checked" : "" ?> />
            </td>
        </tr>
    <?
    $i++;
    endforeach ?>
        <tr id="<?= $tabEntity ?>Row" class="row hidden">
            <td>
                <select name="<?= $tabEntityProperties ?>[][crm]">
                    <option value=""> -</option>
                    <?php
                    foreach ($$tabEntityFieldsVar as $key => $val) : ?>
                        <option value="<?= $key ?>"><?= $val ?></option>
                    <?php
                    endforeach; ?>
                </select>
            </td>

            <td>
                <input type="text" name="<?= $tabEntityProperties ?>[][erp]"/>
            </td>
            <td>
                <input type="radio" name="options[primaryKey][<?= $tabEntityProperties ?>]"/>
            </td>
        </tr>
        <tr class="heading">
            <td colspan="3">
                <button type="button" class="adm-btn-save" id="add<?= $tabEntity ?>Row">Добавить поле</button>
            </td>
        </tr>


        <script type="text/javascript">
            $(document).ready(function () {
                $('#add<?= $tabEntity ?>Row').click(function () {
                    let row = $('#<?= $tabEntity ?>Row').clone().removeClass('hidden');
                    $('select', row).attr("name", "<?= $tabEntity ?>Properties[" + ($('#<?= $tabEntity ?>_edit_table .row').length + 1) + "][crm]");
                    $('input[type=text]', row).attr("name", "<?= $tabEntity ?>Properties[" + ($('#<?= $tabEntity ?>_edit_table .row').length + 1) + "][erp]");
                    $('input[type=radio]', row).attr("value", $('#<?= $tabEntity ?>_edit_table .row').length + 1);
                    $('#<?= $tabEntity ?>_edit_table .row').last().after(row)
                })
            });
        </script>

    <?
    } ?>

    <?php
    $tabControl->Buttons(); ?>
    <input type="submit" name="save" value="<?= Loc::getMessage("MAIN_SAVE") ?>"
           title="<?= Loc::getMessage("MAIN_OPT_SAVE_TITLE") ?>" class="adm-btn-save"/>
    <?php
    $tabControl->End(); ?>

    <?php
    $tabControl->end();
    ?>
</form>