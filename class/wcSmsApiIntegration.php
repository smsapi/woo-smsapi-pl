<?php
declare(strict_types=1);

use Smsapi\Client\SmsapiHttpClient;
use Smsapi\Client\Feature\Sms\Bag\SendSmsBag;

require_once 'vendor/autoload.php';

class WcSmsApiIntegration extends WC_Integration
{
    const LOW_FUNDS_LEVEL = 2.0;
    const PARTNER_ID = '4PT2';

    public static $apiVer = 'pl';
    private $_smsapi_client;
    private $api_token;
    private $sender_name;
    private $marketing_sms_user_consent;
    private $checkbox_text;
    private $checkbox_position;
    private $processing_order_sms_enabled;
    private $processing_order_sms_text;
    private $completed_order_sms_enabled;
    private $completed_order_sms_text;
    private $customer_note_sms_enabled;
    private $message_parameters;
    
    private $test;
    
    public function __construct()
    {
        $this->test = array(
            'name' => '{customer}',
            'description' => __('{customer} - Customer first name and last name', 'superpaczka'),
        );
        
        
        $this->id = 'woocommerce-smsapi';
        $this->method_title = __('SMSAPI', 'woocommerce-smsapi');
        $this->method_description = sprintf(
            wp_kses(
                /* translators: %s: URL */
                __('WooCommerce integration with SMSAPI. <a href="%s" target="_blank">Check out the docs &rarr;</a>', 'woocommerce-smsapi'),
                array(  'a' => array( 'href' => array(), 'target' => '_blank' ) )
            ),
            esc_url('http://www.wpdesk.pl/docs/woocommerce-smsapi-docs/')
        );
        $this->enabled = $this->get_option('enabled');
        $this->api_token = $this->get_option('api_token');
        $this->sender_name = $this->get_option('sender_name');
        $this->marketing_sms_user_consent = $this->get_option('marketing_sms_user_consent');
        $this->checkbox_text = $this->get_option('checkbox_text');
        $this->checkbox_position = $this->get_option('checkbox_position');
        $this->processing_order_sms_enabled = $this->get_option('processing_order_sms_enabled');
        $this->processing_order_sms_text = $this->get_option('processing_order_sms_text');
        $this->completed_order_sms_enabled = $this->get_option('completed_order_sms_enabled');
        $this->completed_order_sms_text = $this->get_option('completed_order_sms_text');
        $this->customer_note_sms_enabled = $this->get_option('customer_note_sms_enabled');

        add_action('admin_enqueue_scripts', array($this,'enqueueAdminScripts'));
    
        $this->setMessageParameters();

        // Load the settings.
        $this->initFormFields();
        $this->init_settings();

        add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));

        if ($this->enabled == 'yes') {
            add_action('admin_notices', array($this, 'lowFundsNotificationAction' ));
            add_action('woocommerce_new_customer_note_notification', array($this, 'newCustomerNoteAction'));
            add_action('woocommerce_order_status_changed', array($this, 'orderStatusChangedSmsAction'), 1, 3);

            if ($this->marketing_sms_user_consent == 'yes') {
                if ($this->checkbox_position == 'before') {
                    add_action('woocommerce_review_order_before_submit', array($this, 'addSmsMarketingCheckboxAction'));
                } else {
                    add_action('woocommerce_review_order_after_submit', array($this, 'addSmsMarketingCheckboxAction'));
                }

                add_action('woocommerce_checkout_update_order_meta', array($this, 'checkoutUpdateOrderMetaAction'), 1, 1);
                add_action('show_user_profile', array($this, 'userProfileMarketingAction'), 100, 1);
                add_action('edit_user_profile', array($this, 'userProfileMarketingAction'), 100, 1);
                add_action('personal_options_update', array($this, 'saveUserProfileMarketingAction'));
                add_action('edit_user_profile_update', array($this, 'saveUserProfileMarketingAction'));
            }
        }
    }
    
    private function setMessageParameters()
    {
        $this->message_parameters = array(
            array(
                'name' => '{customer}',
                'data_name' => array('billing=>first_name', 'billing=>last_name'),
                'description' => __('{customer} - Customer first name and last name', 'woocommerce-smsapi'),
            ),
            array(
                'name' => '{number}',
                'data_name' => 'id',
                'description' => __('{number} - Order number', 'woocommerce-smsapi'),
            ),
            array(
                'name' => '{phone}',
                'data_name' => 'billing=>phone',
                'description' => __('{phone} - Customer phone number', 'woocommerce-smsapi'),
            ),
            array(
                'name' => '{total_price}',
                'data_name' => array('total', 'currency'),
                'description' => __('{total_price} - Total Order value', 'woocommerce-smsapi'),
            ),
        );
    }
    
    private function prepareSmsMessage($message, $order)
    {
        if (empty($order) || empty($this->message_parameters)) {
            return $message;
        }
        
        $order_data = $order->get_data();
        
        foreach ($this->message_parameters as $parameter) {
            if (is_array($parameter['data_name'])) {
                $multi_data = array();
                foreach ($parameter['data_name'] as $array_parameter) {
                    if ($this->isIndentedData($array_parameter)) {
                        $indent_data = $this->getIndentData($array_parameter, $order_data);
        
                        if ($indent_data === '__continue') {
                            continue;
                        }
                        $multi_data[] = $indent_data;
                    } else {
                        if (empty($order_data[$array_parameter])) {
                            continue;
                        }
                        $multi_data[] = $order_data[$array_parameter];
                    }
                }
                $param_order_data = implode(' ', $multi_data);
            } else {
                if ($this->isIndentedData($parameter['data_name'])) {
                    $indent_data = $this->getIndentData($parameter['data_name'], $order_data);

                    if ($indent_data === '__continue') {
                        continue;
                    } else {
                        $param_order_data = $indent_data;
                    }
                } else {
                    if (empty($order_data[$parameter['data_name']])) {
                        continue;
                    }
                    $param_order_data = $order_data[$parameter['data_name']];
                }
            }
            $message = str_replace($parameter['name'], $param_order_data, $message);
        }
        
        return $message;
    }
    
    private function getIndentData($data_name, $data)
    {
        $x = explode('=>', $data_name);
        if (empty($data[$x[0]][$x[1]])) {
            return '__continue';
        }

        return $data[$x[0]][$x[1]];
    }
    
    private function isIndentedData($data_name)
    {
        if (strstr($data_name, '=>')) {
            return true;
        } else {
            return false;
        }
    }

    public function saveUserProfileMarketingAction($user_id)
    {
        update_user_meta($user_id, 'woocommerce_sms_marketing_consent', (isset($_POST['woocommerce_sms_marketing_consent'])) ? 1 : 0);
    }

    protected function getSmsMarketingUserConsent($user_id)
    {
        $sms_marketing_consent = get_the_author_meta('woocommerce_sms_marketing_consent', $user_id);
        if ($sms_marketing_consent === "") {
            $sms_marketing_consent = 0;
        }
        return intval($sms_marketing_consent);
    }

    public function userProfileMarketingAction($user)
    {
        ?>
        <table class="form-table">
            <tr>
                <th><label for="woocommerce_sms_marketing_consent"><?php _e('SMS Marketing', 'woocommerce-smsapi'); ?></label></th>
                <td>
                    <input name="woocommerce_sms_marketing_consent" id="woocommerce_sms_marketing_consent" value="1" type="checkbox"
                        <?php if ($this->getSmsMarketingUserConsent($user->ID)) {
                            echo 'checked';
                        } ?> >
                    <label for="woocommerce_sms_marketing_consent"><?php echo $this->checkbox_text; ?></label>
                </td>
            </tr>
        </table>
        <?php
    }

    protected function refreshSmsApiFields()
    {
        $this->api_token = $this->get_option('api_token');
        $this->_smsapi_client = null;
    }

    protected function sendSms($to, $text)
    {
        self::$apiVer = $this->get_option('api_ver');
    
        if ('int' === self::$apiVer) {
            $service = (new SmsapiHttpClient())->smsapiComService($this->api_token);
        } else {
            $service = (new SmsapiHttpClient())->smsapiPlService($this->api_token);
        }
        
        $sms = SendSmsBag::withMessage($to, $text);
        $sms->partnerId = self::PARTNER_ID;
        $sms->encoding = 'utf-8';
        //$sms->test = 1;

        if (!empty($this->sender_name)) {
            $sms->from = $this->sender_name;
        }
        
        return $service->smsFeature()->sendSms($sms);
    }
    
    private function getSendersName()
    {
        self::$apiVer = $this->get_option('api_ver');
    
        if ($this->getSmsPoints() === false) {
            return  false;
        }
        
        if ('int' === self::$apiVer) {
            $service = (new SmsapiHttpClient())->smsapiComService($this->api_token);
        } else {
            $service = (new SmsapiHttpClient())->smsapiPlService($this->api_token);
        }
        
        $sendernameFeature = $service->smsFeature()->sendernameFeature();
    
        return $sendernameFeature->findSendernames();
    }
    
    private function getActiveSendersName()
    {
        $sensers_name = $this->getSendersName();
        
        if (empty($sensers_name) || !is_array($sensers_name)) {
            return false;
        }
        
        $active_senders = [];
        $i = 0;
        
        foreach ($sensers_name as $sender) {
            if ($sender->status === 'INACTIVE') {
                continue;
            } elseif ($sender->status === 'ACTIVE') {
                $active_senders[$i]['name'] = $sender->sender;
                $active_senders[$i]['is_default'] = $sender->isDefault;
                $i++;
            }
        }
        
        return  $active_senders;
    }
    
    private function getOptionsSendersName()
    {
        $active_senders = $this->getActiveSendersName();
    
        $senders = [
            'options' => [],
            'default' => ''
        ];
        
        if (empty($active_senders)) {
            return $senders;
        }
        
        foreach ($active_senders as $sender) {
            $senders['options'][$sender['name']] = $sender['name'];
            
            if ($sender['is_default'] == 1) {
                $senders['default'] = $sender['name'];
            }
        }
        
        return $senders;
    }

    public function checkoutUpdateOrderMetaAction($order_id)
    {
        $order = new WC_Order($order_id);
        $user_id = $order->get_user_id();
        if ($user_id) {
                update_user_meta($user_id, 'woocommerce_sms_marketing_consent', (isset($_POST['sms_marketing'])) ? 1 : 0);
        }
    }

    public function orderStatusChangedSmsAction($order_id, $old_status, $new_status)
    {
        $order = new WC_Order($order_id);

        if ($new_status == 'processing' && $this->processing_order_sms_enabled == 'yes') {
            $this->sendSms($order->get_billing_phone(), $this->prepareSmsMessage($this->processing_order_sms_text, $order));
        }

        if ($new_status == 'completed' && $this->completed_order_sms_enabled == 'yes') {
            $this->sendSms($order->get_billing_phone(), $this->prepareSmsMessage($this->completed_order_sms_text, $order));
        }
    }

    public function newCustomerNoteAction($note)
    {
        if ($note) {
            $order_id = $note['order_id'];
            $customer_note = $note['customer_note'];

            $order = new WC_Order($order_id);
            if ($this->customer_note_sms_enabled == 'yes') {
                $this->sendSms($order->billing_phone, $customer_note);
            }
        }
    }

    public function addSmsMarketingCheckboxAction()
    {
        $user_id = get_current_user_id(); ?>
        <p class="form-row smsapi">
            <label for="sms_marketing" class="sms_marketing">
                <input type="checkbox" id="sms_marketing" name="sms_marketing" value="1"
                    <?php if ($this->getSmsMarketingUserConsent($user_id)) {
                        echo 'checked';
                    } ?> >
                <?php echo esc_html($this->checkbox_text); ?>
            </label>
        </p>
        <?php
    }

	// phpcs:disable
    public function admin_options()
    {
	    // phpcs:enable
        ?>
        <div class="wrap">
            <div class="inspire-settings">
                <div class="inspire-main-content">
                    <?php
                    parent::admin_options();

                    // refresh fields after save
                    $this->enabled = $this->get_option('enabled');
                    $this->refreshSmsApiFields();

                    if ($this->enabled == 'yes') {
                        $status = __('Error. Please fill in valid API Token.', 'woocommerce-smsapi');
                        if ($this->api_token != "") {
                            if ($this->checkSmsConnection()) {
                                $status = __('OK', 'woocommerce-smsapi');
                            }
                        }
                        ?>
                        <p><?php _e('Connection status:', 'woocommerce-smsapi'); ?> <?php echo $status; ?></p>
                        <?php
                    }
                    ?>
                </div>
                <div class="inspire-sidebar">
                    <a href="http://www.wpdesk.pl/?utm_source=smsapi-settings&utm_medium=banner&utm_campaign=woocommerce-plugins" target="_blank"><img src="<?php echo $this->pluginUrl(); ?>/assets/images/wpdesk-woocommerce-plugins.png" alt="Wtyczki do WooCommerce" height="250" width="250" /></a>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function initFormFields()
    {
        $senders = $this->getOptionsSendersName();
        
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable', 'woocommerce-smsapi'),
                'label' => __('Enable SMSAPI integration', 'woocommerce-smsapi'),
                'type' => 'checkbox',
                'default' => 'yes'
            ),
            'api_ver' => array(
                'title' => __('API version', 'woocommerce-smsapi'),
                'type' => 'select',
                'options' => array( 'int' => 'smsapi.com (international)',
                    'pl' => 'smsapi.pl (polski)'
                ),
            ),
            'api_token' => array(
                'title' => __('API Token', 'woocommerce-smsapi'),
                'type' => 'password',
                'default' => '',
                'description' => __('SMSAPI API Token. You can generate it in SMSAPI customer panel.', 'woocommerce-smsapi'),
                'desc_tip'    => true
            ),
            'sender_name' => array(
                'title' => __('SMS Sender name', 'woocommerce-smsapi'),
                'type' => 'select',
                'options' => $senders['options'],
                'default' => $senders['default'],
                'description' => __('Only names with "Active" status are available. The status can be checked in the SMSAPI customer panel.', 'woocommerce-smsapi'),
            ),
            'marketing_sms_user_consent' => array(
                'label' => __('Enable user consent to SMS Marketing', 'woocommerce-smsapi'),
                'title' => __('SMS Marketing', 'woocommerce-smsapi'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Checkbox will be added to checkout page allowing users to agree to SMS marketing.', 'woocommerce-smsapi')
            ),
            'checkbox_text' => array(
                'title' => __('Checkbox label', 'woocommerce-smsapi'),
                'type' => 'text',
                'default' => __('I agree to receiving marketing content via SMS', 'woocommerce-smsapi')
            ),
            'checkbox_position' => array(
                'title' => __('Checkbox position', 'woocommerce-smsapi'),
                'type' => 'select',
                'options' => array(
                    'before' => __('Above Place order button', 'woocommerce-smsapi'),
                    'after' => __('Below Place order button', 'woocommerce-smsapi'),
                ),
                'default' => 'before',
            ),
            'processing_order_sms_enabled' => array(
                'title' => __('Processing order SMS', 'woocommerce-smsapi'),
                'label' => __('Enable', 'woocommerce-smsapi'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('SMS will be sent when order status is changed to processing.', 'woocommerce-smsapi'),
                'desc_tip'    => true
            ),
            'processing_order_sms_text' => array(
                'title' => __('Processing order SMS text', 'woocommerce-smsapi'),
                'type' => 'textarea',
                'default' =>
                    /* translators: %s: Name of a blog */
                    sprintf(__('Your order number {number} status  at %s changed to processing.', 'woocommerce-smsapi'), get_bloginfo('name')),
                'description' =>
                    __('One SMS is a maximum of 160 characters. Note that if you enter special characters (including Polish characters), the limit will be 70 characters. If this value is exceeded, the system will send a message as several linked SMSes - up to 1530 characters (or 670 with special characters) as 10 linked SMSs, charging the account according to the current price list.', 'woocommerce-smsapi').
                    '<br>'.
                    __('List of available parameters that can be used in the message:', 'woocommerce-smsapi').
                    '<br>'.
                    $this->displayMessageParametersDesc()
            ),
            'completed_order_sms_enabled' => array(
                'title' => __('Completed order SMS', 'woocommerce-smsapi'),
                'label' => __('Enable', 'woocommerce-smsapi'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('SMS will be sent when order status is changed to completed.', 'woocommerce-smsapi'),
                'desc_tip'    => true
            ),
            'completed_order_sms_text' => array(
                'title' => __('Completed order SMS text', 'woocommerce-smsapi'),
                'type' => 'textarea',
                'default' =>
                    /* translators: %s: Name of a blog */
                    sprintf(__('Your order number {number} status at %s changed to completed.', 'woocommerce-smsapi'), get_bloginfo('name')),
                'description' =>
                    __('One SMS is a maximum of 160 characters. Note that if you enter special characters (including Polish characters), the limit will be 70 characters. If this value is exceeded, the system will send a message as several linked SMSes - up to 1530 characters (or 670 with special characters) as 10 linked SMSs, charging the account according to the current price list.', 'woocommerce-smsapi').
                    '<br>'.
                    __('List of available parameters that can be used in the message:', 'woocommerce-smsapi').
                    '<br>'.
                    $this->displayMessageParametersDesc()
            ),
            'customer_note_sms_enabled' => array(
                'title' => __('Customer note SMS', 'woocommerce-smsapi'),
                'label' => __('Enable', 'woocommerce-smsapi'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Text will be taken from the customer note.', 'woocommerce-smsapi')
            )
        );
        if ($this->marketing_sms_user_consent == "no") {
            $this->form_fields['checkbox_position']['disabled'] = 'disabled';
        }
        if ($this->processing_order_sms_enabled == "no") {
            $this->form_fields['processing_order_sms_text']['disabled'] = 'disabled';
        }
        if ($this->completed_order_sms_enabled == "no") {
            $this->form_fields['completed_order_sms_text']['disabled'] = 'disabled';
        }
    }
    
    private function displayMessageParametersDesc()
    {
        $desc = '';
        if (!empty($this->message_parameters)) {
            foreach ($this->message_parameters as $param) {
                $desc .= $param['description'];
                $desc .= '<br>';
            }
        }
        
        return $desc;
    }
    
    public function pluginUrl()
    {
        if (isset($this->pluginUrl)) {
            return $this->pluginUrl;
        }

        if (is_ssl()) {
            return $this->pluginUrl = str_replace('http://', 'https://', WP_PLUGIN_URL) . '/' . plugin_basename(dirname(dirname(__FILE__)));
        } else {
            return $this->pluginUrl = WP_PLUGIN_URL . '/' . plugin_basename(dirname(dirname(__FILE__)));
        }
    }

    public function isIntegration()
    {
        global $woocommerce;

        if (version_compare($woocommerce->version, '2.1.0', '>=')) { // WC 2.1
            $isIntegration = isset($_GET['page']) && $_GET['page'] == 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] == "integration";
        } else {
            $isIntegration = isset($_GET['page']) && $_GET['page'] == 'woocommerce_settings' && isset($_GET['tab']) && $_GET['tab'] == "integration";
        }
        return $isIntegration;
    }

    public function lowFundsNotificationAction()
    {
        $this->refreshSmsApiFields();

        if (!is_admin() || !$this->isIntegration() || !$this->checkSmsConnection() || $_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }

        if ($this->getSmsPoints() < self::LOW_FUNDS_LEVEL) {
            echo '<div class="notice notice-warning"><p>'
                . sprintf(wp_kses(
                    /* translators: %s: URL */
                    __('You are running out of funds. In order to keep sending SMS messages, <a href="%s" target="_blank">log in</a> to you SMSAPI account and buy more points.', 'woocommerce-smsapi'),
                    array(  'a' => array( 'href' => array(), 'target' => '_blank' )
                    )
                ), esc_url('https://ssl.smsapi.pl/')) .
                '</p></div>';
        }
    }

    public function getSmsPoints()
    {
        self::$apiVer = $this->get_option('api_ver');
    
        if ('int' === self::$apiVer) {
            $service = (new SmsapiHttpClient())->smsapiComService($this->api_token);
        } else {
            $service = (new SmsapiHttpClient())->smsapiPlService($this->api_token);
        }
    
        $result = $service->pingFeature()->ping();
    
        if ($result->smsapi) {
            return true;
        } else {
            return false;
        }
    }

    public function checkSmsConnection()
    {
        return $this->getSmsPoints() !== false;
    }
    
    public function enqueueAdminScripts()
    {
        wp_enqueue_script('smsapi-admin-js', $this->pluginUrl() . '/assets/js/admin.js', array('jquery'), '1.0', true);
    }
}
