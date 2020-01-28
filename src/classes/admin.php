<?php

namespace Niteo\WooCart\AdvancedTaxes {

	use Niteo\WooCart\AdvancedTaxes\Vies;

	/**
	 * Class for all the WP admin panel magic.
	 *
	 * @since 1.0.0
	 */
	class Admin {

		/**
		 * Class constructor.
		 */
		public function __construct() {
			// Initialize the admin part.
			add_action( 'admin_init', array( &$this, 'init' ) );
		}

		/**
		 * Initialize on `admin_init` hook.
		 */
		public function init() {
			if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				add_action( 'woocommerce_tax_settings', array( &$this, 'settings' ), PHP_INT_MAX, 2 );
				add_action( 'admin_enqueue_scripts', array( &$this, 'scripts' ) );
				add_action( 'woocommerce_admin_field_button', array( &$this, 'button_field' ), 10, 1 );
				add_action( 'wp_ajax_add_digital_taxes', array( &$this, 'ajax_digital_tax_rates' ) );
				add_action( 'wp_ajax_add_distance_taxes', array( &$this, 'ajax_distance_tax_rates' ) );
				add_action( 'wp_ajax_add_tax_id_check', array( &$this, 'ajax_tax_id_check' ) );
				add_action( 'woocommerce_admin_order_data_after_billing_address', array( &$this, 'order_meta' ), 10, 1 );
			}
		}

		/**
		 * Add custom settings to the `woocommerce` tax options page.
		 */
		public function settings( $settings ) {
			$this->form_fields = array(
				array(
					'id'   => 'vatoptions',
					'name' => esc_html__( 'Tax Handling for B2B', 'advanced-taxes-woocommerce' ),
					'type' => 'title',
					'desc' => esc_html__( 'Customize settings if you sell to companies. Defaults are ticked checkboxes.', 'advanced-taxes-woocommerce' ),
				),
				array(
					'id'      => 'b2b_sales',
					'name'    => esc_html__( 'B2B sales (adds fields Company Name and Tax ID)', 'advanced-taxes-woocommerce' ),
					'type'    => 'select',
					'options' => array(
						'none'  => esc_html__( 'disabled' ),
						'eu'    => esc_html__( 'EU store' ),
						'noneu' => esc_html__( 'Non-EU store' ),
					),
					'default' => 'none',
				),
				array(
					'id'      => 'tax_id_required',
					'name'    => esc_html__( 'Tax ID field required for B2B', 'advanced-taxes-woocommerce' ),
					'type'    => 'checkbox',
					'desc'    => esc_html__( 'Tax ID required', 'advanced-taxes-woocommerce' ),
					'default' => 'yes',
				),
				array(
					'id'      => 'tax_home_country',
					'name'    => esc_html__( 'B2B sales in the home country', 'advanced-taxes-woocommerce' ),
					'type'    => 'checkbox',
					'desc'    => esc_html__( 'Charge Tax', 'advanced-taxes-woocommerce' ),
					'default' => 'yes',
				),
				array(
					'id'      => 'tax_eu_with_vatid',
					'name'    => esc_html__( 'B2B sales in the EU when VIES/VAT ID is provided', 'advanced-taxes-woocommerce' ),
					'type'    => 'checkbox',
					'desc'    => esc_html__( 'Do not charge Tax', 'advanced-taxes-woocommerce' ),
					'default' => 'yes',
				),
				array(
					'id'      => 'tax_charge_vat',
					'name'    => esc_html__( 'B2B sales outside the country', 'advanced-taxes-woocommerce' ),
					'type'    => 'checkbox',
					'desc'    => esc_html__( 'Do not charge Tax', 'advanced-taxes-woocommerce' ),
					'default' => 'yes',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'vatoptions',
				),
				array(
					'id'   => 'vat_digital_goods',
					'name' => esc_html__( 'EU Tax Handling - Digital Goods (B2C)', 'advanced-taxes-woocommerce' ),
					'type' => 'title',
					'desc' => esc_html__( 'If you sell digital goods in/to EU, you need to charge the customer\'s country Tax. Automatically validates the customer IP against their billing address, and prompts the customer to self-declare their address if they do not match. Applies only to digital goods and services sold to consumers (B2C).', 'advanced-taxes-woocommerce' ),
				),
				array(
					'id'      => 'vat_digital_goods_enable',
					'name'    => esc_html__( 'EU Tax Handling for Digital Goods', 'advanced-taxes-woocommerce' ),
					'type'    => 'checkbox',
					'desc'    => esc_html__( 'Enable', 'advanced-taxes-woocommerce' ),
					'default' => 'no',
				),
				array(
					'id'      => 'vat_digital_goods_rates',
					'name'    => esc_html__( 'Import tax rates for all EU countries and create tax class Digital Goods' ),
					'type'    => 'button',
					'default' => esc_html__( 'Import Taxes', 'advanced-taxes-woocommerce' ),
					'class'   => 'button-secondary import-digital-tax-rates',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'vat_digital_goods',
				),
				array(
					'id'   => 'vat_distance_selling',
					'name' => esc_html__( 'EU Tax Handling - Distance Selling (B2C)', 'advanced-taxes-woocommerce' ),
					'type' => 'title',
					'desc' => sprintf( esc_html__( 'You need to register for EU Tax ID in countries where you reach %1$sDistance Selling EU Tax thresholds%2$s. Add countries where you are registered and the customers will be charged the local VAT. Applies only to products sold to consumers (B2C).', 'advanced-taxes-woocommerce' ), '<a href="https://www.vatlive.com/eu-vat-rules/distance-selling/distance-selling-eu-vat-thresholds/" target="_blank">', '</a>' ),
				),
				array(
					'id'      => 'vat_distance_selling_enable',
					'name'    => esc_html__( 'EU VAT Handling for Distance Selling', 'advanced-taxes-woocommerce' ),
					'type'    => 'checkbox',
					'desc'    => esc_html__( 'Enable', 'advanced-taxes-woocommerce' ),
					'default' => 'no',
				),
				array(
					'id'   => 'vat_distance_selling_countries',
					'name' => esc_html__( 'Select countries for which you would like to import tax rates.', 'advanced-taxes-woocommerce' ),
					'type' => 'multi_select_countries',
				),
				array(
					'id'      => 'vat_distance_selling_rates',
					'name'    => esc_html__( 'Import tax rates for specific EU countries', 'advanced-taxes-woocommerce' ),
					'type'    => 'button',
					'default' => esc_html__( 'Import Taxes', 'advanced-taxes-woocommerce' ),
					'class'   => 'button-secondary import-distance-tax-rates',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'vat_distance_selling',
				),
			);

			return array_merge( $settings, $this->form_fields );
		}

