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

if ($action === 'get_mrz_form') {
    $carrierId = (int)Tools::getValue('carrier_id', 0);
    
    if ($carrierId <= 0) {
        http_response_code(400);
        exit('Invalid carrier');
    }

    $carrier = new Carrier($carrierId);
    if (!Validate::isLoadedObject($carrier)) {
        http_response_code(404);
        exit('Carrier not found');
    }

    $carrierReference = (int)$carrier->id_reference;
    $requiredRefs = json_decode(Configuration::get('INTERNAUTENAV_REQUIRED_CARRIER_REFS'), true);
    
    if (!is_array($requiredRefs) || !in_array($carrierReference, $requiredRefs, true)) {
        // Kein Formular erforderlich
        exit('');
    }

    // Prüfe Verifikation
    $context = Context::getContext();
    if ($context->customer->isLogged() && $module->isCustomerVerified((int)$context->customer->id)) {
        exit('');
    }
    if (!$context->customer->isLogged() && isset($_SESSION['internautenav_guest_verified']) && $_SESSION['internautenav_guest_verified']) {
        exit('');
    }

    // Lade TPL
    $context->smarty->assign([
        'internautenav_carrier_id' => $carrierId,
        'internautenav_intro' => $module->l('Fuer diese Versandart ist eine Alters- und Identitaetspruefung ueber MRZ erforderlich.'),
        'internautenav_doc_label' => $module->l('Dokumenttyp'),
        'internautenav_doc_ch_id' => $module->l('Schweizer ID (3 Zeilen)'),
        'internautenav_doc_ch_pass' => $module->l('Schweizer Pass (2 Zeilen)'),
        'internautenav_doc_eu_pass' => $module->l('EU Pass (2 Zeilen)'),
        'internautenav_line1_label' => $module->l('MRZ Zeile 1'),
        'internautenav_line2_label' => $module->l('MRZ Zeile 2'),
        'internautenav_line3_label' => $module->l('MRZ Zeile 3 (nur CH ID)'),
        'internautenav_hint' => $module->l('Bitte Zeilen exakt wie im Dokument inkl. < eingeben.'),
    ]);

    header('Content-Type: text/html; charset=utf-8');
    echo $module->fetch('module:internautenav/views/templates/hook/carrier_extra_form.tpl');
    exit;
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
