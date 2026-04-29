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
    public const DB_TABLE = 'internautenav_customer_verification';
    public const DB_LOG_TABLE = 'internautenav_verification_log';
    private const SESSION_VERIFICATION_KEY = 'internautenav_checkout_verification';

    public function __construct()
    {
        $this->name = 'internautenav';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.7';
        $this->author = 'Internauten';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Internauten AV');
        $this->description = $this->l('MRZ-Verifikation fuer ausgewaehlte Versandarten (CH ID, CH Pass, EU Pass).');
        $this->ps_versions_compliancy = [
            'min' => '1.7.8.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->registerHook('displayPaymentTop')
            && $this->registerHook('displayCarrierExtraContent')
            && $this->registerHook('displayAfterCarrier')
            && $this->registerHook('displayBeforeCarrier')
            && $this->registerHook('actionCarrierProcess')
            && $this->registerHook('actionValidateStepComplete')
            && $this->installDatabase()
            && Configuration::updateValue(self::CONF_REQUIRED_CARRIER_REFS, json_encode([]));
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CONF_REQUIRED_CARRIER_REFS)
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

            $selectedRefs = array_values(array_unique(array_map('intval', $selectedRefs)));
            Configuration::updateValue(self::CONF_REQUIRED_CARRIER_REFS, json_encode($selectedRefs));
            $output .= $this->displayConfirmation($this->l('Einstellungen gespeichert.'));
        }

        $current = $this->getRequiredCarrierReferences();
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
        $output .= '<h3>' . $this->l('MRZ-Verifikation nach Versandart') . '</h3>';
        $output .= '<p>' . $this->l('Waehlen Sie die Versandarten aus, fuer die die MRZ-Pruefung im Checkout erzwungen werden soll.') . '</p>';
        $output .= '<form method="post" action="' . $action . '">';
        $output .= '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        $output .= '<div class="form-group">';
        $output .= '<label>' . $this->l('Versandarten mit MRZ-Pflicht') . '</label>';
        $output .= '<select name="INTERNAUTENAV_REQUIRED_CARRIER_REFS[]" class="form-control" multiple size="10">';

        foreach ($carriers as $carrierRow) {
            $idRef = (int) $carrierRow['id_reference'];
            $idCarrier = (int) $carrierRow['id_carrier'];
            $label = sprintf('#%d / Ref %d - %s', $idCarrier, $idRef, $carrierRow['name']);
            $selected = in_array($idRef, $current, true) ? ' selected' : '';
            $output .= '<option value="' . $idRef . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        }

        $output .= '</select>';
        $output .= '<p class="help-block">' . $this->l('Es wird mit id_reference gespeichert, damit die Auswahl bei Carrier-Neuanlage stabil bleibt.') . '</p>';
        $output .= '</div>';
        $output .= '<button type="submit" name="submitInternautenavConfig" class="btn btn-primary">' . $this->l('Speichern') . '</button>';
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
        $output .= '<h3>' . $this->l('Debug: Verifikations-Log (letzte 200 Eintraege)') . '</h3>';
        $output .= '<div style="overflow-x:auto">';
        $output .= '<table class="table table-bordered table-striped" style="font-size:12px">';
        $output .= '<thead><tr>';
        foreach ([
            'ID', 'Zeitpunkt', 'Referenz', 'Kunde', 'id_cart', 'Dokument', 'Ergebnis', 'Meldung',
        ] as $th) {
            $output .= '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        if (empty($logRows)) {
            $output .= '<tr><td colspan="8" class="text-center text-muted">' . $this->l('Keine Eintraege.') . '</td></tr>';
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
            $output .= '<td style="font-weight:bold">' . ($isOk ? '&#10003; OK' : '&#10007; Fehler') . '</td>';
            $output .= $td($row['result_message'] ?? '');
            $output .= '</tr>';
        }

        $output .= '</tbody></table>';
        $output .= '</div>';
        $output .= '</div>';

        // --- Persistent verifications ---
        $output .= '<div class="panel">';
        $output .= '<h3>' . $this->l('Debug: Gespeicherte Verifikationen (eingeloggte Kunden)') . '</h3>';
        $output .= '<div style="overflow-x:auto">';
        $output .= '<table class="table table-bordered table-striped" style="font-size:12px">';
        $output .= '<thead><tr>';
        foreach ([
              'ID', 'id_customer', 'Kunde', 'E-Mail', 'Dokument', 'Geburtsdatum', 'verified_at',
        ] as $th) {
            $output .= '<th>' . htmlspecialchars($th, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $output .= '</tr></thead><tbody>';

        if (empty($persistRows)) {
            $output .= '<tr><td colspan="6" class="text-center text-muted">' . $this->l('Keine Eintraege.') . '</td></tr>';
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

        return $output;
    }

    public function hookActionFrontControllerSetMedia()
    {
        if ($this->context->controller->php_self !== 'order') {
            return;
        }

        if (!$this->isRegisteredInHook('displayPaymentTop')) {
            $this->registerHook('displayPaymentTop');
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

    public function hookDisplayCarrierExtraContent($params)
    {
        return '';
    }

    public function hookDisplayAfterCarrier($params)
    {
        return '';
    }

    public function hookDisplayBeforeCarrier($params)
    {
        return '';
    }

    public function hookDisplayPaymentTop($params)
    {
        $carrier = $this->getCurrentCheckoutCarrier();
        if (!$carrier || !$carrier['required']) {
            $this->clearCheckoutVerificationState();
            return '';
        }

        $isVerified = $this->isAlreadyVerifiedForCheckout();

        $this->context->smarty->assign([
            'internautenav_carrier_id' => $carrier['id'],
            'internautenav_carrier_name' => $carrier['name'],
            'internautenav_is_verified' => $isVerified,
            'internautenav_payment_title' => $this->l('Alterspruefung fuer diese Versandart'),
            'internautenav_payment_intro' => $this->l('Fuer die gewaehlte Versandart ist vor der Zahlung eine Alters- und Identitaetspruefung erforderlich.'),
            'internautenav_payment_link' => $this->l('MRZ-Pruefung jetzt starten'),
            'internautenav_payment_success' => $this->l('MRZ-Pruefung erfolgreich abgeschlossen. Die Zahlung ist freigeschaltet.'),
            'internautenav_payment_locked' => $this->l('Solange die erfolgreiche Pruefung nicht serverseitig vorliegt, bleiben die Zahlungsfelder gesperrt.'),
            'internautenav_modal_title' => $this->l('MRZ-Daten eingeben'),
            'internautenav_modal_close' => $this->l('Schliessen'),
            'internautenav_modal_submit' => $this->l('Jetzt pruefen'),
            'internautenav_doc_label' => $this->l('Dokumenttyp'),
            'internautenav_doc_ch_id' => $this->l('Schweizer ID (3 Zeilen)'),
            'internautenav_doc_ch_pass' => $this->l('Schweizer Pass (2 Zeilen)'),
            'internautenav_doc_eu_pass' => $this->l('EU Pass (2 Zeilen)'),
            'internautenav_line1_label' => $this->l('MRZ Zeile 1'),
            'internautenav_line2_label' => $this->l('MRZ Zeile 2'),
            'internautenav_line3_label' => $this->l('MRZ Zeile 3 (nur CH ID)'),
            'internautenav_hint' => $this->l('Bitte Zeilen exakt wie im Dokument inklusive < eingeben.'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/payment_gate.tpl');
    }

    private function renderMrzForm($carrierId, $carrierReference)
    {
        $carrierId = (int) $carrierId;
        $carrierReference = (int) $carrierReference;

        if ($carrierId <= 0 || $carrierReference <= 0) {
            return '';
        }

        if (!$this->isCarrierReferenceRequired($carrierReference)) {
            return '';
        }

        if ($this->isAlreadyVerifiedForCheckout()) {
            return '';
        }

        $this->context->smarty->assign([
            'internautenav_carrier_id' => $carrierId,
            'internautenav_intro' => $this->l('Fuer diese Versandart ist eine Alters- und Identitaetspruefung ueber MRZ erforderlich.'),
            'internautenav_doc_label' => $this->l('Dokumenttyp'),
            'internautenav_doc_ch_id' => $this->l('Schweizer ID (3 Zeilen)'),
            'internautenav_doc_ch_pass' => $this->l('Schweizer Pass (2 Zeilen)'),
            'internautenav_doc_eu_pass' => $this->l('EU Pass (2 Zeilen)'),
            'internautenav_line1_label' => $this->l('MRZ Zeile 1'),
            'internautenav_line2_label' => $this->l('MRZ Zeile 2'),
            'internautenav_line3_label' => $this->l('MRZ Zeile 3 (nur CH ID)'),
            'internautenav_hint' => $this->l('Bitte Zeilen exakt wie im Dokument inkl. < eingeben.'),
        ]);

        $output = $this->display(__FILE__, 'views/templates/hook/carrier_extra_form.tpl');
        $this->debugLog('rendered carrier extra content length=' . Tools::strlen((string) $output));

        return $output;
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

    public function validateMrzForCarrier($carrierId, array $payload, $persistOnSuccess = false)
    {
        $this->ensureVerificationLogTable();

        $docType = (string) ($payload['doc_type'] ?? '');
        $carrierId = (int) $carrierId;
        if ($carrierId <= 0) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('Ungueltiger Carrier.'));
        }

        $carrier = new Carrier($carrierId);
        if (!Validate::isLoadedObject($carrier)) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('Carrier nicht gefunden.'));
        }

        $carrierReference = (int) $carrier->id_reference;
        if (!$this->isCarrierReferenceRequired($carrierReference)) {
            return $this->finalizeMrzValidationResult($docType, true, '');
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
                isset($validation['message']) ? (string) $validation['message'] : $this->l('MRZ ungueltig.')
            );
        }

        $idAddressDelivery = (int) $this->context->cart->id_address_delivery;
        $address = new Address($idAddressDelivery);
        if (!Validate::isLoadedObject($address)) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('Lieferadresse konnte nicht geladen werden.'));
        }

        $nameCheck = MrzValidator::matchNames($address->firstname, $address->lastname, $validation['data']);
        if (empty($nameCheck['valid'])) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('Name und Vorname der Lieferadresse stimmen nicht mit der MRZ ueberein.'));
        }

        $adultCheck = MrzValidator::isAdult($validation['data']['birth_date'], 18);
        if (empty($adultCheck['valid'])) {
            return $this->finalizeMrzValidationResult($docType, false, $this->l('Bestellung nur fuer volljaehrige Personen (18+).'));
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

            if ($this->context->customer->isLogged()) {
                $idCustomer = (int) $this->context->customer->id;
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
        if ($this->context->customer->isLogged()) {
            $idCustomer = (int) $this->context->customer->id;
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

        if ($this->context->customer->isLogged()) {
            return $this->isCustomerVerified((int) $this->context->customer->id);
        }

        return $this->isCheckoutVerifiedForCarrier($carrier['id'], $carrier['reference']);
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

        return Db::getInstance()->execute($sql) && $this->ensureVerificationLogTable();
    }

    private function uninstallDatabase()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::DB_TABLE . '`;';
        $logSql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '`;';

        return Db::getInstance()->execute($sql) && Db::getInstance()->execute($logSql);
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

    private function finalizeMrzValidationResult($docType, $isValid, $message)
    {
        $this->logMrzVerificationAttempt((string) $docType, (bool) $isValid, (string) $message);

        $result = [
            'valid' => (bool) $isValid,
        ];

        if (!$isValid && $message !== '') {
            $result['message'] = (string) $message;
        }

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

    private function setCustomerVerified($idCustomer, array $data)
    {
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
