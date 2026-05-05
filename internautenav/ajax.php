<?php
ob_start();

$shopRoot = null;
$candidates = [
    __DIR__,
    realpath(__DIR__) ?: '',
    dirname(__DIR__),
    dirname(dirname(__DIR__)),
    getenv('PS_ROOT_DIR') ?: '',
    getenv('PRESTASHOP_ROOT') ?: '',
    isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '',
    isset($_SERVER['CONTEXT_DOCUMENT_ROOT']) ? (string) $_SERVER['CONTEXT_DOCUMENT_ROOT'] : '',
    isset($_SERVER['SCRIPT_FILENAME']) ? dirname((string) $_SERVER['SCRIPT_FILENAME']) : '',
];

$visited = [];
foreach ($candidates as $candidate) {
    if ($candidate === '') {
        continue;
    }

    $searchDir = $candidate;
    for ($i = 0; $i < 10; $i++) {
        if (isset($visited[$searchDir])) {
            break;
        }
        $visited[$searchDir] = true;

        if (is_file($searchDir . '/config/config.inc.php') && is_file($searchDir . '/init.php')) {
            $shopRoot = $searchDir;
            break 2;
        }

        $parent = dirname($searchDir);
        if ($parent === $searchDir) {
            break;
        }
        $searchDir = $parent;
    }
}

if ($shopRoot === null) {
    http_response_code(500);
    exit('PrestaShop root not found');
}

require_once $shopRoot . '/config/config.inc.php';
require_once $shopRoot . '/init.php';

if (!defined('_PS_VERSION_')) {
    exit('No direct script access');
}

$module = Module::getInstanceByName('internautenav');
if (!$module) {
    http_response_code(404);
    exit('Module not found');
}

$action = Tools::getValue('action', '');

if (!function_exists('internautenav_json_response')) {
    function internautenav_json_response(array $payload, $statusCode = 200)
    {
        if (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if ($action === 'admin_approve_documents' || $action === 'admin_reject_documents') {
    $orderId = (int) Tools::getValue('id_order', 0);
    $token = (string) Tools::getValue('token', '');
    $expectedToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_admin_action' . $orderId);
    if ($orderId <= 0 || !hash_equals($expectedToken, $token)) {
        internautenav_json_response(['success' => false, 'message' => 'Forbidden'], 403);
    }
    if ($action === 'admin_approve_documents') {
        $result = $module->adminApproveOrderDocuments($orderId);
    } else {
        $result = $module->adminRejectOrderDocuments($orderId);
    }
    internautenav_json_response($result, !empty($result['success']) ? 200 : 400);
}

if ($action === 'download_document') {
    $documentId = (int) Tools::getValue('document_id', 0);
    $orderId = (int) Tools::getValue('id_order', 0);
    $module->serveUploadedDocumentDownload($documentId, $orderId);
}

if ($action === 'validate_upload') {
    $carrierId = (int) Tools::getValue('carrier_id', 0);
    $uploadFile = null;
    if (isset($_FILES['document_upload']) && is_array($_FILES['document_upload'])) {
        $uploadFile = $_FILES['document_upload'];
    }

    $result = $module->validateMrzForCarrier($carrierId, [
        'doc_type' => 'upload',
        'line1' => '',
        'line2' => '',
        'line3' => '',
        'upload_file' => $uploadFile,
    ], true);

    internautenav_json_response($result, !empty($result['valid']) ? 200 : 400);
}

if ($action === 'validate_mrz') {
    $carrierId = (int) Tools::getValue('carrier_id', 0);
    $uploadFile = null;
    if (isset($_FILES['document_upload']) && is_array($_FILES['document_upload'])) {
        $uploadFile = $_FILES['document_upload'];
    }

    $result = $module->validateMrzForCarrier($carrierId, [
        'doc_type' => (string) Tools::getValue('doc_type', ''),
        'line1' => (string) Tools::getValue('line1', ''),
        'line2' => (string) Tools::getValue('line2', ''),
        'line3' => (string) Tools::getValue('line3', ''),
        'upload_file' => $uploadFile,
    ], true);

    internautenav_json_response($result, !empty($result['valid']) ? 200 : 400);
}
?>
