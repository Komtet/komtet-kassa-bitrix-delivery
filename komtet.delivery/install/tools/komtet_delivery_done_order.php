<?php

define('NO_KEEP_STATISTIC', 'Y');
define('NO_AGENT_STATISTIC', 'Y');
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/komtet.delivery/include.php';

use Bitrix\Main\Context;

const MODULE_ID = 'komtet.delivery';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$requestData = file_get_contents('php://input');

$request = Context::getCurrent()->getRequest();
$requestHeaders = $request->getHeaders()->toArray();
$requestSignature = $request->getHeader('X-HMAC-Signature') ?? '';

$orderBody = json_decode($requestData, true);
$callbackUrl = $orderBody['callback_url'];

$secretKey = COption::GetOptionString(MODULE_ID, 'secret_key');

if (empty($secretKey)) {
    CEventLog::Add([
        'SEVERITY'      => 'ERROR',
        'AUDIT_TYPE_ID' => 'WEBHOOK_ERROR',
        'MODULE_ID'     => MODULE_ID,
        'DESCRIPTION'   => 'КомтетКасса: секретный ключ не задан в настройках плагина ' . MODULE_ID . ', выход'
    ]);
    http_response_code(403);
    exit();
}

$expectedSignature = hash_hmac('md5', 'POST' . $callbackUrl . $requestData, $secretKey);

$isSignatureValid = hash_equals($expectedSignature, $requestSignature);

CEventLog::Add([
    'SEVERITY'      => 'INFO',
    'AUDIT_TYPE_ID' => 'WEBHOOK_DEBUG',
    'MODULE_ID'     => MODULE_ID,
    'ITEM_ID'       => $_GET['ORDER_ID'],
    'DESCRIPTION'   => "КомтетКасса: аудит отчета о доставке заказа\n" .
                       ($isSignatureValid ? "Подпись валидна, обработка заказа" : "Подпись не валидна, выход") . "\n" .
                       "Headers: " . print_r($requestHeaders, true) . "\n" .
                       "RAW input: " . htmlspecialchars($requestData, ENT_QUOTES, 'UTF-8')
]);

if (!$isSignatureValid) {
    http_response_code(403);
    exit();
}

try {
    KomtetDelivery::doneOrder((int)$_GET['ORDER_ID']);
} catch (\Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log(sprintf('Error updating order: %s', $e->getMessage()));
    exit();
}

header('HTTP/1.1 200 OK');
exit();
