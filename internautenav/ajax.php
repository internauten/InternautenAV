<?php

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';

if (!defined('_PS_VERSION_')) {
    exit('No direct script access');
}

$module = Module::getInstanceByName('internautenav');
if (!$module) {
    http_response_code(404);
    exit('Module not found');
}

$action = Tools::getValue('action', '');

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
}
?>
