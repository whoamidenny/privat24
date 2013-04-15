<?php
/*
Plugin Name: WooCommerce Privat24 Gateway
Plugin URI: http://woothemes.com/woocommerce
Description: Extends WooCommerce with an Privat24 gateway.
Version: 1.01
Author: Denys Kanunnikov
Author URI: http://dargent.com.ua/
*/

add_action('plugins_loaded', 'init_WC_Privat24_Payment_Gateway', 0);

function init_WC_Privat24_Payment_Gateway() {

    if(!class_exists('WC_Payment_Gateway')) return;

    class WC_Privat24_Payment_Gateway extends WC_Payment_Gateway{

        public function __construct(){

            $this->id = 'privat24';
            $this->has_fields         = false;
            $this->method_title       = 'Privat24';
            $this->method_description = __( 'Privat24', 'woocommerce_privat24' );
            $this->liveurl            = 'https://api.privatbank.ua/p24api/ishop';
            $this->init_form_fields();
            $this->init_settings();
            $this->title              =  $this->settings['title'];
            $this->description        =  $this->settings['description'];
            $this->merchant_id        = $this->settings['merchant_id'];
            $this->merchant_password  = $this->settings['merchant_password'];
            $this->icon               = apply_filters('woocommerce_privat24_icon', 'https://privat24.privatbank.ua/p24/img/buttons/api_logo_1.jpg');
            // Actions
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            add_action( 'woocommerce_receipt_'. $this->id, array( $this, 'receipt_page' ) );
            add_action('woocommerce_api_wc_privat24', array($this, 'check_ipn_response'));
        }

        public function init_form_fields(){

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Включить/Отключить', 'woocommerce_privat24' ),
                    'type' => 'checkbox',
                    'label' => __( 'Включить', 'woocommerce_privat24' ),
                    'default' => 'yes'
                                ),
                'title' => array(
                    'title' => __( 'Заголовок', 'woocommerce_privat24' ),
                    'type' => 'text',
                    'description' => __( 'Заголовок, который отображается на странице оформления заказа', 'woocommerce_privat24' ),
                    'default' => 'Privat24',
                    'desc_tip' => true,
                                ),
                'description' => array(
                    'title' => __( 'Описание', 'woocommerce_privat24' ),
                    'type' => 'textarea',
                    'description' => __( 'Описание, которое отображается в процессе выбора формы оплаты', 'woocommerce_privat24' ),
                    'default' => __( 'Оплатить через электронную платежную систему Приват24', 'woocommerce_privat24' ),
                ),
                'merchant_id' => array(
                    'title' => __( 'Merchant ID', 'woocommerce_privat24' ),
                    'type' => 'text',
                    'description' => __( 'Уникальный идентификатор магазина в системе Privat24.', 'woocommerce_privat24' ),
                ),
                'merchant_password' => array(
                    'title' => __( 'Пароль', 'woocommerce_privat24' ),
                    'type' => 'password',
                    'description' => __( 'Пароль мерчанта', 'woocommerce_privat24' ),
                ),
            );
        }

        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect'  => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        public function receipt_page($order){
            echo '<p>'.__('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce').'</p>';
            echo $this->generate_form($order);
        }

        public function generate_form($order_id){
            $order = new WC_Order( $order_id );
            $action_adr = $this->liveurl;
            $result_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_privat24', get_permalink(woocommerce_get_page_id('thanks')) ) );
            $args = array(
                            'amt'         => $order->order_total,
                            'ccy'         => get_woocommerce_currency(),
                            'merchant'    => $this->merchant_id,
                            'order'       => $order_id,
                            'details'     => "Оплата за заказ - $order_id",
                            'ext_details' => "Оплата за заказ - $order_id",
                            'pay_way'     => 'privat24',
                            'return_url'  => $result_url,
                            'server_url'  => '',

            			);

            $args_array = array();

            foreach ($args as $key => $value){
            			$args_array[] = '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'" />';
            }

            return
                    '<form action="'.esc_url($action_adr).'" method="POST" id="privat24_payment_form">'.
                    '<input type="submit" class="button alt" id="submit_privat24_button" value="'.__('Оплатить', 'woocommerce').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Отказаться от оплаты & вернуться в корзину', 'woocommerce').'</a>'."\n".
                    implode("\n", $args_array).
                    '</form>';
        }


        function check_ipn_response(){
            global $woocommerce;

            $posted = $_POST['payment'];
            $hash = sha1(md5($posted.$this->merchant_password));
            if (isset($_POST['payment']) && $hash === $_POST['signature']){
                 $items=explode("&", $_POST['payment']);
                 $ar=array();
                 foreach($items as $it){
                    $key=""; $value="";
                    list($key, $value)=explode("=", $it, 2);
                    $payment_items[$key]=$value;
                 }

                  $order = new WC_Order($payment_items['order']);
                  $order->update_status('processing', __('Платеж успешно оплачен', 'woocommerce'));
                  $order->add_order_note( __('Клиент успешно оплатил заказ', 'woocommerce') );
                  $woocommerce->cart->empty_cart();

            }else{
                wp_die('IPN Request Failure');
            }

        }

    }

}

add_filter( 'woocommerce_payment_gateways', 'add_WC_Privat24_Payment_Gateway' );

function add_WC_Privat24_Payment_Gateway( $methods ){
    $methods[] = 'WC_Privat24_Payment_Gateway';
    return $methods;
}
?>
