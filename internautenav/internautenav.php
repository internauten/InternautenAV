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

    public function __construct()
    {
        $this->name = 'internautenav';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.6';
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

        return $output;
    }

    public function hookActionFrontControllerSetMedia()
    {
        if ($this->context->controller->php_self !== 'order') {
            return;
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
        // In PS 1.7 ist $params['carrier'] ein Array aus CheckoutDeliveryStep
        // Zum Debuggen: alle Keys ins Error-Log schreiben
        if (empty($params['carrier'])) {
            $this->debugLog('hookDisplayCarrierExtraContent - params[carrier] ist leer. params keys: ' . implode(', ', array_keys($params)));
            return '';
        }

        $carrier = $params['carrier'];
        $this->debugLog('carrier keys: ' . (is_array($carrier) ? implode(', ', array_keys($carrier)) : gettype($carrier)));

        // PS 1.7 liefert je nach Checkout-Flow unterschiedliche Strukturen.
        // Primär aus Array lesen, dann robust per Carrier-Objekt nachladen.
        if (is_array($carrier)) {
            $carrierReference = (int) ($carrier['id_reference'] ?? $carrier['id_reference_carrier'] ?? 0);
            $carrierId = (int) ($carrier['id_carrier'] ?? $carrier['id'] ?? 0);

            if ($carrierId <= 0 && isset($carrier['instance']) && $carrier['instance'] instanceof Carrier) {
                $carrierId = (int) $carrier['instance']->id;
            }

            if ($carrierReference <= 0 && isset($carrier['instance']) && $carrier['instance'] instanceof Carrier) {
                $carrierReference = (int) $carrier['instance']->id_reference;
            }
        } else {
            $carrierReference = (int) $carrier->id_reference;
            $carrierId = (int) $carrier->id;
        }

        if (($carrierId > 0) && ($carrierReference <= 0)) {
            $carrierObject = new Carrier($carrierId);
            if (Validate::isLoadedObject($carrierObject)) {
                $carrierReference = (int) $carrierObject->id_reference;
            }
        }

        $this->debugLog('carrierId=' . $carrierId . ' carrierRef=' . $carrierReference . ' required=' . (int) $this->isCarrierReferenceRequired($carrierReference));

        return $this->renderMrzForm($carrierId, $carrierReference);
    }

    public function hookDisplayAfterCarrier($params)
    {
        $carrierId = (int) ($params['id_carrier'] ?? 0);
        if ($carrierId <= 0) {
            return '';
        }

        $carrier = new Carrier($carrierId);
        if (!Validate::isLoadedObject($carrier)) {
            return '';
        }

        return $this->renderMrzForm((int) $carrier->id, (int) $carrier->id_reference);
    }

    public function hookDisplayBeforeCarrier($params)
    {
        return $this->hookDisplayAfterCarrier($params);
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
        return $this->validateCheckoutMrzIfRequired();
    }

    public function hookActionValidateStepComplete($params)
    {
        $isValid = $this->validateCheckoutMrzIfRequired();
        if ($isValid) {
            return true;
        }

        // In 1.7 kann der Checkout trotz Hook-Fehler fortfahren, wenn completed nicht explizit zurueckgesetzt wird.
        if (isset($params['completed'])) {
            $params['completed'] = false;
        }

        if (isset($params['step']) && is_object($params['step']) && method_exists($params['step'], 'setComplete')) {
            $params['step']->setComplete(false);
        }

        return false;
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
        if ($this->context->customer->isLogged()) {
            return $this->isCustomerVerified((int) $this->context->customer->id);
        }

        return $this->isGuestVerified();
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
        $_SESSION['internautenav_guest_verified'] = true;
        $_SESSION['internautenav_guest_data'] = [
            'doc_type' => (string) $data['doc_type'],
            'birth_date' => (string) $data['birth_date'],
            'firstname' => (string) $data['firstname'],
            'lastname' => (string) $data['lastname'],
        ];
    }

    private function isGuestVerified()
    {
        return isset($_SESSION['internautenav_guest_verified']) && (bool) $_SESSION['internautenav_guest_verified'];
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

        return Db::getInstance()->execute($sql);
    }

    private function uninstallDatabase()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::DB_TABLE . '`;';

        return Db::getInstance()->execute($sql);
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