		/**
		 * Add required admin script for the tax page.
		 */
		public function scripts() {
			global $plugin_url, $version;

			wp_enqueue_script( 'advanced-taxes-admin', $plugin_url . 'assets/js/admin.js', array( 'jquery' ), $version, true );

			/**
		   * Localization
		   */
			$localization = array(
				'nonce' => wp_create_nonce( '__atw_nonce' ),
			);

			wp_localize_script( 'advanced-taxes-admin', 'atw_localize', $localization );
		}

		/**
		 * Adds a custom button field for woocommerce settings.
		 *
		 * @param $value array
		 *
		 * @codeCoverageIgnore
		 */
		public function button_field( $value ) {
			// Custom attribute handling.
			$custom_attributes = array();

			if ( ! empty( $value['custom_attributes'] ) && is_array( $value['custom_attributes'] ) ) {
				foreach ( $value['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
				}
			}
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
				</th>
				<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
					<button
						name="<?php echo esc_attr( $value['id'] ); ?>"
						id="<?php echo esc_attr( $value['id'] ); ?>"
						type="<?php echo esc_attr( $value['type'] ); ?>"
						style="<?php echo esc_attr( $value['css'] ); ?>"
						class="<?php echo esc_attr( $value['class'] ); ?>"
						<?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
						><?php echo esc_html( $value['default'] ); ?></button><?php echo esc_html( $value['suffix'] ); ?>
				</td>
			</tr>
			<?php
		}

		/**
		 * AJAX request for importing digital goods tax rates.
		 */
		public function ajax_digital_tax_rates() {
			global $wpdb;

			// Check for nonce.
			check_ajax_referer( '__atw_nonce', 'nonce' );

			// Check for existing classes.
			$class_name = esc_html__( 'Digital Goods', 'advanced-taxes-woocommerce' );
			$class_slug = 'digital-goods';

			$option  = esc_html( get_option( 'woocommerce_tax_classes' ) );
			$classes = explode( PHP_EOL, $option );

			// Initiate new class.
			$rates = new Rates();
			$data  = $rates->get_tax_rates();

			// Check if tax rates already exist!
			if ( ! in_array( $class_name, $classes ) ) {
				$update_option = $option . "\n" . $class_name;

				// Add entry to `woocommerce_tax_classes` option.
				update_option( 'woocommerce_tax_classes', $update_option );
			}

			// Response which we will be sending back to the page.
			$response = array();

			// Adding tax rates to the table.
			if ( ! empty( $data ) && is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					$query  = $wpdb->prepare(
						"
					SELECT * 
					FROM {$wpdb->prefix}woocommerce_tax_rates 
					WHERE tax_rate_country = %s 
					AND tax_rate_class = %s",
						$key,
						$class_slug
					);
					$result = $wpdb->get_row( $query, ARRAY_A, 0 );

					// Determine whether tax rate for specific country and tax class exists. If yes, then just update the option.
					if ( null !== $result ) {
						$wpdb->update(
							$wpdb->prefix . 'woocommerce_tax_rates',
							array(
								'tax_rate'      => $value['standard_rate'] . '.0000',
								'tax_rate_name' => $class_name . ' (' . $value['standard_rate'] . '%)',
							),
							array(
								'tax_rate_id' => $result['tax_rate_id'],
							),
							array(
								'%s',
								'%s',
							),
							array( '%d' )
						);

						// Add to response.
						$response[] = esc_html( 'Updated ', 'advanced-taxes-woocommerce' ) . $value['country'];
					} else {
						$wpdb->insert(
							$wpdb->prefix . 'woocommerce_tax_rates',
							array(
								'tax_rate_country'  => $key,
								'tax_rate'          => $value['standard_rate'] . '.0000',
								'tax_rate_name'     => $class_name . ' (' . $value['standard_rate'] . '%)',
								'tax_rate_priority' => 1,
								'tax_rate_order'    => 1,
								'tax_rate_class'    => $class_slug,
							),
							array(
								'%s',
								'%s',
								'%s',
								'%d',
								'%d',
								'%s',
							)
						);

						// Add to response.
						$response[] = esc_html( 'Added ', 'advanced-taxes-woocommerce' ) . $value['country'];
					}
				}

				wp_send_json_success( $response );
			} else {
				// Nothing added.
				$response[] = esc_html__( 'Nothing has been added or updated in the database.', 'advanced-taxes-woocommerce' );

				wp_send_json_error( $response );
			}
		}

