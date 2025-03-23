<?php
/**
* 2007-2025 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once dirname(__FILE__).'/sdk/ProntoPagoHelper.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

class Vc_prontopaga extends PaymentModule
{
    protected $config_form = false;
    
    public function __construct()
    {
        $this->name = 'vc_prontopaga';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'ProntoPaga';
        $this->developer = 'Victor Castro';
        $this->github = 'https://github.com/victorcastro';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ProntoPaga');
        $this->description = $this->l('Pay with prontoPaga and get better commissions.');

        $this->limited_countries = ['PE'];
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false) {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        $defaultConfig = [
            'VC_PRONTOPAGA_LIVE_MODE' => false,
            'VC_PRONTOPAGA_ACCOUNT_TOKEN' => '',
            'VC_PRONTOPAGA_ACCOUNT_KEY' => '',
            'VC_PRONTOPAGA_SUPPORTED_CURRENCIES' => ''
        ];
    
        foreach ($defaultConfig as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        
        if (!file_exists(dirname(__FILE__) . '/sql/install.php')) {
            return false;
        }
        require_once dirname(__FILE__) . '/sql/install.php';

        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayAdminOrderTabOrder') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('displayPaymentReturn');
    }

    public function uninstall()
    {
        Configuration::deleteByName('VC_PRONTOPAGA_LIVE_MODE');
        Configuration::deleteByName('VC_PRONTOPAGA_ACCOUNT_TOKEN');
        Configuration::deleteByName('VC_PRONTOPAGA_ACCOUNT_KEY');
        Configuration::deleteByName('VC_PRONTOPAGA_SUPPORTED_CURRENCIES');

        if (file_exists(dirname(__FILE__) . '/sql/uninstall.php')) {
            require_once dirname(__FILE__) . '/sql/uninstall.php';
        }
    
        return parent::uninstall();
    }

    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitVc_prontopagaModule')) == true) {
            $this->postProcess();
        }
        
        if (Tools::isSubmit('syncPaymentMethods')) {
            if ($this->syncPaymentMethods()) {
                $this->context->controller->confirmations[] = $this->l('Payment methods successfully synchronized.');
            } else {
                $this->context->controller->errors[] = $this->l('Failed to synchronize payment methods.');
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        $output .= $this->renderForm();
        $output .= $this->renderMethodsList();
        return $output;
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitVc_prontopagaModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }
    
    public function renderMethodsList()
    {
        $methods = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'vc_prontopaga_methods');
    
        if (!$methods || !is_array($methods) || empty($methods)) {
            return '<div class="alert alert-warning">' . $this->l('No payment methods found in the database. please syncronize') . '</div>';
        }
    
        $fields_list = [
            'id' => [
                'title' => $this->l('ID'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'active' => [
                'title' => $this->l('Active'),
                'active' => 'toggle_active',
                'align' => 'center',
                'type' => 'bool',
                'orderby' => true,
            ],
            'name' => [
                'orderby' => true,
                'title' => $this->l('Name'),
            ],
            'logo' => [
                'title' => $this->l('Logo'),
                'callback' => 'renderLogoColumn',
                'callback_object' => $this,
                'orderby' => false,
                'search' => false,
            ],
            'currency' => [
                'orderby' => true,
                'title' => $this->l('Currency'),
            ],
        ];
    
        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->identifier = 'id';
        $helper->actions = [];
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->title = $this->l('Available ProntoPaga Methods');
        $helper->table = 'vc_prontopaga_methods';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
    
        return $helper->generateList($methods, $fields_list);
    }

    public function renderLogoColumn($logoUrl, $row)
    {
        return '<img src="'.Tools::safeOutput($logoUrl).'" alt="Logo" style="height:50px;" />';
    }

    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Mode'),
                        'name' => 'VC_PRONTOPAGA_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Upon activation, all orders will be sent to production'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Production')
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Sandbox')
                            ]
                        ],
                    ],
                    [
                        'col' => 5,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-shield"></i>',
                        'suffix' => '<a href="#" class="toggle-secret" data-target="VC_PRONTOPAGA_ACCOUNT_TOKEN" title="'.$this->l('Show / Hide').'"><i class="icon icon-eye"></i></a>',
                        'name' => 'VC_PRONTOPAGA_ACCOUNT_TOKEN',
                        'required'=>true,
                        'label' => $this->l('Authentication Token'),
                    ],
                    [
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-key"></i>',
                        'suffix' => '<a href="#" class="toggle-secret" data-target="VC_PRONTOPAGA_ACCOUNT_KEY" title="'.$this->l('Show / Hide').'"><i class="icon icon-eye"></i></a>',
                        'name' => 'VC_PRONTOPAGA_ACCOUNT_KEY',
                        'required'=>true,
                        'label' => $this->l('Secret Key'),
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Supported Currencies'),
                        'desc' => $this->l('Select the currencies supported by ProntoPago.'),
                        'name' => 'VC_PRONTOPAGA_SUPPORTED_CURRENCIES',
                        'required'=>true,
                        'values' => [
                            'query' => $this->getCurrencyOptions(),
                            'id' => 'id_option',
                            'name' => 'name'
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save settings'),
                ],
                'buttons' => [
                    [
                        'title' => $this->l('Sync Payment Methods'),
                        'icon' => 'process-icon-refresh',
                        'class' => 'btn btn-warning pull-right',
                        'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&syncPaymentMethods=1&token=' . Tools::getAdminTokenLite('AdminModules'),
                    ],
                ]
            ],
        ];
    }

    protected function getConfigFormValues()
    {
        $values = [
            'VC_PRONTOPAGA_LIVE_MODE'     => (bool)Tools::getValue('VC_PRONTOPAGA_LIVE_MODE', Configuration::get('VC_PRONTOPAGA_LIVE_MODE')),
            'VC_PRONTOPAGA_ACCOUNT_TOKEN'  => Tools::getValue('VC_PRONTOPAGA_ACCOUNT_TOKEN', Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN')),
            'VC_PRONTOPAGA_ACCOUNT_KEY'    => Tools::getValue('VC_PRONTOPAGA_ACCOUNT_KEY', Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY')),
        ];
    
        $selectedCurrencies = Configuration::get('VC_PRONTOPAGA_SUPPORTED_CURRENCIES');
        $selectedArray = !empty($selectedCurrencies) ? explode(',', $selectedCurrencies) : [];
    
        foreach ($this->getCurrencyOptions() as $currency) {
            $fieldName = 'VC_PRONTOPAGA_SUPPORTED_CURRENCIES_' . $currency['id_option'];
            $values[$fieldName] = in_array($currency['id_option'], $selectedArray);
        }
    
        return $values;
    }
    
    public function getCurrencyOptions()
    {
        $currencies = Currency::getCurrencies();
        $options = [];
        foreach ($currencies as $currency) {
            $options[] = [
                'id_option' => (int)$currency['id_currency'],
                'name'      => $currency['name'] . ' (' . $currency['iso_code'] . ')',
                'val'       => (int)$currency['id_currency'],
            ];
        }
        return $options;
    }

    protected function postProcess()
    {
        foreach (['VC_PRONTOPAGA_LIVE_MODE', 'VC_PRONTOPAGA_ACCOUNT_TOKEN', 'VC_PRONTOPAGA_ACCOUNT_KEY'] as $key) {
            $value = Tools::getValue($key, Configuration::get($key));
            if ($value !== '' && $value !== null) {
                Configuration::updateValue($key, $value);
            }
        }
    
        $selectedCurrencies = [];
        foreach ($this->getCurrencyOptions() as $option) {
            $fieldName = 'VC_PRONTOPAGA_SUPPORTED_CURRENCIES_' . $option['id_option'];
            if (Tools::getValue($fieldName)) {
                $selectedCurrencies[] = $option['id_option'];
            }
        }
        Configuration::updateValue('VC_PRONTOPAGA_SUPPORTED_CURRENCIES', implode(',', $selectedCurrencies));
        
        $this->context->controller->confirmations[] = $this->l('Configuración actualizada.');
    }
    
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    public function hookDisplayHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        
        $cart = $params['cart'];
        $liveMode  = (bool) Configuration::get('VC_PRONTOPAGA_LIVE_MODE');
        $token     = Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN');
        $secretKey = Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY');
    
        $helper = new \ProntoPago\ProntoPagoHelper($liveMode, $token, $secretKey);
    
        $methods = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'vc_prontopaga_methods 
             WHERE active = 1 AND currency = "' . pSQL($this->context->currency->iso_code) . '"'
        );
    
        $enrichedMethods = [];
        foreach ($methods as $method) {
            $url = $helper->createNewPayment($cart, $method['method']);
            if ($url) {
                $method['paymentUrl'] = $url;
                $enrichedMethods[] = $method;
            }
        }
    
        $this->context->smarty->assign([
            'payment_methods' => $enrichedMethods,
        ]);
        
        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l('Pay with ProntoPaga'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
            ->setAdditionalInformation(
                $this->fetch('module:' . $this->name . '/views/templates/front/payment_infos.tpl')
            );
        return [
            $option
        ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function hookDisplayAdminOrderTabOrder()
    {
        /* Place your code here. */
    }

    public function hookDisplayOrderConfirmation()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentReturn($params)
    {
        $totalToPaid = $params['order']->getTotalPaid();
        $currency = new Currency($params['order']->id_currency);
        
        $this->smarty->assign([
            'shop_name' => $this->context->shop->name,
            'status' => 'ok',
            'reference' => $params['order']->reference,
            'total' => $this->context->getCurrentLocale()->formatPrice($totalToPaid, $currency->iso_code),
            'contact_url' => $this->context->link->getPageLink('contact', true),
        ]);
        
        return $this->display(__FILE__, 'confirmation.tpl');
    }
    
    /**
     * Generate signature using ProntoPagaHelper.
     *
     * @param array $data
     *
     * @return string
     */
    public function generateSignature(array $data, $concatString = '')
    {
        $secretKey = Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY');
        return ProntoPagoHelper::generateSignature($data, $secretKey, $concatString);
    }
    
    public function callPaymentMethods()
    {
        $liveMode  = (bool) Configuration::get('VC_PRONTOPAGA_LIVE_MODE');
        $token     = Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN');
        $secretKey = Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY');
    
        $helper = new \ProntoPago\ProntoPagoHelper($liveMode, $token, $secretKey);
    
        $methods = $helper->getPaymentMethods();
    
        if ($methods === false) {
            return false;
        }
    
        return $methods;
    }
    
    public function syncPaymentMethods()
    {
        $liveMode = (bool) Configuration::get('VC_PRONTOPAGA_LIVE_MODE');
        $token = Configuration::get('VC_PRONTOPAGA_ACCOUNT_TOKEN');
        $secretKey = Configuration::get('VC_PRONTOPAGA_ACCOUNT_KEY');
    
        $helper = new \ProntoPago\ProntoPagoHelper($liveMode, $token, $secretKey);
        return $helper->syncPaymentMethodsToDb();
    }
}
