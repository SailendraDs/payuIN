<?php

class Payu_Verify_Payments
{
    protected $gateway_module;

    public function __construct()
    {

        // add the 5-minute interval
        add_filter('cron_schedules', array($this, 'cron_add_one_min'));
        $plugin_data = get_option('woocommerce_payubiz_settings');
        $this->gateway_module = $plugin_data['gateway_module'];
        // add a function to the specified hook
        add_action('check_payment_status_after_every_five_min', array($this, 'verify_payment'), 10, 4);
        add_action('pass_arguments_to_verify', array($this, 'passArgumentstoVerify'), 10, 3);
        add_action('clear_scheduled_task', array($this, 'clearScheduledTask'), 10, 4);

        // run a cron after order creation to check the payment status
        add_action('woocommerce_checkout_order_processed', array($this, 'schedulePaymentStatusCheck'));
        add_action('woocommerce_new_order', array($this, 'schedulePaymentStatusCheck'));
    }

    public function schedulePaymentStatusCheck($order_id)
    {
        date_default_timezone_set('Asia/Kolkata');

        $schedule_time = time() + 60;
        $expiry_time = time() + 2700;
        $order = new WC_Order($order_id);
        $order_new = serialize($order);
        $args = array($order_new, $schedule_time, $expiry_time);

        if (!wp_next_scheduled('pass_arguments_to_verify', $args)) {
            wp_schedule_event($schedule_time, 'every_five_min', 'pass_arguments_to_verify', $args);
        }
    }

    public function passArgumentstoVerify($order, $schedule_time, $expiry_time)
    {

        global $wpdb;

        $order = unserialize($order);
        $method = $order->get_payment_method();
        if ($method == 'payubiz') {
            $order_id = $order->ID;
            error_log("ruk scheduler " . $order_id . ' method ' . $method);
            $order_status  = $order->get_status(); // Get the order status
            $now = time();
            if ($expiry_time <= $now) {
                $this->clearScheduledTask($order, $schedule_time, $expiry_time);
                $order->update_status('failed');
                $order->add_order_note('Failed');
            }


            $plugin_data = get_option('woocommerce_payubiz_settings');
            $payu_key = $plugin_data['currency1_payu_key'];
            $salt_key = $plugin_data['currency1_payu_salt'];
            $txnid = get_post_meta($order_id, 'order_txnid', true);
            if ($txnid) {
                $verify_status = $this->verify_payment($order, $txnid, $payu_key, $salt_key, false);
                error_log('payment status from payu:' . $verify_status);
                if ($verify_status) {
                    //$order->update_status('completed');
                    $order->payment_complete($txnid);
                    $order->add_order_note(esc_html__('PayUBiz has processed the payment.'));
                    $order_new = serialize($order);
                    $this->clearScheduledTask($order_new, $schedule_time, $expiry_time);
                } else {
                    error_log("verify failed " . $txnid);
                }
            }
        } else {
            $this->clearScheduledTask($order, $schedule_time, $expiry_time);
        }
    }

    public function clearScheduledTask($order, $schedule_time, $expiry_time)
    {
        $args = array($order, $schedule_time, $expiry_time);
        wp_clear_scheduled_hook('pass_arguments_to_verify', $args);
    }

    function cron_add_one_min($schedules)
    {
        $schedules['every_five_min'] = array(
            'interval' => 60,
            'display' => 'Five Minutes'
        );
        return $schedules;
    }

    // Adding Meta container admin shop_order pages
    private function verify_payment($order, $txnid, $payu_key, $payu_salt, $bypass = false)
    {
        global $table_prefix, $wpdb;
        $tblname = 'wc_orders_meta';
        $wp_order_meta_table = $table_prefix . "$tblname";
        if ($bypass) return true; //bypass verification

        try {
            $datepaid = $order->get_date_paid();
            $fields = array(
                'key' => sanitize_key($payu_key),
                'command' => 'verify_payment',
                'var1' => $txnid,
                'hash' => ''
            );
            $hash = hash("sha512", $fields['key'] . '|' . $fields['command'] . '|' . $fields['var1'] . '|' . $payu_salt);
            $fields['hash'] = sanitize_text_field($hash);
            //$fields_string = http_build_query($fields);
            $url = esc_url('https://info.payu.in/merchant/postservice.php?form=2');
            if ($this->gateway_module == 'sandbox')
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

            $response = wp_remote_post($url, $args);
            error_log('verify api response' . $response['body']);

            if (!isset($response['body']))
                return false;
            else {
                $res = json_decode(sanitize_text_field($response['body']), true);
                if (!isset($res['status']))
                    return false;
                else {
                    $res = $res['transaction_details'];
                    $res = $res[$txnid];
                    $payu_discount_item_id = $wpdb->get_var("SELECT meta_value FROM $wp_order_meta_table WHERE order_id = '$order->ID' AND meta_key = 'payu_discount_item_id'");

                    if (sanitize_text_field($res['status']) == 'success') {
                        if (!$payu_discount_item_id) {
                            if (!is_array($res['transaction_offer'])) {
                                $transaction_offer = json_decode(str_replace('\"', '"', $res['transaction_offer']), true);
                            } else {
                                $transaction_offer = $res['transaction_offer'];
                            }
                            if ($transaction_offer && isset($transaction_offer['offer_data']) && is_array($transaction_offer['offer_data'])) {

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
                            } elseif (isset($res['disc']) && $res['disc'] > 0) {

                                wc_update_order_add_discount($order, 'Payu Offer', $res['disc']);
                            }
                        }
                        return true;
                    } elseif (sanitize_text_field($res['status']) == 'pending' || sanitize_text_field($res['status']) == 'failure') {
                        return false;
                    }
                }
            }
        } catch (Exception $e) {
            error_log('catch error' . $e->getMessage());
            return false;
        }
    }
}
new Payu_Verify_Payments();