		/**
		 * AJAX request for importing distance selling tax rates.
		 */
		public function ajax_distance_tax_rates() {
			global $wpdb;

			// Check for nonce.
			check_ajax_referer( '__atw_nonce', 'nonce' );

			// Check for existing classes.
			$class_name = esc_html__( 'Distance Selling', 'advanced-taxes-woocommerce' );
			$class_slug = 'distance-selling';

			$option    = esc_html( get_option( 'woocommerce_tax_classes' ) );
			$classes   = explode( PHP_EOL, $option );
			$countries = json_decode( json_encode( $_POST['countries'] ), ARRAY_A );

			// Initiate new class.
			$rates = new Rates();
			$data  = $rates->get_tax_rates();

			// Check if tax rates already exist!
			if ( ! in_array( $class_name, $classes ) ) {
				$update_option = $option . "\n" . $class_name;

				// Add entry to `woocommerce_tax_classes` option.
				update_option( 'woocommerce_tax_classes', $update_option );
			}

			// Also, update the countries option cause we refresh the page after AJAX call. So, we don't want to lose the option set for importing taxes for the specific countries.
			update_option( 'vat_distance_selling_countries', $countries );

			// Response which we will be sending back to the page.
			$response = array();

			// Adding tax rates to the table.
			if ( ! empty( $data ) && is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					if ( in_array( $key, $countries ) ) {
						$query  = $wpdb->prepare(
							"
						SELECT * 
						FROM {$wpdb->prefix}woocommerce_tax_rates 
						WHERE tax_rate_country = %s 
						AND tax_rate_class = %s",
							$key,
							$class_slug
						);
						$result = $wpdb->get_row( $query, ARRAY_A, 0 );

						// Determine whether tax rate for specific country and tax class exists. If yes, then just update the option.
						if ( null !== $result ) {
							$wpdb->update(
								$wpdb->prefix . 'woocommerce_tax_rates',
								array(
									'tax_rate'      => $value['standard_rate'] . '.0000',
									'tax_rate_name' => $class_name . ' (' . $value['standard_rate'] . '%)',
								),
								array(
									'tax_rate_id' => $result['tax_rate_id'],
								),
								array(
									'%s',
									'%s',
								),
								array( '%d' )
							);

							// Add to response.
							$response[] = esc_html( 'Updated ', 'advanced-taxes-woocommerce' ) . $value['country'];
						} else {
							$wpdb->insert(
								$wpdb->prefix . 'woocommerce_tax_rates',
								array(
									'tax_rate_country'  => $key,
									'tax_rate'          => $value['standard_rate'] . '.0000',
									'tax_rate_name'     => $class_name . ' (' . $value['standard_rate'] . '%)',
									'tax_rate_priority' => 1,
									'tax_rate_order'    => 1,
									'tax_rate_class'    => $class_slug,
								),
								array(
									'%s',
									'%s',
									'%s',
									'%d',
									'%d',
									'%s',
								)
							);

							// Add to response.
							$response[] = esc_html( 'Added ', 'advanced-taxes-woocommerce' ) . $value['country'];
						}
					} else {
						$response[] = esc_html( 'Skipped ', 'advanced-taxes-woocommerce' ) . $value['country'];
					}
				}

				wp_send_json_success( $response );
			} else {
				// Nothing added.
				$response[] = esc_html__( 'Nothing has been added or updated in the database.', 'advanced-taxes-woocommerce' );

				wp_send_json_error( $response );
			}
		}

		/**
		 * Display field value on the order edit page.
		 *
		 * @param object $order Order object for getting post meta information.
		 * @return void
		 * @codeCoverageIgnore
		 */
		public function order_meta( $order ) {
			$b2b_sale        = get_post_meta( $order->get_id(), 'b2b_sale', true ) ? esc_html__( 'Yes', 'advanced-taxes-woocommerce' ) : esc_html__( 'No', 'advanced-taxes-woocommerce' );
			$business_tax_id = esc_html( get_post_meta( $order->get_id(), 'business_tax_id', true ) );

			if ( empty( $business_tax_id ) ) {
				$business_tax_id = esc_html__( 'None', 'advanced-taxes-woocommerce' );
			}

			echo '<p><strong>' . esc_html__( 'B2B Sale', 'advanced-taxes-woocommerce' ) . ':</strong><br/>' . $b2b_sale . '</p>';
			echo '<p><strong>' . esc_html__( 'Business Tax ID', 'advanced-taxes-woocommerce' ) . ':</strong><br/>' . $business_tax_id . '</p>';
			echo '<p><button id="bth-vat-check" class="button button-primary" data-value="' . $business_tax_id . '">' . esc_html__( 'Check VAT ID', 'advanced-taxes-woocommerce' ) . '</button>';
			echo '<div id="btn-vat-response"></div>';
		}

		/**
		 * Process AJAX request for checking the Tax ID.
		 */
		public function ajax_tax_id_check() {
			// Check for nonce.
			check_ajax_referer( '__atw_nonce', 'nonce' );

			// Business Tax ID
			$business_id = sanitize_text_field( $_POST['business_id'] );

			if ( ! empty( $business_id ) ) {
				// Doing Tax ID check over here.
				// We are using Vies class for validating our request.
				$validator = new Vies();
				$bool      = $validator->isValid( $business_id, true );

				if ( $bool ) {
					wp_send_json_success( esc_html__( 'The TAX ID has been verified correctly and is marked as valid.', 'advanced-taxes-woocommerce' ) );
				}

				wp_send_json_error( esc_html__( 'The Tax ID has been verified correctly and is marked as invalid.', 'advanced-taxes-woocommerce' ) );
			}

			wp_send_json_error( esc_html__( 'The Tax ID has been verified correctly and is marked as invalid.', 'advanced-taxes-woocommerce' ) );
		}

	}

}
