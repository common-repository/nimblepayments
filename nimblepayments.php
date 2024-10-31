<?php

/*
Plugin Name: Nimble Payments
Plugin URI: https://www.nimblepayments.com
Description: Nimble Payments is an online payment gateway supported by BBVA that enables you to accept online payments flexibly and safely.
Version: 3.0.1
Author: BBVA
Author URI: 
License: GPLv2
Text Domain: woocommerce-nimble-payments
Domain Path: /lang/
*/

/* 
Copyright (C) 2016 BBVA

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

class Woocommerce_Nimble_Payments {
    protected static $instance = null;
    var $slug = 'nimble-payments';
    var $options_name = 'nimble_payments_options';
    static $domain = 'woocommerce-nimble-payments';
    protected static $gateway = null;
    protected static $params = null;
    protected $default_options = array(
        'version' => '0',
        'token' => '',
        'refreshToken' => ''
    );

    static function & getInstance() {
        if ( is_null(self::$instance) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }
    
    function __construct() {
        // If instance is null, create it. Prevent creating multiple instances of this class
        if ( is_null( self::$instance ) ) {
            self::$instance = $this;
            
            add_action( 'init', array( $this, 'load_text_domain' ), 0 );
            
            add_action( 'plugins_loaded', array( $this, 'init_your_gateway_class' ) );
            
            add_filter( 'woocommerce_payment_gateways', array( $this, 'add_your_gateway_class' ) );
            
            add_action( 'woocommerce_init', array( $this, 'gateway_loaded'), 0);

            add_action( 'wp_loaded', array( $this, 'check_init'), 99 );
            
            add_action( 'admin_menu', array( $this, 'nimble_menu'));
            
            add_filter( 'wc_order_statuses', array( $this, 'add_custom_statuses' ) );
            
            add_action( 'init', array( $this, 'register_post_status' ), 9 );

            add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'valid_order_statuses_for_payment' ) );

            add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'valid_order_statuses_for_payment' ) );
            
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
            
            add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts') );
            
            add_action('wp_login', array($this, 'login_actions'), 10, 2);
            
            add_action( 'admin_notices', array( $this, 'admin_notices' ), 0 );
            
            add_action( 'wp_ajax_nimble_payments_oauth3', array( $this, 'ajax_oauth3' ) );
            
            add_action( 'wp_ajax_nimble_payments_oauth3_disassociate', array( $this, 'ajax_oauth3_disassociate' ) );

            add_action( 'wp_ajax_nimble_payments_gateway', array( $this, 'ajax_gateway' ) );
            
            add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ), 0 );
            
            //Custom template checkout/payment-method.php
            add_filter( 'wc_get_template', array( $this, 'filter_templates_checkout' ), 10, 3);

            $this->load_settings();
        }
    }

    public function load_settings(){
        //Plugin options
        $this->options = array_merge($this->default_options, get_option($this->options_name, array()));
        $this->oauth3_enabled = ( $this->options['token'] != '' );
        $this->gateway_enabled = $this->isGatewayEnabled();
    }

    public function check_init() {
        //Plugin version
        $header_options = get_file_data(__FILE__, array('version' => 'Version'), 'plugin' );

        // Upgrade Plugin
        if ( version_compare( $this->options['version'], $header_options['version'], '<' ) && $this->isGatewayEnabled() ) {
            $this->upgrade_version();
            $this->options['version'] = $header_options['version'];
            update_option($this->options_name, $this->options);
        }

        // Check Nimble-Pending Orders
        if ( isset( $this->options['check_orders'] ) && $this->options['check_orders'] && $this->isGatewayEnabled() ) {
            $this->check_orders();
            unset($this->options['check_orders']);
            update_option($this->options_name, $this->options);
        }
    }

    private function np_get_orders( $args = array() ) {
        $orders = array();
        global $woocommerce;
        if( version_compare( $woocommerce->version, '2.6.0', ">=" ) ) {
            $orders = wc_get_orders( $args );
        } else {
            if (!isset($args['status'])) {
                return array();
            }
            $where_clause = "post_status ";
            if (is_array($args['status'])) {
                $where_clause .= "in (";
                foreach ($args['status'] as $status) {
                    $where_clause .= "'".$status."', ";
                }
                $where_clause = substr($where_clause, 0, -2);
                $where_clause .= ");";
            } else {
                $where_clause .= "= '".$args['status']."';";
            }
            global $wpdb;
            $orders_id = $wpdb->get_results("select ID from {$wpdb->prefix}posts where {$where_clause}");
            foreach ($orders_id as $id) {
                $order = $this->np_get_order( $id );
                array_push( $orders, $order );
            }
        }
        return $orders;
    }

    private function np_get_order( $order_id ) {
        global $woocommerce;
        $order = false;
        if( version_compare( $woocommerce->version, '2.2.0', "<" ) ) {
            $order = wc_get_order( $order_id );
        } else {
            $order = new WC_Order( $order_id );
        }
        return $order;
    }
    
    private function upgrade_version() {
        if ( version_compare( $this->options['version'], '3.0.0', '<' ) ) {
            $older_orders = $this->np_get_orders( array( 'status' => array('wc-nimble-denied', 'wc-nimble-failed'), 'limit' => -1, 'order' => 'ASC' ) );
            foreach ($older_orders as $order) {
                switch ($order->post_status) {
                    case 'wc-nimble-denied':
                        $order->update_status('pending', __('Update Nimble Payments plugin 3.0.0.', 'woocommerce-nimble-payments')); //LANG: OLD DENIED ORDER STATUS
                        break;
                    case 'wc-nimble-failed':
                        $order->update_status('nimble-pending', __('Update Nimble Payments plugin 3.0.0.', 'woocommerce-nimble-payments')); //LANG: OLD FAILED ORDER STATUS
                        self::$gateway->change_order_status($order->id);
                        break;
                }
            }
        }
        /*FOR FUTURE VERSIONS
        if ( version_compare( $this->options['version'], 'X.X.X', '<' ) ) {
            UPGRADE CODE HERE
        }*/
    }

    private function check_orders() {
        $orders = $this->np_get_orders( array( 'status' => 'wc-nimble-pending', 'limit' => -1, 'order' => 'ASC' ) );
        foreach ($orders as $order_number => $order) {
            self::$gateway->change_order_status($order->id);
        }
    }

    function get_plugin_version(){
        return $this->options['version'];
    }
    
    function gateway_loaded(){
        //Obtain wc_gateway_nimble
        $available_gateways = WC()->payment_gateways()->payment_gateways();
        $gateway_active = isset($available_gateways['nimble_payments_gateway']) ? $available_gateways['nimble_payments_gateway']->is_available() : false;
        if ( $gateway_active ){
            self::$gateway = $available_gateways['nimble_payments_gateway'];
            self::$params = self::$gateway->get_params();
            
            //Refresh Token if neccesary
            $this->refreshToken();
        }
    }
            
    function admin_enqueue_scripts($hook) {
        global $post;
        wp_enqueue_script('nimble-payments-js', plugins_url("js/nimble-payments.js", __FILE__), array('jquery'), '20161023');
        
        //Custom JS for refunds
        if ('post.php' == $hook && 'edit' == filter_input(INPUT_GET, 'action') && 'shop_order' == $post->post_type ){
            $order = wc_get_order( $post->ID );
            if (self::$gateway && $order->payment_method == self::$gateway->id){
                wp_enqueue_script('nimble-payments-refunds-js', plugins_url("js/nimble-payments-refunds.js", __FILE__), array('jquery'), '20160607v2');
                
                //Refund Data for STEP 3 (ajax)
                if ( $this->oauth3_enabled && isset($_REQUEST['ticket']) && isset($_REQUEST['result']) ){
                    $user_id = get_current_user_id();
                    $ticket = filter_input(INPUT_GET, 'ticket');
                    $result = filter_input(INPUT_GET, 'result');
                    $otp_info = get_user_meta($user_id, 'nimblepayments_ticket', true);
                    //Validate ticket
                    if ( isset($otp_info) && isset($otp_info['ticket']) && $otp_info['ticket'] == $ticket ){
                        $current_refund = array(
                            'result' => $result,
                            'ticket' => $ticket,
                            'user_id' => $user_id,
                            'data' => $otp_info,
                            'process_message' => __( 'Refund in progress', 'woocommerce-nimble-payments' ), //LANG: REFUND_PROGRESS
                            'error' => __( 'Refund Failed', 'woocommerce-nimble-payments' ) //LANG: ERROR_REFUND_2
                        );
                        delete_user_meta($user_id, 'nimblepayments_ticket');
                        wp_localize_script( 'nimble-payments-refunds-js', 'np_refund_info', $current_refund );
                    }
                }
                
            }
        }
        
        wp_register_style('wp_nimble_backend_css', plugins_url('css/wp-nimble-backend.css', __FILE__), false, '20160322');
        wp_enqueue_style('wp_nimble_backend_css');
        
        if( "woocommerce_page_wc-settings" == $hook || ( 'edit.php' == $hook && isset( $_GET['post_type'] ) && 'shop_order' == $_GET['post_type'] ) ){
            wp_register_style('nimble_setting_css', plugins_url('css/nimble_setting.css', __FILE__), false, '20160721');
            wp_enqueue_style('nimble_setting_css');
        }
    }
    
    function wp_enqueue_scripts() {
        wp_register_style('nimblepayments_gateway_css', plugins_url('css/nimblepayments-gateway.css', __FILE__), false, '20160608');
        wp_enqueue_style('nimblepayments_gateway_css');
    }
    
    function nimble_menu(){
        if ( !defined('WP_CONTENT_URL') )
            define( 'WP_CONTENT_URL', get_option('siteurl') . '/wp-content'); // full url - WP_CONTENT_DIR is defined further up
        
        //add_menu_page( 'Nimble Payments', 'Nimble Payments', 'manage_options', 'wc-settings&tab=checkout&section=wc_gateway_nimble', array( $this, 'nimble_options' ), null, '54.555');
        add_menu_page( 'Nimble Payments', 'Nimble Payments', 'manage_options', $this->slug, array( $this, 'nimble_options' ), null, '54.555');
        //add_submenu_page($this->slug, __('Settings', self::$domain), __('Settings', self::$domain), 'manage_options', $this->slug.'-settings', array( $this, 'menu_settings'));
        
    }
    
    function nimble_options() {
        //Redirect to gateway config page
        add_submenu_page( $this->slug, 'Nimble Payments', 'Nimble Payments', 'manage_options', 'wc-settings&tab=checkout&section=wc_gateway_nimble', array( $this, 'nimble_options' ));
        $redirect_url = menu_page_url('wc-settings&tab=checkout&section=wc_gateway_nimble', false);
        
        //Obtain token & reflesh token with code
        if ( ! $this->oauth3_enabled && isset($_REQUEST['code']) ){
            $code = filter_input(INPUT_GET, 'code');
            $this->validateOauthCode($code);
        }
        
        //REFUND OTP RESULT
        if ( $this->oauth3_enabled && isset($_REQUEST['ticket']) && isset($_REQUEST['result']) ){
            $ticket = filter_input(INPUT_GET, 'ticket');
            $result = filter_input(INPUT_GET, 'result');
            $user_id = get_current_user_id();
            $nimble_ticket = get_user_meta($user_id, 'nimblepayments_ticket', true);
            if (isset($nimble_ticket['order_id'])){
                $redirect_url = get_edit_post_link($nimble_ticket['order_id']);
                $redirect_url .= "&ticket={$ticket}&result={$result}";
            }
        }
        
        //$this->summary_info();
        include_once( 'templates/nimble-admin-redirect.php' );
    }
    
    function admin_notices() {
        $current_section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';
        if ( 'wc_gateway_nimble' != $current_section ){
            if ( false == $this->gateway_enabled ){
                self::activate_notice();
            } elseif ( ! $this->oauth3_enabled && ! isset($_REQUEST['code']) ){
                self::authorize_notice();
            }
        }
    }
    
    static function activate_notice(){
        ?>
            <div class="updated wc-nimble-message">
                <div class="squeezer">
                    <h4 class="info"><?php _e("Operation rejected.", self::$domain ); //LANG: OPERATION_REJECTED ?></h4>
                    <h4><?php _e("You have not activated your payment gateway Nimble Payments.", self::$domain ); //LANG: ACTIVATE_MESSAGE ?></h4>
                    <p class="submit">
                        <a class="button button-primary" href="<?php echo get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=wc_gateway_nimble" ?>" ><?php _e( 'Activate', self::$domain ); //LANG: ACTIVATE_BUTTON ?></a>
                    </p>
                </div>
            </div>
        <?php
    }
    
    static function authorize_notice(){
        ?>
            <div id="np-authorize-message" class="updated wc-nimble-message"><div class="squeezer">
                    <h4 class="info"><?php _e("Operation rejected.", self::$domain ); //LANG: OPERATION_REJECTED ?></h4>
                    <h4><?php _e("You have not yet WooCommerce authorized to perform operations on Nimble Payments.", self::$domain ); //LANG: AUTHORIZE_MESSAGE ?></h4>
                    <p class="submit">
                        <a id="np-oauth3" class="button button-primary" href="#" target="_blank"><?php _e( 'Authorize Woocommerce', self::$domain ); //LANG: AUTHORIZE_BUTTON ?></a>
                    </p>
            </div></div>
        <?php
    }


    static function disassociate(){
        ?>
            <div id="np-authorize-message" class="updated wc-nimble-message"><div class="squeezer">
                    <h4><?php _e('Ecommerce linked to Nimble Payments', 'woocommerce-nimble-payments'); ?></h4>
                    <p class="submit">
                        <a id="np-oauth3-disassociate" class="button" href="#"><?php _e('Disassociate', 'woocommerce-nimble-payments'); ?></a>
                    </p>
            </div></div>
        <?php
    }
    
    function ajax_oauth3(){
        $data = array();
        $data['url_oauth3'] = $this->getOauth3Url();
        echo json_encode($data);
        die();
    }

    public function ajax_oauth3_disassociate() {
        $this->oauth3_enabled = false;
        $this->options['token'] = '';
        $this->options['refreshToken'] = '';
        update_option($this->options_name, $this->options);
    }
    
    static function get_gateway_url(){
        $platform = 'WooCommerce';
        $storeName = get_bloginfo( 'name' );
        $storeURL = home_url();
        $redirectURL = admin_url('admin.php?page=nimble-payments');
        
        return NimbleAPI::getGatewayUrl($platform, $storeName, $storeURL, $redirectURL);
    }
    
    function ajax_gateway(){
        $data = array();
        $data['url_gateway'] = self::get_gateway_url();
        echo json_encode($data);
        die();
    }
    
    function menu_settings() {
        //to do
    }
    
    static function activar_plugin() {
        $header_options = get_file_data(__FILE__, array('version' => 'Version'), 'plugin' );
        update_option('nimble_payments_options', $header_options);
    }
    
    static function desactivar_plugin() {
        delete_option( 'woocommerce_nimble_payments_gateway_settings' );
        delete_option( 'nimble_payments_options' );
    }
    
    /**
     * Initialise Gateway Settings Form Fields
     */
     function init_your_gateway_class() {
        include_once( 'includes/class-wc-gateway-nimble.php' );
        require_once 'lib/Nimble/base/NimbleAPI.php';
        require_once 'lib/Nimble/extensions/wordpress/WP_NimbleAPI.php';
        require_once 'lib/Nimble/api/NimbleAPIAccount.php';
        require_once 'lib/Nimble/api/NimbleAPICredentials.php';
        require_once 'lib/Nimble/api/NimbleAPIPayments.php';
        require_once 'lib/Nimble/api/NimbleAPIStoredCards.php';
    } // End init_form_fields()
    
    function add_your_gateway_class( $methods ) {
        $methods[] = 'WC_Gateway_Nimble';
        return $methods;
    }
    
    function load_text_domain(){
        load_plugin_textdomain('woocommerce-nimble-payments', null, plugin_basename(dirname(__FILE__)) . "/lang");
    }
    
    function add_custom_statuses($order_statuses){
        $new_statuses = array(
            'wc-nimble-pending'    => _x( 'Processing transaction', 'Order status', 'woocommerce-nimble-payments' ), //LANG: PENDING STATUS
        );
        return array_merge($order_statuses, $new_statuses);
    }
    
    function register_post_status() {
        register_post_status('wc-nimble-pending', array(
            'label' =>  _x( 'Processing transaction', 'Order status', 'woocommerce-nimble-payments' ), //LANG: PENDING STATUS
            'public'    => false,
            'exclude_from_search'   =>  false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' =>  true,
            'label_count' => _n_noop('Processing Payment <span class="count">(%s)</span>', 'Processing Payment <span class="count">(%s)</span>', 'woocommerce') //LANG: PENDING STATUS LIST
        ));
    }

    function valid_order_statuses_for_payment($order_statuses){
       $order_statuses[]='nimble-pending';
       return $order_statuses;
   }
    
    function filter_templates_checkout($located, $template_name, $args){
        if ( 'checkout/payment-method.php' == $template_name  && isset($args['gateway']) && 'nimble_payments_gateway' == $args['gateway']->id ){
            $located = plugin_dir_path(__FILE__) . "templates/nimble-checkout-payment-method.php";
        }
        elseif ( 'checkout/thankyou.php' == $template_name && isset($args['order']) ){
            $order = $args['order'];
            $payment_method_id = get_post_meta( $order->id, '_payment_method', true);
            if ('nimble_payments_gateway' == $payment_method_id){
                $located = plugin_dir_path(__FILE__) . "templates/nimble-checkout-thankyou.php";
            }
        }
        return $located;
    }
    
    /**
     * 
     * @return $url to OAUTH 3 step or false
     */
    function getOauth3Url(){
        if ( self::$gateway ){
            try {
                $nimble_api = new WP_NimbleAPI(self::$params);
                $url=$nimble_api->getOauth3Url();
            } catch (Exception $e) {
                return false;
            }
        }
        return self::$gateway ? $url : false;
    }
    
    /**
     * Validate Oauth Code and update options
     */
    function validateOauthCode($code){
        if ( self::$gateway ){
            try {
                $params = array(
                    'oauth_code' => $code
                );
                $params = wp_parse_args($params, self::$params);
                $nimble_api = new WP_NimbleAPI($params);
                $options = array(
                    'token' => $nimble_api->authorization->getAccessToken(),
                    'refreshToken' => $nimble_api->authorization->getRefreshToken()
                );
                update_option($this->options_name, $options);
                $this->oauth3_enabled = true;
            } catch (Exception $e) {
                $this->oauth3_enabled = false;
            }
        }
    }
    
    /**
     * Refresh token
     */
    function refreshToken(){
        if ( self::$gateway ){
            try {
                if (isset($this->options['login']) && $this->options['login']){
                    unset($this->options['login']);
                    $params = wp_parse_args($this->options, self::$params);
                    $nimble_api = new WP_NimbleAPI($params);
                    $this->options = array_merge($this->options,
                            array(
                                'token' => $nimble_api->authorization->getAccessToken(),
                                'refreshToken' => $nimble_api->authorization->getRefreshToken()
                            )
                    );
                    if ( empty($this->options['token']) || empty($this->options['refreshToken']) ){
                        $this->options['token'] = '';
                        $this->options['refreshToken'] = '';
                    }
                    update_option($this->options_name, $this->options);
                    $this->oauth3_enabled = true;
                }
            } catch (Exception $e) {
                $this->oauth3_enabled = false;
            }
        }
    }
    
    /*
     * Set var login = True
     */
    function login_actions($user_login, $user){
        // For refreshToken ::: hook woocommerce_init
        if ( user_can($user, 'manage_options') ){
            $options = get_option($this->options_name);
            if (is_array($options)){
                $options['login'] = true;
                update_option($this->options_name, $options);
            }
        }
        // For Check Nimble-Pending Orders ::: hook wp_loaded
        $is_admin = in_array('administrator', $user->roles);
        if ( self::$gateway && $is_admin ) {
            $options = get_option($this->options_name);
            if (is_array($options)){
                $options['check_orders'] = true;
                update_option($this->options_name, $options);
            }
        }
    }
    
    /*
     * Get Resumen
     */
    function getResumen(){
        if ( self::$gateway ){
            try {
                $options = $this->options;
                unset($options['refreshToken']);
                $params = wp_parse_args($options, self::$params);
                $nimble_api = new WP_NimbleAPI($params);
                $summary = NimbleAPIAccount::balanceSummary($nimble_api);
                if ( !isset($summary['result']) || ! isset($summary['result']['code']) || 200 != $summary['result']['code'] || !isset($summary['data'])){
                    $this->manageApiError($summary);
                } else{
                    include_once( 'templates/nimble-summary.php' );
                }
                
            } catch (Exception $e) {
                $this->oauth3_enabled = false;
            }
        }
    }
    
    /*
     * Get Dashboard Info
     */
    function getDashboardInfo(){
        if ( self::$gateway ){
            try {
                $options = $this->options;
                unset($options['refreshToken']);
                $params = wp_parse_args($options, self::$params);
                $nimble_api = new WP_NimbleAPI($params);
                $summary = NimbleAPIAccount::balanceSummary($nimble_api);
                if ( !isset($summary['result']) || ! isset($summary['result']['code']) || 200 != $summary['result']['code'] || !isset($summary['data'])){
                    $this->manageApiError($summary);
                } else{
                    include_once( 'templates/nimble-dashboard-widget.php' );
                }
                
            } catch (Exception $e) {
                $this->oauth3_enabled = false;
            }
        }
    }
    
    function manageApiError($response){
        $this->oauth3_enabled = false;
        $this->options['token'] = '';
        $this->options['refreshToken'] = '';
        update_option($this->options_name, $this->options);
    }
    
    function isOauth3Enabled(){
        return $this->oauth3_enabled;
    }
    
    function isGatewayEnabled(){
        $gateway_options = get_option( 'woocommerce_nimble_payments_gateway_settings', null );
        if (!$gateway_options
                || (!isset($gateway_options['enabled']))
                || (!isset($gateway_options['status_nimble']))
                || "yes" != $gateway_options['enabled']
                || ! $gateway_options['status_nimble']
                ){
            return FALSE;
        }
        return TRUE;
    }
    
    /**
     * Get assigned transaction_id on Nimble Payments if neccesary
     * @param type $order
     * @return type
     */
    function get_transaction_id($order){
        $transaction_id = $order->get_transaction_id();
        if ( !$transaction_id && self::$gateway ){
            try {
                $nimble_api = new WP_NimbleAPI(self::$params);
                $result = NimbleAPIPayments::getPaymentStatus($nimble_api, null, $order->id);
                if ( isset($result['data']) && isset($result['data']['details']) && isset($result['data']['details'][0]) && isset($result['data']['details'][0]['transactionId']) ){
                    $transaction_id = $result['data']['details'][0]['transactionId'];
                    update_post_meta( $order->id, '_transaction_id', $transaction_id );
                }
                
            } catch (Exception $e) {
            }
        }
        return $transaction_id;
    }
    
    function add_dashboard_widgets() {
        if ( is_blog_admin() && current_user_can('manage_options') )
            wp_add_dashboard_widget( 'nimble_payments_dashboard', __('Nimble Payments Summary', 'woocommerce-nimble-payments'), array( $this, 'dashboard_widget' ) ); //LANG: SUMMARY_TITLE_DASHBOARD
    }
    
    function summary_info(){
        //Show resumen
        if ( $this->oauth3_enabled ){
            $this->getResumen();
        }
        //Show Authentication URL to AOUTH3
        if ( ! $this->oauth3_enabled ){
            $this->oauth3_url = $this->getOauth3Url();
            include_once( 'templates/nimble-oauth-form.php' );
        }
    }
    
    function dashboard_widget(){
        //Show resumen
        if ( $this->oauth3_enabled ){
            $this->getDashboardInfo();
        }
        //Show Authentication URL to AOUTH3
        if ( ! $this->oauth3_enabled ){
            $this->oauth3_url = $this->getOauth3Url();
            include_once( 'templates/nimble-oauth-form.php' );
        }
    }
    
}


/**
 * Check if WooCommerce is active (including WordPress Multisite)
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) 
    || ( is_multisite() && array_key_exists( 'woocommerce/woocommerce.php', get_site_option( 'active_sitewide_plugins') ) ) ){
    $oWoocommerceNimblePayments = Woocommerce_Nimble_Payments::getInstance();
}
register_activation_hook(__FILE__, array('Woocommerce_Nimble_Payments', 'activar_plugin'));
register_deactivation_hook(__FILE__, array( 'Woocommerce_Nimble_Payments', 'desactivar_plugin'));
