<?php

class InternautenavProtocolModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $auth = true;
    public $guestAllowed = false;

    public function initContent()
    {
        parent::initContent();

        if (!Validate::isLoadedObject($this->context->customer) || !$this->context->customer->isLogged()) {
            Tools::redirect($this->context->link->getPageLink('authentication', true));
        }

        $title = $this->module->l('Alterspruefungsprotokoll');
        $this->context->smarty->assign('page', array_merge(
            $this->context->smarty->getTemplateVars('page') ?: [],
            ['page_title' => $title]
        ));

        $idCustomer = (int) $this->context->customer->id;
        $rows = $this->module->getCustomerVerificationProtocol($idCustomer, 100);

        $protocolRows = [];
        foreach ($rows as $row) {
            $isOk = (int) ($row['result'] ?? 0) === 1;
            $message = trim((string) ($row['result_message'] ?? ''));
            $docType = trim((string) ($row['doc_type'] ?? ''));

            $protocolRows[] = [
                'checked_at' => (string) ($row['checked_at'] ?? ''),
                'cart_id' => (int) ($row['id_cart'] ?? 0),
                'doc_type' => $docType !== '' ? ucfirst($docType) : '-',
                'result_class' => $isOk ? 'text-success' : 'text-danger',
                'result_label' => $isOk ? $this->module->l('Pruefung bestanden') : $this->module->l('Pruefung abgelehnt'),
                'message' => $message,
            ];
        }

        $this->context->smarty->assign([
            'internautenav_protocol_title' => $this->module->l('Alterspruefungsprotokoll'),
            'internautenav_protocol_intro' => $this->module->l('Hier sehen Sie die Alterspruefungen, die fuer Ihr Kundenkonto gespeichert wurden.'),
            'internautenav_protocol_empty' => $this->module->l('Keine Eintraege.'),
            'internautenav_protocol_cart' => $this->module->l('Warenkorb'),
            'internautenav_protocol_timestamp' => $this->module->l('Zeitpunkt'),
            'internautenav_protocol_doc' => $this->module->l('Dokumenttyp'),
            'internautenav_protocol_result' => $this->module->l('Ergebnis'),
            'internautenav_protocol_message' => $this->module->l('Meldung'),
            'internautenav_protocol_rows' => $protocolRows,
        ]);

        $this->setTemplate('module:internautenav/views/templates/front/protocol.tpl');
    }
}