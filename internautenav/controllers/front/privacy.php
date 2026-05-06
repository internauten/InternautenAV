<?php

class InternautenavPrivacyModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $this->context->smarty->assign([
            'internautenav_privacy_title' => $this->module->l('Datenschutzerklaerung (Beispiel)', 'privacy'),
            'internautenav_privacy_updated_at' => date('d.m.Y'),
        ]);

        $this->setTemplate('module:internautenav/views/templates/front/privacy.tpl');
    }
}
