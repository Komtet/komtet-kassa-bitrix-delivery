<?php

define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/komtet.delivery/include.php";

try {
    KomtetDelivery::doneOrder($_GET['ORDER_ID']);
} catch (\Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log(sprintf('Error updating order: %s', $e->getMessage()));
    exit();
}

header('HTTP/1.1 200 OK');
exit();
