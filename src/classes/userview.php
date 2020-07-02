<?php
/**
 * User facing plugin view.
 *
 * @category   Plugins
 * @package    WordPress
 * @subpackage eu-vat-b2b-taxes
 * @since      1.0.0
 */

namespace Niteo\WooCart\EUVatTaxes {

	/**
	 * User class where we calculate taxes and get stuff done.
	 *
	 * @since 1.0.0
	 */
	class UserView {

		/**
		 * @var string
		 */
		public $enable_digital_tax;

		/**
		 * @var string
		 */
		public $b2b_sales_status;

		/**
		 * @var string
		 */
		public $b2b_tax_id_required;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'init' ) );
		}

		/**
		 * Initialize the user facing part of the plugin.
		 */
		public function init() : void {
			// Get user settings for the plugin
			$this->enable_digital_tax = sanitize_text_field( get_option( 'wc_vat_digital_goods_enable', 'no' ) );
			$this->b2b_sales_status = sanitize_text_field( get_option( 'wc_b2b_sales', 'none' ) );
			$this->b2b_tax_id_required = sanitize_text_field( get_option( 'wc_tax_id_required', 'no' ) );

			// Assign Hooks & Filters
			add_filter( 'woocommerce_billing_fields', array( $this, 'checkout_fields' ) );
			add_filter( 'woocommerce_product_get_tax_class', array( $this, 'digital_goods' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_product_get_tax_status', array( $this, 'digital_goods_verify' ), PHP_INT_MAX, 2 );

			add_action( 'wp_enqueue_scripts', array( $this, 'scripts' ) );
			add_action( 'woocommerce_after_checkout_validation', array( $this, 'checkout_validation' ), PHP_INT_MAX, 2 );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta' ) );
		}

		/**
		 * Add styles and scripts for the frontend.
		 */
		public function scripts() : void {
			wp_enqueue_script( 'euvat-public', Config::$plugin_url . 'assets/js/public.js', array( 'jquery' ), Config::VERSION, true );
			wp_enqueue_style( 'euvat-public', Config::$plugin_url . 'assets/css/public.css', '', Config::VERSION );

			// Pass data to JS
			$localize = array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( '__wc_euvat_nonce' ),
			);

			wp_localize_script( 'euvat-public', 'atw_localize', $localize );
		}

		/**
		 * Add custom fields to the checkout page.
		 *
		 * @param array $fields Checkout fields contained in an array
		 * @return array
		 */
		public function checkout_fields( array $fields ) : array {
			$b2b_sales = sanitize_text_field( get_option( 'wc_b2b_sales', 'none' ) );

			if ( 'none' === $b2b_sales ) {
				return $fields;
			}

			// Check for business status
			$fields['business_check'] = array(
				'label'    => esc_html__( 'Are you making this purchase as a Business entity?', 'eu-vat-b2b-taxes' ),
				'type'     => 'checkbox',
				'required' => false,
				'class'    => array( 'wc-euvat-business-check', 'update_totals_on_change' ),
				'clear'    => true,
				'priority' => 5,
			);

			// Ask for VAT ID
			$fields['business_tax_id'] = array(
				'label'    => esc_html__( 'Business Tax ID', 'eu-vat-b2b-taxes' ),
				'type'     => 'text',
				'required' => false,
				'class'    => array( 'form-row-wide', 'wc-euvat-hidden', 'update_totals_on_change' ),
				'priority' => 6,
			);

			return $fields;
		}

		/**
		 * Taxation for digital goods.
		 *
		 * @param string $tax_class Tax class assigned to the product
		 * @param object $product Object containing product information
		 *
		 * @return string
		 */
		public function digital_goods( string $tax_class, object $product ) : string {
			if ( 'no' === $this->enable_digital_tax ) {
				return $tax_class;
			}

			if ( ! $product->get_virtual() && ! $product->get_downloadable() ) {
				return $tax_class;
			}
			
			return 'digital-goods';
		}

		/**
		 * Verifies the existence of the `digital-goods` tax class.
		 * If not present, removes tax from the products.
		 *
		 * @param string $tax_status Tax status assigned to the product
		 * @param object $product Object containing product information
		 *
		 * @return string
		 */
		public function digital_goods_verify( string $tax_status, object $product ) : string {
			if ( 'no' === $this->enable_digital_tax ) {
				return $tax_status;
			}

			if ( ! $product->get_virtual() && ! $product->get_downloadable() ) {
				return $tax_status;
			}

			// Fetch list of available tax classes
			$tax_rates = $this->get_digital_tax_rate_for_user();

			// In case of missing tax rates for user's country set to no-tax
			if ( ! $tax_rates ) {
				return 'none';
			}

			return $tax_status;
		}

		/**
		 * Add custom validation for the business tax ID field which is set to optional but we enforce it as required if the purchase is being made as a business entity.
		 *
		 * @param  array    $data An array of posted data.
		 * @param  WP_Error $errors
		 */
		public function checkout_validation( array $data, \WP_Error $errors ) : void {
			if ( 'no' === $this->b2b_tax_id_required ) {
				return;
			}

			if ( ! isset( $_POST['business_check'] ) ) {
				return;
			}
			
			if ( ! empty( $_POST['business_tax_id'] ) ) {
				return;
			}

			$errors->add( 'billing', sprintf( esc_html__( '%1$sBusiness Tax ID%2$s is a required field.', 'eu-vat-b2b-taxes' ), '<strong>', '</strong>' ) );
		}

		/**
		 * Update order meta for the specified order.
		 *
		 * @param int $order_id Order ID
		 * @return void
		 */
		public function update_order_meta( int $order_id ) : void {
			if ( ! isset( $_POST['business_check'] ) ) {
				return;
			}

			$business_check = sanitize_text_field( $_POST['business_check'] );
			$business_tax_id = sanitize_text_field( $_POST['business_tax_id'] );

			if ( ! empty( $business_check ) ) {
				update_post_meta( $order_id, 'b2b_sale', $business_check );
			}

			if ( ! empty( $business_tax_id ) ) {
				update_post_meta( $order_id, 'business_tax_id', $business_tax_id );
			}
		}

		/**
		 * Return tax rate for user's country for `digital-goods` tax class.
		 *
		 * @return array
		 */
		private function get_digital_tax_rate_for_user() : array {
			return \WC_Tax::get_rates( 'digital-goods' );
		}

	}

}
