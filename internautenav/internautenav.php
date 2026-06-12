<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$mrzValidatorPath = __DIR__ . '/classes/MRZValidator.php';
if (!is_file($mrzValidatorPath)) {
    $mrzValidatorPath = __DIR__ . '/classes/MrzValidator.php';
}
require_once $mrzValidatorPath;

class Internautenav extends Module
{
    public const CONF_REQUIRED_CARRIER_REFS = 'INTERNAUTENAV_REQUIRED_CARRIER_REFS';
    public const CONF_LAST_UPLOAD_CLEANUP_AT = 'INTERNAUTENAV_LAST_UPLOAD_CLEANUP_AT';
    public const CONF_PRIVACY_CMS_ID = 'INTERNAUTENAV_PRIVACY_CMS_ID';
    public const DB_TABLE = 'internautenav_customer_verification';
    public const DB_LOG_TABLE = 'internautenav_verification_log';
    public const DB_UPLOAD_TABLE = 'internautenav_uploaded_documents';
    private const UPLOAD_RETENTION_DAYS = 90;
    private const UPLOAD_CLEANUP_MIN_INTERVAL_SECONDS = 21600;
    private const SESSION_VERIFICATION_KEY = 'internautenav_checkout_verification';

    public function __construct()
    {
        $this->name = 'internautenav';
        $this->tab = 'shipping_logistics';
        $this->version = '2.0.1';
        $this->author = 'die.internauten.ch';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Internauten AV');
        $this->description = $this->l('MRZ-Verifikation fuer ausgewaehlte Versandarten (CH ID, CH Pass, EU Pass, Upload).');
        $this->ps_versions_compliancy = [
            'min' => '1.7.8.0',
            'max' => _PS_VERSION_,
        ];

        if ((int) $this->id > 0 && !$this->isRegisteredInHook('displayAdminCustomers')) {
            $this->registerHook('displayAdminCustomers');
        }
        if ((int) $this->id > 0 && !$this->isRegisteredInHook('displayPDFInvoice')) {
            $this->registerHook('displayPDFInvoice');
        }
        if ((int) $this->id > 0 && !$this->isRegisteredInHook('displayPDFDeliverySlip')) {
            $this->registerHook('displayPDFDeliverySlip');
        }
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayPaymentTop')
            && $this->registerHook('displayCustomerAccount')
            && $this->registerHook('displayAdminCustomers')
            && $this->registerHook('displayPDFInvoice')
            && $this->registerHook('displayPDFDeliverySlip')
            && $this->registerHook('actionCarrierProcess')
            && $this->registerHook('actionValidateStepComplete')
            && $this->registerHook('actionValidateOrder')
            && $this->registerHook('displayAdminOrderMainBottom')
            && $this->registerHook('actionCronJob')
            && $this->registerHook('displayAdminOrderTop')
            && $this->installDatabase()
            && Configuration::updateValue(self::CONF_REQUIRED_CARRIER_REFS, json_encode([]))
            && Configuration::updateValue(self::CONF_PRIVACY_CMS_ID, '0')
            && Configuration::updateValue(self::CONF_LAST_UPLOAD_CLEANUP_AT, '0');
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CONF_REQUIRED_CARRIER_REFS)
            && Configuration::deleteByName(self::CONF_PRIVACY_CMS_ID)
            && Configuration::deleteByName(self::CONF_LAST_UPLOAD_CLEANUP_AT)
            && $this->uninstallDatabase()
            && parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitInternautenavConfig')) {
            $selectedRefs = Tools::getValue('INTERNAUTENAV_REQUIRED_CARRIER_REFS', []);
            if (!is_array($selectedRefs)) {
                $selectedRefs = [];
            }
            $privacyCmsId = (int) Tools::getValue('INTERNAUTENAV_PRIVACY_CMS_ID', 0);
            if ($privacyCmsId < 0) {
                $privacyCmsId = 0;
            }

