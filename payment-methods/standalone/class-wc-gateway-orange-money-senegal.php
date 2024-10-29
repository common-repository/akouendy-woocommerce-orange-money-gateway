<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Akouendy_OrangeMoneySn extends WC_Payment_Gateway {

    public function __construct() {
        $this->akouendy_errors = new WP_Error();

        $this->id = 'akd-orange-money-sn';
        $this->medthod_title = 'Orange Money Sénégal';
        $this->method_description = "Facturer vos clients par Orange Money Sénégal";
        $this->icon = WC_AKOUENDY_PLUGIN_URL . '/assets/images/orange-money.png';
        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];

        $this->site_id = $this->settings['site_id'];
        $this->site_token = $this->settings['site_token'];
        
        if (isset($this->settings['sandbox'])) {
            $this->sandbox = $this->settings['sandbox'];
        } else {
            $this->sandbox = "no";
        }
        $this->provider = "orange-money-sn-api";    

        $this->msg['message'] = "";
        $this->msg['class'] = "";            

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
        } else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        }
         
        // handling callback
        add_action( 'woocommerce_api_'.$this->id, array( $this, 'akouendy_webhook' ) );
    }

        /**
     * Safely get post data if set
     *
     * @param string $name name of post argument to get
     * @return mixed post data, or null
     */
    private function get_post($name){
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
                'label' => __('Activer le module de paiement Orange Money avec AKOUENDY.', 'akouendy'),
                'default' => 'no'),
            'title' => array(
                'title' => __('Titre:', 'akouendy'),
                'type' => 'text',
                'description' => __('Texte que verra le client lors du paiement de sa commande.', 'akouendy'),
                'default' => __('Orange Money Sénégal', 'akouendy')),
            'description' => array(
                'title' => __('Description:', 'akouendy'),
                'type' => 'textarea',
                'description' => __('Description que verra le client lors du paiement de sa commande.', 'akouendy'),
                'default' => __("Payer par Orange Money Sénégal avec AKOUENDY", 'akouendy')),
            'site_id' => array(
                'title' => __('Cl&eacute; Priv&eacute; de production', 'akouendy'),
                'type' => 'text',
                'description' => __('Cl&eacute; Priv&eacute; de production fournie par AKOUENDY lors de la cr&eacute;ation de votre application sur https://console.akouendy.com.')),
            'site_token' => array(
                'title' => __('Token de production', 'akouendy'),
                'type' => 'text',
                'description' => __('Token de production fourni par AKOUENDY lors de la cr&eacute;ation de votre application sur https://console.akouendy.com.')),
            /* 'sandbox' => array(
                'title' => __('Activer le mode test', 'akouendy'),
                'type' => 'checkbox',
                'description' => __("Cocher cette case pour faire des tests de paiements.", 'akouendy')), */

        );
    }

    public function admin_options() {
        echo '<h3>' . __('Payer par Orange Money Sénégal avec AKOUENDY', 'akouendy') . '</h3>';
        echo '<p>' . __('Passerelle de paiement AKOUENDY pour les achats en ligne.') . '</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this->generate_settings_html();
        echo '</table>';
        wp_enqueue_script('akouendy_admin_option_js', plugin_dir_url(__FILE__) . 'assets/js/settings.js', array('jquery'), '1.0.1');
    }

    function payment_fields() {
        if ($this->description)
            echo wpautop(wptexturize($this->description));

            $value = max( 0, apply_filters( 'woocommerce_calculated_total', round( WC()->cart->cart_contents_total + WC()->cart->fee_total + WC()->cart->tax_total, WC()->cart->dp ), WC()->cart ) );
            echo "Toooootalll == : $value";
            //echo "<br/>===== checkout " . esc_url( wc_get_checkout_url() );
            $transactionId = md5(esc_url( wc_get_checkout_url() . $value ));
            echo "<br/>=======  transactionId ==> $transactionId";
            $checkout_page_id = wc_get_page_id( 'checkout' );
            echo "<br/>=======  checkout_page_id ==> $checkout_page_id";

            echo "<br/>=======  order woocommerce";
            /*
            global $woocommerce;

            $order_id = $woocommerce->session->order_awaiting_payment;


            $order = wc_get_order( $order_id );
*/
$products_ids_array = array();

foreach( WC()->cart->get_cart() as $cart_item ){
    $products_ids_array[] = $cart_item['product_id'];
}

            error_log('MY CONSTRUCT REQUEST : ' .  print_r($products_ids_array, true ));

           // echo "<br/>=======  wc_get_order ==>" . print_r($order);
        ?>
        <div ><label ><strong>Paiement par QrCode</stong></label></div>
        <hr/>
        <akd-notif-widget payment-id="3612feff-5c8f-40a3-8bf1-52f90f5d34d2"></akd-notif-widget>
        <section style="width: 200px; height: 200px;">
            <img style="max-height: 100% !important" src="data:image/png;base64, iVBORw0KGgoAAAANSUhEUgAAAMgAAADIAQAAAACFI5MzAAABaElEQVR4Xu2WO5IDIQxERcQxuCmfm3IMotF2S8Nu2S472AQFVlEM8AgafQZE35k8L/zal3wmS2BpSVPtUi4pnQslBimQOFwpxqtwvKIQiB0LeqXaYCwcIhSRmqF9so9HhGuMdiiijPZsSEZm5UsenCReJRB7t+f6OUhuY5zRtz0PQXacMWGhZLoWhwhCkocawulLc2cQotrz5MzMUrL07L4+Tey/cvGuuPOx/53nOCkK4Ywz+4aJzqSm+jjBF9DyEa4FH9xI1QEIi0PsIQCxgMKHQBDCEqkW4Vsv7o3sHj1PhEoZbTVy+TgEYdVWLxEr3wsbg5BlVcsils2xNwZxL+bJtSXNdoUhjHYC38JhKQoxQ/mysUQwFikhCL+QOTEb3tsdEoMUtdQbFmpXXV11ANKpF3Ai1ImHgPBIhEqhmq5tLj8QoSOb3RVoD6qPEt2hvvhIwcaHTDxKrEoWA+5jyTMKeWdf8j/yA5BNxozHcZ08AAAAAElFTkSuQmCC">
        </section>
        <div ><label ><strong>Paiement par code d'autorisation</stong></label></div>
        <hr/>
        <section class="form-group">
            <section ><label for="<?php echo $this->id; ?>_phoneNumber"><strong>Numéro Orange Money <span style="color:#ff0000">*</span></stong></label></section>
            <section class="input-box">
                <!-- <span class="prefix">+221</span> -->
                <input type="number" class="form-control akd-input" style="width: 100%" id="<?php echo $this->id; ?>_phoneNumber" name="<?php echo $this->id; ?>_phoneNumber" placeholder="771234567" required>
            </section>
        </section>
        <section class="form-group">
            <div ><label for="<?php echo $this->id; ?>_authCode"><strong>Code d'autorisation <span style="color:#ff0000">*</span></stong></label></div>
            <div ><small id="<?php echo $this->id; ?>_authCodeHelp" class="form-text text-muted">
            Composer le numéro <strong style="color: red">#144#391# </strong> sur votre téléphone pour obtenir un code d'autorisation afin de valider le paiement par Orange Money.
            </small></div>
            <input type="number"  class="form-control akd-input" style="width: 100%" id="<?php echo $this->id; ?>_authCode" name="<?php echo $this->id; ?>_authCode" aria-describedby="<?php echo $this->id; ?>_authCodeHelp" required>
            
        </section> 
  <?php
    }

    public function validate_fields() {
     $is_valid = parent::validate_fields();
     $phoneNumber = $this->get_post($this->id .'_phoneNumber');
     $authCode = $this->get_post($this->id .'_authCode');

         if (empty($phoneNumber)) {
             wc_add_notice( __('Veuillez saisir votre numéro de téléphone Orange Money.', 'akouendy'), 'error' );
             $is_valid = false;
         }

         if (empty($authCode)) {
            wc_add_notice( __("Veuillez saisir le code d'autorisation pour valider le paiement.", 'akouendy'), 'error' );
            $is_valid = false;
        }

     return $is_valid;
 }

    function process_payment($order_id) {
        global $woocommerce;
        $order = new WC_Order( $order_id );
        require_once 'class-akouendy-pay-api.php';
        $provider_settings["settings"] = $this->settings;
        $provider_settings["site_id"] = $this->site_id;
        $provider_settings["site_token"] = $this->site_token;
        $provider_settings["provider"] = $this->provider;
        $provider_settings["webhook"] = $this->id;
        
    
        $akd_client = new Akouendy_Pay_Api($provider_settings,WC_AKOUENDY_PAY_BASE_URL,$this->provider);
        $response = $akd_client->init_payment($order);

        //WC()->session->set('akouendy_wc_oder_id', $order_id);

        if ($response["Code"] && $response["Code"] == "00") {
            $order->add_order_note("AKOUENDY Payment Token: " . $response["Token"]);
            //*
            // Make payment
            $billing_data = array("Code"=> $this->get_post($this->id.'_authCode'),"PhoneNumber" => $this->get_post($this->id.'_phoneNumber'));
            $payResponse = $akd_client->one_step_payment($response["Token"],$billing_data);
            if ($payResponse["Code"] && $payResponse["Code"] == "200") {
                $order->payment_complete();
                // Remove cart
                $woocommerce->cart->empty_cart();
                // Return thank you redirect
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            } else {
                wc_add_notice( $payResponse["Text"], 'error' );
                return;
                
            } //*/
        } else {
            wc_add_notice($response["Text"], 'error' );
            return;
        }


    }

    function akouendy_webhook() {
        require_once 'class-akouendy-pay-api.php';
        $akd_client = new Akouendy_Pay_Api(array(),"","");;
        $akd_client->process_akouendy_webhook($this->site_token,file_get_contents( 'php://input' ));
    }


    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title)
            $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
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
