<?php
require_once "$_SERVER[DOCUMENT_ROOT]/bitrix/header.php";

$APPLICATION->SetTitle('Тестовое задание');

$APPLICATION->IncludeComponent(
    'only.digital:trip.slots',
    '',
    []
);

require_once "$_SERVER[DOCUMENT_ROOT]/bitrix/footer.php";