            $selectedRefs = array_values(array_unique(array_map('intval', $selectedRefs)));
            Configuration::updateValue(self::CONF_REQUIRED_CARRIER_REFS, json_encode($selectedRefs));
            Configuration::updateValue(self::CONF_PRIVACY_CMS_ID, (string) $privacyCmsId);
            $output .= $this->displayConfirmation($this->l('backoffice_settings_saved'));
        }

        if (Tools::isSubmit('submitInternautenavCleanup')) {
            $deleted = $this->runUploadRetentionCleanup(true);
            if ($deleted > 0) {
                $output .= $this->displayConfirmation(sprintf(
                    $this->l('%d Datei(en) aelter als %d Tage wurden geloescht.'),
                    $deleted,
                    self::UPLOAD_RETENTION_DAYS
                ));
            } else {
                $output .= $this->displayConfirmation($this->l('Keine abgelaufenen Dateien gefunden.'));
            }
        }

        if (Tools::isSubmit('submitInternautenavCleanupPending')) {
            $deleted = $this->deletePendingUploads();
            if ($deleted > 0) {
                $output .= $this->displayConfirmation(sprintf(
                    $this->l('%d Datei(en) ohne abgeschlossene Altersprüfung wurden geloescht.'),
                    $deleted
                ));
            } else {
                $output .= $this->displayConfirmation($this->l('Keine ausstehenden Dateien gefunden.'));
            }
        }

        $current = $this->getRequiredCarrierReferences();
        $privacyCmsId = (int) Configuration::get(self::CONF_PRIVACY_CMS_ID);
        $privacyCmsStatusClass = 'text-muted';
        $privacyCmsStatusMessage = $this->l('Beispielseite aktiv (keine CMS-ID gesetzt).');
        if ($privacyCmsId > 0) {
            $privacyCms = new CMS($privacyCmsId, (int) $this->context->language->id);
            if (Validate::isLoadedObject($privacyCms) && (int) $privacyCms->active === 1) {
                $privacyCmsStatusClass = 'text-success';
                $privacyCmsStatusMessage = sprintf($this->l('CMS-Seite #%d ist gueltig und aktiv.'), $privacyCmsId);
            } else {
                $privacyCmsStatusClass = 'text-danger';
                $privacyCmsStatusMessage = sprintf($this->l('CMS-Seite #%d ist ungueltig oder inaktiv. Fallback auf Beispielseite.'), $privacyCmsId);
            }
        }
        $carriers = Carrier::getCarriers(
            (int) $this->context->language->id,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS
        );

        $token = Tools::getAdminTokenLite('AdminModules');
        $action = htmlspecialchars(
            $this->context->link->getAdminLink('AdminModules', true, [], [
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name,
            ]),
            ENT_QUOTES,
            'UTF-8'
        );

        $output .= '<div class="panel">';
        $output .= '<h3>' . $this->l('backoffice_title') . '</h3>';
        $output .= '<p>' . $this->l('backoffice_description') . '</p>';
        $output .= '<form method="post" action="' . $action . '">';
        $output .= '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        $output .= '<div class="form-group">';
        $output .= '<label>' . $this->l('backoffice_label') . '</label>';
        $output .= '<select name="INTERNAUTENAV_REQUIRED_CARRIER_REFS[]" class="form-control" multiple size="10">';

        foreach ($carriers as $carrierRow) {
            $idRef = (int) $carrierRow['id_reference'];
            $idCarrier = (int) $carrierRow['id_carrier'];
            $label = sprintf('#%d / Ref %d - %s', $idCarrier, $idRef, $carrierRow['name']);
            $selected = in_array($idRef, $current, true) ? ' selected' : '';
            $output .= '<option value="' . $idRef . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }

        $output .= '</select>';
        $output .= '<p class="help-block">' . $this->l('backoffice_help') . '</p>';
        $output .= '</div>';
        $cmsPages = CMS::listCms((int) $this->context->language->id, false, true);
        if (!is_array($cmsPages)) {
            $cmsPages = [];
        }
        $output .= '<div class="form-group">';
        $output .= '<label>' . $this->l('Datenschutzerklaerung (CMS-Seite)') . '</label>';
        $output .= '<select name="INTERNAUTENAV_PRIVACY_CMS_ID" class="form-control">';
        $output .= '<option value="0"' . ($privacyCmsId === 0 ? ' selected' : '') . '>— ' . $this->l('Modul-Beispielseite verwenden') . ' —</option>';
        foreach ($cmsPages as $cmsPage) {
            $cmsPageId = (int) $cmsPage['id_cms'];
            $cmsPageTitle = htmlspecialchars((string) $cmsPage['meta_title'], ENT_QUOTES, 'UTF-8');
            $sel = ($privacyCmsId === $cmsPageId) ? ' selected' : '';
            $output .= '<option value="' . $cmsPageId . '"' . $sel . '>#' . $cmsPageId . ' – ' . $cmsPageTitle . '</option>';
        }
        $output .= '</select>';
        $output .= '<p class="help-block"><strong>' . $this->l('Status') . ':</strong> <span class="' . $privacyCmsStatusClass . '">' . htmlspecialchars($privacyCmsStatusMessage, ENT_QUOTES, 'UTF-8') . '</span></p>';
        $output .= '</div>';
        $output .= '<button type="submit" name="submitInternautenavConfig" class="btn btn-primary">' . $this->l('backoffice_save_button') . '</button>';
        $output .= '</form>';
        $output .= '</div>';

        // --- Verification log panel ---
        $this->ensureVerificationLogTable();

        $logRows = Db::getInstance()->executeS(
            'SELECT l.*, c.firstname, c.lastname, c.email
             FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '` l
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = l.id_customer
             ORDER BY l.checked_at DESC
             LIMIT 200'
        );
        if (!is_array($logRows)) {
            $logRows = [];
        }

        $persistRows = Db::getInstance()->executeS(
            'SELECT v.*, c.firstname, c.lastname, c.email
             FROM `' . _DB_PREFIX_ . self::DB_TABLE . '` v
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = v.id_customer
             ORDER BY v.verified_at DESC
             LIMIT 100'
        );
        if (!is_array($persistRows)) {
            $persistRows = [];
        }

        // Helper to emit a td safely
        $td = static function ($val, $extra = '') {
            return '<td' . ($extra ? ' ' . $extra : '') . '>' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</td>';
        };

        // --- Attempt log ---
        $output .= '<div class="panel">';
        $output .= '<h3>' . $this->l('debug_log_title') . '</h3>';
        $output .= '<div style="overflow-x:auto">';
        $output .= '<table class="table table-bordered table-striped" style="font-size:12px">';
        $output .= '<thead><tr>';
        foreach ([
            $this->l('debug_log_col_id'),
            $this->l('debug_log_col_timestamp'),
            $this->l('debug_log_col_reference'),
            $this->l('debug_log_col_customer'),
            $this->l('debug_log_col_cart'),
            $this->l('debug_log_col_doc'),
            $this->l('debug_log_col_result'),
            $this->l('debug_log_col_message'),
        ] as $th) {
            $output .= '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        if (empty($logRows)) {
            $output .= '<tr><td colspan="8" class="text-center text-muted">' . $this->l('debug_log_empty') . '</td></tr>';
        }

        foreach ($logRows as $row) {
            $isOk = (int) $row['result'] === 1;
            $rowStyle = $isOk ? 'background:#dff0d8' : 'background:#f2dede';
            $customerName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
            if ($customerName === '' && (int) ($row['id_guest'] ?? 0) > 0) {
                $customerName = 'Guest #' . $row['id_guest'];
            }
            $email = $row['email'] ?? '';
            $customerDisplay = $customerName . ($email ? ' <' . $email . '>' : '');

            $output .= '<tr style="' . $rowStyle . '">';
            $output .= $td($row['id_internautenav_verification_log']);
            $output .= $td($row['checked_at']);
            $output .= $td($row['customer_reference']);
            $output .= '<td>' . htmlspecialchars($customerDisplay, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= $td($row['id_cart'] ?? '');
            $output .= $td($row['doc_type']);
            $output .= '<td style="font-weight:bold">' . ($isOk ? '&#10003; ' . $this->l('debug_log_result_ok') : '&#10007; ' . $this->l('debug_log_result_fail')) . '</td>';
            $output .= $td($row['result_message'] ?? '');
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';
        $output .= '</div>';

        // --- Persistent verifications ---
        $output .= '<div class="panel">';
        $output .= '<h3>' . $this->l('debug_persistent_title') . '</h3>';
        $output .= '<div style="overflow-x:auto">';
        $output .= '<table class="table table-bordered table-striped" style="font-size:12px">';
        $output .= '<thead><tr>';
        foreach ([
              $this->l('debug_persistent_col_id'),
              $this->l('debug_persistent_col_customer_id'),
              $this->l('debug_persistent_col_name'),
              $this->l('debug_persistent_col_email'),
              $this->l('debug_persistent_col_doc'),
              $this->l('debug_persistent_col_birth'),
              $this->l('debug_persistent_col_verified'),
        ] as $th) {
            $output .= '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        if (empty($persistRows)) {
            $output .= '<tr><td colspan="7" class="text-center text-muted">' . $this->l('debug_log_empty') . '</td></tr>';
        }

        foreach ($persistRows as $row) {
            $output .= '<tr>';
            $output .= $td($row['id_internautenav_customer_verification'] ?? $row['id'] ?? '');
            $output .= $td($row['id_customer']);
            $output .= $td(trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')));
            $output .= $td($row['email'] ?? '');
                $output .= $td($row['doc_type'] ?? '');
                $output .= $td($row['birth_date'] ?? '');
                $output .= $td($row['verified_at'] ?? '');
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';
        $output .= '</div>';

        // --- DSGVO Upload-Cleanup Panel ---
        $lastCleanup = Configuration::get(self::CONF_LAST_UPLOAD_CLEANUP_AT);
        $lastCleanupDisplay = $lastCleanup ? date('d.m.Y H:i:s', (int) $lastCleanup) : $this->l('Noch nie ausgefuehrt');

        $pendingCount = 0;
        $pendingUnassignedCount = 0;
        $expiredCount = 0;
        if ($this->ensureUploadTable()) {
            $cutoff = date('Y-m-d H:i:s', time() - self::UPLOAD_RETENTION_DAYS * 86400);
            $pendingCount = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
                 WHERE 1'
            );
            $pendingUnassignedCount = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
                 WHERE (id_order IS NULL OR id_order = 0)'
            );
            $expiredCount = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
                 WHERE created_at < \'' . pSQL($cutoff) . '\''
            );
        }

        $cleanupAction = htmlspecialchars(
            $this->context->link->getAdminLink('AdminModules', true, [], [
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name,
            ]),
            ENT_QUOTES,
            'UTF-8'
        );

        $output .= '<div class="panel">';
        $output .= '<h3>' . $this->l('DSGVO Upload-Cleanup') . '</h3>';
        $output .= '<table class="table" style="font-size:13px;max-width:500px">';
        $output .= '<tr><td><strong>' . $this->l('Aufbewahrungsfrist') . '</strong></td><td>' . self::UPLOAD_RETENTION_DAYS . ' ' . $this->l('Tage') . '</td></tr>';
        $output .= '<tr><td><strong>' . $this->l('Letzter Cleanup') . '</strong></td><td>' . htmlspecialchars($lastCleanupDisplay, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $output .= '<tr><td><strong>' . $this->l('Ausstehende Uploads (nicht abgeschlossen, gesamt)') . '</strong></td><td>' . $pendingCount . '</td></tr>';
        $output .= '<tr><td><strong>' . $this->l('Davon ohne Bestellung') . '</strong></td><td>' . $pendingUnassignedCount . '</td></tr>';
        $output .= '<tr><td><strong>' . $this->l('Abgelaufene Eintraege') . '</strong></td><td>' . $expiredCount . '</td></tr>';
        $cronToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_cron');
        $cronUrl = (Tools::usingSecureMode() ? 'https' : 'http') . '://' . Tools::getShopDomain(false, true)
            . __PS_BASE_URI__ . 'modules/' . $this->name . '/cron.php?token=' . $cronToken;
        $output .= '<tr><td><strong>' . $this->l('Cron-URL') . '</strong></td>'
            . '<td><code style="word-break:break-all">' . htmlspecialchars($cronUrl, ENT_QUOTES, 'UTF-8') . '</code>'
            . '<br><small class="text-muted">' . $this->l('Taeglicher Aufruf empfohlen, z.B. via wget oder curl.') . '</small></td></tr>';
        $output .= '</table>';
        $output .= '<form method="post" action="' . $cleanupAction . '" style="margin-top:12px">';
        $output .= '<button type="submit" name="submitInternautenavCleanup" class="btn btn-warning">'
            . $this->l('Cleanup jetzt ausfuehren')
            . '</button>';
        $output .= ' <span class="help-block" style="display:inline-block;margin-left:8px">'
            . sprintf($this->l('Loescht alle Uploads aelter als %d Tage.'), self::UPLOAD_RETENTION_DAYS)
            . '</span>';
        $output .= '</form>';
        $output .= '<form method="post" action="' . $cleanupAction . '" style="margin-top:8px"'
            . ' onsubmit="return confirm(\'' . $this->l('Wirklich alle Dateien ohne abgeschlossene Altersprüfung löschen?') . '\')">';
        $output .= '<button type="submit" name="submitInternautenavCleanupPending" class="btn btn-danger">'
            . $this->l('Alle Dateien ohne Altersprüfung löschen')
            . '</button>';
        $output .= ' <span class="help-block" style="display:inline-block;margin-left:8px">'
            . $this->l('Loescht alle Uploads die keiner abgeschlossenen Bestellung zugeordnet sind.')
            . '</span>';
        $output .= '</form>';
        $output .= '</div>';

        return $output;
    }

    public function hookActionFrontControllerSetMedia()
    {
        $this->runUploadRetentionCleanup(false);

        if (!$this->isRegisteredInHook('displayCustomerAccount')) {
            $this->registerHook('displayCustomerAccount');
        }
        if (!$this->isRegisteredInHook('displayAdminCustomers')) {
            $this->registerHook('displayAdminCustomers');
        }

        if ($this->context->controller->php_self !== 'order') {
            return;
        }

        if (!$this->isRegisteredInHook('displayPaymentTop')) {
            $this->registerHook('displayPaymentTop');
        }
        if (!$this->isRegisteredInHook('actionValidateOrder')) {
            $this->registerHook('actionValidateOrder');
        }
        if (!$this->isRegisteredInHook('displayAdminOrderMainBottom')) {
            $this->registerHook('displayAdminOrderMainBottom');
        }

        Media::addJsDef([
            'internautenav_ajax_url' => __PS_BASE_URI__ . 'modules/' . $this->name . '/ajax.php',
        ]);

        $this->context->controller->registerJavascript(
            'module-internautenav-checkout',
            'modules/' . $this->name . '/views/js/checkout.js',
            [
                'position' => 'bottom',
                'priority' => 150,
            ]
        );

        $this->context->controller->registerStylesheet(
            'module-internautenav-checkout',
            'modules/' . $this->name . '/views/css/checkout.css',
            [
                'media' => 'all',
                'priority' => 150,
            ]
        );
    }

    public function hookActionCronJob($params)
    {
        $this->runUploadRetentionCleanup(true);
    }

    public function hookDisplayPaymentTop($params)
    {
        $carrier = $this->getCurrentCheckoutCarrier();
        if (!$carrier || !$carrier['required']) {
            $this->clearCheckoutVerificationState();
            return '';
        }

        $isVerified = $this->isAlreadyVerifiedForCheckout();
        $privacyLinkData = $this->getPrivacyPageLinkData();

        $this->context->smarty->assign([
            'internautenav_carrier_id' => $carrier['id'],
            'internautenav_carrier_name' => $carrier['name'],
            'internautenav_is_verified' => $isVerified,
            'internautenav_privacy_url' => $privacyLinkData['url'],
            'internautenav_privacy_label' => $this->l('Datenschutzerklaerung'),
            'internautenav_privacy_is_example' => $privacyLinkData['is_example'],
            'internautenav_privacy_example_hint' => $this->l('Aktuell wird die Modul-Beispielseite verwendet. Die finale Datenschutzerklaerung bitte als CMS-Seite hinterlegen.'),
            'internautenav_line3_prefill' => $this->getDeliveryAddressMrzLine3Prefill(),
            'internautenav_pass_line1_prefill' => $this->getDeliveryAddressSwissPassLine1Prefill(),
            'internautenav_customer_sex' => $this->getCustomerMrzSex(),
            'internautenav_payment_title' => $this->l('payment_title'),
            'internautenav_payment_intro' => $this->l('payment_intro'),
            'internautenav_payment_link' => $this->l('payment_link'),
            'internautenav_payment_success' => $this->l('payment_success'),
            'internautenav_payment_locked' => $this->l('payment_locked'),
            'internautenav_modal_title' => $this->l('modal_title'),
            'internautenav_modal_close' => $this->l('modal_close'),
            'internautenav_modal_submit' => $this->l('modal_submit'),
            'internautenav_modal_submit_upload' => $this->l('Speichern'),
            'internautenav_doc_label' => $this->l('form_doc_label'),
            'internautenav_doc_ch_id' => $this->l('form_doc_ch_id'),
            'internautenav_doc_ch_pass' => $this->l('form_doc_ch_pass'),
            'internautenav_doc_eu_pass' => $this->l('form_doc_eu_pass'),
            'internautenav_doc_upload' => $this->l('Upload'),
            'internautenav_chid_fields_header' => $this->l('chid_fields_header'),
            'internautenav_pass_front_label' => $this->l('pass_front_label'),
            'internautenav_line1_label' => $this->l('form_line1_label'),
            'internautenav_line2_label' => $this->l('form_line2_label'),
            'internautenav_line3_label' => $this->l('form_line3_label'),
            'internautenav_upload_label' => $this->l('Dokumentbild hochladen'),
            'internautenav_upload_hint' => $this->l('Bitte laden Sie ein gut lesbares Bild eines amtlichen Dokuments hoch (Reisepass, Identitaetskarte, Aufenthaltsbewilligung oder anderes amtliches Dokument).'),
            'internautenav_hint' => $this->l('modal_hint'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/payment_gate.tpl');
    }

    private function getPrivacyPageLinkData()
    {
        $fallbackUrl = $this->context->link->getModuleLink($this->name, 'privacy');
        $configuredCmsId = (int) Configuration::get(self::CONF_PRIVACY_CMS_ID);

        if ($configuredCmsId <= 0) {
            return [
                'url' => $fallbackUrl,
                'is_example' => true,
            ];
        }

        $cms = new CMS($configuredCmsId, (int) $this->context->language->id);
        if (!Validate::isLoadedObject($cms) || !$cms->active) {
            return [
                'url' => $fallbackUrl,
                'is_example' => true,
            ];
        }

        return [
            'url' => $this->context->link->getCMSLink($cms),
            'is_example' => false,
        ];
    }

    public function getDeliveryAddressMrzLine3Prefill()
    {
        if (!Validate::isLoadedObject($this->context->cart)) {
            return '';
        }

        $idAddressDelivery = (int) $this->context->cart->id_address_delivery;
        if ($idAddressDelivery <= 0) {
            return '';
        }

        $address = new Address($idAddressDelivery);
        if (!Validate::isLoadedObject($address)) {
            return '';
        }

        $surname = $this->normalizeMrzNamePart((string) $address->lastname);
        $givenNames = $this->normalizeMrzNamePart((string) $address->firstname);

        if ($surname === '' || $givenNames === '') {
            return '';
        }

        return $this->buildSwissIdMrzLine3($surname, $givenNames);
    }

    public function getDeliveryAddressSwissPassLine1Prefill()
    {
        if (!Validate::isLoadedObject($this->context->cart)) {
            return '';
        }

        $idAddressDelivery = (int) $this->context->cart->id_address_delivery;
        if ($idAddressDelivery <= 0) {
            return '';
        }

        $address = new Address($idAddressDelivery);
        if (!Validate::isLoadedObject($address)) {
            return '';
        }

        $surname = $this->normalizeMrzNamePart((string) $address->lastname);
        $givenNames = $this->normalizeMrzNamePart((string) $address->firstname);

        if ($surname === '' || $givenNames === '') {
            return '';
        }

        return $this->buildSwissPassMrzLine1($surname, $givenNames);
    }

    private function buildSwissIdMrzLine3($surname, $givenNames)
    {
        $surname = (string) $surname;
        $givenNames = (string) $givenNames;

        if ($surname === '' || $givenNames === '') {
            return '';
        }

        // TD1 name line: SURNAME<<GIVEN<NAMES then padded with '<' to 30 chars.
        $maxLength = 30;
        $nameBudget = $maxLength - 2; // reserve for "<<"

        if (Tools::strlen($surname . $givenNames) > $nameBudget) {
            // Keep surname priority but preserve at least one char for given names.
            $surnameMax = $nameBudget - 1;
            $surname = Tools::substr($surname, 0, $surnameMax);
            $givenBudget = $nameBudget - Tools::strlen($surname);
            if ($givenBudget < 1) {
                $givenBudget = 1;
            }
            $givenNames = Tools::substr($givenNames, 0, $givenBudget);
        }

        $line3 = $surname . '<<' . $givenNames;

        return str_pad($line3, $maxLength, '<');
    }

    private function buildSwissPassMrzLine1($surname, $givenNames)
    {
        $surname = (string) $surname;
        $givenNames = (string) $givenNames;

        if ($surname === '' || $givenNames === '') {
            return '';
        }

        // Swiss passport MRZ TD3: PMCHE + SURNAME<<GIVENNAMES padded to 44 chars total.
        $prefix = 'PMCHE';
        $nameBudget = 44 - Tools::strlen($prefix); // 39 chars for name field

        if (Tools::strlen($surname . $givenNames) > ($nameBudget - 2)) { // -2 for '<<' separator
            $surnameMax = ($nameBudget - 2) - 1;
            $surname = Tools::substr($surname, 0, $surnameMax);
            $givenBudget = ($nameBudget - 2) - Tools::strlen($surname);
            if ($givenBudget < 1) {
                $givenBudget = 1;
            }
            $givenNames = Tools::substr($givenNames, 0, $givenBudget);
        }

        $nameField = $surname . '<<' . $givenNames;
        $nameField = str_pad($nameField, $nameBudget, '<');

        return $prefix . $nameField;
    }

    public function getCustomerMrzSex()
    {
        $context = Context::getContext();
        if (!$context) {
            return '<';
        }

        $customer = null;
        if (isset($context->customer) && Validate::isLoadedObject($context->customer)) {
            $customer = $context->customer;
        } elseif (isset($context->cart) && Validate::isLoadedObject($context->cart) && (int) $context->cart->id_customer > 0) {
            $cartCustomer = new Customer((int) $context->cart->id_customer);
            if (Validate::isLoadedObject($cartCustomer)) {
                $customer = $cartCustomer;
            }
        }

        if (!$customer) {
            return '<';
        }

        $idGender = (int) $customer->id_gender;
        if ($idGender === 1) {
            return 'M';
        }
        if ($idGender === 2) {
            return 'F';
        }

        if ($idGender > 0) {
            $idLang = isset($context->language) ? (int) $context->language->id : null;
            $gender = new Gender($idGender, $idLang);
            if (Validate::isLoadedObject($gender)) {
                $name = Tools::strtolower((string) $gender->name);
                if (preg_match('/frau|mrs|ms|madam|madame|fem|woman|donna|femme/', $name)) {
                    return 'F';
                }
                if (preg_match('/herr|mr|sir|male|man|uomo|homme/', $name)) {
                    return 'M';
                }
            }
        }

        return '<';
    }

    private function normalizeMrzNamePart($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        $value = Tools::strtoupper($value);
        $value = strtr($value, [
            'Ä' => 'AE',
            'Ö' => 'OE',
            'Ü' => 'UE',
            'ä' => 'AE',
            'ö' => 'OE',
            'ü' => 'UE',
            'ß' => 'SS',
        ]);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted)) {
                $value = $converted;
            }
        }

        $value = preg_replace('/[^A-Z0-9]+/', '<', $value);
        $value = trim((string) $value, '<');
        $value = preg_replace('/<+/', '<', (string) $value);

        return (string) $value;
    }

    private function debugLog($message)
    {
        PrestaShopLogger::addLog(
            'internautenav: ' . (string) $message,
            1,
            null,
            'Module',
            (int) $this->id,
            true
        );
    }

    public function hookActionCarrierProcess($params)
    {
        return true;
    }

    public function hookActionValidateStepComplete($params)
    {
        return true;
    }

    public function hookActionValidateOrder($params)
    {
        if (!$this->ensureUploadTable()) {
            return;
        }

        $order = isset($params['order']) ? $params['order'] : null;
        $cart = isset($params['cart']) ? $params['cart'] : null;

        if (!Validate::isLoadedObject($order) || !Validate::isLoadedObject($cart)) {
            return;
        }

        $idOrder = (int) $order->id;
        $idCart = (int) $cart->id;

        if ($idOrder <= 0 || $idCart <= 0) {
            return;
        }

        $this->attachPendingUploadsToOrder($idCart, $idOrder);
    }

    public function hookDisplayAdminOrderTop($params)
    {
        $idOrder = $this->resolveOrderIdFromHookParams($params);
        if ($idOrder <= 0) {
            return '';
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        $carrier = new Carrier((int) $order->id_carrier);
        $carrierRequired = Validate::isLoadedObject($carrier)
            && $this->isCarrierReferenceRequired((int) $carrier->id_reference);

        if (!$carrierRequired) {
            return $this->renderOrderStatusBadge(
                'info',
                '&#128666; ' . htmlspecialchars($this->l('Pruefung bei Uebergabe'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($this->l('Diese Versandart erfordert keine Online-Altersprüfung.'), ENT_QUOTES, 'UTF-8')
            );
        }

        // Offene Dokumente vorhanden → manuell prüfen
        if ($this->ensureUploadTable()) {
            $pendingDocs = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
                 WHERE id_order = ' . $idOrder
            );
            if ($pendingDocs > 0) {
                return $this->renderOrderStatusBadge(
                    'warning',
                    '&#9888; ' . htmlspecialchars($this->l('Pruefung manuell erledigen'), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars(
                        sprintf($this->l('%d Dokument(e) hochgeladen, noch nicht geprüft.'), $pendingDocs),
                        ENT_QUOTES, 'UTF-8'
                    )
                );
            }
        }

        // Log-Eintrag für diesen Warenkorb suchen
        $idCart = (int) $order->id_cart;
        if ($idCart > 0 && $this->ensureVerificationLogTable()) {
            $logRow = Db::getInstance()->getRow(
                'SELECT `result`, `result_message`, `doc_type`, `checked_at`
                 FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '`
                 WHERE id_cart = ' . $idCart . '
                 ORDER BY checked_at DESC'
            );
            if (is_array($logRow) && isset($logRow['result'])) {
                $isManual = (strpos((string) ($logRow['result_message'] ?? ''), 'Manuelle Prüfung') !== false);
                if ((int) $logRow['result'] === 1) {
                    $label = $isManual
                        ? '&#10003; ' . htmlspecialchars($this->l('Pruefung manuell bestanden'), ENT_QUOTES, 'UTF-8')
                        : '&#10003; ' . htmlspecialchars($this->l('Pruefung automatisch bestanden'), ENT_QUOTES, 'UTF-8');
                    $detail = htmlspecialchars(
                        ($isManual ? 'Manuell' : ucfirst((string) $logRow['doc_type'])) . ' – ' . (string) $logRow['checked_at'],
                        ENT_QUOTES, 'UTF-8'
                    );
                    return $this->renderOrderStatusBadge('success', $label, $detail);
                } else {
                    return $this->renderOrderStatusBadge(
                        'danger',
                        '&#10007; ' . htmlspecialchars($this->l('Pruefung abgelehnt'), ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($logRow['result_message'] ?? ''), ENT_QUOTES, 'UTF-8')
                    );
                }
            }
        }

        // Kein Eintrag gefunden
        return $this->renderOrderStatusBadge(
            'default',
            '&#63; ' . htmlspecialchars($this->l('Keine Pruefung vorhanden'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->l('Für diese Bestellung liegt kein Verifikationseintrag vor.'), ENT_QUOTES, 'UTF-8')
        );
    }

    public function hookDisplayPDFInvoice($params)
    {
        return $this->renderOrderStatusBadgeForPdf($params);
    }

    public function hookDisplayPDFDeliverySlip($params)
    {
        return $this->renderOrderStatusBadgeForPdf($params);
    }

    private function renderOrderStatusBadgeForPdf($params)
    {
        $object = isset($params['object']) ? $params['object'] : null;
        $order = $this->resolveOrderFromPdfObject($object);
        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        $badgeHtml = $this->hookDisplayAdminOrderTop([
            'id_order' => (int) $order->id,
            'order' => $order,
        ]);

        if ($badgeHtml === '') {
            return '';
        }

        // Unknown status should not be shown in PDF.
        if (strpos($badgeHtml, '&#63;') !== false) {
            return '';
        }

        // PDF fonts may not contain glyphs for icon entities; use ASCII-safe labels.
        $badgeHtml = str_replace(
            ['&#10003;', '&#10007;', '&#63;', '&#9888;', '&#128666;'],
            ['BESTANDEN!', 'ABGELEHNT!', 'UNBEKANNT!', 'HINWEIS!', 'HINWEIS'],
            $badgeHtml
        );

        return '<div style="margin:8px 0 10px;">' . $badgeHtml . '</div>';
    }

    private function resolveOrderFromPdfObject($object)
    {
        if (Validate::isLoadedObject($object) && $object instanceof Order) {
            return $object;
        }

        $idOrder = 0;
        if (is_object($object) && isset($object->id_order)) {
            $idOrder = (int) $object->id_order;
        }

        if ($idOrder <= 0) {
            return null;
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return null;
        }

        return $order;
    }

    public function hookDisplayCustomerAccount($params)
    {
        if (!Validate::isLoadedObject($this->context->customer) || !$this->context->customer->isLogged()) {
            return '';
        }

        $url = $this->context->link->getModuleLink($this->name, 'protocol', [], true);

        return '<a class="account-menu__link " href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($this->l('Alterspruefungsprotokoll'), ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="link-item">'
            . '<i class="material-icons">assignment</i>'
            . '<span>' . htmlspecialchars($this->l('Alterspruefungsprotokoll'), ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>'
            . '</a>';
    }

    public function hookDisplayAdminCustomers($params)
    {
        $idCustomer = isset($params['id_customer']) ? (int) $params['id_customer'] : 0;
        if ($idCustomer <= 0) {
            return '';
        }

        $rows = $this->getCustomerVerificationProtocol($idCustomer, 200);
        $statusBadge = $this->renderCustomerVerificationStatusBadge($idCustomer, $rows);

        $badgeId = 'internautenav-customer-status-badge';
        $inlineBadge = '<div id="' . $badgeId . '" style="margin-bottom:8px">' . $statusBadge . '</div>';

        $output = $inlineBadge;
                $protocolCardId = 'internautenav-protocol-card';
                $output .= '<script>(function(){
    function inav_moveBadge() {
        var badge = document.getElementById("' . $badgeId . '");
        if (!badge) return;
        // Search in main content area; fall back to body
        var contentSelectors = ["#content", "#main", "main", "#main-div", ".page-body", "body"];
        var content = null;
        for (var c = 0; c < contentSelectors.length; c++) {
            content = document.querySelector(contentSelectors[c]);
            if (content) break;
        }
        // Find first card that is neither our protocol card nor contains the badge already
        var cards = (content || document.body).querySelectorAll(".card");
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            if (card.id === "' . $protocolCardId . '") continue;
            if (card.contains(badge)) continue;
            var body = card.querySelector(".card-body");
            if (body) {
                body.insertBefore(badge, body.firstChild);
                return;
            }
        }
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", inav_moveBadge);
    } else {
        inav_moveBadge();
    }
})();</script>';

                $output .= '<div class="card mt-2" id="' . $protocolCardId . '">';
        $output .= '<h3 class="card-header">' . htmlspecialchars($this->l('Alterspruefungsprotokoll', 'protocol'), ENT_QUOTES, 'UTF-8') . '</h3>';
        $output .= '<div class="card-body">';

        if (empty($rows)) {
            $output .= '<p class="text-muted mb-0">' . htmlspecialchars($this->l('Keine Eintraege.'), ENT_QUOTES, 'UTF-8') . '</p>';
            $output .= '</div></div>';

            return $output;
        }

        $output .= '<div class="table-responsive">';
        $output .= '<table class="table table-striped table-bordered">';
        $output .= '<thead><tr>';
        $output .= '<th>' . htmlspecialchars($this->l('Zeitpunkt'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('id_cart'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Dokumenttyp'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Ergebnis'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Meldung'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $message = (string) ($row['result_message'] ?? '');
            $isOk = (int) ($row['result'] ?? 0) === 1;
            $docType = (string) ($row['doc_type'] ?? '');
            if (strpos($message, 'Manuelle Prüfung') !== false) {
                $docType = 'Manuell';
            } elseif ($docType !== '') {
                $docType = ucfirst($docType);
            }

            $resultLabel = $isOk
                ? '&#10003; ' . htmlspecialchars($this->l('OK'), ENT_QUOTES, 'UTF-8')
                : '&#10007; ' . htmlspecialchars($this->l('Fehler'), ENT_QUOTES, 'UTF-8');

            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars((string) ($row['checked_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars((string) ($row['id_cart'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($docType, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . $resultLabel . '</td>';
            $output .= '<td>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    private function renderOrderVerificationLogPanel($idOrder, Order $order)
    {
        if ($idOrder <= 0 || !$this->ensureVerificationLogTable()) {
            return '';
        }

        $idCart = (int) $order->id_cart;
        if ($idCart <= 0) {
            return '';
        }

        $logRows = Db::getInstance()->executeS(
            'SELECT `result`, `result_message`, `doc_type`, `checked_at`
             FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '`
             WHERE id_cart = ' . $idCart . '
             ORDER BY checked_at DESC'
        );
        if (!is_array($logRows) || empty($logRows)) {
            $output = '<div class="card mt-2">';
            $output .= '<div class="card-header">';
            $output .= '<h3>' . htmlspecialchars($this->l('Altersprüfung – Protokoll'), ENT_QUOTES, 'UTF-8') . '</h3>';
            $output .= '</div>';
            $output .= '<div class="card-body">';
            $output .= '<p class="text-muted">' . htmlspecialchars($this->l('Keine Eintraege.'), ENT_QUOTES, 'UTF-8') . '</p>';
            $output .= '</div>';
            $output .= '</div>';

            return $output;
        }

        $output = '<div class="card mt-2">';
        $output .= '<div class="card-header">';
        $output .= '<h3>' . htmlspecialchars($this->l('Altersprüfung – Protokoll'), ENT_QUOTES, 'UTF-8') . '</h3>';
        $output .= '</div>';
        $output .= '<div class="card-body">';
        $output .= '<div class="table-responsive">';
        $output .= '<table class="table table-hover">';
        $output .= '<thead><tr>';
        $output .= '<th>' . htmlspecialchars($this->l('Zeitpunkt'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Dokumenttyp'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Ergebnis'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Meldung'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($logRows as $row) {
            $isOk = (int) ($row['result'] ?? 0) === 1;
            $resultLabel = $isOk
                ? '&#10003; ' . htmlspecialchars($this->l('OK'), ENT_QUOTES, 'UTF-8')
                : '&#10007; ' . htmlspecialchars($this->l('Fehler'), ENT_QUOTES, 'UTF-8');
            $docType = (string) ($row['doc_type'] ?? '');
            if (strpos((string) ($row['result_message'] ?? ''), 'Manuelle Prüfung') !== false) {
                $docType = 'Manuell';
            } elseif ($docType !== '') {
                $docType = ucfirst($docType);
            }

            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars((string) ($row['checked_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($docType, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . $resultLabel . '</td>';
            $output .= '<td>' . htmlspecialchars((string) ($row['result_message'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    public function getCustomerVerificationProtocol($idCustomer, $limit = 100)
    {
        $idCustomer = (int) $idCustomer;
        $limit = (int) $limit;

        if ($idCustomer <= 0 || $limit <= 0 || !$this->ensureVerificationLogTable()) {
            return [];
        }

        if ($limit > 200) {
            $limit = 200;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_cart`, `doc_type`, `result`, `result_message`, `checked_at`
             FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '`
             WHERE `id_customer` = ' . $idCustomer . '
             ORDER BY `checked_at` DESC
             LIMIT ' . $limit
        );

        return is_array($rows) ? $rows : [];
    }

    private function renderCustomerVerificationStatusBadge($idCustomer, array $rows)
    {
        $idCustomer = (int) $idCustomer;

        // Same priority as order badge: pending uploaded documents require manual decision first.
        if ($idCustomer > 0 && $this->ensureUploadTable()) {
            $pendingDocs = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
                 WHERE id_customer = ' . $idCustomer . ' AND id_order > 0'
            );
            if ($pendingDocs > 0) {
                return $this->renderOrderStatusBadge(
                    'warning',
                    '&#9888; ' . htmlspecialchars($this->l('Pruefung manuell erledigen'), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars(
                        sprintf($this->l('%d Dokument(e) hochgeladen, noch nicht geprüft.'), $pendingDocs),
                        ENT_QUOTES,
                        'UTF-8'
                    )
                );
            }
        }

        if (empty($rows)) {
            return $this->renderOrderStatusBadge(
                'default',
                '&#63; ' . htmlspecialchars($this->l('Keine Pruefung vorhanden'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($this->l('Für diesen Kunden liegt kein Verifikationseintrag vor.'), ENT_QUOTES, 'UTF-8')
            );
        }

        $row = $rows[0];
        $isOk = (int) ($row['result'] ?? 0) === 1;
        $message = (string) ($row['result_message'] ?? '');
        $docType = ucfirst((string) ($row['doc_type'] ?? ''));
        $checkedAt = (string) ($row['checked_at'] ?? '');
        $isManual = strpos($message, 'Manuelle Prüfung') !== false;

        if ($isOk) {
            $label = $isManual
                ? '&#10003; ' . htmlspecialchars($this->l('Pruefung manuell bestanden'), ENT_QUOTES, 'UTF-8')
                : '&#10003; ' . htmlspecialchars($this->l('Pruefung automatisch bestanden'), ENT_QUOTES, 'UTF-8');
            $detail = ($isManual ? 'Manuell' : $docType);
            if ($checkedAt !== '') {
                $detail .= ' - ' . $checkedAt;
            }

            return $this->renderOrderStatusBadge('success', $label, htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'));
        }

        return $this->renderOrderStatusBadge(
            'danger',
            '&#10007; ' . htmlspecialchars($this->l('Pruefung abgelehnt'), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($message !== '' ? $message : $this->l('Letzte Prüfung war nicht erfolgreich.'), ENT_QUOTES, 'UTF-8')
        );
    }

    private function renderOrderStatusBadge($type, $label, $detail)
    {
        $colors = [
            'success' => '#3c763d',
            'warning' => '#f01212',
            'danger'  => '#a94442',
            'info'    => '#31708f',
            'default' => '#555',
        ];
        $backgrounds = [
            'success' => '#dff0d8',
            'warning' => '#fcf8e3',
            'danger'  => '#f2dede',
            'info'    => '#d9edf7',
            'default' => '#f5f5f5',
        ];
        $borders = [
            'success' => '#d6e9c6',
            'warning' => '#faebcc',
            'danger'  => '#ebccd1',
            'info'    => '#bce8f1',
            'default' => '#ccc',
        ];
        $color = $colors[$type] ?? $colors['default'];
        $bg    = $backgrounds[$type] ?? $backgrounds['default'];
        $border = $borders[$type] ?? $borders['default'];

        $out = '<div style="margin:8px 0 4px;padding:10px 14px;border:1px solid ' . $border . ';border-radius:4px;background:' . $bg . ';color:' . $color . ';font-size:13px">';
        $out .= '<strong>' . $label . '</strong>';
        if ($detail !== '') {
            $out .= ' &nbsp;<span style="font-weight:normal;font-size:12px">' . $detail . '</span>';
        }
        $out .= '</div>';

        return $out;
    }

    public function hookDisplayAdminOrderMainBottom($params)
    {
        if (!$this->ensureUploadTable()) {
            return '';
        }

        $idOrder = $this->resolveOrderIdFromHookParams($params);
        if ($idOrder <= 0) {
            return '';
        }

        $protocolPanel = '';
        $order = new Order($idOrder);
        if (Validate::isLoadedObject($order)) {
            $protocolPanel = $this->renderOrderVerificationLogPanel($idOrder, $order);
        }

        $rows = $this->getUploadedDocumentsByOrder($idOrder);

        $output = '<div class="card mt-2">';
        $output .= '<div class="card-header">'
            . '<h3>' . htmlspecialchars($this->l('Altersprüfung – Hochgeladene Dokumente'), ENT_QUOTES, 'UTF-8') . '</h3>'
            . '</div>';
        $output .= '<div class="card-body">';

        if (empty($rows)) {
            $output .= '<p class="text-muted">'
                . htmlspecialchars($this->l('Zu dieser Bestellung liegen keine hochgeladenen Dokumente vor.'), ENT_QUOTES, 'UTF-8')
                . '</p>';
            $output .= '</div></div>';

            return $protocolPanel . $output;
        }

        $output .= '<div class="table-responsive">';
        $output .= '<table class="table table-hover">';
        $output .= '<thead><tr>';
        $output .= '<th>' . htmlspecialchars($this->l('ID'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Originaldatei'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Typ'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('MIME'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Grösse'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Hochgeladen am'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '<th>' . htmlspecialchars($this->l('Aktion'), ENT_QUOTES, 'UTF-8') . '</th>';
        $output .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $docId = (int) $row['id_internautenav_uploaded_document'];
            $dlToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_dl' . $docId . $idOrder);
            $downloadUrl = __PS_BASE_URI__ . 'modules/' . $this->name . '/ajax.php?action=download_document'
                . '&document_id=' . $docId
                . '&id_order=' . (int) $idOrder
                . '&token=' . rawurlencode($dlToken);

            $output .= '<tr>';
            $output .= '<td>' . $docId . '</td>';
            $output .= '<td>' . htmlspecialchars((string) $row['original_name'], ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td><span class="badge">' . htmlspecialchars((string) $row['doc_type'], ENT_QUOTES, 'UTF-8') . '</span></td>';
            $output .= '<td><small class="text-muted">' . htmlspecialchars((string) $row['mime_type'], ENT_QUOTES, 'UTF-8') . '</small></td>';
            $output .= '<td>' . htmlspecialchars($this->formatFileSize((int) $row['file_size']), ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars((string) $row['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>'
                . '<button type="button" class="btn btn-default btn-xs js-internautenav-preview"'
                . ' data-preview-url="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '"'
                . ' data-file-name="' . htmlspecialchars((string) $row['original_name'], ENT_QUOTES, 'UTF-8') . '">'
                . '<i class="icon-eye"></i> ' . htmlspecialchars($this->l('Ansehen'), ENT_QUOTES, 'UTF-8')
                . '</button></td>';
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';
        $output .= '</div>';

        $adminToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_admin_action' . $idOrder);
        $ajaxUrl = htmlspecialchars(__PS_BASE_URI__ . 'modules/' . $this->name . '/ajax.php', ENT_QUOTES, 'UTF-8');
        $output .= '<div id="internautenav-preview-modal-' . (int) $idOrder . '" class="internautenav-admin-modal" style="display:none;position:fixed;z-index:20000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.65);">';
        $output .= '<div class="internautenav-admin-modal-dialog" style="position:relative;max-width:980px;margin:4vh auto;background:#fff;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.35);padding:14px 14px 12px;">';
        $output .= '<button type="button" class="btn btn-link js-internautenav-modal-close" style="position:absolute;right:10px;top:6px;font-size:24px;line-height:1;text-decoration:none;">&times;</button>';
        $output .= '<h4 style="margin:0 0 8px;">' . htmlspecialchars($this->l('Dokumentvorschau'), ENT_QUOTES, 'UTF-8') . '</h4>';
        $output .= '<div id="internautenav-preview-filename-' . (int) $idOrder . '" class="text-muted" style="margin-bottom:8px;font-size:12px;"></div>';
        $output .= '<div style="text-align:center;max-height:68vh;overflow:auto;border:1px solid #ddd;background:#fafafa;">';
        $output .= '<img id="internautenav-preview-image-' . (int) $idOrder . '" src="" alt="' . htmlspecialchars($this->l('Dokumentvorschau'), ENT_QUOTES, 'UTF-8') . '" style="max-width:100%;max-height:66vh;display:block;margin:0 auto;">';
        $output .= '</div>';
        $output .= '<div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
        $output .= '<button type="button" class="btn btn-success btn-sm js-internautenav-modal-action" data-action="approve" data-order-id="' . (int) $idOrder . '" data-token="' . $adminToken . '" data-ajax-url="' . $ajaxUrl . '">'
            . '<i class="icon-ok"></i> ' . htmlspecialchars($this->l('Pruefung bestanden'), ENT_QUOTES, 'UTF-8')
            . '</button>';
        $output .= '<button type="button" class="btn btn-danger btn-sm js-internautenav-modal-action" data-action="reject" data-order-id="' . (int) $idOrder . '" data-token="' . $adminToken . '" data-ajax-url="' . $ajaxUrl . '">'
            . '<i class="icon-remove"></i> ' . htmlspecialchars($this->l('Pruefung abgelehnt'), ENT_QUOTES, 'UTF-8')
            . '</button>';
        $output .= '<button type="button" class="btn btn-default btn-sm js-internautenav-modal-close">' . htmlspecialchars($this->l('Schliessen'), ENT_QUOTES, 'UTF-8') . '</button>';
        $output .= '<span class="text-muted" style="margin-left:8px;font-size:11px"><i class="icon-shield"></i> '
            . htmlspecialchars($this->l('Loescht alle Dokumente DSGVO-konform sofort.'), ENT_QUOTES, 'UTF-8')
            . '</span>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';

        if (!defined('INTERNAUTENAV_ADMIN_JS_LOADED')) {
            define('INTERNAUTENAV_ADMIN_JS_LOADED', true);
            $output .= '<script>
function internautenavAdminAction(action, orderId, token, ajaxUrl) {
    var labels = {
        approve: { confirm: "Prüfung als bestanden markieren und alle Dokumente DSGVO-konform löschen?", ok: "Prüfung bestanden gespeichert. Dokumente gelöscht." },
        reject:  { confirm: "Prüfung als abgelehnt markieren und alle Dokumente DSGVO-konform löschen?", ok: "Prüfung abgelehnt gespeichert. Dokumente gelöscht." }
    };
    if (!confirm(labels[action].confirm)) { return; }
    var params = new URLSearchParams();
    params.append("action", "admin_" + action + "_documents");
    params.append("id_order", orderId);
    params.append("token", token);
    fetch(ajaxUrl, { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: params.toString() })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { alert(data.message || labels[action].ok); location.reload(); }
            else { alert("Fehler: " + (data.message || "Unbekannter Fehler")); }
        })
        .catch(function() { alert("Verbindungsfehler beim Speichern der Entscheidung."); });
}

function internautenavOpenPreviewModal(orderId, imageUrl, fileName) {
    var modal = document.getElementById("internautenav-preview-modal-" + orderId);
    var image = document.getElementById("internautenav-preview-image-" + orderId);
    var nameNode = document.getElementById("internautenav-preview-filename-" + orderId);
    if (!modal || !image || !nameNode) { return; }
    image.setAttribute("src", imageUrl || "");
    nameNode.textContent = fileName || "";
    modal.style.display = "block";
}

function internautenavClosePreviewModal(orderId) {
    var modal = document.getElementById("internautenav-preview-modal-" + orderId);
    var image = document.getElementById("internautenav-preview-image-" + orderId);
    if (!modal) { return; }
    modal.style.display = "none";
    if (image) {
        image.setAttribute("src", "");
    }
}

document.addEventListener("click", function (event) {
    var previewBtn = event.target.closest(".js-internautenav-preview");
    if (previewBtn) {
        event.preventDefault();
        var modal = previewBtn.closest(".card").querySelector("[id^=\"internautenav-preview-modal-\"]");
        if (!modal) { return; }
        var orderId = (modal.id || "").replace("internautenav-preview-modal-", "");
        internautenavOpenPreviewModal(orderId, previewBtn.getAttribute("data-preview-url") || "", previewBtn.getAttribute("data-file-name") || "");
        return;
    }

    var closeBtn = event.target.closest(".js-internautenav-modal-close");
    if (closeBtn) {
        event.preventDefault();
        var closeModal = closeBtn.closest("[id^=\"internautenav-preview-modal-\"]");
        if (!closeModal) { return; }
        var closeOrderId = (closeModal.id || "").replace("internautenav-preview-modal-", "");
        internautenavClosePreviewModal(closeOrderId);
        return;
    }

    var actionBtn = event.target.closest(".js-internautenav-modal-action");
    if (actionBtn) {
        event.preventDefault();
        var action = actionBtn.getAttribute("data-action") || "";
        var orderId = parseInt(actionBtn.getAttribute("data-order-id") || "0", 10);
        var token = actionBtn.getAttribute("data-token") || "";
        var ajaxUrl = actionBtn.getAttribute("data-ajax-url") || "";
        if (!action || !orderId || !token || !ajaxUrl) { return; }
        internautenavAdminAction(action, orderId, token, ajaxUrl);
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") { return; }
    var openModals = document.querySelectorAll("[id^=\"internautenav-preview-modal-\"]");
    for (var i = 0; i < openModals.length; i++) {
        if (openModals[i].style.display === "block") {
            var orderId = (openModals[i].id || "").replace("internautenav-preview-modal-", "");
            internautenavClosePreviewModal(orderId);
        }
    }
});
</script>';
        }

        $output .= '</div>';

        return $protocolPanel . $output;
    }

    public function validateMrzForCarrier($carrierId, array $payload, $persistOnSuccess = false)
    {
        $this->ensureVerificationLogTable();

        $docType = (string) ($payload['doc_type'] ?? '');
        $carrierId = (int) $carrierId;
        if ($carrierId <= 0) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('error_invalid_carrier'));
        }

        $carrier = new Carrier($carrierId);
        if (!Validate::isLoadedObject($carrier)) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('error_carrier_not_found'));
        }

        $carrierReference = (int) $carrier->id_reference;
        if (!$this->isCarrierReferenceRequired($carrierReference)) {
            return $this->finalizeMrzValidationResult($docType, true, '');
        }

        if ($docType === 'upload') {
            return $this->validateUploadForCarrier($carrierId, $carrierReference, $payload, $persistOnSuccess);
        }

        $validation = MrzValidator::validate(
            $docType,
            (string) ($payload['line1'] ?? ''),
            (string) ($payload['line2'] ?? ''),
            (string) ($payload['line3'] ?? '')
        );

        if (empty($validation['valid'])) {
            return $this->finalizeMrzValidationResult(
                $docType,
                false,
                isset($validation['message']) ? (string) $validation['message'] : $this->l('error_mrz_invalid')
            );
        }

        $idAddressDelivery = (int) $this->context->cart->id_address_delivery;
        $address = new Address($idAddressDelivery);
        if (!Validate::isLoadedObject($address)) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('error_address_not_found'));
        }

        $nameCheck = MrzValidator::matchNames($address->firstname, $address->lastname, $validation['data']);
        if (empty($nameCheck['valid'])) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('error_name_mismatch'));
        }

        $adultCheck = MrzValidator::isAdult($validation['data']['birth_date'], 18);
        if (empty($adultCheck['valid'])) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('error_age_check'));
        }

        if ($persistOnSuccess) {
            $verificationData = [
                'carrier_id' => $carrierId,
                'carrier_reference' => $carrierReference,
                'doc_type' => $docType,
                'birth_date' => $validation['data']['birth_iso'],
                'firstname' => (string) $address->firstname,
                'lastname' => (string) $address->lastname,
            ];

            $idCustomer = $this->resolveCheckoutCustomerId();
            if ($idCustomer > 0) {
                if (!$this->setCustomerVerified($idCustomer, $verificationData)) {
                    return $this->finalizeMrzValidationResult($docType, false, $this->l('Verifikationsstatus konnte nicht gespeichert werden.'));
                }
            } else {
                $this->setCheckoutVerificationState($verificationData);
            }
        }

        return $this->finalizeMrzValidationResult($docType, true, '');
    }

    private function validateCheckoutMrzIfRequired()
    {
        // Prüfe, ob bereits verifiziert (Kunde oder Gast)
        if ($this->isAlreadyVerifiedForCheckout()) {
            return true;
        }

        $selectedCarrierId = $this->resolveSelectedCarrierId();
        if ($selectedCarrierId <= 0) {
            return true;
        }

        $carrier = new Carrier($selectedCarrierId);
        if (!Validate::isLoadedObject($carrier)) {
            return true;
        }

        if (!$this->isCarrierReferenceRequired((int) $carrier->id_reference)) {
            return true;
        }

        // Bei MRZ-pflichtigem Carrier muss das Formular im Request vorhanden sein.
        // Sonst darf der Checkout nicht weitergehen.
        if (!$this->isMrzFormPostedForCarrier($selectedCarrierId)) {
            return $this->failCheckoutValidation($this->l('Bitte Dokumenttyp waehlen.'));
        }

        $payload = $this->extractMrzPayloadForCarrier($selectedCarrierId);

        $validation = MrzValidator::validate(
            $payload['doc_type'],
            $payload['line1'],
            $payload['line2'],
            $payload['line3']
        );

        if (!$validation['valid']) {
            return $this->failCheckoutValidation($validation['message']);
        }

        $idAddressDelivery = (int) $this->context->cart->id_address_delivery;
        $address = new Address($idAddressDelivery);
        if (!Validate::isLoadedObject($address)) {
            return $this->failCheckoutValidation($this->l('Lieferadresse konnte nicht geladen werden.'));
        }

        $nameCheck = MrzValidator::matchNames($address->firstname, $address->lastname, $validation['data']);
        if (!$nameCheck['valid']) {
            return $this->failCheckoutValidation($this->l('Name und Vorname der Lieferadresse stimmen nicht mit der MRZ ueberein.'));
        }

        $adultCheck = MrzValidator::isAdult($validation['data']['birth_date'], 18);
        if (!$adultCheck['valid']) {
            return $this->failCheckoutValidation($this->l('Bestellung nur fuer volljaehrige Personen (18+).'));
        }

        $verificationData = [
            'doc_type' => $payload['doc_type'],
            'birth_date' => $validation['data']['birth_iso'],
            'firstname' => pSQL((string) $address->firstname),
            'lastname' => pSQL((string) $address->lastname),
        ];

        // Speichere unterschiedlich je nach Benutzer-Status
        $idCustomer = $this->resolveCheckoutCustomerId();
        if ($idCustomer > 0) {
            if (!$this->setCustomerVerified($idCustomer, $verificationData)) {
                return $this->failCheckoutValidation($this->l('Verifikationsstatus konnte nicht gespeichert werden.'));
            }
        } else {
            $this->setGuestVerificationInSession($verificationData);
        }

        return true;
    }

    private function isMrzFormPostedForCarrier($carrierId)
    {
        $carrierId = (int) $carrierId;
        $docTypes = Tools::getValue('internautenav_doc_type', null);

        return is_array($docTypes) && array_key_exists($carrierId, $docTypes);
    }

    private function isAlreadyVerifiedForCheckout()
    {
        $carrier = $this->getCurrentCheckoutCarrier();
        if (!$carrier) {
            $this->clearCheckoutVerificationState();
            return false;
        }

        $idCustomer = $this->resolveCheckoutCustomerId();
        if ($idCustomer > 0) {
            return $this->isCustomerVerified($idCustomer);
        }

        return $this->isCheckoutVerifiedForCarrier($carrier['id'], $carrier['reference']);
    }

    private function resolveCheckoutCustomerId()
    {
        if (Validate::isLoadedObject($this->context->customer)) {
            $idCustomer = (int) $this->context->customer->id;
            if ($idCustomer > 0) {
                return $idCustomer;
            }
        }

        if (Validate::isLoadedObject($this->context->cart)) {
            $idCustomer = (int) $this->context->cart->id_customer;
            if ($idCustomer > 0) {
                return $idCustomer;
            }
        }

        return 0;
    }

    private function extractMrzPayloadForCarrier($carrierId)
    {
        $carrierId = (int) $carrierId;
        $docTypes = Tools::getValue('internautenav_doc_type', []);
        $line1List = Tools::getValue('internautenav_mrz_line1', []);
        $line2List = Tools::getValue('internautenav_mrz_line2', []);
        $line3List = Tools::getValue('internautenav_mrz_line3', []);

        return [
            'doc_type' => $this->extractByCarrierKey($docTypes, $carrierId),
            'line1' => $this->extractByCarrierKey($line1List, $carrierId),
            'line2' => $this->extractByCarrierKey($line2List, $carrierId),
            'line3' => $this->extractByCarrierKey($line3List, $carrierId),
        ];
    }

    private function extractByCarrierKey($value, $carrierId)
    {
        if (is_array($value)) {
            if (array_key_exists($carrierId, $value)) {
                return (string) $value[$carrierId];
            }

            return '';
        }

        return (string) $value;
    }

    private function resolveSelectedCarrierId()
    {
        $deliveryOption = Tools::getValue('delivery_option');
        if (empty($deliveryOption) && Validate::isLoadedObject($this->context->cart)) {
            $deliveryOption = $this->context->cart->getDeliveryOption();
        }

        if (is_array($deliveryOption)) {
            $first = reset($deliveryOption);
        } else {
            $first = $deliveryOption;
        }

        if (!is_string($first) || $first === '') {
            return 0;
        }

        if (preg_match('/^(\d+),/', $first, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function addCheckoutError($message)
    {
        if (!isset($this->context->controller->errors) || !is_array($this->context->controller->errors)) {
            return;
        }

        $this->context->controller->errors[] = $message;
    }

    private function failCheckoutValidation($message)
    {
        $this->addCheckoutError($message);

        return false;
    }

    private function isCarrierReferenceRequired($carrierReference)
    {
        return in_array((int) $carrierReference, $this->getRequiredCarrierReferences(), true);
    }

    private function getRequiredCarrierReferences()
    {
        $raw = (string) Configuration::get(self::CONF_REQUIRED_CARRIER_REFS);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $decoded)));
    }

    private function setGuestVerificationInSession(array $data)
    {
        $this->setCheckoutVerificationState($data);
    }

    private function isGuestVerified()
    {
        return $this->isAlreadyVerifiedForCheckout();
    }

    private function getCurrentCheckoutCarrier()
    {
        $carrierId = $this->resolveSelectedCarrierId();
        if ($carrierId <= 0) {
            return null;
        }

        $carrier = new Carrier($carrierId);
        if (!Validate::isLoadedObject($carrier)) {
            return null;
        }

        return [
            'id' => (int) $carrier->id,
            'reference' => (int) $carrier->id_reference,
            'name' => (string) $carrier->name,
            'required' => $this->isCarrierReferenceRequired((int) $carrier->id_reference),
        ];
    }

    private function getCheckoutVerificationState()
    {
        if (!isset($_SESSION[self::SESSION_VERIFICATION_KEY]) || !is_array($_SESSION[self::SESSION_VERIFICATION_KEY])) {
            return [];
        }

        return $_SESSION[self::SESSION_VERIFICATION_KEY];
    }

    private function setCheckoutVerificationState(array $data)
    {
        $carrier = $this->getCurrentCheckoutCarrier();
        if (!$carrier) {
            return;
        }

        $_SESSION[self::SESSION_VERIFICATION_KEY] = [
            'verified' => true,
            'cart_id' => (int) $this->context->cart->id,
            'carrier_id' => (int) $carrier['id'],
            'carrier_reference' => (int) $carrier['reference'],
            'doc_type' => (string) ($data['doc_type'] ?? ''),
            'birth_date' => (string) ($data['birth_date'] ?? ''),
            'firstname' => (string) ($data['firstname'] ?? ''),
            'lastname' => (string) ($data['lastname'] ?? ''),
            'verified_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function clearCheckoutVerificationState()
    {
        if (isset($_SESSION[self::SESSION_VERIFICATION_KEY])) {
            unset($_SESSION[self::SESSION_VERIFICATION_KEY]);
        }

        unset($_SESSION['internautenav_guest_verified'], $_SESSION['internautenav_guest_data']);
    }

    private function isCheckoutVerifiedForCarrier($carrierId, $carrierReference)
    {
        $state = $this->getCheckoutVerificationState();
        if (empty($state)) {
            return false;
        }

        $cartId = Validate::isLoadedObject($this->context->cart) ? (int) $this->context->cart->id : 0;
        $isValid = !empty($state['verified'])
            && (int) ($state['cart_id'] ?? 0) === $cartId
            && (int) ($state['carrier_id'] ?? 0) === (int) $carrierId
            && (int) ($state['carrier_reference'] ?? 0) === (int) $carrierReference;

        if (!$isValid) {
            $this->clearCheckoutVerificationState();
            return false;
        }

        return true;
    }

    private function installDatabase()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::DB_TABLE . '` (
            `id_customer` INT(10) UNSIGNED NOT NULL,
            `is_verified` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `doc_type` VARCHAR(16) NOT NULL,
            `birth_date` DATE NULL,
            `firstname` VARCHAR(64) NULL,
            `lastname` VARCHAR(64) NULL,
            `verified_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_customer`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql) && $this->ensureVerificationLogTable() && $this->ensureUploadTable();
    }

    private function uninstallDatabase()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::DB_TABLE . '`;';
        $logSql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '`;';
        $uploadSql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`;';

        return Db::getInstance()->execute($sql) && Db::getInstance()->execute($logSql) && Db::getInstance()->execute($uploadSql);
    }

    private function ensureVerificationLogTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '` (
            `id_internautenav_verification_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `customer_reference` VARCHAR(64) NOT NULL,
            `id_customer` INT(10) UNSIGNED NULL,
            `id_guest` INT(10) UNSIGNED NULL,
            `id_cart` INT(10) UNSIGNED NULL,
            `doc_type` VARCHAR(16) NOT NULL,
            `result` TINYINT(1) UNSIGNED NOT NULL,
            `result_message` VARCHAR(255) NULL,
            `checked_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_internautenav_verification_log`),
            KEY `idx_customer_reference` (`customer_reference`),
            KEY `idx_checked_at` (`checked_at`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    private function ensureUploadTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '` (
            `id_internautenav_uploaded_document` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_cart` INT(10) UNSIGNED NOT NULL,
            `id_order` INT(10) UNSIGNED NULL,
            `id_customer` INT(10) UNSIGNED NULL,
            `doc_type` VARCHAR(16) NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `file_size` INT(10) UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `attached_at` DATETIME NULL,
            PRIMARY KEY (`id_internautenav_uploaded_document`),
            KEY `idx_id_cart` (`id_cart`),
            KEY `idx_id_order` (`id_order`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    private function validateUploadForCarrier($carrierId, $carrierReference, array $payload, $persistOnSuccess)
    {
        $uploadFile = isset($payload['upload_file']) && is_array($payload['upload_file']) ? $payload['upload_file'] : null;
        if (!$uploadFile) {
            return $this->finalizeMrzValidationResult('upload', false, 'Bitte waehlen Sie eine Datei zum Hochladen.');
        }

        $storeResult = $this->storeUploadedDocument($uploadFile, 'upload');
        if (empty($storeResult['valid'])) {
            return $this->finalizeMrzValidationResult('upload', false, (string) ($storeResult['message'] !== '' ? $storeResult['message'] : 'Das Dokument konnte nicht gespeichert werden.'));
        }

        // Nach erfolgreichem move_uploaded_file existiert die tmp-Datei nicht mehr.
        // Symfony's FileBag (Request::createFromGlobals) wirft sonst FileNotFoundException,
        // wenn nachfolgende PS-Operationen (Cart::getDeliveryOption etc.) den Dispatcher triggern.
        unset($_FILES['document_upload']);

        if ($persistOnSuccess) {
            $verificationData = [
                'carrier_id' => (int) $carrierId,
                'carrier_reference' => (int) $carrierReference,
                'doc_type' => 'upload',
                'birth_date' => null,
                'firstname' => '',
                'lastname' => '',
                'uploaded_document_id' => (int) ($storeResult['uploaded_document_id'] ?? 0),
            ];

            $idCustomer = $this->resolveCheckoutCustomerId();
            if ($idCustomer > 0) {
                if (!$this->setCustomerVerified($idCustomer, $verificationData)) {
                    return $this->finalizeMrzValidationResult('upload', false, 'Verifikationsstatus konnte nicht gespeichert werden.');
                }
            } else {
                $this->setCheckoutVerificationState($verificationData);
            }
        }

        return $this->finalizeMrzValidationResult('upload', true, 'Dokument erfolgreich gespeichert.');
    }

    private function storeUploadedDocument(array $uploadFile, $docType)
    {
        if (!$this->ensureUploadTable()) {
            return [
                'valid' => false,
                'message' => 'Datenbank-Fehler: Upload-Tabelle konnte nicht erstellt werden.',
            ];
        }

        $uploadError = isset($uploadFile['error']) ? (int) $uploadFile['error'] : UPLOAD_ERR_NO_FILE;
        if ($uploadError !== UPLOAD_ERR_OK) {
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'Die Datei ueberschreitet die maximale Upload-Groesse (server).',
                UPLOAD_ERR_FORM_SIZE  => 'Die Datei ueberschreitet die maximale Upload-Groesse (formular).',
                UPLOAD_ERR_PARTIAL    => 'Die Datei wurde nur teilweise hochgeladen.',
                UPLOAD_ERR_NO_FILE    => 'Keine Datei empfangen.',
                UPLOAD_ERR_NO_TMP_DIR => 'Kein temporaeres Verzeichnis verfuegbar.',
                UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht auf dem Server gespeichert werden.',
            ];
            $errMsg = isset($uploadErrorMessages[$uploadError])
                ? $uploadErrorMessages[$uploadError]
                : 'Datei-Upload fehlgeschlagen (Code ' . $uploadError . ').';
            return ['valid' => false, 'message' => $errMsg];
        }

        $tmpName = isset($uploadFile['tmp_name']) ? (string) $uploadFile['tmp_name'] : '';
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return [
                'valid' => false,
                'message' => 'Ungueltige Upload-Datei (Sicherheitspruefung fehlgeschlagen).',
            ];
        }

        $maxSize = 10 * 1024 * 1024;
        $fileSize = isset($uploadFile['size']) ? (int) $uploadFile['size'] : 0;
        if ($fileSize <= 0 || $fileSize > $maxSize) {
            return [
                'valid' => false,
                'message' => 'Die Datei ist zu gross. Maximal 10 MB sind erlaubt.',
            ];
        }

        $originalName = isset($uploadFile['name']) ? (string) $uploadFile['name'] : 'document';
        $originalName = basename($originalName);
        $extension = Tools::strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'bmp', 'gif', 'wmf'];
        if (!in_array($extension, $allowedExtensions, true)) {
            return [
                'valid' => false,
                'message' => 'Bitte laden Sie eine Datei im Format JPG, JPEG, PNG, BMP, GIF oder WMF hoch. (Erkannte Endung: ' . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') . ')',
            ];
        }

        $mimeType = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = finfo_file($finfo, $tmpName);
                if (is_string($detectedMime)) {
                    $mimeType = $detectedMime;
                }
                finfo_close($finfo);
            }
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/bmp', 'image/x-bmp', 'image/gif', 'image/wmf', 'image/x-wmf', 'application/x-msmetafile'];
        if ($mimeType !== '' && !in_array($mimeType, $allowedMimes, true)) {
            return [
                'valid' => false,
                'message' => 'Ungueltige Datei. Erkannter Typ: ' . $mimeType . '. Erlaubt: JPG, PNG, BMP, GIF, WMF.',
            ];
        }

        $uploadDir = rtrim(_PS_MODULE_DIR_, '/\\') . '/' . $this->name . '/uploads';
        $pendingDir = $uploadDir . '/pending';
        if (!is_dir($pendingDir) && !@mkdir($pendingDir, 0755, true)) {
            return [
                'valid' => false,
                'message' => $this->l('Upload-Verzeichnis konnte nicht erstellt werden.'),
            ];
        }

        $randomPart = '';
        try {
            $randomPart = bin2hex(random_bytes(12));
        } catch (Exception $exception) {
            $randomPart = sha1(uniqid((string) mt_rand(), true));
        }

        $generated = date('YmdHis') . '_' . $randomPart;
        $safeFileName = $generated . '.' . $extension;
        $targetPath = $pendingDir . '/' . $safeFileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            return [
                'valid' => false,
                'message' => 'Die Datei konnte nicht ins Zielverzeichnis verschoben werden.',
            ];
        }

        $idCart = Validate::isLoadedObject($this->context->cart) ? (int) $this->context->cart->id : 0;
        $idCustomer = Validate::isLoadedObject($this->context->customer) ? (int) $this->context->customer->id : null;
        $insertOk = Db::getInstance()->insert(self::DB_UPLOAD_TABLE, [
            'id_cart' => $idCart,
            'id_order' => null,
            'id_customer' => $idCustomer > 0 ? $idCustomer : null,
            'doc_type' => pSQL((string) $docType),
            'file_name' => pSQL($safeFileName),
            'original_name' => pSQL($originalName),
            'mime_type' => pSQL($mimeType !== '' ? $mimeType : 'application/octet-stream'),
            'file_size' => (int) $fileSize,
            'created_at' => date('Y-m-d H:i:s'),
            'attached_at' => null,
        ]);

        if (!$insertOk) {
            @unlink($targetPath);
            return [
                'valid' => false,
                'message' => 'Datenbankfehler beim Speichern des Dokuments.',
            ];
        }

        return [
            'valid' => true,
            'uploaded_document_id' => (int) Db::getInstance()->Insert_ID(),
        ];
    }

    private function attachPendingUploadsToOrder($idCart, $idOrder)
    {
        $idCart = (int) $idCart;
        $idOrder = (int) $idOrder;
        if ($idCart <= 0 || $idOrder <= 0) {
            return;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_internautenav_uploaded_document`, `file_name`
             FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
             WHERE id_cart = ' . $idCart . ' AND (id_order IS NULL OR id_order = 0)'
        );

        if (!is_array($rows) || empty($rows)) {
            return;
        }

        $baseDir = rtrim(_PS_MODULE_DIR_, '/\\') . '/' . $this->name . '/uploads';
        $pendingDir = $baseDir . '/pending';

        foreach ($rows as $row) {
            $safeFile = basename((string) $row['file_name']);
            if ($safeFile === '') {
                continue;
            }
            $src = $pendingDir . '/' . $safeFile;
            $dst = $baseDir . '/' . $safeFile;
            if (is_file($src)) {
                @rename($src, $dst);
            }
        }

        Db::getInstance()->update(
            self::DB_UPLOAD_TABLE,
            [
                'id_order' => $idOrder,
                'attached_at' => date('Y-m-d H:i:s'),
            ],
            'id_cart = ' . $idCart . ' AND (id_order IS NULL OR id_order = 0)'
        );
    }

    public function serveUploadedDocumentDownload($documentId, $orderId = 0)
    {
        if (!$this->ensureUploadTable()) {
            http_response_code(500);
            exit('Upload table is not available');
        }

        $documentId = (int) $documentId;
        $orderId = (int) $orderId;

        // Token-Authentifizierung: gebunden an documentId + orderId + _COOKIE_KEY_
        $expectedToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_dl' . $documentId . $orderId);
        $providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';
        if (!hash_equals($expectedToken, $providedToken)) {
            http_response_code(403);
            exit('Forbidden');
        }
        if ($documentId <= 0) {
            http_response_code(404);
            exit('Document not found');
        }

        $where = 'id_internautenav_uploaded_document = ' . $documentId . ' AND id_order > 0';
        if ($orderId > 0) {
            $where .= ' AND id_order = ' . $orderId;
        }

        $row = Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '` WHERE ' . $where
        );

        if (!is_array($row) || empty($row)) {
            http_response_code(404);
            exit('Document not found');
        }

        $safeStoredName = basename((string) $row['file_name']);
        if ($safeStoredName === '') {
            http_response_code(404);
            exit('File missing');
        }

        $baseDir = rtrim(_PS_MODULE_DIR_, '/\\') . '/' . $this->name . '/uploads';
        $candidatePaths = [
            $baseDir . '/pending/' . $safeStoredName,
            $baseDir . '/' . $safeStoredName,
        ];

        $resolvedPath = '';
        foreach ($candidatePaths as $candidatePath) {
            if (is_file($candidatePath)) {
                $resolvedPath = $candidatePath;
                break;
            }
        }

        if ($resolvedPath === '') {
            http_response_code(404);
            exit('File missing');
        }

        $downloadName = trim((string) $row['original_name']) !== '' ? (string) $row['original_name'] : $safeStoredName;
        $mimeType = trim((string) $row['mime_type']) !== '' ? (string) $row['mime_type'] : 'application/octet-stream';
        $fileSize = (int) filesize($resolvedPath);

        if (ob_get_level()) {
            @ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Expires: 0');

        readfile($resolvedPath);
        exit;
    }

    private function resolveOrderIdFromHookParams($params)
    {
        if (isset($params['id_order'])) {
            return (int) $params['id_order'];
        }

        if (isset($params['order']) && Validate::isLoadedObject($params['order'])) {
            return (int) $params['order']->id;
        }

        return (int) Tools::getValue('id_order');
    }

    private function getUploadedDocumentsByOrder($idOrder)
    {
        $idOrder = (int) $idOrder;
        if ($idOrder <= 0) {
            return [];
        }

        $sql = 'SELECT *
            FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
            WHERE `id_order` = ' . $idOrder . '
            ORDER BY `created_at` DESC';

        $rows = Db::getInstance()->executeS($sql);

        return is_array($rows) ? $rows : [];
    }

    private function formatFileSize($bytes)
    {
        $bytes = max(0, (int) $bytes);
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return number_format($kb, 1, '.', '') . ' KB';
        }

        $mb = $kb / 1024;
        return number_format($mb, 2, '.', '') . ' MB';
    }

    private function deletePendingUploads()
    {
        if (!$this->ensureUploadTable()) {
            return 0;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_internautenav_uploaded_document`, `file_name`
             FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
             WHERE (id_order IS NULL OR id_order = 0)'
        );

        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id_internautenav_uploaded_document'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $this->deleteUploadedDocumentPhysicalFile((string) ($row['file_name'] ?? ''));
            $ids[] = $id;
        }

        if (empty($ids)) {
            return 0;
        }

        Db::getInstance()->delete(
            self::DB_UPLOAD_TABLE,
            'id_internautenav_uploaded_document IN (' . implode(',', array_map('intval', $ids)) . ')'
        );

        return count($ids);
    }

    public function runUploadRetentionCleanup($force = false)    {
        if (!$this->ensureUploadTable()) {
            return 0;
        }

        $now = time();
        $lastRun = (int) Configuration::get(self::CONF_LAST_UPLOAD_CLEANUP_AT);
        if (!$force && $lastRun > 0 && ($now - $lastRun) < self::UPLOAD_CLEANUP_MIN_INTERVAL_SECONDS) {
            return 0;
        }

        Configuration::updateValue(self::CONF_LAST_UPLOAD_CLEANUP_AT, (string) $now);

        $cutoff = date('Y-m-d H:i:s', strtotime('-' . self::UPLOAD_RETENTION_DAYS . ' days', $now));
        $rows = Db::getInstance()->executeS(
            'SELECT `id_internautenav_uploaded_document`, `file_name`
             FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
             WHERE `created_at` < \'' . pSQL($cutoff) . '\''
        );

        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id_internautenav_uploaded_document'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $this->deleteUploadedDocumentPhysicalFile((string) ($row['file_name'] ?? ''));
            $ids[] = $id;
        }

        if (empty($ids)) {
            return 0;
        }

        Db::getInstance()->delete(
            self::DB_UPLOAD_TABLE,
            'id_internautenav_uploaded_document IN (' . implode(',', array_map('intval', $ids)) . ')'
        );

        return count($ids);
    }

    private function deleteUploadedDocumentPhysicalFile($storedFileName)
    {
        $safeStoredName = basename((string) $storedFileName);
        if ($safeStoredName === '') {
            return;
        }

        $baseDir = rtrim(_PS_MODULE_DIR_, '/\\') . '/' . $this->name . '/uploads';
        $candidatePaths = [
            $baseDir . '/pending/' . $safeStoredName,
            $baseDir . '/' . $safeStoredName,
        ];

        foreach ($candidatePaths as $candidatePath) {
            if (is_file($candidatePath)) {
                @unlink($candidatePath);
            }
        }
    }

    private function finalizeMrzValidationResult($docType, $isValid, $message)
    {
        $this->logMrzVerificationAttempt((string) $docType, (bool) $isValid, (string) $message);

        $result = [
            'valid' => (bool) $isValid,
            'message' => (string) $message,
        ];

        return $result;
    }

    private function logMrzVerificationAttempt($docType, $isValid, $message)
    {
        if (!$this->ensureVerificationLogTable()) {
            return false;
        }

        $subject = $this->getVerificationLogSubject();

        return Db::getInstance()->insert(self::DB_LOG_TABLE, [
            'customer_reference' => pSQL($subject['customer_reference']),
            'id_customer' => $subject['id_customer'] > 0 ? (int) $subject['id_customer'] : null,
            'id_guest' => $subject['id_guest'] > 0 ? (int) $subject['id_guest'] : null,
            'id_cart' => $subject['id_cart'] > 0 ? (int) $subject['id_cart'] : null,
            'doc_type' => pSQL((string) $docType),
            'result' => (int) $isValid,
            'result_message' => $message !== '' ? pSQL((string) $message) : null,
            'checked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getVerificationLogSubject()
    {
        $idCustomer = 0;
        if (Validate::isLoadedObject($this->context->customer)) {
            $idCustomer = (int) $this->context->customer->id;
        }

        $idGuest = 0;
        if (Validate::isLoadedObject($this->context->cart)) {
            $idGuest = (int) $this->context->cart->id_guest;
        }

        $idCart = Validate::isLoadedObject($this->context->cart) ? (int) $this->context->cart->id : 0;

        if ($idCustomer > 0) {
            return [
                'customer_reference' => 'customer-' . $idCustomer,
                'id_customer' => $idCustomer,
                'id_guest' => $idGuest,
                'id_cart' => $idCart,
            ];
        }

        if ($idGuest > 0) {
            return [
                'customer_reference' => 'guest-' . $idGuest,
                'id_customer' => 0,
                'id_guest' => $idGuest,
                'id_cart' => $idCart,
            ];
        }

        return [
            'customer_reference' => 'guest-cart-' . $idCart,
            'id_customer' => 0,
            'id_guest' => 0,
            'id_cart' => $idCart,
        ];
    }

    public function isCustomerVerified($idCustomer)
    {
        $idCustomer = (int) $idCustomer;
        if ($idCustomer <= 0) {
            return false;
        }

        $sql = 'SELECT `is_verified`
            FROM `' . _DB_PREFIX_ . self::DB_TABLE . '`
            WHERE `id_customer` = ' . $idCustomer;

        $value = Db::getInstance()->getValue($sql);

        return (bool) $value;
    }

    public function adminApproveOrderDocuments($orderId)
    {
        return $this->adminDecideOrderDocuments((int) $orderId, true);
    }

    public function adminRejectOrderDocuments($orderId)
    {
        return $this->adminDecideOrderDocuments((int) $orderId, false);
    }

    private function adminDecideOrderDocuments($orderId, $approved)
    {
        $orderId = (int) $orderId;
        if ($orderId <= 0 || !$this->ensureUploadTable() || !$this->ensureVerificationLogTable()) {
            return ['success' => false, 'message' => 'Ungültige Bestellnummer oder Datenbankfehler.'];
        }

        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return ['success' => false, 'message' => 'Bestellung nicht gefunden.'];
        }

        $rows = $this->getUploadedDocumentsByOrder($orderId);
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Keine Dokumente für diese Bestellung gefunden.'];
        }

        $idCustomer = (int) $order->id_customer;
        $idCart = (int) $order->id_cart;
        $customerRef = $idCustomer > 0 ? 'customer-' . $idCustomer : 'cart-' . $idCart;
        $resultLabel = $approved ? 'Manuelle Prüfung durch Admin: bestanden' : 'Manuelle Prüfung durch Admin: abgelehnt';

        Db::getInstance()->insert(self::DB_LOG_TABLE, [
            'customer_reference' => pSQL($customerRef),
            'id_customer' => $idCustomer > 0 ? $idCustomer : null,
            'id_guest' => null,
            'id_cart' => $idCart > 0 ? $idCart : null,
            'doc_type' => pSQL('upload'),
            'result' => (int) $approved,
            'result_message' => pSQL($resultLabel),
            'checked_at' => date('Y-m-d H:i:s'),
        ]);

        if ($approved && $idCustomer > 0) {
            $this->setCustomerVerified($idCustomer, [
                'doc_type' => 'upload',
                'birth_date' => null,
                'firstname' => null,
                'lastname' => null,
            ]);
        }

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) $row['id_internautenav_uploaded_document'];
            if ($id > 0) {
                $this->deleteUploadedDocumentPhysicalFile((string) $row['file_name']);
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            Db::getInstance()->delete(
                self::DB_UPLOAD_TABLE,
                'id_internautenav_uploaded_document IN (' . implode(',', array_map('intval', $ids)) . ')'
            );
        }

        $verb = $approved ? 'bestanden' : 'abgelehnt';

        return ['success' => true, 'message' => 'Prüfung ' . $verb . '. ' . count($ids) . ' Datei(en) DSGVO-konform gelöscht.'];
    }

    private function setCustomerVerified($idCustomer, array $data)    {
        $idCustomer = (int) $idCustomer;
        if ($idCustomer <= 0) {
            return false;
        }

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . self::DB_TABLE . '`
            (`id_customer`, `is_verified`, `doc_type`, `birth_date`, `firstname`, `lastname`, `verified_at`)
            VALUES (
                ' . $idCustomer . ',
                1,
                \'' . pSQL((string) $data['doc_type']) . '\',
                ' . (!empty($data['birth_date']) ? '\'' . pSQL((string) $data['birth_date']) . '\'' : 'NULL') . ',
                ' . (!empty($data['firstname']) ? '\'' . pSQL((string) $data['firstname']) . '\'' : 'NULL') . ',
                ' . (!empty($data['lastname']) ? '\'' . pSQL((string) $data['lastname']) . '\'' : 'NULL') . ',
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                `is_verified` = VALUES(`is_verified`),
                `doc_type` = VALUES(`doc_type`),
                `birth_date` = VALUES(`birth_date`),
                `firstname` = VALUES(`firstname`),
                `lastname` = VALUES(`lastname`),
                `verified_at` = VALUES(`verified_at`)';

        return Db::getInstance()->execute($sql);
    }
}
