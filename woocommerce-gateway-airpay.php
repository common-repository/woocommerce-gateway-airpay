<?php
/*
Plugin Name: WooCommerce AirPay
Plugin URI: http://www.kdclabs.com/?p=115
Description: AirPay Payment Gateway for WooCommerce. Your one step to online and face-to-face payment solutions. Empowering any business to collect money online within minutes that helps you sell anything. Beautifully.
Version: 2.1.0
Author: _KDC-Labs
Author URI: http://www.kdclabs.com
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://www.payumoney.com/webfront/index/kdclabs
Contributors: kdclabs, vachan
*/

add_action('plugins_loaded', 'woocommerce_gateway_airpay_init', 0);
define('airpay_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_airpay_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Gateway class
 	 */
	class WC_Gateway_AirPay extends WC_Payment_Gateway {

	     /**
         * Make __construct()
         **/	
		public function __construct(){
			
			$this->id 					= 'airpay'; // ID for WC to associate the gateway values
			$this->method_title 			= 'AirPay'; // Gateway Title as seen in Admin Dashboad
			$this->method_description	= 'Your one step to online and face-to-face payment solutions'; // Gateway Description as seen in Admin Dashboad
			$this->has_fields 			= false; // Inform WC if any fileds have to be displayed to the visitor in Frontend 
			
			$this->init_form_fields();	// defines your settings to WC
			$this->init_settings();		// loads the Gateway settings into variables for WC

			$this->title 			= $this->settings['title']; // Title as displayed on Frontend
			$this->description 		= $this->settings['description']; // Description as displayed on Frontend
			if ( $this->settings['show_logo'] != "no" ) { // Check if Show-Logo has been allowed
				$this->icon 			= airpay_IMG . 'logo_' . $this->settings['show_logo'] . '.png';
			}
            $this->key_id 			= $this->settings['key_id'];
            $this->key_secret 		= $this->settings['key_secret'];
            $this->username 			= $this->settings['username'];
            $this->password 			= $this->settings['password'];
			if ( $this->settings['payment_mode'] == "manual" ) { // Check if Set to Mannual to generate chmod value.
				$payment_mode = array();
				$payment_mode[] .= ($this->settings['payment_mode_pg']=='yes')?'pg':'';
				$payment_mode[] .= ($this->settings['payment_mode_nb']=='yes')?'nb':'';
				$payment_mode[] .= ($this->settings['payment_mode_ppc']=='yes')?'ppc':'';
				$this->payment_mode 	= implode( '_', array_filter( $payment_mode ) );
			}
            $this->redirect_page 	= $this->settings['redirect_page'];

            $this->msg['message']	= '';
            $this->msg['class'] 	= '';
			
			add_action('init', array(&$this, 'check_airpay_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_airpay_response')); //update for woocommerce >2.0

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
                 } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
                }
            add_action('woocommerce_receipt_airpay', array(&$this, 'receipt_page'));	
		} //END-__construct
		
        /**
         * Initiate Form Fields in the Admin Backend
         **/
		function init_form_fields(){

			$this->form_fields = array(
				// Activate the Gateway
				'enabled' => array(
					'title' 			=> __('Enable/Disable:', 'woo_airpay'),
					'type' 			=> 'checkbox',
					'label' 			=> __('Enable AirPay', 'woo_airpay'),
					'default' 		=> 'no',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
				// Title as displayed on Frontend
      			'title' => array(
					'title' 			=> __('Title:', 'woo_airpay'),
					'type'			=> 'text',
					'default' 		=> __('Online Payments', 'woo_airpay'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'woo_airpay'),
					'desc_tip' 		=> true
				),
				// Description as displayed on Frontend
      			'description' => array(
					'title' 			=> __('Description:', 'woo_airpay'),
					'type' 			=> 'textarea',
					'default' 		=> __('Pay securely by Credit or Debit card or internet banking through AirPay.', 'woo_airpay'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'woo_airpay'),
					'desc_tip' 		=> true
				),
				// Key-ID
      			'key_id' => array(
					'title' 			=> __('Merchant Id:', 'woo_airpay'),
					'type' 			=> 'text',
					'description' 	=> __('Available at <a href="https://ma.airpay.co.in/setting.php" target="_blank">https://ma.airpay.co.in/setting.php</a>'),
					'desc_tip' 		=> false
				),
				// Username
      			'username' => array(
					'title' 			=> __('Username:', 'woo_airpay'),
					'type' 			=> 'text',
					'description' 	=> __('Available at <a href="https://ma.airpay.co.in/setting.php" target="_blank">https://ma.airpay.co.in/setting.php</a>'),
					'desc_tip' 		=> false
				),
  				// Password
    			'password' => array(
					'title' 			=> __('Password:', 'woo_airpay'),
					'type' 			=> 'text',
					'description' 	=> __('Available at <a href="https://ma.airpay.co.in/setting.php" target="_blank">https://ma.airpay.co.in/setting.php</a>'),
					'desc_tip' 		=> false
                ),
  				// Key-Secret
    			'key_secret' => array(
					'title' 			=> __('API Key:', 'woo_airpay'),
					'type' 			=> 'text',
					'description' 	=> __('Available at <a href="https://ma.airpay.co.in/setting.php" target="_blank">https://ma.airpay.co.in/setting.php</a>'),
					'desc_tip' 		=> false
                ),
  				// Mode of Transaction
      			'payment_mode' => array(
					'title' 			=> __('Payment Mode(s):', 'woo_airpay'),
					'type' 			=> 'select',
					'label' 			=> __('AirPay Tranasction Mode.', 'woo_airpay'),
					'options' 		=> array('auto'=>'All active options','manual'=>'As set below'),
					'default' 		=> 'test',
					'description' 	=> __('<small>The Payment Option Tabs to show to the user as payment method(s).</small><br/><br/><br/><strong><u>Please Note:</u></strong> <span style="font-style: normal;">If "All active options" is selected, then the following settings will not be effect.</span>'),
					'desc_tip' 		=> false
                ),
				  'payment_mode_pg' => array(
					  'title' 			=> __('<span class="sub-pay">- Credit/Debit Card:</span>', 'woo_airpay'),
					  'type' 			=> 'checkbox',
					  'label' 			=> __('Show Credit/Debit Card Tab as a payment option.', 'woo_airpay'),
					  'default' 		=> 'yes',
					  'description' 	=> __('If enabled, the user will see this as a payment method'),
					  'desc_tip' 		=> true
				  ),
				  'payment_mode_nb' => array(
					  'title' 			=> __('<span class="sub-pay">- Net Banking:</span>', 'woo_airpay'),
					  'type' 			=> 'checkbox',
					  'label' 			=> __('Show Net Banking Card Tab as a payment option.', 'woo_airpay'),
					  'default' 		=> 'yes',
					  'description' 	=> __('If enabled, the user will see this as a payment method'),
					  'desc_tip' 		=> true
				  ),
				  'payment_mode_ppc' => array(
					  'title' 			=> __('<span class="sub-pay">- Pre-Paid Card/Wallet:</span>', 'woo_airpay'),
					  'type' 			=> 'checkbox',
					  'label' 			=> __('Show Pre-Paid Card/Wallet Tab as a payment option.', 'woo_airpay'),
					  'default' 		=> 'yes',
					  'description' 	=> __('If enabled, the user will see this as a payment method'),
					  'desc_tip' 		=> true
				  ),
  				// Page for Redirecting after Transaction
      			'redirect_page' => array(
					'title' 			=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->airpay_get_pages('Select Page'),
					'description' 	=> __('URL of Response page', 'woo_airpay'),
					'desc_tip' 		=> true
                ),
  				// Show Logo on Frontend
      			'show_logo' => array(
					'title' 			=> __('Show Logo:', 'woo_airpay'),
					'type' 			=> 'select',
					'label' 			=> __('Logo on Checkout Page', 'woo_airpay'),
					'options' 		=> array('no'=>'No Logo','icon'=>'Icon','icon-grey'=>'Icon (Grey)','airpay'=>'AirPay','airpay-grey'=>'AirPay (Grey)'),
					'default' 		=> 'no',
					'description' 	=> __('<strong>color</strong> | Icon: <img src="'. airpay_IMG . 'logo_icon.png" height="24px" /> | Logo: <img src="'. airpay_IMG . 'logo_airpay.png" height="24px" /><br/>' . "\n"
										 .'<strong>grey </strong> | Icon: <img src="'. airpay_IMG . 'logo_icon-grey.png" height="24px" /> | Logo: <img src="'. airpay_IMG . 'logo_airpay-grey.png" height="24px" /><br/>', 'woo_airpay'),
					'desc_tip' 		=> false
                )
			);

		} //END-init_form_fields
		
        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
		public function admin_options(){
			global $woocommerce;

			// Redirect URL
			if ( $this->redirect_page == '' || $this->redirect_page == 0 ) {
				$return_url = get_permalink( get_option ( 'woocommerce_myaccount_page_id' ) );
			} else {
				$redirect_url_static = get_permalink( $this->redirect_page );
			}
			echo '<h3>'.__('AirPay', 'woo_airpay').'</h3>';
			echo '<p>'.__('Your one step to online and face-to-face payment solutions', 'woo_airpay').'</p>';
			echo '<table class="widefat" cellspacing="0">
              <thead>
                <tr>
                  <th>Kindly share the following detials with the <strong>AirPay Tech contact</strong> (<a href="mailto:tech@airpay.co.in?bcc='.get_bloginfo('admin_email').'&subject=MID%3A%20'.$this->key_id.'%20%7C%20Whitelist%20Response%20URL&body=Hello%20AirPay%20Tech%20Team%2C%0D%0AWith%20reference%20to%20the%20Merchant%20Id%20%3D%20'.$this->key_id.'%0D%0AKindly%20whitelist%20the%20return%20URL%20%3D%20'.$redirect_url_static.'%0D%0A%0D%0A'.get_bloginfo('name').'">tech@airpay.co.in</a>).<br/><small>The URL has to be Whitelisted for geting successful integration.</small></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><strong>Response URL:</strong> <code>'.$redirect_url_static.'</code></td>
                </tr>
              </tbody>
            </table>
            <p>&nbsp;</p>
			<style>.sub-pay{padding-left:10%;font-weight:normal;font-style:italic;}</style>
			';			
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		} //END-admin_options

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
		function payment_fields(){
			if( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		} //END-payment_fields
		
        /**
         * Receipt Page
         **/
		function receipt_page($order){
			echo '<p><strong>' . __('Thank you for your order.', 'woo_airpay').'</strong><br/>' . __('The payment page will open soon.', 'woo_airpay').'</p>';
			echo $this->generate_airpay_form($order);
		} //END-receipt_page
    
        /**
         * Generate button link
         **/
		function generate_airpay_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );

			// Transaction ID
			$order_number = $order_id.'-'.date("ymdhms");
			
			// Address Billing
			if($order->billing_address_2 != "") { // Check if Address Line 2 is set.
				$address = $order->billing_address_1.", ".$order->billing_address_2;
			} else {
				$address = $order->billing_address_1;
			}
			$address = substr( $address, 0, 50); // Cut the address to max 50 characters.
			$address = ( strlen($address) <= 3 ) ? $order->billing_city : $address; // Minimum 4 charaters required
			
			$alldata = 	$order->billing_email.
						$order->billing_first_name.
						$order->billing_last_name.
						$address.
						$order->billing_city.
						$order->billing_state.
						$order->billing_country.
						number_format($order->order_total, 2, '.', '').
						$order_number;

			$privatekey = airpay_encrypt( $this->username.":|:".$this->password, $this->key_secret );
			$checksum 	= airpay_calculate_checksum( $alldata.date('Y-m-d'), $privatekey );

			$airpay_args = array(
				'buyerEmail' 	=> $order->billing_email,
				'buyerPhone' 	=> $order->billing_phone,
				'buyerFirstName'=> $order->billing_first_name,
				'buyerLastName' => $order->billing_last_name,
				'buyerAddress' 	=> $address,
				'buyerCity' 		=> $order->billing_city,
				'buyerState' 	=> $order->billing_state,
				'buyerCountry' 	=> $order->billing_country,
				'buyerPinCode' 	=> $order->billing_postcode,
				'amount' 		=> number_format($order->order_total, 2, '.', ''),
				'orderid' 		=> $order_number,
				'privatekey' 	=> $privatekey,
				'mercid' 		=> $this->key_id,
				'checksum' 		=> $checksum,
				'currency' 		=> '356',
				'isocurrency' 	=> 'INR'
			);
			// Payment Mode
			if ( isset( $this->payment_mode ) && $this->payment_mode != '' ) {
				$airpay_args['chmod'] = $this->payment_mode;
			}
			$airpay_args_array = array();
			foreach($airpay_args as $key => $value){
				$airpay_args_array[] = '                <input type="hidden" name="'.$key.'" value="'.$value.'">'."\n";
			}
			
			return '	<form action="https://payments.airpay.co.in/pay/index.php" method="post" id="airpay_payment_form">
  				' . implode('', $airpay_args_array) . '
				<input type="submit" class="button-alt" id="submit_airpay_payment_form" value="'.__('Pay via AirPay', 'woo_airpay').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woo_airpay').'</a>
					<script type="text/javascript">
					jQuery(function(){
					jQuery("body").block({
						message: "'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'woo_airpay').'",
						overlayCSS: {
							background		: "#fff",
							opacity			: 0.6
						},
						css: {
							padding			: 20,
							textAlign		: "center",
							color			: "#555",
							border			: "3px solid #aaa",
							backgroundColor	: "#fff",
							cursor			: "wait",
							lineHeight		: "32px"
						}
					});
					jQuery("#submit_airpay_payment_form").click();});
					</script>
				</form>';		
		
		} //END-generate_airpay_form

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
			global $woocommerce;
            $order = new WC_Order($order_id);
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) { // For WC 2.1.0
			  	$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'order', 
					$order->id, 
					add_query_arg(
						'key', 
						$order->order_key, 
						$checkout_payment_url						
					)
				)
			);
		} //END-process_payment

        /**
         * Check for valid gateway server callback
         **/
        function check_airpay_response(){
            global $woocommerce;
			if( isset($_REQUEST['TRANSACTIONID']) && isset($_REQUEST['TRANSACTIONSTATUS']) && isset($_REQUEST['ap_SecureHash']) ){
				$order_id = $_REQUEST['TRANSACTIONID'];
				if($order_id != ''){
					try{
						$order = new WC_Order( $order_id );
						$status = $_REQUEST['TRANSACTIONSTATUS'];
						$hash = $_REQUEST['ap_SecureHash'];
						$checkhash = sprintf( "%u", crc32 ($_REQUEST['TRANSACTIONID'].':'.$_REQUEST['APTRANSACTIONID'].':'.$_REQUEST['AMOUNT'].':'.$_REQUEST['TRANSACTIONSTATUS'].':'.$_REQUEST['MESSAGE'].':'.$this->key_id.':'.$this->username ) );
						$trans_authorised = false;

						if( $order->status !== 'completed' ) {
							if($hash == $checkhash){
								$status = strtolower($status);
								if($status=="success"){
									$trans_authorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
									$this->msg['class'] = 'woocommerce-message';
									if($order->status == 'processing'){
										$order->add_order_note('AirPay ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
										if( isset($_POST['MESSAGE']) && $_POST['MESSAGE'] != "" ) {
											$order->add_order_note('<br/>Msg: '.$_REQUEST['MESSAGE']);
										}
									}else{
										$order->payment_complete();
										$order->add_order_note('AirPay payment successful.<br/>AirPay ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
										if( isset($_POST['MESSAGE']) && $_POST['MESSAGE'] != "" ) {
											$order->add_order_note('<br/>Msg: '.$_REQUEST['MESSAGE']);
										}
										$woocommerce->cart->empty_cart();
									}
								}else if($status=="pending"){
									$this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. We will keep you posted regarding the status of your order through eMail";
									$this->msg['class'] = 'woocommerce-info';
									$order->add_order_note('AirPay payment status is pending<br/>AirPay ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
									if( isset($_POST['MESSAGE']) && $_POST['MESSAGE'] != "" ) {
										$order->add_order_note('<br/>Msg: '.$_REQUEST['MESSAGE']);
									}
									$order->update_status('on-hold');
									$woocommerce -> cart -> empty_cart();
								}else{
									$this->msg['class'] = 'woocommerce-error';
									$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
									$order->add_order_note('Transaction ERROR: '.$_REQUEST['MESSAGE'].'<br/>AirPay ID: '.$_REQUEST['APTRANSACTIONID'].' ('.$_REQUEST['TRANSACTIONID'].')');
								}
							}else{
								$this->msg['class'] = 'error';
								$this->msg['message'] = "Security Error. Illegal access detected.";
								$order->add_order_note('Checksum ERROR: '.json_encode($_REQUEST));
							}
							if($trans_authorised==false){
								$order->update_status('failed');
							}
						}
					}catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
					}
				}
	
			}

			if ( $this->redirect_page == '' || $this->redirect_page == 0 ) {
				//$redirect_url = $order->get_checkout_payment_url( true );
				$redirect_url = get_permalink( get_option('woocommerce_myaccount_page_id') );
			} else {
				$redirect_url = get_permalink( $this->redirect_page );
			}
			
			wp_redirect( $redirect_url );
			exit;

        } //END-check_airpay_response

        /**
         * Get Page list from WordPress
         **/
		function airpay_get_pages($title = false, $indent = true) {
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
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
		} //END-airpay_get_pages

	} //END-class


		/**
		* CheckSum for AirPay
		**/
		function airpay_calculate_checksum( $data, $key_secret ) {
			$checksum = md5( $data.$key_secret );
			return $checksum;
		} //END-calculate_checksum
		function airpay_encrypt($data, $key_secret) {
			$key = hash( 'SHA256', $key_secret.'@'.$data );
        	return $key;
    	} //END-encrypt
		function airpay_verify_checksum( $checksum, $all, $key_secret ) {
			$cal_checksum = airpay_calculate_checksum( $key_secret, $all );
			$bool = 0;
			if( $checksum == $cal_checksum ) {
				$bool = 1;
			}
			return $bool;
		} //END-verfiy_checksum
		//END-checksum

	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_airpay_gateway($methods) {
		$methods[] = 'WC_Gateway_AirPay';
		return $methods;
	}//END-wc_add_gateway
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_airpay_gateway' );
	
} //END-init

/**
* 'Settings' link on plugin page
**/
add_filter( 'plugin_action_links', 'airpay_add_action_plugin', 10, 5 );
function airpay_add_action_plugin( $actions, $plugin_file ) {
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {

			$settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_airpay">' . __('Settings') . '</a>');
		
    			$actions = array_merge($settings, $actions);
			
		}
		
		return $actions;
}//END-settings_add_action_link