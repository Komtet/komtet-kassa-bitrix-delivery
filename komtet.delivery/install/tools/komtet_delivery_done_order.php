<?php

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Allow: POST');
    exit();
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/komtet.delivery/include.php";

try {
    KomtetDelivery::doneOrder($_GET['ORDER_ID']);
} catch (\Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log(sprintf('Error updating order: %s', $e->getMessage()));
    exit();
}
