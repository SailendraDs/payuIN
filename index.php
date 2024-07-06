<?php
/*
Plugin Name: PayU India
Plugin URI: https://payu.in/
Description: Extends WooCommerce with PayU.
Version: 4.1.0
Author: PayU
Author URI: https://payu.in/
Copyright: © 2023, PayU. All rights reserved.
*/
if ( ! defined( 'ABSPATH' ) )
{
    exit; // Exit if accessed directly
}

add_action('plugins_loaded', 'woocommerce_payubiz_init', 0);

require_once(plugin_dir_path(__FILE__) . 'includes/class-payu-verify-payment.php');

require_once(plugin_dir_path(__FILE__) . 'includes/admin/payu-webhook-calls.php');


function woocommerce_payubiz_init() {

	
  if ( !class_exists( 'WC_Payment_Gateway' ) ) return;  
 
  /**
   * Localisation
   */
   
  if(isset($_GET['msg'])){
	if(sanitize_text_field($_GET['msg'])!='')
		add_action('the_content', 'showpayubizMessage');
  }
  
  function showpayubizMessage($content){
    return '<div class="box '.sanitize_text_field($_GET['type']).'-box">'.esc_html__(sanitize_text_field($_GET['msg']),'payubiz').'</div>'.$content;
  }
  /**
   * Gateway class
   */
  class WC_Payubiz extends WC_Payment_Gateway {
    protected $msg = array();
	
	protected $logger;

	protected $bypass_verify_payment;
	
    public function __construct($receiptPageFlag = true){
		global $wpdb;
      // Go wild in here	  
      $this -> id = 'payubiz';
      $this -> method_title = __('PayUBiz', 'payubiz');	  
      $this -> icon = plugins_url('images/payubizlogo.png',__FILE__);
      $this -> has_fields = false;
      $this -> init_form_fields();
      $this -> init_settings();
      $this -> title = 'PayUBiz'; //$this -> settings['title'];
      $this -> description = sanitize_text_field($this -> settings['description']);
      $this -> gateway_module = sanitize_text_field($this -> settings['gateway_module']);
      $this -> redirect_page_id = sanitize_text_field($this -> settings['redirect_page_id']);
	  $this -> payment_gateway_options = sanitize_text_field($this -> settings['payment_gateway_options']);
	  $this -> currency1 = sanitize_text_field($this -> settings['currency1']);	
	  $this -> currency1_payu_key = sanitize_text_field($this -> settings['currency1_payu_key']);
	  $this -> currency1_payu_salt = sanitize_text_field($this -> settings['currency1_payu_salt']);	  

	  $this -> currency2 = sanitize_text_field($this -> settings['currency2']);	
	  $this -> currency2_payu_key = sanitize_text_field($this -> settings['currency2_payu_key']);
	  $this -> currency2_payu_salt = sanitize_text_field($this -> settings['currency2_payu_salt']);	  

	  $this -> currency3 = sanitize_text_field($this -> settings['currency3']);	
	  $this -> currency3_payu_key = sanitize_text_field($this -> settings['currency3_payu_key']);
	  $this -> currency3_payu_salt = sanitize_text_field($this -> settings['currency3_payu_salt']);	  
	  
	  $this -> currency4 = sanitize_text_field($this -> settings['currency4']);	
	  $this -> currency4_payu_key = sanitize_text_field($this -> settings['currency4_payu_key']);
	  $this -> currency4_payu_salt = sanitize_text_field($this -> settings['currency4_payu_salt']);
	  
	  $this -> currency5 = sanitize_text_field($this -> settings['currency5']);
	  $this -> currency5_payu_key = sanitize_text_field($this -> settings['currency5_payu_key']);
	  $this -> currency5_payu_salt = sanitize_text_field($this -> settings['currency5_payu_salt']);

	  $this -> currency6 = sanitize_text_field($this -> settings['currency6']);
	  $this -> currency6_payu_key = sanitize_text_field($this -> settings['currency6_payu_key']);
	  $this -> currency6_payu_salt = sanitize_text_field($this -> settings['currency6_payu_salt']);
	  
	  $this -> currency7 = sanitize_text_field($this -> settings['currency7']);
	  $this -> currency7_payu_key = sanitize_text_field($this -> settings['currency7_payu_key']);
	  $this -> currency7_payu_salt = sanitize_text_field($this -> settings['currency7_payu_salt']);
	  
	  $this -> currency8 = sanitize_text_field($this -> settings['currency8']);
	  $this -> currency8_payu_key = sanitize_text_field($this -> settings['currency8_payu_key']);
	  $this -> currency8_payu_salt = sanitize_text_field($this -> settings['currency8_payu_salt']);
	  
	  $this -> currency9 = sanitize_text_field($this -> settings['currency9']);
	  $this -> currency9_payu_key = sanitize_text_field($this -> settings['currency9_payu_key']);
	  $this -> currency9_payu_salt = sanitize_text_field($this -> settings['currency9_payu_salt']);
	  
	  $this -> currency10 = sanitize_text_field($this -> settings['currency10']);
	  $this -> currency10_payu_key = sanitize_text_field($this -> settings['currency10_payu_key']);
	  $this -> currency10_payu_salt = sanitize_text_field($this -> settings['currency10_payu_salt']);
	  
	  $this->bypass_verify_payment=false;
	  
	  if(sanitize_text_field($this -> settings['verify_payment'])!="yes")
		$this->bypass_verify_payment=true;
	
	  $this -> msg['message'] = "";
      $this -> msg['class'] = "";
	
		
      add_action('init', array(&$this, 'check_payubiz_response'));
      //update for woocommerce >2.0
      add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_payubiz_response' ) );

      add_action('valid-payubiz-request', array(&$this, 'SUCCESS'));
	  
	  if (!has_action('woocommerce_receipt_payubiz', array(&$this, 'receipt_page')) && $receiptPageFlag) {
	  add_action('woocommerce_receipt_payubiz', array(&$this, 'receipt_page'));
	  }

	  //add_action('woocommerce_thankyou_payubiz',array($this, 'thankyou')); 	  
  
      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      } else {
        add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
      }

	  add_filter('woocommerce_get_order_item_totals', array(&$this, 'add_custom_order_total_row'), 10, 2);

	  $this->logger = wc_get_logger();
    }
    
	/**
	* Session patch CSRF Samesite=None; Secure
	**/
	function manage_session()
	{
		$context = array( 'source' => $this->id );
		try
		{
			if(PHP_VERSION_ID >= 80200)
			{
				$options = session_get_cookie_params();  
				$options['samesite'] = 'None';
				$options['secure'] = true;
				unset($options['lifetime']); 
				$cookies = $_COOKIE;  	
				foreach ($cookies as $key => $value)
				{
					if (!preg_match('/cart/', sanitize_key($key)))
						setcookie(sanitize_key($key), sanitize_text_field($value), $options);
				}
			}
			else {
				$this->logger->error( "PayU payment plugin does not support this PHP version for cookie management. 
				Required PHP v8.1 or higher.", $context );
			}
		}
		catch(Exception $e) {
			$this->logger->error( $e->getMessage(), $context );
		}
	}
	
	
    function init_form_fields(){

	$site_url = get_site_url();
	$payu_payment_success_webhook_url = $site_url.'/wp-json/payu/v1/get-payment-success-update';
	$payu_payment_failed_webhook_url = $site_url.'/wp-json/payu/v1/get-payment-failed-update';

      $this -> form_fields = array(
        'enabled' => array(
            'title' => __('Enable/Disable', 'payubiz'),
            'type' => 'checkbox',
						'label' => __('Enable PayUBiz', 'payubiz'),
            'default' => 'no'),
		  'description' => array(
			'title' => __('Description:', 'payubiz'),
			'type' => 'textarea',
			'description' => __('This controls the description which the user sees during checkout.', 'payubiz'),
			'default' => __('Pay securely by Credit or Debit card or net banking through PayUBiz.', 'payubiz')),
          'gateway_module' => array(
            'title' => __('Gateway Mode', 'payubiz'),
            'type' => 'select',
            'options' => array("0"=>"Select","sandbox"=>"Sandbox","production"=>"Production"),
            'description' => __('Mode of gateway subscription.','payubiz')
            ),
			'payment_gateway_options' => array(
				'title' => __('Payment Gateway Method'),
				'type' => 'select',
				'options' => array('payu_redirect' => 'PayU Redirect','bolt' => 'Bolt'),
				'description' => "Payment Gateway Method Options."
			),
		  'enable_webhook' => array(
				'title' => __('Webhoook URLs', 'payubiz'),
				'type' => 'hidden',
				'description' => __('Please add the following URLs to the PayU dashboard webhook settings:<br> <span style="font-weight:700;">Success URL:</span> '.$payu_payment_success_webhook_url.'<br> <span style="font-weight:700;">Failed URL:</span> '.$payu_payment_failed_webhook_url,'payubiz'),
			),
		  'currency1' => array(
            'title' => __('Currency 1', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 1 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency1_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 1', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency1_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 1', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'currency2' => array(
            'title' => __('Currency 2', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 2 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency2_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 2', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency2_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 2', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		   'currency3' => array(
            'title' => __('Currency 3', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 3 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency3_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 3', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency3_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 3', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'currency4' => array(
            'title' => __('Currency 4', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 4 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency4_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 4', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency4_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 4', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'currency5' => array(
            'title' => __('Currency 5', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 5 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency5_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 5', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency5_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 5', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'currency6' => array(
            'title' => __('Currency 6', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 6 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency6_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 6', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency6_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 6', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'currency7' => array(
            'title' => __('Currency 7', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 7 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency7_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 7', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency7_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 7', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		   'currency8' => array(
            'title' => __('Currency 8', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 8 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency8_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 8', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency8_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 8', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'currency9' => array(
            'title' => __('Currency 9', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 9 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency9_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 9', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency9_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 9', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'currency10' => array(
            'title' => __('Currency 10', 'payubiz'),
            'type' => 'text',
            'description' =>  __('Currency Code 10 as configured in multi-currency plugin.', 'payubiz')
            ),
		  'currency10_payu_key' => array(
            'title' => __('PayUBiz Key for Currency 10', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant key.', 'payubiz')
            ),
		  'currency10_payu_salt' => array(
            'title' => __('PayUBiz Salt for Currency 10', 'payubiz'),
            'type' => 'text',
            'description' =>  __('PayUBiz merchant salt.', 'payubiz')
            ),
		  'verify_payment' => array(
            'title' => __('Verify Payment', 'payubiz'),
            'type' => 'select',
            'options' => array("0"=>"Select","yes"=>"Yes","no"=>"No"),
            'description' => __('Verify Payment at server.','payubiz')
            ),
          'redirect_page_id' => array(
            'title' => __('Return Page'),
            'type' => 'select',
            'options' => $this -> get_pages('Select Page'),
            'description' => "Post payment redirect URL for which payment is not successful."
            )
		  );
    }
    
    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     **/
    public function admin_options(){
      echo '<h3>'.esc_html__('PayUBiz payment', 'payubiz').'</h3>';
      echo '<p>'.esc_html__('PayUBiz most popular payment gateways for online shopping.','payubiz').'</p>';	  
	  if(PHP_VERSION_ID < 80200)
		  echo "<h1 style=\"color:red;\">".esc_html__('**Notice: PayU payment plugin requires PHP v8.1 or higher.<br />
		  Plugin will not work properly below PHP v7.3 due to SameSite cookie restriction.','payubiz')."</h1>";
      echo '<table class="form-table">';
      $this -> generate_settings_html();
      echo '</table>';
	  
    }
		
    /**
     *  There are no payment fields for Citrus, but we want to show the description if set.
     **/
    function payment_fields(){
		if($this -> description) echo wpautop(wptexturize($this -> description));
    }
		
    /**
     * Receipt Page
     **/
    function receipt_page($order){
		$this->manage_session(); //Update cookies with samesite 
		echo '<p>'.esc_html__( 'Thank you for your order, please wait as you will be automatically redirected to PayUBiz.', 'payubiz' ).'</p>';
		echo $this -> generate_payubiz_form($order);
    }
    
    /**
     * Process the payment and return the result
     **/   
     function process_payment($order_id){
            $order = new WC_Order($order_id);

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $order->id,
                        add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)))
                );
            }
            else {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $order->id,
                        add_query_arg('key', $order->get_order_key(), get_permalink(get_option('woocommerce_pay_page_id'))))
                );
            }
        }
    /**
     * Check for valid PayU server callback
     **/    
    function check_payubiz_response()
	{      
		global $woocommerce;
		
		$payu_key = '';
		$payu_salt = '';
		$currency = '';
			
	  if ( isset( $_GET['wc-api'] ) ) {
		if( sanitize_text_field( $_GET['wc-api'] ) == get_class( $this ) ) 
		{
			if(isset($_POST['payu_resp'])){
				$_POST = json_decode(stripslashes($_POST['payu_resp']),true);
				}
			$postdata = array();
			//sanitize entire response
			foreach( $_POST as $key=>$val )
			{
				if ($key == 'transaction_offer' || $key == 'cart_details' || $key == 'shipping_address') {
					$postdata[$key] = $val;
				} else {
					$postdata[$key] = sanitize_text_field($val);
				}
			}
			if( isset( $postdata['key'] ) )
			{
				switch($postdata['key'])
				{
					case $this->currency1_payu_key:
						$currency= $this->currency1;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency1_payu_salt;
						break;
					case $this->currency2_payu_key:
						$currency= $this->currency2;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency2_payu_salt;
						break;
					case $this->currency3_payu_key:
						$currency= $this->currency3;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency3_payu_salt;
						break;
					case $this->currency4_payu_key:
						$currency= $this->currency4;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency4_payu_salt;
						break;
					case $this->currency5_payu_key:
						$currency= $this->currency5;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency5_payu_salt;
						break;
					case $this->currency6_payu_key:
						$currency= $this->currency6;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency6_payu_salt;
						break;
					case $this->currency7_payu_key:
						$currency= $this->currency7;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency7_payu_salt;
						break;
					case $this->currency8_payu_key:
						$currency= $this->currency8;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency8_payu_salt;
						break;
					case $this->currency9_payu_key:
						$currency= $this->currency9;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency9_payu_salt;
						break;
					case $this->currency10_payu_key:
						$currency= $this->currency10;
						$payu_key = $postdata['key'];
						$payu_salt = $this->currency10_payu_salt;
						break;
					default:
						break;
				}	
				
				$txnid = $postdata['txnid'];
    	    	$order_id = explode('_', $txnid);
				$order_id = (int)$order_id[0];    //get rid of time part
				
				$order = new WC_Order($order_id);
				update_post_meta($order_id,'order_txnid',$txnid);
				$order->update_meta_data('payu_bankcode', $postdata['bankcode']);
				$order->update_meta_data('payu_mode', $postdata['mode']);
				$order_currency = sanitize_text_field($order->get_currency());
				error_log('payu awaiting id confirm  '.WC()->session->get('orderid_awaiting_payubiz'));	
				if ($postdata['key'] == $payu_key && $currency == $order_currency) {
					error_log('payu awaiting id confirm');
					WC()->session->set( 'orderid_awaiting_payubiz', '' );
					$amount      		= 	$postdata['amount'];
					$productInfo  		= 	$postdata['productinfo'];
					$firstname    		= 	$postdata['firstname'];
					$email        		=	$postdata['email'];
					$udf5				=   $postdata['udf5'];
					$additionalCharges 	= 	0; 
					If (isset($postdata["additionalCharges"])) $additionalCharges = $postdata['additionalCharges'];
								
					$keyString 	  		=  	$payu_key.'|'.$txnid.'|'.$amount.'|'.$productInfo.'|'.$firstname.'|'.$email.'|||||'.$udf5.'|||||';
					$keyArray 	  		= 	explode("|",$keyString);
					$reverseKeyArray 	= 	array_reverse($keyArray);
					$reverseKeyString	=	implode("|",$reverseKeyArray);
						
					if (isset($postdata['status']) && $postdata['status'] == 'success') {
						error_log('payu success status');
						$saltString     = $payu_salt.'|'.$postdata['status'].'|'.$reverseKeyString;					
						if($additionalCharges > 0)
							$saltString     = $additionalCharges.'|'.$payu_salt.'|'.$postdata['status'].'|'.$reverseKeyString;
					
						$sentHashString = strtolower(hash('sha512', $saltString));
						$responseHashString=$postdata['hash'];
				
						$this -> msg['class'] = 'error';
						$this -> msg['message'] = esc_html__('Thank you for shopping with us. However, the transaction has been declined.','payubiz');

						if( $sentHashString == $responseHashString && $this->verify_payment( $order, $txnid, $payu_key, $payu_salt, $this->bypass_verify_payment ) )
						{						
							error_log('payu verified status');
							$this -> msg['message'] = esc_html__('Thank you for shopping with us. Your account has been charged and your transaction is successful with following order details:','payubiz');
							$this -> msg['message'] .='<br>'.esc_html__('Order Id:'. $order_id,'payubiz').'<br/>'.esc_html__('Amount:'. $amount,'payubiz').'<br />'.esc_html__('We will be shipping your order to you soon.','payubiz');
						
							if($additionalCharges > 0)
								$this -> msg['message'] .= '<br /><br />'.esc_html__('Additional amount charged by PayUBiz - '.$additionalCharges,'payubiz');
										
							$this -> msg['class'] = 'success';
								
							if($order -> status == 'processing' || $order -> status == 'completed' )
							{
								//do nothing
							}
							else
							{	
								// echo '<pre>';
								// print_r($postdata['transaction_offer']);
								// die;
								error_log('offer data '.serialize($postdata['transaction_offer']));
								if(!is_array($postdata['transaction_offer'])){
									$transaction_offer = json_decode(str_replace('\"','"',$postdata['transaction_offer']),true);
								} else {
									$transaction_offer = $postdata['transaction_offer'];
								}
								
								if (isset($postdata["discount"]) && isset($transaction_offer['offer_data']) && is_array($transaction_offer['offer_data'])) {
						
									foreach ($transaction_offer['offer_data'] as $offer_data) {
										if ($offer_data['status'] == 'SUCCESS') {
											$offer_title = $offer_data['offer_title'];
											$discount = $offer_data['discount'];
											if($offer_data['offer_type'] != 'CASHBACK'){
											wc_update_order_add_discount($order, $offer_title, $discount);
											}
											$offer_key = $offer_data['offer_key'];
											$offer_type = $offer_data['offer_type'];
											$order->update_meta_data('payu_offer_key', $offer_key);
											$order->update_meta_data('payu_offer_type', $offer_type);
										}
									}
								}
								
								//complete the order
								$order -> payment_complete($txnid);				
								$order -> add_order_note(esc_html__( 'PayUBiz has processed the payment. Ref Number: '.$postdata['mihpayid'],'payubiz' ));
								$order -> add_order_note($this->msg['message']);
								$order -> add_order_note('Paid by PayUBiz');
								$woocommerce -> cart -> empty_cart();
							}
						
						}
						else {
							//tampered
							error_log('payu verified status error');
							$this->msg['class'] = 'error';
							$this->msg['message'] = esc_html__( 'Thank you for shopping with us. However, the payment failed' );
							$order -> update_status('failed');
							$order -> add_order_note('Failed');
							$order -> add_order_note($this->msg['message']);						
						}
					} else {
						$this -> msg['class'] = 'error';
						$this -> msg['message'] = esc_html__( 'Thank you for shopping with us. However, the transaction has been declined.','payubiz' );							
						
						//Here you need to put in the routines for a failed
						//transaction such as sending an email to customer
						//setting database status etc etc			
					} 
				}
			}
		
		}
		
		//manage msessages
		if (function_exists('wc_add_notice')) {
			wc_clear_notices();			
			if($this->msg['class']!='success'){
				wc_add_notice( $this->msg['message'], $this->msg['class'] );
			}
		}
		else {
			if($this->msg['class']!='success'){
				$woocommerce->add_error($this->msg['message']);				
			}
			else{
				//$woocommerce->add_message($this->msg['message']);
			}
			$woocommerce->set_messages();
		}
			
		$redirect_url = ($this ->redirect_page_id=='' || $this -> redirect_page_id==0)?get_site_url() . '/':get_permalink($this -> redirect_page_id);
		if($order && $this->msg['class'] == 'success') 
			$redirect_url = $order->get_checkout_order_received_url();
		
		//For wooCoomerce 2.0
		//$redirect_url = add_query_arg( array('msg'=> urlencode($this -> msg['message']), 'type'=>$this -> msg['class']), $redirect_url );
		wp_redirect( $redirect_url );
		exit;
	  }
    }
    
	// Adding Meta container admin shop_order pages
	private function verify_payment($order,$txnid,$payu_key,$payu_salt,$bypass=false)
    {
        global $woocommerce;
		
		if($bypass) return true; //bypass verification
		
		try
		{
			$datepaid = $order->get_date_paid();
			$fields = array(
				'key' => sanitize_key($payu_key),
				'command' => 'verify_payment',
				'var1' => $txnid,
				'hash' => ''
			);
				
			$hash = hash("sha512", $fields['key'].'|'.$fields['command'].'|'.$fields['var1'].'|'.$payu_salt );
			$fields['hash'] = sanitize_text_field($hash);
			//$fields_string = http_build_query($fields);
			$url = esc_url('https://info.payu.in/merchant/postservice.php?form=2');
			if( $this -> gateway_module == 'sandbox' )
				$url = esc_url("https://test.payu.in/merchant/postservice.php?form=2");	
			
			$args = array(
				'body' => $fields,
				'timeout' => '5',
				'redirection' => '5',
				'httpversion' => '1.1',
				'blocking'    => true,
				'headers'     => array(),
				'cookies'     => array(),
			);
			
			$response = wp_remote_post( $url, $args );
			
			if($response && !isset($response['body']))			
				return false;			
			else {
				$res = json_decode(sanitize_text_field($response['body']),true);	
				if(!isset($res['status']))
					return false;
				else{
					$res = $res['transaction_details'];
					$res = $res[$txnid];						
					error_log('verify payment'.$response['body'] );
					if(sanitize_text_field($res['status']) == 'success')	
						return true;					
					elseif(sanitize_text_field($res['status']) == 'pending' || sanitize_text_field($res['status']) == 'failure')
						return false;
				}
			}			
		}
		catch (Exception $e)
		{
			return false;	
		}
    }
    
    
    /*
     //Removed For WooCommerce 2.0
    function showMessage($content){
         return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
     }*/
    
    /**
     * Generate PayUBiz button link
     **/    
    public function generate_payubiz_form($order_id){
      
		global $woocommerce;
		$payu_key="";
		$payu_salt="";
		$site_url = get_site_url();
		
		$order = new WC_Order($order_id);
		
		$order_currency = sanitize_text_field($order->get_currency());
		switch($order_currency)
		{
			case $this->currency1:
				$payu_key = $this->currency1_payu_key;
				$payu_salt = $this->currency1_payu_salt;
				break;
			case $this->currency2:
				$payu_key = $this->currency2_payu_key;
				$payu_salt = $this->currency2_payu_salt;
				break;
			case $this->currency3:
				$payu_key = $this->currency3_payu_key;
				$payu_salt = $this->currency3_payu_salt;
				break;
			case $this->currency4:
				$payu_key = $this->currency4_payu_key;
				$payu_salt = $this->currency4_payu_salt;
				break;
			case $this->currency5:
				$payu_key = $this->currency5_payu_key;
				$payu_salt = $this->currency5_payu_salt;
				break;
			case $this->currency6:
				$payu_key = $this->currency6_payu_key;
				$payu_salt = $this->currency6_payu_salt;
				break;
			case $this->currency7:
				$payu_key = $this->currency7_payu_key;
				$payu_salt = $this->currency7_payu_salt;
				break;
			case $this->currency8:
				$payu_key = $this->currency8_payu_key;
				$payu_salt = $this->currency8_payu_salt;
				break;
			case $this->currency9:
				$payu_key = $this->currency9_payu_key;
				$payu_salt = $this->currency9_payu_salt;
				break;
			case $this->currency10:
				$payu_key = $this->currency10_payu_key;
				$payu_salt = $this->currency10_payu_salt;
				break;
			default:
				break;
		}
		$redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
      
		//For wooCoomerce 2.0
		$redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );	
		WC()->session->set( 'orderid_awaiting_payubiz', $order_id );
		$txnid = $order_id.'_'.date("ymd").':'.rand(1,100);
		update_post_meta($order_id,'order_txnid',$txnid);
		//do we have a phone number?
		//get currency      
		$address = sanitize_text_field($order -> billing_address_1);
		if ($order -> billing_address_2 != "")
			$address = $address.' '.sanitize_text_field($order -> billing_address_2);
		
		$productInfo='';
		foreach ($order->get_items() as $item ) {
			$product = wc_get_product($item->get_product_id());
			$productInfo .= $product->get_sku().':';
		}
		$productInfo=rtrim($productInfo,':');
		if('' == $productInfo)
			$productInfo = "Product Information";
		elseif(100 < strlen($productInfo))
			$productInfo=substr($productInfo,0,100);
			
		$action = esc_url('https://secure.payu.in/_payment');
		$boltendpoint = 'https://apitest.payu.in/js-sdks/bolt.min.js';
			
		if('sandbox' == $this->gateway_module )
			$action = esc_url('https://test.payu.in/_payment');
			$boltendpoint = 'https://apitest.payu.in/js-sdks/bolt.min.js';
			
		$amount = sanitize_text_field($order -> order_total);		
		$firstname = sanitize_text_field($order -> billing_first_name);
		$lastname = sanitize_text_field($order -> billing_last_name);
		$zipcode = sanitize_text_field($order -> billing_postcode);
		$email = sanitize_email($order -> billing_email);
		$phone = sanitize_text_field($order -> billing_phone);			
        $state = sanitize_text_field($order -> billing_state);
        $city = sanitize_text_field($order -> billing_city);
        $country = sanitize_text_field($order -> billing_country);
		$Pg = '';
		$udf5 = 'WooCommerce_v_3.8.1';
		$hash=hash('sha512', $payu_key.'|'.$txnid.'|'.$amount.'|'.$productInfo.'|'.$firstname.'|'.$email.'|||||'.$udf5.'||||||'.$payu_salt); 
			
		if($this->payment_gateway_options == 'bolt'){
			$html = "<form method='post' action='$redirect_url' id='payu_bolt_form'>
			<input type='hidden' name='payu_resp'>
			</form>
			";
		$requestArr = [
			'key' => $payu_key,
			'Hash' => $hash,
			'txnid' => $txnid,
			'amount' => $amount,
			'firstname' => $firstname,
			'Lastname' => $lastname,
			'email' => $email,
			'phone' => $phone,
			'productinfo' => $productInfo,
			'udf5' => $udf5,
			'surl' => $site_url,
			'furl' => $site_url,
			'enforce_paymethod' => 'creditcard|debitcard|UPI|cashcard|SODEXO|qr|emi|neftrtgs|HDFB|AXIB'
		];
			?>
			<script type='text/javascript' src="<?php echo $boltendpoint; ?>"></script>
		<script type='text/javascript'>
			function boltSubmit()
			{
				var data = <?php echo json_encode($requestArr,JSON_UNESCAPED_SLASHES); ?>;
				var handlers = {responseHandler: function (BOLT) {
                        if (BOLT.response.txnStatus == "FAILED") {
                           console.log('Payment failed. Please try again.');
                        }
                        if(BOLT.response.txnStatus == "CANCEL"){
                           console.log('Payment failed. Please try again.');
                        }
						var payu_frm = document.getElementById('payu_bolt_form');
						payu_frm.action = '<?php echo $redirect_url; ?>';
							payu_frm.elements.namedItem('payu_resp').value = JSON.stringify(BOLT.response);
							payu_frm.submit();
                    },
                    catchException: function (BOLT) {
                        console.log('Payment failed. Please try again.');
                    }};
                bolt.launch( data , handlers );
				//return false;
			}		
			boltSubmit();
		</script>
			<?php
		} else {
			$html = '<form action="'.$action .'" method="post" id="payu_form" name="payu_form">
				<input type="hidden" name="key" value="'. $payu_key. '" />
				<input type="hidden" name="txnid" value="'.$txnid.'" />
				<input type="hidden" name="amount" value="'.$amount.'" />
				<input type="hidden" name="productinfo" value="'.$productInfo.'" />
				<input type="hidden" name="firstname" value="'. $firstname.'" />
				<input type="hidden" name="Lastname" value="'. $lastname.'" />
				<input type="hidden" name="Zipcode" value="'. $zipcode. '" />
				<input type="hidden" name="email" value="'. $email.'" />
				<input type="hidden" name="phone" value="'.$phone.'" />
				<input type="hidden" name="surl" value="'. esc_url($redirect_url). '" />
				<input type="hidden" name="furl" value="'. esc_url($redirect_url).'" />
				<input type="hidden" name="curl" value="'.esc_url($redirect_url).'" />
				<input type="hidden" name="Hash" value="'.$hash.'" />
				<input type="hidden" name="Pg" value="'. $Pg.'" />						
				<input type="hidden" name="address1" value="'.$address .'" />
		        <input type="hidden" name="address2" value="" />
			    <input type="hidden" name="city" value="'. $city.'" />
		        <input type="hidden" name="country" value="'.$country.'" />
		        <input type="hidden" name="state" value="'. $state.'" />
				<input type="hidden" name="udf5" value="'. $udf5.'" />
		        <button style="display:none" id="submit_payubiz_payment_form" name="submit_payubiz_payment_form">Pay Now</button>
				</form>
				<script type="text/javascript">document.getElementById("payu_form").submit();</script>';
		}
		
		return $html;
    }


	public function add_custom_order_total_row($total_rows, $order)
	{
		if ($total_rows['payment_method']['value'] == 'PayUBiz') {
			$payment_mode['payment_mode'] = array(
				'label' => __('Payment Mode', 'your-text-domain'),
				'value' => $order->get_meta('payu_mode'),
			);
			// $payment_mode['payment_bank_code'] = array(
			// 	'label' => __('Bank Code', 'your-text-domain'),
			// 	'value' => $order->get_meta('payu_bankcode'),
			// );

			// $payu_offer_key = $order->get_meta('payu_offer_key');
			// if ($payu_offer_key) {
			// 	$payment_mode['payment_offer_key'] = array(
			// 		'label' => __('Offer Key', 'your-text-domain'),
			// 		'value' => $payu_offer_key,
			// 	);
			// }

			$payu_offer_type = $order->get_meta('payu_offer_type');
			if ($payu_offer_type) {
				$payment_mode['payment_offer_type'] = array(
					'label' => __('Offer Type', 'your-text-domain'),
					'value' => $payu_offer_type,
				);
			}
			$this->payment_array_insert($total_rows, 'payment_method', $payment_mode);
		}
		return $total_rows;
	}

	private function payment_array_insert(&$array, $position, $insert)
	{
		if (is_int($position)) {
			array_splice($array, $position, 0, $insert);
		} else {
			$pos   = array_search($position, array_keys($array));
			$array = array_merge(
				array_slice($array, 0, $pos),
				$insert,
				array_slice($array, $pos)
			);
		}
	}

	

	public function generatePayuHash($key,$txnid, $amount, $productInfo, $name,
            $email,$udf1,$udf5, $SALT) {
 
        $posted = array(
            'key' => $key,
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productInfo,
            'firstname' => $name,
            'email' => $email,
			'udf1' => $udf1,
			'udf5' => $udf5,
        );
 
        $hashSequence = 'key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|udf6|udf7|udf8|udf9|udf10';
 
        $hashVarsSeq = explode('|', $hashSequence);
        $hash_string = '';
        foreach ($hashVarsSeq as $hash_var) {
            $hash_string .= isset($posted[$hash_var]) ? $posted[$hash_var] : '';
            $hash_string .= '|';
        }
        $hash_string .= $SALT;
 
        return strtolower(hash('sha512', $hash_string));
    }

    function get_pages($title = false, $indent = true) {
      $wp_pages = get_pages('sort_column=menu_order');
      $page_list = array();
      if ($title) $page_list[] = $title;
      foreach ($wp_pages as $page) {
        $prefix = '';
        // show indented child pages?
        if ($indent) {
          $has_parent = $page->post_parent;
          while($has_parent) {
            $prefix .=  ' - ';
            $next_page = get_page($has_parent);
            $has_parent = $next_page->post_parent;
          }
        }
        // add to page list array array
        $page_list[$page->ID] = $prefix . $page->post_title;
      }
      return $page_list;
    }

  }
	 	
	

  /**
   * Add the Gateway to WooCommerce
   **/
  function woocommerce_add_payubiz_gateway($methods) {
    $methods[] = 'WC_Payubiz';
    return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_payubiz_gateway' );

  
	function wc_update_order_add_discount($order, $title, $amount, $tax_class = '')
	{
		global $table_prefix, $wpdb;
		$tblname = 'wc_orders_meta';
		$wp_order_meta_table = $table_prefix . "$tblname";
		
		$subtotal = $order->get_subtotal();
		$optional_fee_exists = false;
		foreach ( $order->get_fees() as $item_fee ) {
			$fee_name = $item_fee->get_name();
			if ( $fee_name == $title ) {
				return;
			}
		}
		$item = new WC_Order_Item_Fee();
		

		if (strpos($amount, '%') !== false) {
			$percentage = (float) str_replace(array('%', ' '), array('', ''), $amount);
			$percentage = $percentage > 100 ? -100 : -$percentage;
			$discount   = $percentage * $subtotal / 100;
		} else {
			$discount = (float) str_replace(' ', '', $amount);
			$discount = $discount > $subtotal ? -$subtotal : -$discount;
		}

		$item->set_tax_class($tax_class);
		$item->set_name($title);
		$item->set_amount($discount);
		$item->set_total($discount);
		
		$item->set_taxes(false);
		$has_taxes = false;
		
		$item->save();
		$item_id = $item->get_id();
		$order->calculate_totals($has_taxes);
		$payu_discount_item_id = $wpdb->get_var("SELECT meta_value FROM $wp_order_meta_table WHERE order_id = '$order->ID' AND meta_key = 'payu_discount_item_id'");
		if($payu_discount_item_id && $payu_discount_item_id != '') { return; }
		$order->update_meta_data('payu_discount_item_id', $item_id);
		$order->add_item($item);
		$order->calculate_totals($has_taxes);
		$order->save();
		
		
		
	}

  
}


/**
 * Custom function to declare compatibility with cart_checkout_blocks feature 
*/
function declare_cart_checkout_blocks_compatibility() {
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        // Declare compatibility for 'cart_checkout_blocks'
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'declare_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'oawoo_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type

 */
function oawoo_register_order_approval_payment_method_type() {
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    // Include the custom Blocks Checkout class
    require_once plugin_dir_path(__FILE__) . 'class-payu-block.php';

    // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            // Register an instance of My_Custom_Gateway_Blocks
            $payment_method_registry->register( new Payu_Gateway_Blocks );
        }
    );
}


?>
