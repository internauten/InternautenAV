<?php
/**
 * InternautenAV – Cron-Endpoint für DSGVO Upload-Retention-Cleanup.
 *
 * Aufruf über Webserver (täglich via wget/curl):
 *   wget -q "https://shop.example.com/modules/internautenav/cron.php?token=TOKEN" -O /dev/null
 *
 * Aufruf via PHP-CLI (z.B. in einem Shell-Cronjob):
 *   php /path/to/prestashop/modules/internautenav/cron.php --token=TOKEN
 *
 * Den korrekten Token-Wert findest du im PrestaShop-Backoffice unter
 * Module → InternautenAV → Konfiguration → DSGVO Upload-Cleanup → Cron-URL.
 */

if (!defined('_PS_VERSION_')) {
    // Standalone-Aufruf: PrestaShop bootstrappen
    // Suche den PS-Root-Ordner (enthält config/config.inc.php + init.php)
    $psRoot = null;
    $candidates = [
        __DIR__,
        realpath(__DIR__) ?: '',
        getenv('PS_ROOT_DIR') ?: '',
        getenv('PRESTASHOP_ROOT') ?: '',
        isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '',
        isset($_SERVER['CONTEXT_DOCUMENT_ROOT']) ? (string) $_SERVER['CONTEXT_DOCUMENT_ROOT'] : '',
    ];

    $visited = [];
    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $dir = $candidate;
        for ($i = 0; $i < 10; $i++) {
            if (isset($visited[$dir])) {
                break;
            }
            $visited[$dir] = true;
            if (is_file($dir . '/config/config.inc.php') && is_file($dir . '/init.php')) {
                $psRoot = $dir;
                break 2;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }
    }

    if ($psRoot === null || !is_file($psRoot . '/config/config.inc.php')) {
        exit('ERROR: PrestaShop config.inc.php nicht gefunden. PS_ROOT_DIR als Umgebungsvariable setzen.' . PHP_EOL);
    }

    require_once $psRoot . '/config/config.inc.php';
}

if (!defined('_COOKIE_KEY_')) {
    http_response_code(500);
    exit('ERROR: PrestaShop bootstrap incomplete.' . PHP_EOL);
}

// Token aus GET-Parameter oder CLI-Argument lesen
$token = '';
$mode = '';
if (PHP_SAPI === 'cli') {
    foreach (array_slice($_SERVER['argv'], 1) as $arg) {
        if (strncmp($arg, '--token=', 8) === 0) {
            $token = substr($arg, 8);
            continue;
        }
        if (strncmp($arg, '--mode=', 7) === 0) {
            $mode = substr($arg, 7);
        }
    }
} else {
    $token = isset($_GET['token']) ? (string) $_GET['token'] : '';
    $mode = isset($_GET['mode']) ? (string) $_GET['mode'] : '';
}

// Token verifizieren
$expectedToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_cron');
if (!hash_equals($expectedToken, $token)) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
    }
    exit('ERROR: Forbidden – ungültiger Token.' . PHP_EOL);
}

// Modul laden und Cleanup ausführen
$module = Module::getInstanceByName('internautenav');
if (!$module) {
    exit('ERROR: Modul internautenav nicht gefunden oder nicht aktiviert.' . PHP_EOL);
}

if ($mode === 'mark_existing_customers') {
    if (!method_exists($module, 'markExistingCustomersAsVerified')) {
        exit('ERROR: Aktion nicht verfügbar (markExistingCustomersAsVerified).' . PHP_EOL);
    }

    $result = $module->markExistingCustomersAsVerified();
    if (empty($result['success'])) {
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }
        exit('ERROR: ' . (string) ($result['message'] ?? 'Unbekannter Fehler') . PHP_EOL);
    }

    echo 'OK: ' . (string) ($result['message'] ?? 'Abgeschlossen')
        . ' Erstellt: ' . (int) ($result['created'] ?? 0)
        . ', Fehlgeschlagen: ' . (int) ($result['failed'] ?? 0)
        . PHP_EOL;
    exit;
}

if (!method_exists($module, 'runUploadRetentionCleanup')) {
    exit('ERROR: Modulmethode runUploadRetentionCleanup nicht gefunden.' . PHP_EOL);
}

$deleted = $module->runUploadRetentionCleanup(true);
echo 'OK: Cleanup abgeschlossen. Gelöschte Dateien: ' . (int) $deleted . PHP_EOL;
