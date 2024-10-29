<?php
class Akouendy_Pay_Api {

    public function __construct($settings,$base_url,$provider) {
        $this->base_url = $base_url;
        $this->provider = $provider;
        $this->settings = $settings;
    }
    public static function create() {
        return new self();
    }


    function init_payment($order,$lang,$partner) {
        $eep=explode('-',$lang);
        $lang = strtolower($eep[0]);
        $data = $this->get_init_payment_args($order,$this->settings['webhook']);
        $args = array(
            'body' =>  json_encode($data),
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'method'      => 'POST',
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8','partner' => $partner),
            'cookies' => array()
        );
        $base_url  = WC_AKOUENDY_PAY_BASE_URL ;
        if($this->settings['sandbox'] == "yes") {
            $base_url  = WC_AKOUENDY_PAY_SANDBOX_BASE_URL ;
        }
        $request_url = $base_url  . WC_AKOUENDY_PAY_POST_INIT . "?lang=$lang" ;
        error_log('WC_AKOUENDY_PAY_POST_INIT: ' .  $request_url);
        $request = wp_remote_post( $request_url,$args);

        error_log('initiating payment wp_remote_post' . print_r($args,true));
        $this->akouendy_debug($args);
        if( is_wp_error( $request ) ) {
            error_log('WC_AKOUENDY_PAY_POST_INIT_ERROR: ' . $request->get_error_message());
            return array("Code" => 500,"Text" => "Une erreur est survenu lors du paiement.");
        }
        $response = wp_remote_retrieve_body( $request );

        if( wp_remote_retrieve_response_code( $request ) == 201 ) {
            return json_decode($response,true);
        } else {
            return array("Code" => 500,"Text" => $response);
        }
        
        
    }

    function one_step_payment($token,$data) {
        $args = array(
            'body' =>  json_encode($data),
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'method'      => 'POST',
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'cookies' => array()
        );

        $base_url  = WC_AKOUENDY_PAY_BASE_URL ;
        if($this->settings['sandbox'] == "yes") {
            $base_url  = WC_AKOUENDY_PAY_SANDBOX_BASE_URL ;
        }
        $request_url = $base_url  . WC_AKOUENDY_PAY_POST_ONESTEP_PAY . "?lang=$lang" ;

        $place_holder = array("{provider}", "{token}");
        $values   = array($this->provider, $token);
        $url = str_replace($place_holder, $values, $request_url);

        $request = wp_remote_post($url,$args);

        if( is_wp_error( $request ) ) {
            return array("Code" => 500,"Text" => "Une erreur est survenu lors du paiement.");
        }
        $response = wp_remote_retrieve_body( $request );
        return json_decode($response,true);
    }

    function process_akouendy_webhook($site_token,$raw_post) {
        global $woocommerce;
        $data = json_decode( $raw_post );   
        error_log('process_akouendy_webhook '. print_r($data,true));     
        $id = explode("_",sanitize_text_field($data->TransactionID));
        $wc_order_id = $id[0];
        $order = new WC_Order($wc_order_id);
        $status = sanitize_text_field($data->Status);
        $str = $site_token."|".sanitize_text_field($data->TransactionID)."|". $status;
        $hash = hash('sha512', $str);
        WC()->session->set('akouendy_wc_hash_key', $hash);

        if ($hash === sanitize_text_field($data->Hash)) {
            switch ($status) {
                case 'FAILED':
                    $message = "Paiement échoué";
                    $order_status = 'failed';
                    break;
                case 'SUCCESS':
                    $order_status = 'completed';
                    $woocommerce->cart->empty_cart();
                    $message = "Paiement effectuée avec succès";
                    break;
                case 'NOTFOUND':
                    // transaction not found
                    $message = "Transaction non trouvée";
                    $order_status = "pending";
                    break;
                case 'PENDING':
                    $message = "Paiement en cours";
                    $order_status = 'pending'; // or processing
                    break;
                case 'CANCELED':
                    $message = "Paiement annulée";
                    $order_status = 'failed';
                    break;
                
            }
            if(!empty($order_status)) {
                $order->add_order_note($message);
                $order->update_status($order_status); 
                $order->add_order_note("Transaction ID: ".$data->TransactionID);              
            }            
        } 

    }

