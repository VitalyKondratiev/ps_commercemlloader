<?php

class Ps_CommerceMLLoaderExchangeModuleFrontController extends ModuleFrontController
{
    private $type;
    private $mode;
    private $response;

    public function initContent()
    {
        parent::initContent();
        $this->context->smarty->assign(array(
            'response' => $this->response,
        ));
        $this->setTemplate('module:ps_commercemlloader/views/templates/front/exchange.tpl');
    }

    public function postProcess()
    {
        $this->type = isset($_GET['type']) ? $_GET['type'] : null;
        $this->mode = isset($_GET['mode']) ? $_GET['mode'] : null;
        $this->response = $this->module->exchange($this->type, $this->mode);
    }
}