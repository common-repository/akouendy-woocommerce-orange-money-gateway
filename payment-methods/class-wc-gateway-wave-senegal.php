<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Akouendy_Wave extends WC_Payment_Gateway {

    public function __construct() {
        $this->akouendy_errors = new WP_Error();
        $this->partner = 'default';
        $this->id = 'akd-wave';
        $this->method_title = '[Akouendy]Wave Sénégal';
        $this->method_description = "Facturer vos clients par Wave Sénégal";
        $this->icon = WC_AKOUENDY_PLUGIN_URL . '/assets/images/wave.png';
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];

        $this->site_id = $this->settings['site_id'];
        $this->site_token = $this->settings['site_token'];

        if (!isset($this->settings['sandbox'])) {
            $this->settings['sandbox'] = "no";
        }
        $this->sandbox = $this->settings['sandbox'];
        $this->provider = "wave";    

        $this->msg['message'] = "";
        $this->msg['class'] = ""; 
        
        if (isset($_REQUEST["akouendy"])) {
            wc_add_notice(sanitize_text_field($_REQUEST["akouendy"]), "error");
        } elseif (isset($_REQUEST["akouendy-msg"])) {
            wc_add_notice(sanitize_text_field($_REQUEST["akouendy-msg"]), "notice");
        }

        if (isset($_REQUEST["token"]) && $_REQUEST["token"] !== "" && $_REQUEST["provider"] == $this->provider) {
            $token = sanitize_text_field(trim($_REQUEST["token"]));
            $this->check_akouendy_response($token);
        }

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
        } else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        }
         
        // handling callback
        add_action('woocommerce_api_'.$this->id, array($this, 'akouendy_webhook'));

    }

    /**
     * Safely get post data if set
     *
     * @param string $name name of post argument to get
     * @return mixed post data, or null
     */
    private function get_post($name) {
        if (isset($_POST[$name])) {
            return trim($_POST[$name]);
        }
        return null;
    }

    function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Activer/D&eacute;sactiver', 'akouendy'),
                'type' => 'checkbox',
                'label' => __('Activer le module de paiement Wave avec AKOUENDY.', 'akouendy'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Titre:', 'akouendy'),
                'type' => 'text',
                'description' => __('Texte que verra le client lors du paiement de sa commande.', 'akouendy'),
                'default' => __('Wave Sénégal', 'akouendy')
            ),
            'description' => array(
                'title' => __('Description:', 'akouendy'),
                'type' => 'textarea',
                'description' => __('Description que verra le client lors du paiement de sa commande.', 'akouendy'),
                'default' => __("Payer par Wave Sénégal avec AKOUENDY", 'akouendy')
            ),
            'site_id' => array(
                'title' => __('Cl&eacute; Priv&eacute; de production', 'akouendy'),
                'type' => 'text',
                'description' => __('Cl&eacute; Priv&eacute; de production fournie par AKOUENDY lors de la cr&eacute;ation de votre application sur https://console.akouendy.com.')
            ),
            'site_token' => array(
                'title' => __('Token de production', 'akouendy'),
                'type' => 'text',
                'description' => __('Token de production fourni par AKOUENDY lors de la cr&eacute;ation de votre application sur https://console.akouendy.com.')
            ),
        );
    }

    public function admin_options() {
        echo '<h3>' . __('Payer par Wave Sénégal avec AKOUENDY', 'akouendy') . '</h3>';
        echo '<p>' . __('Passerelle de paiement AKOUENDY pour les achats en ligne.') . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
        wp_enqueue_script('akouendy_admin_option_js', plugin_dir_url(__FILE__) . 'assets/js/settings.js', array('jquery'), '1.0.1');
    }

    function process_payment($order_id) {
        global $woocommerce;
        $order = wc_get_order($order_id);
        require_once 'class-akouendy-pay-api.php';
        $provider_settings["settings"] = $this->settings;
        $provider_settings["site_id"] = $this->site_id;
        $provider_settings["site_token"] = $this->site_token;
        $provider_settings["provider"] = $this->provider;
        $provider_settings["webhook"] = $this->id;
        $provider_settings["sandbox"] = $this->sandbox;


        $akd_client = new Akouendy_Pay_Api($provider_settings, WC_AKOUENDY_PAY_BASE_URL, $this->provider);
        $response = $akd_client->init_payment($order, get_bloginfo('language'), $this->partner);

        error_log('akd_client init_payment ' . print_r($response, true));

        if ($response["Code"] && $response["Code"] == "00") {
            $order->add_order_note("AKOUENDY Payment Token: " . $response["Token"]);
            $base_url  = WC_AKOUENDY_PAY_BASE_URL;
            if ($this->sandbox == "yes") {
                $base_url  = WC_AKOUENDY_PAY_SANDBOX_BASE_URL;
            }
            $request_url = $base_url  . WC_AKOUENDY_PAY_REDIRECT;
            $place_holder = array("{provider}", "{token}");
            $values   = array($this->provider, $response["Token"]);
            $url = str_replace($place_holder, $values, $request_url);   
            error_log('akd_client redirect ' . print_r($url, true)); 
            return array(
                'result' => 'success',
                'redirect' => $url
            );
        } else {
            wc_add_notice($response["Text"], 'error');
            return;
        }
    }

    function akouendy_webhook() {
        require_once 'class-akouendy-pay-api.php';
        $akd_client = new Akouendy_Pay_Api(array(), "", "");
        $akd_client->process_akouendy_webhook($this->site_token, file_get_contents('php://input'));
    }

    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }

    function update_order_on_return() {
        $query_str = sanitize_text_field($_SERVER['QUERY_STRING']);
        $query_str_arr = explode("?", $query_str);
        foreach ($query_str_arr as $value) {
            $data = explode("=", $value);
            if (trim($data[0]) == "token") {
                error_log('update_order_on_return token' . trim($data[1]));
                $token = isset($data[1]) ? trim($data[1]) : "";
                if ($token !== "") {
                    $this->check_akouendy_response($token);
                }
                break;
            }
        }
    }

    function notification_on_return() {
        if (isset($_REQUEST["akouendy"])) {
            wc_add_notice(sanitize_text_field($_REQUEST["akouendy"]), "error");
        } elseif (isset($_REQUEST["akouendy-msg"])) {
            wc_add_notice(sanitize_text_field($_REQUEST["akouendy-msg"]), "notice");
        }
    } 

    function check_akouendy_response($token) {
        error_log($this->id . ' : check_akouendy_response ' . print_r($_SERVER['QUERY_STRING'], true));
        global $woocommerce;
        if ($token !== "") {
            $wc_order_id = WC()->session->get('akouendy_wc_order_id');
            $hash = WC()->session->get('akouendy_wc_hash_key');
            $order = wc_get_order($wc_order_id);
            try {            
                $base_url  = WC_AKOUENDY_PAY_BASE_URL;
                if ($this->sandbox == "yes") {
                    $base_url  = WC_AKOUENDY_PAY_SANDBOX_BASE_URL;
                }
                $request_url = $base_url  . WC_AKOUENDY_PAY_GET_STATUS;            
                $place_holder = array("{applicationId}", "{paymenId}");
                $values   = array($this->site_id, $token);
                $url = str_replace($place_holder, $values, $request_url);
                
                $request = wp_remote_get($url);

                if (is_wp_error($request)) {
                    error_log('Check payment status error: ');
                }
                $response = wp_remote_retrieve_body($request);
                $response_decoded = json_decode($response);
                
                $respond_code = $response_decoded->Code;
                error_log($this->id . ' : check_akouendy_response ' . print_r($respond_code, true));

                if ($respond_code == "00") {
                    $status = $response_decoded->Status;
                    $custom_data = $response_decoded->MerchantData;
                    $trxId = explode("_", $custom_data->TransactionId);
                    if ($wc_order_id !== $trxId[0]) {
                        $message = "Votre session de transaction a expiré.";
                        $message_type = "notice";
                        $order->add_order_note($message);
                        $redirect_url = $order->get_cancel_order_url();
                    }
                    if ($status == "SUCCESS") {
                        $total_amount = strip_tags($woocommerce->cart->get_cart_total());
                        $message = "Merci pour votre achat. Le paiement a été reçu. Votre commande est en cours de traitement.";
                        $message_type = "success";
                        $order->payment_complete();
                        $order->update_status('completed');
                        $order->add_order_note('Paiement AKOUENDY effectué avec succès<br/>ID: ' . $token);
                        $order->add_order_note($this->msg['message']);
                        $woocommerce->cart->empty_cart();
                        $redirect_url = $this->get_return_url($order);
                        $customer = trim($order->get_billing_last_name() . " " . $order->get_billing_first_name());

                    } else {
                        $message = "Le paiement n'a pu être complété.";
                        $message_type = "error";
                        $order->add_order_note("La paiement a échoué ou l'utilisateur a eu à une faire demande d'annulation de paiement");
                        $redirect_url = $order->get_cancel_order_url();
                    }
                } else {
                    $message = "Merci de nous avoir choisi. Malheureusement, la transaction a été refusée.";
                    $message_type = "error";
                    $redirect_url = $order->get_cancel_order_url();
                }

                $notification_message = array(
                    'message' => $message,
                    'message_type' => $message_type
                );
                if (version_compare(WOOCOMMERCE_VERSION, "2.2") >= 0) {
                    add_post_meta($wc_order_id, '_akouendy_hash', $hash, true);
                }
                $order->update_meta_data('_akouendy_wc_message', $notification_message);
                $order->save();

                WC()->session->__unset('akouendy_wc_hash_key');
                WC()->session->__unset('akouendy_wc_order_id');

                error_log('update_post_meta  respond_code :' . $wc_order_id . print_r($notification_message, true));
                wc_add_notice($message, $message_type);
                wp_redirect($redirect_url);
                exit;
            } catch (Exception $e) {
                $order->add_order_note('Erreur: ' . $e->getMessage());
                $redirect_url = wc_get_checkout_url();
                wc_add_notice($message, $message_type);
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
}
