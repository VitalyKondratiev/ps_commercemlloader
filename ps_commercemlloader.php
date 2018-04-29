<?php

if (!defined('_PS_VERSION_'))
{
    exit;
}

class Ps_CommerceMLLoader extends Module
{
    private static $session_name = "CommerceMLLoader";

    public function __construct()
    {
        $this->name = 'ps_commercemlloader';
        $this->tab = 'others';
        $this->version = '0.0.1';
        $this->author = 'Vitaly Kondratiev';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('CommerceML Loader');
        $this->description = $this->l('The module helps to get import files from 1C');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('COMMERCEML'))
            $this->warning = $this->l('No name provided');
    }

    public function install()
    {
        if (!parent::install() ||
            !Configuration::updateValue('exchange_path', '/import_data')
        )
            return false;
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
            !Configuration::deleteByName('exchange_login') ||
            !Configuration::deleteByName('exchange_password') ||
            !Configuration::deleteByName('exchange_path') ||
            !Configuration::deleteByName('exchange_zip')
        )
            return false;
        return true;
    }

    public function getContent()
    {
        $output = null;
        if (Tools::isSubmit('submit'.$this->name))
        {
            $exchange_login = strval(Tools::getValue('exchange_login'));
            if (!$exchange_login
                || empty($exchange_login)
                || !Validate::isGenericName($exchange_login))
                $output .= $this->displayError($this->l('Invalid Login value'));
            else if (Configuration::get('exchange_login') != $exchange_login) {
                Configuration::updateValue('exchange_login', $exchange_login);
                $output .= $this->displayConfirmation($this->l('Login updated'));
            }

            $exchange_password = strval(Tools::getValue('exchange_password'));
            if (!$exchange_password
                || empty($exchange_password)
                || !Validate::isGenericName($exchange_password))
                $output .= $this->displayError($this->l('Invalid Password value'));
            else if (Configuration::get('exchange_password') != $exchange_password) {
                Configuration::updateValue('exchange_password', $exchange_password);
                $output .= $this->displayConfirmation($this->l('Password updated'));
            }

            $exchange_path = strval(Tools::getValue('exchange_path'));
            if (!$exchange_path
                || empty($exchange_path)
                || !Validate::isGenericName($exchange_path))
                $output .= $this->displayError($this->l('Invalid Exchange path value'));
            else if (Configuration::get('exchange_path') != $exchange_path) {
                Configuration::updateValue('exchange_path', $exchange_path);
                $output .= $this->displayConfirmation($this->l('Exchange path updated'));
            }

            $exchange_zip = intval(Tools::getValue('exchange_zip'));
            Configuration::updateValue('exchange_zip', $exchange_zip);
        }
        return $output.$this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Exchange credentials'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Login',
                    'name' => 'exchange_login',
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => 'Password',
                    'name' => 'exchange_password',
                    'required' => true
                ),
            )
        );
        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Exchange preferences'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'Relative path to "import_data" directory',
                    'name' => 'exchange_path',
                    'required' => true
                ),
                array(
                    'type' => 'switch',
                    'label' => 'Get the ZIP and unzip it',
                    'name' => 'exchange_zip',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->trans('Enabled', array(), 'Admin.Global')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->trans('Disabled', array(), 'Admin.Global')
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        $helper->fields_value['exchange_login'] = Configuration::get('exchange_login');
        $helper->fields_value['exchange_password'] = Configuration::get('exchange_password');
        $helper->fields_value['exchange_zip'] = Configuration::get('exchange_zip');
        $helper->fields_value['exchange_path'] = Configuration::get('exchange_path');

        return $helper->generateForm($fields_form);
    }

    public function exchange($type, $mode){
        session_name(self::$session_name);
        session_start();
        $upload_path = dirname(__FILE__).'/../../'.Configuration::get('exchange_path').'/';
        header("Content-Type: text/plain");
        $response = 'failure';
        if ($type != 'catalog' || empty($mode)) {
            $response .= "\nEmpty command type or mode.";
        }
        if (($mode != 'checkauth') && (!isset($_COOKIE[self::$session_name]) || ($_COOKIE[self::$session_name] != $_SESSION['fixed_session_id']))) {
            $response .= "\nUnauthorized.";
        }
        else switch ($mode) {
            case 'checkauth': // Authorization query
                if(!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW']) &&
                    (Configuration::get('exchange_login') == $_SERVER['PHP_AUTH_USER']) &&
                    (Configuration::get('exchange_password') == $_SERVER['PHP_AUTH_PW'])){
                    $response = "success";
                    $response .= "\n" . session_name();
                    $response .= "\n" . session_id();
                    $response .= "\n" . self::sessid_get();
                    $response .= "\ntimestamp=" . time();
                }
                else {
                    $response .= "\nAccess denied.";
                }
                break;
            case 'init': // Initialize query
                if (!is_dir($upload_path)){
                    mkdir($upload_path, 0777, true);
                }
                /*else {
                    self::cleanup_import_directory($upload_path);
                }*/
                $response = "zip=" . ((intval(Configuration::get('exchange_zip')) == 1) ? 'yes' : 'no');
                $response .= "\nfile_limit=".self::‌‌parse_size(ini_get("upload_max_filesize"));
                $response .= "\n" . self::sessid_get();
                $response .= "\nversion=2.08";
                break;
            case 'file': // Upload files from 1C
                $filepath = $upload_path . $_GET['filename'];
                $data = file_get_contents("php://input");
                $data_length = $_SERVER['CONTENT_LENGTH'];
                if (isset($data) && $data !== false) {
                    if (dirname($_GET['filename']) != '.') {
                        mkdir($upload_path . '/' . dirname($_GET['filename']), 0777, true);
                    }
                    $file = fopen($filepath, "w+");
                    if ($file) {
                        $bytes_writed = fwrite($file, $data);
                        if ($bytes_writed == $data_length){
                            if (mime_content_type($filepath) == 'application/zip') {
                                $zip = new ZipArchive();
                                $zip->open($filepath);
                                $zip->extractTo($upload_path);
                                $zip->close();
                            }
                            $response = "success";
                        }
                        fclose($file);
                    }
                }
                break;
            case 'import': // Processing data
                if (file_exists($upload_path.$_GET['filename'])){
                    $response = "success";
                }
                else {
                    $response = "failure";
                }
                break;
            default:
                $response = "failure";
        }
        return $response."\n";
    }

    private static function sessid_get($varname='sessid')
    {
        $sessid = null;
        if(!is_array($_SESSION) || !isset($_SESSION['fixed_session_id'])) {
            $_SESSION["fixed_session_id"] = session_id();
        }
        else {
            $sessid = $_SESSION["fixed_session_id"];
        }
        return $varname."=".$sessid;
    }

    private static function ‌‌parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        else {
            return round($size);
        }
    }

    private static function cleanup_import_directory($path){
        $elements = scandir($path);
        foreach ($elements as $element){
            if (in_array($element, array('.', '..'))) continue;
            if (is_dir($path. '/' . $element)){
                if (!rmdir($path. '/' . $element)){
                    self::cleanup_import_directory($path. '/' . $element);
                    rmdir($path. '/' . $element);
                }
            }
            else {
                unlink($path. '/' . $element);
            }
        }
    }
}