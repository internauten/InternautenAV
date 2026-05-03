<?php

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
        http_response_code((int) $statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if ($action === 'validate_mrz') {
    $carrierId = (int) Tools::getValue('carrier_id', 0);
    $result = $module->validateMrzForCarrier($carrierId, [
        'doc_type' => (string) Tools::getValue('doc_type', ''),
        'line1' => (string) Tools::getValue('line1', ''),
        'line2' => (string) Tools::getValue('line2', ''),
        'line3' => (string) Tools::getValue('line3', ''),
    ], true);

    internautenav_json_response($result, !empty($result['valid']) ? 200 : 400);
}
?>
