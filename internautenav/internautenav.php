<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

$mrzValidatorPath = __DIR__ . '/classes/MRZValidator.php';
if (!is_file($mrzValidatorPath)) {
    $mrzValidatorPath = __DIR__ . '/classes/MrzValidator.php';
}
require_once $mrzValidatorPath;
require_once __DIR__ . '/sql.php';

class Internautenav extends Module
{
    public const CONF_REQUIRED_CARRIER_REFS = 'INTERNAUTENAV_REQUIRED_CARRIER_REFS';
    public const CONF_LAST_UPLOAD_CLEANUP_AT = 'INTERNAUTENAV_LAST_UPLOAD_CLEANUP_AT';
    public const CONF_PRIVACY_CMS_ID = 'INTERNAUTENAV_PRIVACY_CMS_ID';
    public const CONF_STATUS_DEBUG_ENABLED = 'INTERNAUTENAV_STATUS_DEBUG_ENABLED';
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
        $this->version = '3.0.3';
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
            && Configuration::updateValue(self::CONF_STATUS_DEBUG_ENABLED, '0')
            && Configuration::updateValue(self::CONF_LAST_UPLOAD_CLEANUP_AT, '0');
    }

    public function uninstall()
    {
        return Configuration::deleteByName(self::CONF_REQUIRED_CARRIER_REFS)
            && Configuration::deleteByName(self::CONF_PRIVACY_CMS_ID)
            && Configuration::deleteByName(self::CONF_STATUS_DEBUG_ENABLED)
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
            $statusDebugEnabled = (int) Tools::getValue('INTERNAUTENAV_STATUS_DEBUG_ENABLED', 0) === 1 ? 1 : 0;

            $selectedRefs = array_values(array_unique(array_map('intval', $selectedRefs)));
            Configuration::updateValue(self::CONF_REQUIRED_CARRIER_REFS, json_encode($selectedRefs));
            Configuration::updateValue(self::CONF_PRIVACY_CMS_ID, (string) $privacyCmsId);
            Configuration::updateValue(self::CONF_STATUS_DEBUG_ENABLED, (string) $statusDebugEnabled);
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
        $statusDebugEnabled = (int) Configuration::get(self::CONF_STATUS_DEBUG_ENABLED) === 1;
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
        $persistListTable = 'internautenav_persistent';
        $logListTable = 'internautenav_log';
        if (Tools::isSubmit('submitReset' . $persistListTable)) {
            Tools::redirectAdmin(
                AdminController::$currentIndex
                . '&configure=' . $this->name
                . '&tab_module=' . $this->tab
                . '&module_name=' . $this->name
                . '&token=' . $token
            );
        }
        if (Tools::isSubmit('submitReset' . $logListTable)) {
            Tools::redirectAdmin(
                AdminController::$currentIndex
                . '&configure=' . $this->name
                . '&tab_module=' . $this->tab
                . '&module_name=' . $this->name
                . '&token=' . $token
            );
        }
        $action = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ]);

        // Prepare carriers for template
        $carriersForTemplate = [];
        foreach ($carriers as $carrierRow) {
            $idRef = (int) $carrierRow['id_reference'];
            $idCarrier = (int) $carrierRow['id_carrier'];
            $label = sprintf('#%d / Ref %d - %s', $idCarrier, $idRef, $carrierRow['name']);
            $carriersForTemplate[] = [
                'id_reference' => $idRef,
                'label' => $label,
            ];
        }

        // Prepare CMS pages for template
        $cmsPages = CMS::listCms((int) $this->context->language->id, false, true);
        if (!is_array($cmsPages)) {
            $cmsPages = [];
        }

        $this->context->smarty->assign([
            'internautenav_backoffice_title' => $this->l('backoffice_title'),
            'internautenav_backoffice_description' => $this->l('backoffice_description'),
            'internautenav_form_action' => $action,
            'internautenav_form_token' => $token,
            'internautenav_carriers' => $carriersForTemplate,
            'internautenav_selected_refs' => $current,
            'internautenav_backoffice_label' => $this->l('backoffice_label'),
            'internautenav_backoffice_help' => $this->l('backoffice_help'),
            'internautenav_privacy_cms_label' => $this->l('Datenschutzerklaerung (CMS-Seite)'),
            'internautenav_privacy_cms_id' => $privacyCmsId,
            'internautenav_cms_pages' => $cmsPages,
            'internautenav_privacy_default_label' => $this->l('Modul-Beispielseite verwenden'),
            'internautenav_privacy_cms_status_class' => $privacyCmsStatusClass,
            'internautenav_privacy_cms_status_message' => $privacyCmsStatusMessage,
            'internautenav_status_debug_enabled' => $statusDebugEnabled,
            'internautenav_status_debug_label' => $this->l('Status-Debug-Logging aktivieren'),
            'internautenav_status_debug_help' => $this->l('Schreibt Entscheidungsdaten fuer BO/PDF-Status in das PrestaShop-Log.'),
            'internautenav_status_label' => $this->l('Status'),
            'internautenav_save_button' => $this->l('backoffice_save_button'),
        ]);

        $output .= $this->display(__FILE__, 'views/templates/hook/backoffice_config.tpl');

        // --- Shared filter helper functions ---
        $listFilters = (array) Tools::getAllValues();
        $readListFilter = static function ($table, $field, array $aliases = []) use ($listFilters) {
            $keys = array_merge([$field], $aliases);
            foreach ($keys as $key) {
                $variants = [$key, str_replace('!', '_', $key), str_replace('!', '', $key)];
                foreach ($variants as $variant) {
                    $requestKey = $table . 'Filter_' . $variant;
                    if (array_key_exists($requestKey, $listFilters)) {
                        $rawValue = $listFilters[$requestKey];
                        if (is_array($rawValue)) {
                            $normalized = [];
                            foreach ($rawValue as $item) {
                                if (is_scalar($item)) {
                                    $normalized[] = (string) $item;
                                }
                            }

                            return trim(implode(' ', $normalized));
                        }
                        if (!is_scalar($rawValue) && $rawValue !== null) {
                            return '';
                        }

                        return trim((string) $rawValue);
                    }
                }
            }

            return '';
        };
        $readListDateRange = static function ($table, $field, array $aliases = []) use ($listFilters) {
            $keys = array_merge([$field], $aliases);
            foreach ($keys as $key) {
                $variants = [$key, str_replace('!', '_', $key), str_replace('!', '', $key)];
                foreach ($variants as $variant) {
                    $requestKey = $table . 'Filter_' . $variant;
                    $from = '';
                    $to = '';

                    if (array_key_exists($requestKey, $listFilters) && is_array($listFilters[$requestKey])) {
                        $rawRange = $listFilters[$requestKey];
                        $from = isset($rawRange[0]) && is_scalar($rawRange[0]) ? trim((string) $rawRange[0]) : '';
                        $to = isset($rawRange[1]) && is_scalar($rawRange[1]) ? trim((string) $rawRange[1]) : '';
                    }

                    $fromKey = $requestKey . '_0';
                    $toKey = $requestKey . '_1';
                    if (array_key_exists($fromKey, $listFilters) && is_scalar($listFilters[$fromKey])) {
                        $from = trim((string) $listFilters[$fromKey]);
                    }
                    if (array_key_exists($toKey, $listFilters) && is_scalar($listFilters[$toKey])) {
                        $to = trim((string) $listFilters[$toKey]);
                    }

                    if ($from !== '' || $to !== '') {
                        return [$from, $to];
                    }
                }
            }

            return ['', ''];
        };
        $normalizeDateForSql = static function ($value) {
            if ($value === '') {
                return '';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $value;
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+\d{2}:\d{2}(?::\d{2})?$/', $value, $m)) {
                return $m[1];
            }

            return '';
        };

        // --- Verification log panel ---
        $this->ensureVerificationLogTable();

        $logOrderByRaw = (string) Tools::getValue($logListTable . 'Orderby', 'checked_at');
        $logOrderWayRaw = strtoupper((string) Tools::getValue($logListTable . 'Orderway', 'DESC'));
        $logOrderColumns = [
            'id_internautenav_verification_log' => 'l.id_internautenav_verification_log',
            'checked_at' => 'l.checked_at',
            'customer_reference' => 'l.customer_reference',
            'customer' => 'CONCAT(COALESCE(c.firstname, \'\'), \' \', COALESCE(c.lastname, \'\'))',
            'id_cart' => 'l.id_cart',
            'doc_type' => 'l.doc_type',
            'result' => 'l.result',
        ];
        $logOrderBy = isset($logOrderColumns[$logOrderByRaw]) ? $logOrderColumns[$logOrderByRaw] : 'l.checked_at';
        $logOrderWay = in_array($logOrderWayRaw, ['ASC', 'DESC'], true) ? $logOrderWayRaw : 'DESC';

        $logWhere = [];
        $logFilterIdLog = $readListFilter($logListTable, 'id_internautenav_verification_log');
        $logFilterCustomerRef = $readListFilter($logListTable, 'customer_reference');
        $logFilterCustomerName = $readListFilter($logListTable, 'customer');
        $logFilterIdCart = $readListFilter($logListTable, 'id_cart');
        $logFilterDocType = $readListFilter($logListTable, 'doc_type');
        $logFilterResult = trim((string) Tools::getValue($logListTable . 'Filter_result', ''));
        $logFilterResultMessage = $readListFilter($logListTable, 'result_message');
        list($logFilterCheckedAtFromRaw, $logFilterCheckedAtToRaw) = $readListDateRange($logListTable, 'checked_at');
        $logFilterCheckedAtFrom = $normalizeDateForSql($logFilterCheckedAtFromRaw);
        $logFilterCheckedAtTo = $normalizeDateForSql($logFilterCheckedAtToRaw);

        if ($logFilterIdLog !== '' && ctype_digit($logFilterIdLog)) {
            $logWhere[] = 'l.id_internautenav_verification_log = ' . (int) $logFilterIdLog;
        }
        if ($logFilterCustomerRef !== '') {
            $logWhere[] = 'COALESCE(l.customer_reference, \'\') LIKE \'' . pSQL('%' . $logFilterCustomerRef . '%') . '\'';
        }
        if ($logFilterCustomerName !== '') {
            $logWhere[] = 'CONCAT(COALESCE(c.firstname, \'\'), \' \', COALESCE(c.lastname, \'\')) LIKE \'' . pSQL('%' . $logFilterCustomerName . '%') . '\'';
        }
        if ($logFilterIdCart !== '' && ctype_digit($logFilterIdCart)) {
            $logWhere[] = 'l.id_cart = ' . (int) $logFilterIdCart;
        }
        if ($logFilterDocType !== '') {
            $logWhere[] = 'COALESCE(l.doc_type, \'\') LIKE \'' . pSQL('%' . $logFilterDocType . '%') . '\'';
        }
        if ($logFilterResult !== '') {
            $logWhere[] = 'l.result = ' . (int) (in_array($logFilterResult, ['0', '1'], true) ? $logFilterResult : -1);
        }
        if ($logFilterResultMessage !== '') {
            $logWhere[] = 'COALESCE(l.result_message, \'\') LIKE \'' . pSQL('%' . $logFilterResultMessage . '%') . '\'';
        }
        if ($logFilterCheckedAtFrom !== '') {
            $logWhere[] = 'l.checked_at >= \'' . pSQL($logFilterCheckedAtFrom . ' 00:00:00') . '\'';
        }
        if ($logFilterCheckedAtTo !== '') {
            $logWhere[] = 'l.checked_at <= \'' . pSQL($logFilterCheckedAtTo . ' 23:59:59') . '\'';
        }

        $logWhereSql = '';
        if (!empty($logWhere)) {
            $logWhereSql = ' WHERE ' . implode(' AND ', $logWhere);
        }

        $logLimit = (int) Tools::getValue($logListTable . '_pagination', 20);
        if (!in_array($logLimit, [20, 50, 100, 300], true)) {
            $logLimit = 20;
        }
        $logPage = (int) Tools::getValue('submitFilter' . $logListTable, 1);
        if ($logPage < 1) {
            $logPage = 1;
        }

        $logTotal = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '` l
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = l.id_customer'
            . $logWhereSql
        );

        $logOffset = ($logPage - 1) * $logLimit;
        if ($logOffset >= $logTotal && $logTotal > 0) {
            $logPage = (int) ceil($logTotal / $logLimit);
            $logOffset = ($logPage - 1) * $logLimit;
        }

        $logRows = Db::getInstance()->executeS(
            'SELECT
                l.id_internautenav_verification_log,
                l.checked_at,
                l.customer_reference,
                c.firstname,
                c.lastname,
                c.email,
                l.id_cart,
                l.doc_type,
                l.result,
                l.result_message
             FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '` l
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = l.id_customer'
            . $logWhereSql . '
             ORDER BY ' . $logOrderBy . ' ' . $logOrderWay . '
             LIMIT ' . (int) $logOffset . ', ' . (int) $logLimit
        );
        if (!is_array($logRows)) {
            $logRows = [];
        }

        $orderByRaw = (string) Tools::getValue($persistListTable . 'Orderby', 'verified_at');
        $orderWayRaw = strtoupper((string) Tools::getValue($persistListTable . 'Orderway', 'DESC'));
        $orderColumns = [
            'id_customer' => 'v.id_customer',
            'fullname' => 'fullname',
            'email' => 'c.email',
            'doc_type' => 'v.doc_type',
            'birth_date' => 'v.birth_date',
            'verified_at' => 'v.verified_at',
        ];
        $orderBy = isset($orderColumns[$orderByRaw]) ? $orderColumns[$orderByRaw] : 'v.verified_at';
        $orderWay = in_array($orderWayRaw, ['ASC', 'DESC'], true) ? $orderWayRaw : 'DESC';

        $persistWhere = [];
        $filterIdCustomer = $readListFilter($persistListTable, 'id_customer', ['v!id_customer']);
        $filterFullname = $readListFilter($persistListTable, 'fullname');
        $filterEmail = $readListFilter($persistListTable, 'email', ['c!email']);
        $filterDocType = $readListFilter($persistListTable, 'doc_type', ['v!doc_type']);
        $filterBirthDate = $readListFilter($persistListTable, 'birth_date', ['v!birth_date']);
        list($filterVerifiedAtFromRaw, $filterVerifiedAtToRaw) = $readListDateRange($persistListTable, 'verified_at', ['v!verified_at']);
        $filterVerifiedAtFrom = $normalizeDateForSql($filterVerifiedAtFromRaw);
        $filterVerifiedAtTo = $normalizeDateForSql($filterVerifiedAtToRaw);

        if ($filterIdCustomer !== '' && ctype_digit($filterIdCustomer)) {
            $persistWhere[] = 'v.id_customer = ' . (int) $filterIdCustomer;
        }
        if ($filterFullname !== '') {
            $persistWhere[] = 'CONCAT(COALESCE(c.firstname, \'' . '\'), \'' . ' ' . '\', COALESCE(c.lastname, \'' . '\')) LIKE \'' . pSQL('%' . $filterFullname . '%') . '\'';
        }
        if ($filterEmail !== '') {
            $persistWhere[] = 'COALESCE(c.email, \'' . '\') LIKE \'' . pSQL('%' . $filterEmail . '%') . '\'';
        }
        if ($filterDocType !== '') {
            $persistWhere[] = 'COALESCE(v.doc_type, \'' . '\') LIKE \'' . pSQL('%' . $filterDocType . '%') . '\'';
        }
        if ($filterBirthDate !== '') {
            $persistWhere[] = 'COALESCE(v.birth_date, \'' . '\') LIKE \'' . pSQL('%' . $filterBirthDate . '%') . '\'';
        }
        if ($filterVerifiedAtFrom !== '') {
            $persistWhere[] = 'v.verified_at >= \'' . pSQL($filterVerifiedAtFrom . ' 00:00:00') . '\'';
        }
        if ($filterVerifiedAtTo !== '') {
            $persistWhere[] = 'v.verified_at <= \'' . pSQL($filterVerifiedAtTo . ' 23:59:59') . '\'';
        }

        $persistWhereSql = '';
        if (!empty($persistWhere)) {
            $persistWhereSql = ' WHERE ' . implode(' AND ', $persistWhere);
        }

        $persistLimit = (int) Tools::getValue($persistListTable . '_pagination', 20);
        if (!in_array($persistLimit, [20, 50, 100, 300], true)) {
            $persistLimit = 20;
        }
        $persistPage = (int) Tools::getValue('submitFilter' . $persistListTable, 1);
        if ($persistPage < 1) {
            $persistPage = 1;
        }

        $persistTotal = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*)
             FROM `' . _DB_PREFIX_ . self::DB_TABLE . '` v
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = v.id_customer'
            . $persistWhereSql
        );

        $persistOffset = ($persistPage - 1) * $persistLimit;
        if ($persistOffset >= $persistTotal && $persistTotal > 0) {
            $persistPage = (int) ceil($persistTotal / $persistLimit);
            $persistOffset = ($persistPage - 1) * $persistLimit;
        }

        $persistRows = Db::getInstance()->executeS(
            'SELECT
                v.id_customer,
                CONCAT(COALESCE(c.firstname, \'' . '\'), \'' . ' ' . '\', COALESCE(c.lastname, \'' . '\')) AS fullname,
                COALESCE(c.email, \'' . '\') AS email,
                v.doc_type,
                v.birth_date,
                v.verified_at
             FROM `' . _DB_PREFIX_ . self::DB_TABLE . '` v
             LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = v.id_customer'
            . $persistWhereSql . '
             ORDER BY ' . $orderBy . ' ' . $orderWay . '
             LIMIT ' . (int) $persistOffset . ', ' . (int) $persistLimit
        );
        if (!is_array($persistRows)) {
            $persistRows = [];
        }

        // --- Attempt log ---


        $logFieldsList = [
            'id_internautenav_verification_log' => [
                'title' => $this->l('debug_log_col_id'),
                'type' => 'int',
                'align' => 'text-left',
            ],
            'checked_at' => [
                'title' => $this->l('debug_log_col_timestamp'),
                'type' => 'datetime',
                'align' => 'text-left',
            ],
            'customer_reference' => [
                'title' => $this->l('debug_log_col_reference'),
                'type' => 'text',
                'align' => 'text-left',
            ],
            'customer' => [
                'title' => $this->l('debug_log_col_customer'),
                'type' => 'text',
                'align' => 'text-left',
            ],
            'id_cart' => [
                'title' => $this->l('debug_log_col_cart'),
                'type' => 'int',
                'align' => 'text-left',
            ],
            'doc_type' => [
                'title' => $this->l('debug_log_col_doc'),
                'type' => 'text',
                'align' => 'text-left',
            ],
            'result' => [
                'title' => $this->l('debug_log_col_result'),
                'type' => 'int',
                'align' => 'text-left',
            ],
            'result_message' => [
                'title' => $this->l('debug_log_col_message'),
                'type' => 'text',
                'align' => 'text-left',
            ],
        ];

        // Prepare log rows for HelperList display
        $logRowsForDisplay = [];
        foreach ($logRows as $row) {
            $isOk = (int) $row['result'] === 1;
            $customerName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
            $email = $row['email'] ?? '';
            $customerDisplay = $customerName . ($email ? ' <' . $email . '>' : '');
            $resultDisplay = $isOk ? '&#10003; ' . $this->l('debug_log_result_ok') : '&#10007; ' . $this->l('debug_log_result_fail');

            $logRowsForDisplay[] = [
                'id_internautenav_verification_log' => $row['id_internautenav_verification_log'],
                'checked_at' => $row['checked_at'],
                'customer_reference' => $row['customer_reference'],
                'customer' => $customerDisplay,
                'id_cart' => $row['id_cart'] ?? '',
                'doc_type' => $row['doc_type'],
                'result' => $resultDisplay,
                'result_message' => $row['result_message'] ?? '',
            ];
        }

        $logHelper = new HelperList();
        $logHelper->module = $this;
        $logHelper->title = $this->l('debug_log_title');
        $logHelper->identifier = 'id_internautenav_verification_log';
        $logHelper->table = $logListTable;
        $logHelper->token = $token;
        $logHelper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $logHelper->simple_header = false;
        $logHelper->show_toolbar = false;
        $logHelper->listTotal = $logTotal;
        $logHelper->_defaultOrderBy = 'checked_at';
        $logHelper->_defaultOrderWay = 'DESC';
        $logHelper->no_link = true;

        $output .= $logHelper->generateList($logRowsForDisplay, $logFieldsList);
        

        // --- Persistent verifications (PrestaShop-like list with filter + paging) ---
        $persistFieldsList = [
            'id_customer' => [
                'title' => $this->l('debug_persistent_col_customer_id'),
                'type' => 'int',
                'align' => 'text-left',
                'filter_key' => 'v!id_customer',
            ],
            'fullname' => [
                'title' => $this->l('debug_persistent_col_name'),
                'type' => 'text',
                'align' => 'text-left',
            ],
            'email' => [
                'title' => $this->l('debug_persistent_col_email'),
                'type' => 'text',
                'align' => 'text-left',
            ],
            'doc_type' => [
                'title' => $this->l('debug_persistent_col_doc'),
                'type' => 'text',
                'align' => 'text-left',
                'filter_key' => 'v!doc_type',
            ],
            'birth_date' => [
                'title' => $this->l('debug_persistent_col_birth'),
                'type' => 'text',
                'align' => 'text-left',
                'filter_key' => 'v!birth_date',
            ],
            'verified_at' => [
                'title' => $this->l('debug_persistent_col_verified'),
                'type' => 'datetime',
                'align' => 'text-left',
                'filter_key' => 'v!verified_at',
            ],
        ];

        $persistHelper = new HelperList();
        $persistHelper->module = $this;
        $persistHelper->title = $this->l('debug_persistent_title');
        $persistHelper->identifier = 'id_customer';
        $persistHelper->table = $persistListTable;
        $persistHelper->token = $token;
        $persistHelper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $persistHelper->simple_header = false;
        $persistHelper->show_toolbar = false;
        $persistHelper->listTotal = $persistTotal;
        $persistHelper->_defaultOrderBy = 'verified_at';
        $persistHelper->_defaultOrderWay = 'DESC';
        $persistHelper->no_link = true;

        $output .= $persistHelper->generateList($persistRows, $persistFieldsList);

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

        $cleanupAction = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ]);

        // Prepare Cron URLs
        $cronToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_cron');
        $cronUrl = (Tools::usingSecureMode() ? 'https' : 'http') . '://' . Tools::getShopDomain(false, true)
            . __PS_BASE_URI__ . 'modules/' . $this->name . '/cron.php?token=' . $cronToken;
        $bestandskundeUrl = (Tools::usingSecureMode() ? 'https' : 'http') . '://' . Tools::getShopDomain(false, true)
            . __PS_BASE_URI__ . 'modules/' . $this->name . '/cron.php?token=' . $cronToken . '&mode=mark_existing_customers';

        $this->context->smarty->assign([
            'internautenav_cleanup_title' => $this->l('DSGVO Upload-Cleanup'),
            'internautenav_cleanup_retention_days_label' => $this->l('Aufbewahrungsfrist'),
            'internautenav_upload_retention_days' => self::UPLOAD_RETENTION_DAYS,
            'internautenav_days_label' => $this->l('Tage'),
            'internautenav_cleanup_last_run_label' => $this->l('Letzter Cleanup'),
            'internautenav_last_cleanup_display' => $lastCleanupDisplay,
            'internautenav_cleanup_pending_total_label' => $this->l('Ausstehende Uploads (nicht abgeschlossen, gesamt)'),
            'internautenav_pending_count' => $pendingCount,
            'internautenav_cleanup_pending_unassigned_label' => $this->l('Davon ohne Bestellung'),
            'internautenav_pending_unassigned_count' => $pendingUnassignedCount,
            'internautenav_cleanup_expired_label' => $this->l('Abgelaufene Eintraege'),
            'internautenav_expired_count' => $expiredCount,
            'internautenav_cron_url_label' => $this->l('Cron-URL'),
            'internautenav_cron_url' => $cronUrl,
            'internautenav_cron_url_help' => $this->l('Taeglicher Aufruf empfohlen, z.B. via wget oder curl.'),
            'internautenav_existing_customers_url_label' => $this->l('Bestandskunden-URL'),
            'internautenav_existing_customers_url' => $bestandskundeUrl,
            'internautenav_existing_customers_url_help' => $this->l('Markiert alle bestehenden Nicht-Gastkunden mit mindestens einer Bestellung als Bestandskunde.'),
            'internautenav_cleanup_action' => $cleanupAction,
            'internautenav_cleanup_now_button' => $this->l('Cleanup jetzt ausfuehren'),
            'internautenav_cleanup_now_help' => sprintf($this->l('Loescht alle Uploads aelter als %d Tage.'), self::UPLOAD_RETENTION_DAYS),
            'internautenav_cleanup_pending_button' => $this->l('Alle Dateien ohne Altersprüfung löschen'),
            'internautenav_cleanup_pending_help' => $this->l('Loescht alle Uploads die keiner abgeschlossenen Bestellung zugeordnet sind.'),
            'internautenav_cleanup_pending_confirm' => $this->l('Wirklich alle Dateien ohne abgeschlossene Altersprüfung löschen?'),
        ]);

        $output .= $this->display(__FILE__, 'views/templates/hook/backoffice_cleanup.tpl');

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

    private function isStatusDebugEnabled()
    {
        return (int) Configuration::get(self::CONF_STATUS_DEBUG_ENABLED) === 1;
    }

    private function debugOrderStatusDecision(Order $order, array $status, $scope)
    {
        if (!$this->isStatusDebugEnabled()) {
            return;
        }

        $idOrder = (int) $order->id;
        $idCustomer = (int) $order->id_customer;
        $state = (string) ($status['state'] ?? '');
        $type = (string) ($status['type'] ?? '');

        $this->debugLog(sprintf(
            'status[%s] id_order=%d id_customer=%d state=%s type=%s is_verified=%d',
            (string) $scope,
            $idOrder,
            $idCustomer,
            $state,
            $type,
            $idCustomer > 0 && $this->isCustomerVerified($idCustomer) ? 1 : 0
        ));
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
        $idCustomer = (int) $order->id_customer;

        if ($idOrder <= 0) {
            return;
        }

        $this->attachPendingUploadsToOrder($idCart, $idOrder, $idCustomer);
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

        $status = $this->getOrderVerificationStatus($order);
        $this->debugOrderStatusDecision($order, $status, 'bo');

        return $this->renderOrderStatusBadge($status['type'], $status['label'], $status['detail']);
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

        $status = $this->getOrderVerificationStatus($order);
        $this->debugOrderStatusDecision($order, $status, 'pdf');

        // Unknown status should not be shown in PDF.
        if ($status['state'] === 'unknown') {
            return '';
        }

        $idCustomer = (int) $order->id_customer;
        if ($idCustomer > 0 && $this->isCustomerVerified($idCustomer) && $status['state'] !== 'pending') {
            return '';
        }

        $badgeHtml = $this->renderOrderStatusBadge($status['type'], $status['label'], $status['detail']);

        // PDF fonts may not contain glyphs for icon entities; use ASCII-safe labels.
        $badgeHtml = str_replace(
            ['&#10003;', '&amp;#10003;', '&#10007;', '&amp;#10007;', '&#63;', '&amp;#63;', '&#9888;', '&amp;#9888;', '&#128666;', '&amp;#128666;'],
            ['BESTANDEN!', 'BESTANDEN!', 'ABGELEHNT!', 'ABGELEHNT!', 'UNBEKANNT!', 'UNBEKANNT!', 'HINWEIS!', 'HINWEIS!', 'HINWEIS', 'HINWEIS'],
            $badgeHtml
        );

        $this->context->smarty->assign([
            'internautenav_pdf_badge_content' => $badgeHtml,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/order_status_badge_pdf.tpl');
    }

    private function getOrderVerificationStatus(Order $order)
    {
        $idOrder = (int) $order->id;
        $idCustomer = (int) $order->id_customer;
        $idCart = (int) $order->id_cart;

        $carrier = new Carrier((int) $order->id_carrier);
        $carrierRequired = Validate::isLoadedObject($carrier)
            && $this->isCarrierReferenceRequired((int) $carrier->id_reference);

        if (!$carrierRequired) {
            return [
                'state' => 'handover',
                'type' => 'info',
                'label' => '&#128666; ' . htmlspecialchars($this->l('Pruefung bei Uebergabe'), ENT_QUOTES, 'UTF-8'),
                'detail' => htmlspecialchars($this->l('Diese Versandart erfordert keine Online-Altersprüfung.'), ENT_QUOTES, 'UTF-8'),
            ];
        }

        if ($this->ensureUploadTable()) {
            $pendingDocs = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
                 WHERE id_order = ' . $idOrder
            );
            if ($pendingDocs > 0) {
                return [
                    'state' => 'pending',
                    'type' => 'warning',
                    'label' => '&#9888; ' . htmlspecialchars($this->l('Pruefung manuell erledigen'), ENT_QUOTES, 'UTF-8'),
                    'detail' => htmlspecialchars(
                        sprintf($this->l('%d Dokument(e) hochgeladen, noch nicht geprüft.'), $pendingDocs),
                        ENT_QUOTES,
                        'UTF-8'
                    ),
                ];
            }
        }

        if ($this->ensureVerificationLogTable()) {
            $logCondition = $idCustomer > 0
                ? 'id_customer = ' . $idCustomer
                : ($idCart > 0 ? 'id_cart = ' . $idCart : null);
            $logRow = $logCondition ? Db::getInstance()->getRow(
                'SELECT `result`, `result_message`, `doc_type`, `checked_at`
                 FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '`
                 WHERE ' . $logCondition . '
                 ORDER BY checked_at DESC'
            ) : null;

            if (is_array($logRow) && isset($logRow['result'])) {
                $isManual = (strpos((string) ($logRow['result_message'] ?? ''), 'Manuelle Prüfung') !== false);
                if ((int) $logRow['result'] === 1) {
                    return [
                        'state' => 'success',
                        'type' => 'success',
                        'label' => $isManual
                            ? '&#10003; ' . htmlspecialchars($this->l('Pruefung manuell bestanden'), ENT_QUOTES, 'UTF-8')
                            : '&#10003; ' . htmlspecialchars($this->l('Pruefung automatisch bestanden'), ENT_QUOTES, 'UTF-8'),
                        'detail' => htmlspecialchars(
                            ($isManual ? 'Manuell' : ucfirst((string) ($logRow['doc_type'] ?? ''))) . ' – ' . (string) ($logRow['checked_at'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ),
                    ];
                }

                return [
                    'state' => 'rejected',
                    'type' => 'danger',
                    'label' => '&#10007; ' . htmlspecialchars($this->l('Pruefung abgelehnt'), ENT_QUOTES, 'UTF-8'),
                    'detail' => htmlspecialchars((string) ($logRow['result_message'] ?? ''), ENT_QUOTES, 'UTF-8'),
                ];
            }
        }

        // Fallback for customers marked as verified without log entries (e.g. Bestandskunde).
        if ($idCustomer > 0) {
            $persistent = Db::getInstance()->getRow(
                'SELECT `doc_type`, `verified_at`
                 FROM `' . _DB_PREFIX_ . self::DB_TABLE . '`
                 WHERE `id_customer` = ' . $idCustomer . '
                   AND `is_verified` = 1'
            );

            if (is_array($persistent) && !empty($persistent)) {
                $docType = trim((string) ($persistent['doc_type'] ?? ''));
                if ($docType === '') {
                    $docType = 'Bestandskunde';
                }

                $detail = $docType;
                $verifiedAt = (string) ($persistent['verified_at'] ?? '');
                if ($verifiedAt !== '') {
                    $detail .= ' – ' . $verifiedAt;
                }

                return [
                    'state' => 'success',
                    'type' => 'success',
                    'label' => '&#10003; ' . htmlspecialchars($this->l('Pruefung automatisch bestanden'), ENT_QUOTES, 'UTF-8'),
                    'detail' => htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'),
                ];
            }
        }

        return [
            'state' => 'unknown',
            'type' => 'default',
            'label' => '&#63; ' . htmlspecialchars($this->l('Keine Pruefung vorhanden'), ENT_QUOTES, 'UTF-8'),
            'detail' => htmlspecialchars($this->l('Für diese Bestellung liegt kein Verifikationseintrag vor.'), ENT_QUOTES, 'UTF-8'),
        ];
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

        $this->context->smarty->assign([
            'internautenav_account_url' => $url,
            'internautenav_account_title' => $this->l('Alterspruefungsprotokoll'),
            'internautenav_account_label' => $this->l('Alterspruefungsprotokoll'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/customer_account.tpl');
    }

    public function hookDisplayAdminCustomers($params)
    {
        $idCustomer = isset($params['id_customer']) ? (int) $params['id_customer'] : 0;
        if ($idCustomer <= 0) {
            return '';
        }

        $rows = $this->getCustomerVerificationProtocol($idCustomer, 200);
        $statusBadge = $this->renderCustomerVerificationStatusBadge($idCustomer, $rows);

        $protocolRows = [];
        foreach ($rows as $row) {
            $message = (string) ($row['result_message'] ?? '');
            $docType = (string) ($row['doc_type'] ?? '');
            if (strpos($message, 'Manuelle Prüfung') !== false) {
                $docType = 'Manuell';
            } elseif ($docType !== '') {
                $docType = ucfirst($docType);
            }

            $protocolRows[] = [
                'checked_at' => (string) ($row['checked_at'] ?? ''),
                'id_cart' => (string) ($row['id_cart'] ?? ''),
                'doc_type' => $docType,
                'is_ok' => (int) ($row['result'] ?? 0) === 1,
                'message' => $message,
            ];
        }

        $this->context->smarty->assign([
            'internautenav_status_badge_html' => $statusBadge,
            'internautenav_badge_id' => 'internautenav-customer-status-badge',
            'internautenav_protocol_card_id' => 'internautenav-protocol-card',
            'internautenav_protocol_title' => $this->l('Alterspruefungsprotokoll', 'protocol'),
            'internautenav_protocol_empty' => $this->l('Keine Eintraege.'),
            'internautenav_protocol_col_checked_at' => $this->l('Zeitpunkt'),
            'internautenav_protocol_col_id_cart' => $this->l('id_cart'),
            'internautenav_protocol_col_doc_type' => $this->l('Dokumenttyp'),
            'internautenav_protocol_col_result' => $this->l('Ergebnis'),
            'internautenav_protocol_col_message' => $this->l('Meldung'),
            'internautenav_protocol_ok' => $this->l('OK'),
            'internautenav_protocol_fail' => $this->l('Fehler'),
            'internautenav_protocol_rows' => $protocolRows,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_customers_protocol.tpl');
    }

    private function renderOrderVerificationLogPanel($idOrder, Order $order)
    {
        if ($idOrder <= 0 || !$this->ensureVerificationLogTable()) {
            return '';
        }

        $idCustomerOrder = (int) $order->id_customer;
        $idCart = (int) $order->id_cart;
        $logCondition = $idCustomerOrder > 0
            ? 'id_customer = ' . $idCustomerOrder
            : ($idCart > 0 ? 'id_cart = ' . $idCart : null);

        if ($logCondition === null) {
            return '';
        }

        $logRows = Db::getInstance()->executeS(
            'SELECT `result`, `result_message`, `doc_type`, `checked_at`
             FROM `' . _DB_PREFIX_ . self::DB_LOG_TABLE . '`
             WHERE ' . $logCondition . '
             ORDER BY checked_at DESC'
        );
        $protocolRows = [];
        if (is_array($logRows)) {
            foreach ($logRows as $row) {
                $docType = (string) ($row['doc_type'] ?? '');
                $message = (string) ($row['result_message'] ?? '');

                if (strpos($message, 'Manuelle Prüfung') !== false) {
                    $docType = 'Manuell';
                } elseif ($docType !== '') {
                    $docType = ucfirst($docType);
                }

                $protocolRows[] = [
                    'checked_at' => (string) ($row['checked_at'] ?? ''),
                    'doc_type' => $docType,
                    'is_ok' => (int) ($row['result'] ?? 0) === 1,
                    'message' => $message,
                ];
            }
        }

        $this->context->smarty->assign([
            'internautenav_order_protocol_title' => $this->l('Altersprüfung – Protokoll'),
            'internautenav_order_protocol_empty' => $this->l('Keine Eintraege.'),
            'internautenav_order_protocol_col_checked_at' => $this->l('Zeitpunkt'),
            'internautenav_order_protocol_col_doc_type' => $this->l('Dokumenttyp'),
            'internautenav_order_protocol_col_result' => $this->l('Ergebnis'),
            'internautenav_order_protocol_col_message' => $this->l('Meldung'),
            'internautenav_order_protocol_ok' => $this->l('OK'),
            'internautenav_order_protocol_fail' => $this->l('Fehler'),
            'internautenav_order_protocol_rows' => $protocolRows,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/order_verification_log_panel.tpl');
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

        if (is_array($rows) && !empty($rows)) {
            return $rows;
        }

        // Backward-compatible fallback: customers marked as verified without log rows
        // (e.g. via mark_existing_customers) should still show protocol and green badge.
        $persistent = Db::getInstance()->getRow(
            'SELECT `doc_type`, `verified_at`
             FROM `' . _DB_PREFIX_ . self::DB_TABLE . '`
             WHERE `id_customer` = ' . $idCustomer . '
                             AND `is_verified` = 1'
        );

        if (!is_array($persistent) || empty($persistent)) {
            return [];
        }

        $docType = trim((string) ($persistent['doc_type'] ?? ''));
        if ($docType === '') {
            $docType = 'Bestandskunde';
        }

        return [[
            'id_cart' => null,
            'doc_type' => $docType,
            'result' => 1,
            'result_message' => $this->l('Als Bestandskunde verifiziert (ohne Einzelpruefungs-Log).'),
            'checked_at' => (string) ($persistent['verified_at'] ?? ''),
        ]];
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

        $this->context->smarty->assign([
            'internautenav_badge_color' => $color,
            'internautenav_badge_bg' => $bg,
            'internautenav_badge_border' => $border,
            'internautenav_badge_label' => $label,
            'internautenav_badge_detail' => $detail,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/order_status_badge.tpl');
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

        $documentRows = [];
        foreach ($rows as $row) {
            $docId = (int) $row['id_internautenav_uploaded_document'];
            $dlToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_dl' . $docId . $idOrder);
            $downloadUrl = __PS_BASE_URI__ . 'modules/' . $this->name . '/ajax.php?action=download_document'
                . '&document_id=' . $docId
                . '&id_order=' . (int) $idOrder
                . '&token=' . rawurlencode($dlToken);

            $documentRows[] = [
                'id' => $docId,
                'original_name' => (string) ($row['original_name'] ?? ''),
                'doc_type' => (string) ($row['doc_type'] ?? ''),
                'mime_type' => (string) ($row['mime_type'] ?? ''),
                'file_size' => $this->formatFileSize((int) ($row['file_size'] ?? 0)),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'preview_url' => $downloadUrl,
            ];
        }

        $adminToken = hash('sha256', _COOKIE_KEY_ . 'internautenav_admin_action' . $idOrder);
        $ajaxUrl = __PS_BASE_URI__ . 'modules/' . $this->name . '/ajax.php';
        $loadAdminScript = false;
        if (!defined('INTERNAUTENAV_ADMIN_JS_LOADED')) {
            define('INTERNAUTENAV_ADMIN_JS_LOADED', true);
            $loadAdminScript = true;
        }
        $loadAdminCss = false;
        if (!defined('INTERNAUTENAV_ADMIN_CSS_LOADED')) {
            define('INTERNAUTENAV_ADMIN_CSS_LOADED', true);
            $loadAdminCss = true;
        }

        $this->context->smarty->assign([
            'internautenav_order_protocol_panel_html' => $protocolPanel,
            'internautenav_uploaded_documents_title' => $this->l('Altersprüfung – Hochgeladene Dokumente'),
            'internautenav_uploaded_documents_empty' => $this->l('Zu dieser Bestellung liegen keine hochgeladenen Dokumente vor.'),
            'internautenav_uploaded_documents_col_id' => $this->l('ID'),
            'internautenav_uploaded_documents_col_name' => $this->l('Originaldatei'),
            'internautenav_uploaded_documents_col_type' => $this->l('Typ'),
            'internautenav_uploaded_documents_col_mime' => $this->l('MIME'),
            'internautenav_uploaded_documents_col_size' => $this->l('Grösse'),
            'internautenav_uploaded_documents_col_created_at' => $this->l('Hochgeladen am'),
            'internautenav_uploaded_documents_col_action' => $this->l('Aktion'),
            'internautenav_uploaded_documents_preview' => $this->l('Ansehen'),
            'internautenav_uploaded_documents_rows' => $documentRows,
            'internautenav_order_id' => (int) $idOrder,
            'internautenav_admin_order_token' => $adminToken,
            'internautenav_admin_order_ajax_url' => $ajaxUrl,
            'internautenav_admin_order_modal_title' => $this->l('Dokumentvorschau'),
            'internautenav_admin_order_modal_close' => $this->l('Schliessen'),
            'internautenav_admin_order_modal_approve' => $this->l('Pruefung bestanden'),
            'internautenav_admin_order_modal_reject' => $this->l('Pruefung abgelehnt'),
            'internautenav_admin_order_modal_hint' => $this->l('Loescht alle Dokumente DSGVO-konform sofort.'),
            'internautenav_admin_order_load_script' => $loadAdminScript,
            'internautenav_admin_order_load_style' => $loadAdminCss,
            'internautenav_admin_order_css_url' => __PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin_order_main_bottom.css',
            'internautenav_admin_order_js_url' => __PS_BASE_URI__ . 'modules/' . $this->name . '/views/js/admin_order_main_bottom.js',
            'internautenav_admin_order_msg_confirm_approve' => $this->l('Prüfung als bestanden markieren und alle Dokumente DSGVO-konform löschen?'),
            'internautenav_admin_order_msg_confirm_reject' => $this->l('Prüfung als abgelehnt markieren und alle Dokumente DSGVO-konform löschen?'),
            'internautenav_admin_order_msg_ok_approve' => $this->l('Prüfung bestanden gespeichert. Dokumente gelöscht.'),
            'internautenav_admin_order_msg_ok_reject' => $this->l('Prüfung abgelehnt gespeichert. Dokumente gelöscht.'),
            'internautenav_admin_order_msg_error_prefix' => $this->l('Fehler:'),
            'internautenav_admin_order_msg_error_unknown' => $this->l('Unbekannter Fehler'),
            'internautenav_admin_order_msg_error_connection' => $this->l('Verbindungsfehler beim Speichern der Entscheidung.'),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_order_main_bottom.tpl');
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
        $idCustomer = $this->resolveCheckoutCustomerId();
        if ($idCustomer > 0) {
            return $this->isCustomerVerified($idCustomer);
        }

        $carrier = $this->getCurrentCheckoutCarrier();
        if (!$carrier) {
            // No carrier detected yet (e.g. early in checkout) — do not clear session.
            return false;
        }

        return $this->isCheckoutVerifiedForCarrier($carrier['reference']);
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

    private function isCheckoutVerifiedForCarrier($carrierReference)
    {
        $state = $this->getCheckoutVerificationState();
        if (empty($state)) {
            return false;
        }

        $isValid = !empty($state['verified'])
            && (int) ($state['carrier_reference'] ?? 0) === (int) $carrierReference;

        if (!$isValid) {
            $this->clearCheckoutVerificationState();
            return false;
        }

        return true;
    }

    private function installDatabase()
    {
        return InternautenavSql::installSchema(
            _DB_PREFIX_,
            _MYSQL_ENGINE_,
            self::DB_TABLE,
            self::DB_LOG_TABLE,
            self::DB_UPLOAD_TABLE
        );
    }

    private function uninstallDatabase()
    {
        return InternautenavSql::uninstallSchema(
            _DB_PREFIX_,
            self::DB_TABLE,
            self::DB_LOG_TABLE,
            self::DB_UPLOAD_TABLE
        );
    }

    private function ensureVerificationLogTable()
    {
        return InternautenavSql::tableExists(_DB_PREFIX_ . self::DB_LOG_TABLE);
    }

    private function ensureUploadTable()
    {
        return InternautenavSql::tableExists(_DB_PREFIX_ . self::DB_UPLOAD_TABLE);
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
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        if (!in_array($extension, $allowedExtensions, true)) {
            return [
                'valid' => false,
                'message' => 'Bitte laden Sie eine Datei im Format JPG, JPEG oder PNG hoch. (Erkannte Endung: ' . htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') . ')',
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

        $allowedMimes = ['image/jpeg', 'image/png'];
        if ($mimeType !== '' && !in_array($mimeType, $allowedMimes, true)) {
            return [
                'valid' => false,
                'message' => 'Ungueltige Datei. Erkannter Typ: ' . $mimeType . '. Erlaubt: JPG, PNG.',
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

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo) || !isset($imageInfo['mime'])) {
            return [
                'valid' => false,
                'message' => 'Die hochgeladene Datei ist kein gueltiges Bild.',
            ];
        }

        $detectedImageMime = (string) $imageInfo['mime'];
        $outputExtension = 'jpg';
        $outputMime = 'image/jpeg';
        if ($detectedImageMime === 'image/png') {
            $outputExtension = 'png';
            $outputMime = 'image/png';
        } elseif ($detectedImageMime !== 'image/jpeg') {
            return [
                'valid' => false,
                'message' => 'Nur JPEG und PNG sind erlaubt.',
            ];
        }

        if (!function_exists('imagecreatefromstring')) {
            return [
                'valid' => false,
                'message' => 'Server-Konfiguration unvollstaendig: GD imagecreatefromstring fehlt.',
            ];
        }

        $safeFileName = $generated . '.' . $outputExtension;
        $targetPath = $pendingDir . '/' . $safeFileName;

        // Re-encode the image to strip potentially dangerous payloads and metadata.
        $rawData = @file_get_contents($tmpName);
        if ($rawData === false) {
            return [
                'valid' => false,
                'message' => 'Die Datei konnte nicht gelesen werden.',
            ];
        }

        $imageResource = @imagecreatefromstring($rawData);
        if ($imageResource === false) {
            return [
                'valid' => false,
                'message' => 'Das Bild konnte nicht verarbeitet werden.',
            ];
        }

        $writeOk = false;
        if ($outputMime === 'image/png' && function_exists('imagepng')) {
            $writeOk = @imagepng($imageResource, $targetPath, 6);
        } elseif ($outputMime === 'image/jpeg' && function_exists('imagejpeg')) {
            $writeOk = @imagejpeg($imageResource, $targetPath, 90);
        }
        imagedestroy($imageResource);

        if (!$writeOk) {
            return [
                'valid' => false,
                'message' => 'Die Datei konnte nicht sicher gespeichert werden.',
            ];
        }

        $storedFileSize = (int) @filesize($targetPath);
        if ($storedFileSize <= 0) {
            @unlink($targetPath);
            return [
                'valid' => false,
                'message' => 'Die gespeicherte Datei ist ungueltig.',
            ];
        }

        $idCart = Validate::isLoadedObject($this->context->cart) ? (int) $this->context->cart->id : 0;
        $idCustomer = $this->resolveCheckoutCustomerId();
        if ($idCustomer <= 0 && Validate::isLoadedObject($this->context->customer)) {
            $idCustomer = (int) $this->context->customer->id;
        }
        $insertOk = Db::getInstance()->insert(self::DB_UPLOAD_TABLE, [
            'id_cart' => $idCart > 0 ? $idCart : null,
            'id_order' => null,
            'id_customer' => $idCustomer > 0 ? $idCustomer : null,
            'doc_type' => pSQL((string) $docType),
            'file_name' => pSQL($safeFileName),
            'original_name' => pSQL($originalName),
            'mime_type' => pSQL($outputMime),
            'file_size' => $storedFileSize,
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

    private function attachPendingUploadsToOrder($idCart, $idOrder, $idCustomer = 0)
    {
        $idCart = (int) $idCart;
        $idOrder = (int) $idOrder;
        $idCustomer = (int) $idCustomer;
        if ($idOrder <= 0) {
            return;
        }

        // Primary: match by id_customer (survives cart change after CC failure).
        // Fallback: match by id_cart for guest sessions without a customer id.
        if ($idCustomer > 0) {
            $where = 'id_customer = ' . $idCustomer . ' AND (id_order IS NULL OR id_order = 0)';
        } elseif ($idCart > 0) {
            $where = 'id_cart = ' . $idCart . ' AND (id_order IS NULL OR id_order = 0)';
        } else {
            return;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_internautenav_uploaded_document`, `file_name`
             FROM `' . _DB_PREFIX_ . self::DB_UPLOAD_TABLE . '`
             WHERE ' . $where
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
            $where
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
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
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


        // Fallback for customers that were marked as verified without a log entry
        // (e.g. mark_existing_customers / Bestandskunde).
        if ($idCustomerOrder > 0) {
            $persistentVerification = Db::getInstance()->getRow(
                'SELECT `doc_type`, `verified_at`
                 FROM `' . _DB_PREFIX_ . self::DB_TABLE . '`
                 WHERE `id_customer` = ' . (int) $idCustomerOrder . '
                   AND `is_verified` = 1'
            );

            if (is_array($persistentVerification) && !empty($persistentVerification)) {
                $docType = trim((string) ($persistentVerification['doc_type'] ?? ''));
                if ($docType === '') {
                    $docType = 'Bestandskunde';
                }

                $detail = $docType;
                $verifiedAt = (string) ($persistentVerification['verified_at'] ?? '');
                if ($verifiedAt !== '') {
                    $detail .= ' - ' . $verifiedAt;
                }

                return $this->renderOrderStatusBadge(
                    'success',
                    '&#10003; ' . htmlspecialchars($this->l('Pruefung automatisch bestanden'), ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($detail, ENT_QUOTES, 'UTF-8')
                );
            }
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

    public function markExistingCustomersAsVerified()
    {
        if (!$this->ensureVerificationLogTable()) {
            return [
                'success' => false,
                'message' => 'Verifikations-Log-Tabelle konnte nicht erstellt werden.',
                'created' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT c.id_customer, c.firstname, c.lastname
             FROM `' . _DB_PREFIX_ . 'customer` c
             INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_customer = c.id_customer
             LEFT JOIN `' . _DB_PREFIX_ . self::DB_TABLE . '` v ON v.id_customer = c.id_customer
             WHERE c.id_customer > 0
               AND c.is_guest = 0
               AND (c.deleted = 0 OR c.deleted IS NULL)
               AND v.id_customer IS NULL'
        );

        if (!is_array($rows)) {
            return [
                'success' => false,
                'message' => 'Kunden konnten nicht geladen werden.',
                'created' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $created = 0;
        $failed = 0;
        foreach ($rows as $row) {
            $idCustomer = (int) ($row['id_customer'] ?? 0);
            if ($idCustomer <= 0) {
                continue;
            }

            $ok = $this->setCustomerVerified($idCustomer, [
                'doc_type' => 'Bestandskunde',
                'birth_date' => null,
                'firstname' => (string) ($row['firstname'] ?? ''),
                'lastname' => (string) ($row['lastname'] ?? ''),
            ]);

            if ($ok) {
                $created++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'message' => 'Bestandskunden-Markierung abgeschlossen.',
            'created' => $created,
            'skipped' => 0,
            'failed' => $failed,
        ];
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
