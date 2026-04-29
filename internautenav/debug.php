<?php
/**
 * Debug-Skript zum Testen des Internautenav-Moduls
 * Aufruf: http://dein-shop.de/modules/internautenav/debug.php
 */

$shopRoot = realpath(__DIR__ . '/../../');
if ($shopRoot === false || !is_file($shopRoot . '/config/config.inc.php')) {
    die('PrestaShop config not found. Expected: ' . __DIR__ . '/../../config/config.inc.php');
}

require_once $shopRoot . '/config/config.inc.php';

if (!defined('_PS_VERSION_')) {
    die('PrestaShop not found');
}

require_once __DIR__ . '/internautenav.php';
require_once __DIR__ . '/classes/MRZValidator.php';

$output = '<pre style="font-family:monospace; padding:20px; background:#f5f5f5;">';

// 1. Modulinstanz prüfen
$module = Module::getInstanceByName('internautenav');
if (!$module) {
    $output .= "ERROR: Modul konnte nicht geladen werden\n";
} else {
    $output .= "[OK] Modul geladen\n";
}

// 2. Konfiguration prüfen
$requiredRefs = Configuration::get('INTERNAUTENAV_REQUIRED_CARRIER_REFS');
$decoded = json_decode($requiredRefs, true);
$output .= "\n[CONFIG] Carrier-References:\n";
$output .= print_r($decoded, true) . "\n";

// 3. Datenbanktruktur prüfen
$dbTableName = _DB_PREFIX_ . 'internautenav_customer_verification';
$tableExists = Db::getInstance()->getRow(
    "SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '" . _DB_NAME_ . "' AND TABLE_NAME = '" . $dbTableName . "'"
);
$output .= "\n[DB] Tabelle " . $dbTableName . ": " . ($tableExists ? "EXISTS" : "MISSING") . "\n";

// 3b. Hook-Registrierung pruefen
$hookName = 'displayCarrierExtraContent';
$idHook = (int) Hook::getIdByName($hookName);
$idModule = (int) Module::getModuleIdByName('internautenav');
$isRegistered = false;
if ($idHook > 0 && $idModule > 0) {
    $isRegistered = (bool) Db::getInstance()->getValue(
        'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module`'
        . ' WHERE `id_hook` = ' . $idHook
        . ' AND `id_module` = ' . $idModule
    );
}
$output .= "[HOOK] " . $hookName . ": id_hook=" . $idHook . ", id_module=" . $idModule
    . ", registered=" . ($isRegistered ? 'YES' : 'NO') . "\n";

// 4. All Carriers auflisten
$output .= "\n[CARRIERS] Alle Versandarten:\n";
$carriers = Carrier::getCarriers((int) Context::getContext()->language->id, true, false, false, null, Carrier::ALL_CARRIERS);
foreach ($carriers as $c) {
    $ref = (int) $c['id_reference'];
    $required = is_array($decoded) && in_array($ref, $decoded) ? " [REQUIRED]" : "";
    $output .= sprintf("  ID %d, Ref %d: %s%s\n", $c['id_carrier'], $ref, $c['name'], $required);
}

// 4b. Hook-Ausgabe je Carrier testen
$output .= "\n[HOOK OUTPUT] displayCarrierExtraContent je Carrier:\n";
if ($module && $isRegistered) {
    foreach ($carriers as $c) {
        $carrierId = (int) $c['id_carrier'];
        $carrierRef = (int) $c['id_reference'];
        $html = $module->hookDisplayCarrierExtraContent(['carrier' => $c]);
        $len = Tools::strlen((string) $html);
        $output .= sprintf("  ID %d, Ref %d => length=%d\n", $carrierId, $carrierRef, $len);
    }
} else {
    $output .= "  SKIPPED (Modul nicht geladen oder Hook nicht registriert)\n";
}

// 5. Test: MRZ Validator
$output .= "\n[TEST] MRZ Validator - Test Case:\n";
$testLine1 = 'IDBFA00008151D<<<<<<<<<<<<<<<<<<<<<<<<<<<<<';
$testLine2 = '7012101F1912311<<<<<<<<<<<<<<<<<<<<<<<<<<';
$result = MrzValidator::validate('eu_pass', $testLine1, $testLine2, '');
$output .= "Result: " . print_r($result, true) . "\n";

$output .= "\n[INFO] Wenn CONFIG leer ist, überprüfe die Modulkonfiguration im Backoffice und wähle mindestens einen Carrier.\n";
$output .= "[INFO] Wenn Tabelle MISSING ist, deinstalliere und reinstalliere das Modul.\n";

$output .= '</pre>';
echo $output;
?>
