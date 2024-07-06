<?php 

class Payu_Webhook_Calls {

    protected $scope = '';
	
	protected $currency1_payu_salt;

    public function __construct() {
       
    add_action('rest_api_init',array(&$this,'get_payment_success_update'));

	add_action('rest_api_init',array(&$this,'get_payment_failed_update'));

	$plugin_data = get_option('woocommerce_payubiz_settings');
	$this->currency1_payu_salt = sanitize_text_field($plugin_data['currency1_payu_salt']);

    }

    
	public function get_payment_success_update() {
		register_rest_route('payu/v1', '/get-payment-success-update', array(
		'methods' => ['POST'],
		'callback' => array($this,'payu_get_payment_success_update_callback'),
			'permission_callback' => '__return_true'
		));
	}

	public function payu_get_payment_success_update_callback(WP_REST_Request $request) {
		$parameters = $request->get_body();
		parse_str($parameters, $response_data);
		$this->payu_order_status_update($response_data);
	}

	public function get_payment_failed_update() {
		register_rest_route('payu/v1', '/get-payment-failed-update', array(
		'methods' => ['POST'],
		'callback' => array($this,'payu_get_payment_failed_update_callback'),
		'permission_callback' => '__return_true'
		));
	}

	public function payu_get_payment_failed_update_callback(WP_REST_Request $request) {
		$parameters = $request->get_body();
		parse_str($parameters, $response_data);
		$this->payu_order_status_update($response_data);
	}

	private function payu_order_status_update($response){
		global $table_prefix, $wpdb;
		$txnid = $response['txnid'];
		$payu_salt = $this->currency1_payu_salt;
		$payu_key = $response['key'];
		$order_id = explode('_',$response['txnid'])[0];
		$amount      		= 	$response['amount'];
		$productInfo  		= 	$response['productinfo'];
		$firstname    		= 	$response['firstname'];
		$email        		=	$response['email'];
		$udf5				=   $response['udf5'];
		$order 				= 	wc_get_order($order_id);
		if($order){
			$additionalCharges 	= 	0;
			if (isset($response["additionalCharges"])) $additionalCharges = $response['additionalCharges'];
			$keyString 	  		=  	$payu_key . '|' . $txnid . '|' . $amount . '|' . $productInfo . '|' . $firstname . '|' . $email . '|||||' . $udf5 . '|||||';
			$keyArray 	  		= 	explode("|", $keyString);
			$reverseKeyArray 	= 	array_reverse($keyArray);
			$reverseKeyString	=	implode("|", $reverseKeyArray);
			if ($order_id && isset($response['status']) && $response['status'] == 'success') {
				$saltString     = $payu_salt . '|' . $response['status'] . '|' . $reverseKeyString;
					if ($additionalCharges > 0)
						$saltString     = $additionalCharges . '|' . $payu_salt . '|' . $response['status'] . '|' . $reverseKeyString;

				$sentHashString = strtolower(hash('sha512', $saltString));
				$responseHashString = $response['hash'];
				if ($sentHashString == $responseHashString){
					if(!is_array($response['transaction_offer'])){
						$transaction_offer = json_decode(str_replace('\"','"',$response['transaction_offer']),true);
					} else {
						$transaction_offer = $response['transaction_offer'];
					}
					
					if (isset($response["discount"]) && isset($transaction_offer['offer_data']) && is_array($transaction_offer['offer_data'])) {
			
						foreach ($transaction_offer['offer_data'] as $offer_data) {
							if ($offer_data['status'] == 'SUCCESS') {
								$offer_title = $offer_data['offer_title'];
								$discount = $offer_data['discount'];
								wc_update_order_add_discount($order, $offer_title, $discount);
								$offer_key = $offer_data['offer_key'];
								$offer_type = $offer_data['offer_type'];
								$order->update_meta_data('payu_offer_key', $offer_key);
								$order->update_meta_data('payu_offer_type', $offer_type);
							}
						}
					}
					error_log("webhook marked payment completed ".$order_id);
					$order -> payment_complete($txnid);								
					$order -> add_order_note(esc_html__( 'PayUBiz has processed the payment. Ref Number: '.$response['mihpayid'],'payubiz' ));
					$order -> add_order_note($this->msg['message']);
					$order -> add_order_note('Paid by PayUBiz');
				} 
			} else {
				$this->msg['class'] = 'error';
				$this->msg['message'] = esc_html__( 'Thank you for shopping with us. However, the payment failed' );
				$order -> update_status('failed');
				error_log("webhook marked order failed ".$order_id);
				$order -> add_order_note('Failed');
				$order -> add_order_note($this->msg['message']);
			}
		} else {
			error_log("webhook order not found ".json_encode($response));
		}
		
		
		
	}
    

    
}
New Payu_Webhook_Calls();