    protected function get_init_payment_args($order,$webhook) {
        global $woocommerce;

        $txnid = $order->get_id() . '_' . date("Y-m-d_H-i-s");
        WC()->session->set('akouendy_wc_order_id', $order->get_id());

        $redirect_url =  wc_get_checkout_url();

        $logs["get_checkout_payment_url"] = $order->get_checkout_payment_url( false);
        $logs["wc_get_checkout_url"] =  wc_get_checkout_url();
        $logs["get_permalink_checkout_url"] = get_permalink( wc_get_page_id( 'checkout' ) );
        $logs["home_url"] = home_url();
        $logs["orders"] =  wc_get_account_endpoint_url( 'orders' );
        $logs["view-order"] =  wc_get_account_endpoint_url( 'view-order' );
        $logs["get_checkout_order_received_url"] = $order->get_checkout_order_received_url();
        error_log("=========get_checkout_payment_url======== " . $order->get_checkout_payment_url( false));

        $this->akouendy_debug($logs);


        $site_id = $this->settings['site_id'];

        $str = "$site_id|$txnid|".intval($order->get_total())."|akouna_matata";
        $hash = hash('sha512', $str);

        WC()->session->set('akouendy_wc_hash_key', $hash);

        $items = $woocommerce->cart->get_cart();
        $akouendy_items = array();
        foreach ($items as $item) {
            $akouendy_items[] = array(
                "name" => $item["data"]->get_title(),
                "quantity" => $item["quantity"],
                "unit_price" => $item["line_total"] / (($item["quantity"] == 0) ? 1 : $item["quantity"]),
                "total_price" => $item["line_total"],
                "description" => ""
            );
        }
        $total = intval($order->get_total());

        $description = "Achat de ". count($akouendy_items)." article(s) pour un total de $total sur ". get_bloginfo("name").".";
        error_log("=========settings======== " . print_r($this->settings,true));
        if ($this->settings['sandbox'] == "yes"){
            $env = "testbed";
        } else {
            $env = "prod";
        }
        
        $fullName = $order->get_billing_first_name() . " ". $order->get_billing_last_name();
        $akouendy_args = [
            "AppId" => $this->settings['site_id'], 
            "Description" => $description, 
            "Hash" => $hash, 
            "ReturnUrl" => $redirect_url, 
            "TotalAmount" => $total, 
            "TransactionId" => $txnid, 
            "Env" => $env,
            "Webhook" => get_site_url() . "/?wc-api=".$webhook,
            "Email" => $order->get_billing_email(),
            "FullName" => $fullName, 
         ]; 

 
 

        apply_filters('woocommerce_akouendy_args', $akouendy_args, $order);
        return $akouendy_args;
    }

    function akouendy_debug($data) {
        $args = array(
            'body' =>  json_encode($data),
            'timeout' => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking' => true,
            'method'      => 'POST',
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'cookies' => array()
        );

        $request = wp_remote_post("https://httpdump.akouendy.com/callback",$args);
    }

    function process_payment_redirect($order,$response,$provider, $redirect_url) {
        if ($response["Code"] && $response["Code"] == "00") {
            $order->add_order_note("AKOUENDY Payment Token: " . $response["Token"]);
            $place_holder = array("{provider}", "{token}");
            $values   = array($provider, $response["Token"]);
            $url = str_replace($place_holder, $values, $redirect_url);    
            return array(
                'result' => 'success',
                'redirect' => $url
            );
        } else {
            wc_add_notice($response["Text"], 'error' );
            return;
        }
    }
}