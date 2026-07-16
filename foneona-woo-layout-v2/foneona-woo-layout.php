<?php
/**
 * Plugin Name: Foneona Woo Layout (Cart + Checkout)
 * Description: Custom WooCommerce cart + checkout layouts matching provided mockups. Includes shortcodes [foneona_cart] and [foneona_checkout].
 * Version: 1.8.6
 * Author: Foneona
 * License: GPLv2 or later
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'FONEONA_WOO_LAYOUT_VERSION' ) ) {
	define( 'FONEONA_WOO_LAYOUT_VERSION', '1.8.6' );
}
if ( ! defined( 'FONEONA_WOO_LAYOUT_DIR' ) ) {
	define( 'FONEONA_WOO_LAYOUT_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'FONEONA_WOO_LAYOUT_URL' ) ) {
	define( 'FONEONA_WOO_LAYOUT_URL', plugin_dir_url( __FILE__ ) );
}

final class Foneona_Woo_Layout {

	private static $instance = null;

	private static $settings_option = 'foneona_woo_layout_settings';

	private static $cart_template_map = array(
		'cart/cart.php'                => 'templates/cart/cart.php',
		'cart/cart-totals.php'         => 'templates/cart/cart-totals.php',
		'cart/cart-shipping.php'       => 'templates/cart/cart-shipping.php',
		'cart/shipping-calculator.php' => 'templates/cart/shipping-calculator.php',
	);

	private static $checkout_template_map = array(
		'checkout/form-checkout.php' => 'templates/checkout/form-checkout.php',
		'checkout/review-order.php'  => 'templates/checkout/review-order.php',
		'checkout/form-login.php'    => 'templates/checkout/form-login.php',
		'checkout/thankyou.php'      => 'templates/checkout/thankyou.php',
	);


	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 10, 3 );
		add_action( 'wp', array( $this, 'setup_checkout_hooks' ) );
		add_action( 'wp', array( $this, 'setup_order_received_hooks' ), 20 );
		add_action( 'wp', array( $this, 'force_russia_customer_context' ), 15 );
		add_action( 'wp', array( $this, 'maybe_reset_cart_shipping_address' ), 20 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_custom_checkout_fragments' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_checkout_points_amount' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_checkout_points_discount' ), 20 );
		add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_coupon_against_checkout_points' ), 20, 3 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout_points_amount' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_redeemed_points_order_meta' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'reserve_redeemed_points_for_order' ), 30, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_restore_redeemed_points_for_order' ), 30, 4 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'clear_checkout_points_session' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_checkout_fields' ), 99 );
		add_filter( 'woocommerce_form_field', array( $this, 'filter_checkout_optional_label_markup' ), 20, 4 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'filter_checkout_posted_data' ) );
		add_filter( 'woocommerce_order_button_text', array( $this, 'filter_order_button_text' ) );
		add_filter( 'woocommerce_shipping_calculator_enable_country', '__return_false' );
		add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_rates_by_settings' ), 100, 2 );

		/* v1.7.0 — Secure guest account registration offer on checkout */
		add_filter( 'woocommerce_checkout_registration_enabled', array( $this, 'filter_native_checkout_registration_enabled' ), 20 );
		add_filter( 'woocommerce_checkout_registration_required', array( $this, 'filter_native_checkout_registration_required' ), 20 );
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_account_registration_offer' ), 30 );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_account_registration_request' ), 15, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_account_registration_order_meta' ), 15, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_create_account_after_order_processed' ), 40, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_create_account_for_paid_order' ), 40 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_create_account_after_status_change' ), 35, 4 );

		/* v1.5.0 — Three checkboxes */
		add_action( 'woocommerce_review_order_before_submit', array( $this, 'render_custom_privacy_checkboxes' ), 5 );
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_custom_privacy_checkboxes' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_custom_privacy_checkboxes' ), 10, 2 );

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_foneona_dadata_suggest_address', array( $this, 'ajax_dadata_suggest_address' ) );
		add_action( 'wp_ajax_nopriv_foneona_dadata_suggest_address', array( $this, 'ajax_dadata_suggest_address' ) );
	}

	/* ================================================================= */
	/*  Settings                                                         */
	/* ================================================================= */

	public static function get_settings() {
		$defaults = array(
			'checkbox1_text'       => 'Я даю согласие на обработку моих <a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank" rel="noopener">персональных данных</a>.',
			'checkbox2_text'       => 'Я принимаю условия <a href="" target="_blank" rel="noopener">Публичной оферты</a>.',
			'checkbox3_text'       => 'Я даю согласие на получение рекламных сообщений. С <a href="' . esc_url( get_privacy_policy_url() ) . '" target="_blank" rel="noopener">Политикой обработки персональных данных</a> можно ознакомиться здесь.',
			'privacy_checkbox_text' => '',
			'dadata_enabled'       => '0',
			'dadata_api_key'       => '',
			'dadata_secret_key'    => '',
			'dadata_placeholder'   => 'Начните вводить адрес',
			'shipping_cards_enabled'          => '1',
			'show_yandex_delivery'           => '1',
			'show_russian_post_delivery'     => '1',
			'account_registration_enabled'             => '1',
			'account_registration_default_checked'     => '0',
			'account_registration_text'                => 'Создать личный кабинет по данным заказа и получать историю заказов, баллы и персональные предложения.',
			'account_registration_existing_email_mode' => 'block_guest',
			'account_creation_timing'                  => 'paid',
			'account_creation_statuses'                => 'processing,completed',
			'account_password_email_enabled'           => '1',
			'account_password_email_subject'           => 'Ваш личный кабинет на сайте {site_name}',
			'account_password_email_message'           => '<div class="foneona-email-card"><p>Здравствуйте, {customer_first_name}!</p><p>Мы создали для вас личный кабинет по данным заказа № {order_number}.</p><p><strong>Логин:</strong> {customer_email}<br><strong>Временный пароль:</strong> {temporary_password}</p><p>Вы можете войти в личный кабинет и изменить пароль в настройках профиля.</p><p><a class="foneona-email-button" href="{my_account_url}">Войти в личный кабинет</a></p></div>',
			'account_password_email_css'               => 'body { margin: 0; padding: 24px; background: #f7f2ea; font-family: Arial, Helvetica, sans-serif; color: #1f1f1f; }\n.foneona-email-wrap { max-width: 640px; margin: 0 auto; }\n.foneona-email-card { background: #ffffff; border-radius: 18px; padding: 28px; box-shadow: 0 10px 28px rgba(0,0,0,.08); }\n.foneona-email-card p { margin: 0 0 16px; font-size: 16px; line-height: 1.55; }\n.foneona-email-button { display: inline-block; padding: 14px 22px; background: #7b1f35; color: #ffffff !important; text-decoration: none; border-radius: 0; font-weight: 700; letter-spacing: .04em; }',
		);

		$saved = get_option( self::$settings_option, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	public static function get_setting( $key, $default = '' ) {
		$settings = self::get_settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public static function is_dadata_enabled() {
		return '1' === (string) self::get_setting( 'dadata_enabled', '0' ) && '' !== trim( (string) self::get_setting( 'dadata_api_key', '' ) );
	}

	/**
	 * v1.5.0 — Detect if the Yandex Delivery plugin has its own DaData
	 * configured. If yes, we must NOT attach our DaData on checkout.
	 */
	public static function is_yandex_dadata_active_on_checkout() {
		if ( ! function_exists( 'wc_yandex_shipping' ) ) {
			return false;
		}
		$dadata_token = wc_yandex_shipping()->get_integration_option( 'dadata_token' );
		return ! empty( $dadata_token );
	}

	public static function get_fixed_country_code() {
		return 'RU';
	}

	public static function get_fixed_country_name() {
		return 'Россия';
	}

	/* ================================================================= */
	/*  Shortcodes                                                       */
	/* ================================================================= */

	public function register_shortcodes() {
		add_shortcode( 'foneona_cart', array( $this, 'shortcode_cart' ) );
		add_shortcode( 'foneona_checkout', array( $this, 'shortcode_checkout' ) );
	}

	public function shortcode_cart( $atts = array(), $content = null ) {
		return function_exists( 'WC' ) ? do_shortcode( '[woocommerce_cart]' ) : '';
	}

	public function shortcode_checkout( $atts = array(), $content = null ) {
		return function_exists( 'WC' ) ? do_shortcode( '[woocommerce_checkout]' ) : '';
	}

	/* ================================================================= */
	/*  Context helpers                                                  */
	/* ================================================================= */

	private static function page_has_shortcode( $tag ) {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_post();
		return ( $post && ! empty( $post->post_content ) && has_shortcode( $post->post_content, $tag ) );
	}

	private static function is_cart_context() {
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return true;
		}
		return self::page_has_shortcode( 'woocommerce_cart' ) || self::page_has_shortcode( 'foneona_cart' );
	}

	private static function is_order_received_context() {
		return function_exists( 'is_order_received_page' ) && is_order_received_page();
	}

	private static function is_checkout_context() {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			if ( self::is_order_received_context() ) {
				return false;
			}
			return true;
		}
		return self::page_has_shortcode( 'woocommerce_checkout' ) || self::page_has_shortcode( 'foneona_checkout' );
	}

	private static function is_checkout_visual_context() {
		return self::is_checkout_context() || self::is_order_received_context();
	}

	/* ================================================================= */
	/*  Assets                                                           */
	/* ================================================================= */

	private static function dequeue_wc_styles() {
		$handles = array(
			'woocommerce-general',
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'wc-blocks-style',
			'wc-blocks-vendors-style',
			'wc-blocks-packages-style',
			'woocommerce_prettyPhoto_css',
		);
		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	public function enqueue_assets() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		if ( self::is_cart_context() ) {
			self::dequeue_wc_styles();
			wp_enqueue_style( 'foneona-cart-layout', FONEONA_WOO_LAYOUT_URL . 'assets/css/cart-layout.css', array(), FONEONA_WOO_LAYOUT_VERSION );
			wp_enqueue_script( 'foneona-cart-layout', FONEONA_WOO_LAYOUT_URL . 'assets/js/cart-layout.js', array( 'jquery' ), FONEONA_WOO_LAYOUT_VERSION, true );
		}

		if ( self::is_checkout_visual_context() ) {
			self::dequeue_wc_styles();
			wp_enqueue_style( 'foneona-checkout-layout', FONEONA_WOO_LAYOUT_URL . 'assets/css/checkout-layout.css', array(), FONEONA_WOO_LAYOUT_VERSION );

			if ( self::is_checkout_context() ) {
				wp_enqueue_script( 'foneona-maskedinput', 'https://cdn.jsdelivr.net/npm/jquery.maskedinput@1.4.1/src/jquery.maskedinput.min.js', array( 'jquery' ), '1.4.1', true );
				wp_enqueue_script( 'foneona-checkout-layout', FONEONA_WOO_LAYOUT_URL . 'assets/js/checkout-layout.js', array( 'jquery', 'foneona-maskedinput' ), FONEONA_WOO_LAYOUT_VERSION, true );
			}
		}

		if ( self::is_dadata_enabled() && ( self::is_cart_context() || self::is_checkout_context() ) ) {
			/*
			 * v1.5.0 — When Yandex Delivery plugin has its own DaData token,
			 * skip foneona DaData on the checkout page to avoid conflicts.
			 */
			$skip = self::is_checkout_context() && self::is_yandex_dadata_active_on_checkout();

			if ( ! $skip ) {
				wp_enqueue_style( 'foneona-dadata-layout', FONEONA_WOO_LAYOUT_URL . 'assets/css/dadata-layout.css', array(), FONEONA_WOO_LAYOUT_VERSION );
				wp_enqueue_script( 'foneona-dadata-layout', FONEONA_WOO_LAYOUT_URL . 'assets/js/dadata-layout.js', array( 'jquery' ), FONEONA_WOO_LAYOUT_VERSION, true );
				wp_localize_script(
					'foneona-dadata-layout',
					'foneonaDadata',
					array(
						'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
						'nonce'        => wp_create_nonce( 'foneona_dadata_nonce' ),
						'placeholder'  => (string) self::get_setting( 'dadata_placeholder', 'Начните вводить адрес' ),
						'minLength'    => 3,
						'countryCode'  => self::get_fixed_country_code(),
						'countryLabel' => self::get_fixed_country_name(),
						'noResults'    => __( 'Ничего не найдено', 'woocommerce' ),
						'errorText'    => __( 'Не удалось получить подсказки', 'woocommerce' ),
					)
				);
			}
		}
	}

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_foneona-woo-layout' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_editor();
		wp_enqueue_style( 'foneona-admin-email-editor', FONEONA_WOO_LAYOUT_URL . 'assets/css/admin-email-editor.css', array(), FONEONA_WOO_LAYOUT_VERSION );
		wp_enqueue_script( 'foneona-admin-email-editor', FONEONA_WOO_LAYOUT_URL . 'assets/js/admin-email-editor.js', array( 'jquery' ), FONEONA_WOO_LAYOUT_VERSION, true );

		wp_localize_script(
			'foneona-admin-email-editor',
			'foneonaEmailEditor',
			array(
				'editorId'     => 'foneona_account_password_email_message',
				'cssFieldId'   => 'foneona_account_password_email_css',
				'previewId'    => 'foneona-email-preview-frame',
				'replacements' => self::get_sample_email_replacements(),
			)
		);
	}

	/* ================================================================= */
	/*  Template overrides                                               */
	/* ================================================================= */

	public function locate_template( $template, $template_name, $template_path ) {
		if ( ! function_exists( 'WC' ) ) {
			return $template;
		}

		$can_override_cart_template = self::is_cart_context();

		/*
		 * Checkout also calls wc_cart_totals_shipping_html(), which loads
		 * cart/cart-shipping.php. Without this condition the checkout page keeps
		 * using the default WooCommerce radio list and the visual delivery cards
		 * are never rendered. This was the reason v1.8.5 did not change the UI.
		 */
		if ( 'cart/cart-shipping.php' === $template_name && self::is_checkout_shipping_render_context() ) {
			$can_override_cart_template = true;
		}

		if ( $can_override_cart_template && isset( self::$cart_template_map[ $template_name ] ) ) {
			$custom = FONEONA_WOO_LAYOUT_DIR . self::$cart_template_map[ $template_name ];
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}

		if ( isset( self::$checkout_template_map[ $template_name ] ) ) {
			$can_override_checkout_template = self::is_checkout_context();

			if ( self::is_order_received_context() ) {
				$can_override_checkout_template = in_array( $template_name, array( 'checkout/thankyou.php', 'checkout/form-login.php' ), true );
			}

			if ( $can_override_checkout_template ) {
				$custom = FONEONA_WOO_LAYOUT_DIR . self::$checkout_template_map[ $template_name ];
				if ( file_exists( $custom ) ) {
					return $custom;
				}
			}
		}

		return $template;
	}

	public function setup_checkout_hooks() {
		if ( ! self::is_checkout_context() ) {
			return;
		}
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
	}

	public function setup_order_received_hooks() {
		if ( ! self::is_order_received_context() ) {
			return;
		}

		/* The custom thank-you template already renders the order summary. */
		remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
	}

	/* ================================================================= */
	/*  Country / shipping address                                       */
	/* ================================================================= */

	public function force_russia_customer_context() {
		if ( is_admin() || wp_doing_ajax() || ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}
		if ( ! self::is_cart_context() && ! self::is_checkout_context() ) {
			return;
		}

		$customer      = WC()->customer;
		$country_code  = self::get_fixed_country_code();
		$country_dirty = false;

		if ( $country_code !== $customer->get_shipping_country() ) {
			$customer->set_shipping_country( $country_code );
			$country_dirty = true;
		}
		if ( $country_code !== $customer->get_billing_country() ) {
			$customer->set_billing_country( $country_code );
			$country_dirty = true;
		}
		if ( $country_dirty ) {
			$customer->save();
		}
	}

	public function maybe_reset_cart_shipping_address() {
		if ( is_admin() || wp_doing_ajax() || ! function_exists( 'WC' ) || ! WC()->customer ) {
			return;
		}
		if ( ! self::is_cart_context() ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $request_method ) {
			return;
		}

		$customer = WC()->customer;
		$changed  = false;
		$fields   = array(
			'shipping_city'      => '',
			'shipping_state'     => '',
			'shipping_postcode'  => '',
			'shipping_address_1' => '',
			'shipping_address_2' => '',
		);

		foreach ( $fields as $field => $value ) {
			$getter = 'get_' . $field;
			$setter = 'set_' . $field;
			if ( method_exists( $customer, $getter ) && method_exists( $customer, $setter ) && '' !== (string) $customer->$getter() ) {
				$customer->$setter( $value );
				$changed = true;
			}
		}

		if ( method_exists( $customer, 'set_calculated_shipping' ) ) {
			$customer->set_calculated_shipping( false );
			$changed = true;
		}

		if ( $changed ) {
			$customer->save();
			if ( WC()->session ) {
				WC()->session->__unset( 'chosen_shipping_methods' );
			}
		}
	}

	/* ================================================================= */
	/*  Checkout fields                                                  */
	/* ================================================================= */

	public function filter_checkout_fields( $fields ) {
		if ( isset( $fields['billing']['billing_country'] ) ) {
			unset( $fields['billing']['billing_country'] );
		}
		if ( isset( $fields['shipping']['shipping_country'] ) ) {
			unset( $fields['shipping']['shipping_country'] );
		}
		if ( isset( $fields['billing']['billing_address_2'] ) ) {
			unset( $fields['billing']['billing_address_2'] );
		}
		if ( isset( $fields['shipping']['shipping_address_2'] ) ) {
			unset( $fields['shipping']['shipping_address_2'] );
		}

		$address_groups = array( 'billing', 'shipping' );

		foreach ( $address_groups as $group ) {
			foreach ( array( 'city', 'address_1' ) as $key ) {
				$field_key = $group . '_' . $key;
				if ( ! isset( $fields[ $group ][ $field_key ] ) ) {
					continue;
				}

				$fields[ $group ][ $field_key ]['autocomplete']      = 'off';
				$fields[ $group ][ $field_key ]['input_class']       = isset( $fields[ $group ][ $field_key ]['input_class'] ) && is_array( $fields[ $group ][ $field_key ]['input_class'] ) ? $fields[ $group ][ $field_key ]['input_class'] : array();
				$fields[ $group ][ $field_key ]['input_class'][]     = 'foneona-dadata-target';
				$fields[ $group ][ $field_key ]['custom_attributes'] = isset( $fields[ $group ][ $field_key ]['custom_attributes'] ) && is_array( $fields[ $group ][ $field_key ]['custom_attributes'] ) ? $fields[ $group ][ $field_key ]['custom_attributes'] : array();
				$fields[ $group ][ $field_key ]['custom_attributes']['data-foneona-dadata-field'] = $field_key;
				$fields[ $group ][ $field_key ]['custom_attributes']['data-foneona-dadata-mode']  = ( 'city' === $key ) ? 'city' : 'address';
			}

			foreach ( array( 'state', 'postcode' ) as $key ) {
				$field_key = $group . '_' . $key;
				if ( isset( $fields[ $group ][ $field_key ] ) ) {
					$fields[ $group ][ $field_key ]['autocomplete'] = 'off';
				}
			}
		}

		/*
		 * v1.5.0 — Guarantee billing_address_1 is present and visible.
		 * Other plugins (e.g. Yandex Delivery) may hide it via "form-field-hidden"
		 * class when a pickup method is selected. We strip that class so the
		 * address field is always visible.  The Yandex plugin dynamically
		 * shows/hides it via its own JS fragment updates — that still works.
		 */
		if ( isset( $fields['billing']['billing_address_1'] ) ) {
			$fields['billing']['billing_address_1']['label']       = 'Адрес';
			$fields['billing']['billing_address_1']['placeholder'] = (string) self::get_setting( 'dadata_placeholder', 'Начните вводить адрес' );
			$fields['billing']['billing_address_1']['priority']    = 65;
			/* Strip hidden classes added by Yandex Delivery for pickup methods */
			if ( isset( $fields['billing']['billing_address_1']['class'] ) && is_array( $fields['billing']['billing_address_1']['class'] ) ) {
				$fields['billing']['billing_address_1']['class'] = array_values(
					array_diff( $fields['billing']['billing_address_1']['class'], array( 'form-field-hidden', 'hidden-field' ) )
				);
				if ( empty( $fields['billing']['billing_address_1']['class'] ) ) {
					$fields['billing']['billing_address_1']['class'] = array( 'form-row-wide', 'address-field' );
				}
			}
		} else {
			/* Re-add the field if another plugin removed it entirely */
			$fields['billing']['billing_address_1'] = array(
				'label'             => 'Адрес',
				'placeholder'       => (string) self::get_setting( 'dadata_placeholder', 'Начните вводить адрес' ),
				'priority'          => 65,
				'required'          => true,
				'type'              => 'text',
				'class'             => array( 'form-row-wide', 'address-field' ),
				'autocomplete'      => 'off',
				'input_class'       => array( 'foneona-dadata-target' ),
				'custom_attributes' => array( 'data-foneona-dadata-field' => 'billing_address_1' ),
			);
		}


		foreach ( array( 'billing_phone', 'shipping_phone' ) as $phone_field_key ) {
			$group = ( 0 === strpos( $phone_field_key, 'shipping_' ) ) ? 'shipping' : 'billing';
			if ( ! isset( $fields[ $group ][ $phone_field_key ] ) ) {
				continue;
			}

			$fields[ $group ][ $phone_field_key ]['label'] = 'Телефон';
			$fields[ $group ][ $phone_field_key ]['placeholder'] = '+7 (999) 999-99-99';
			$fields[ $group ][ $phone_field_key ]['autocomplete'] = 'tel-national';
			$fields[ $group ][ $phone_field_key ]['inputmode'] = 'tel';
			$fields[ $group ][ $phone_field_key ]['custom_attributes'] = isset( $fields[ $group ][ $phone_field_key ]['custom_attributes'] ) && is_array( $fields[ $group ][ $phone_field_key ]['custom_attributes'] ) ? $fields[ $group ][ $phone_field_key ]['custom_attributes'] : array();
			$fields[ $group ][ $phone_field_key ]['custom_attributes']['maxlength'] = '18';
		}

		if ( isset( $fields['billing']['billing_city'] ) ) {
			$fields['billing']['billing_city']['priority'] = 60;
		}
		if ( isset( $fields['billing']['billing_state'] ) ) {
			$fields['billing']['billing_state']['priority'] = 70;
		}
		if ( isset( $fields['billing']['billing_postcode'] ) ) {
			$fields['billing']['billing_postcode']['priority'] = 80;
		}

		if ( isset( $fields['shipping']['shipping_address_1'] ) ) {
			$fields['shipping']['shipping_address_1']['label']       = 'Адрес';
			$fields['shipping']['shipping_address_1']['placeholder'] = (string) self::get_setting( 'dadata_placeholder', 'Начните вводить адрес' );
			$fields['shipping']['shipping_address_1']['priority']    = 65;
		}
		if ( isset( $fields['shipping']['shipping_city'] ) ) {
			$fields['shipping']['shipping_city']['priority'] = 60;
		}
		if ( isset( $fields['shipping']['shipping_state'] ) ) {
			$fields['shipping']['shipping_state']['priority'] = 70;
		}
		if ( isset( $fields['shipping']['shipping_postcode'] ) ) {
			$fields['shipping']['shipping_postcode']['priority'] = 80;
		}

		return $fields;
	}

	public function filter_checkout_optional_label_markup( $field, $key, $args, $value ) {
		if ( ! self::is_checkout_context() ) {
			return $field;
		}

		if ( ! in_array( (string) $key, array( 'billing_address_1', 'shipping_address_1', 'billing_phone', 'shipping_phone' ), true ) ) {
			return $field;
		}

		return preg_replace( '/\s*<span class="optional">.*?<\/span>/u', '', (string) $field );
	}

	private static function normalize_ru_phone_number( $phone ) {
		$phone = is_scalar( $phone ) ? (string) $phone : '';
		$digits = preg_replace( '/\D+/', '', $phone );

		if ( ! is_string( $digits ) || '' === $digits ) {
			return '';
		}

		if ( 11 === strlen( $digits ) && '8' === $digits[0] ) {
			$digits = '7' . substr( $digits, 1 );
		} elseif ( 10 === strlen( $digits ) ) {
			$digits = '7' . $digits;
		}

		if ( 11 === strlen( $digits ) && '7' === $digits[0] ) {
			return '+' . $digits;
		}

		return '+' . $digits;
	}

	public function filter_checkout_posted_data( $data ) {
		$data['billing_country']  = self::get_fixed_country_code();
		$data['shipping_country'] = self::get_fixed_country_code();

		/*
		 * The custom checkout hides the native shipping-address column.
		 * Some delivery plugins calculate rates from shipping_* fields, so copy
		 * billing address data into empty shipping fields before validation/order creation.
		 */
		$shipping_field_map = array(
			'shipping_first_name' => 'billing_first_name',
			'shipping_last_name'  => 'billing_last_name',
			'shipping_company'    => 'billing_company',
			'shipping_address_1'  => 'billing_address_1',
			'shipping_city'       => 'billing_city',
			'shipping_state'      => 'billing_state',
			'shipping_postcode'   => 'billing_postcode',
			'shipping_phone'      => 'billing_phone',
		);

		foreach ( $shipping_field_map as $shipping_key => $billing_key ) {
			if ( empty( $data[ $shipping_key ] ) && ! empty( $data[ $billing_key ] ) ) {
				$data[ $shipping_key ] = $data[ $billing_key ];
			}
		}

		if ( isset( $data['billing_phone'] ) ) {
			$data['billing_phone'] = self::normalize_ru_phone_number( $data['billing_phone'] );
		}

		if ( isset( $data['shipping_phone'] ) ) {
			$data['shipping_phone'] = self::normalize_ru_phone_number( $data['shipping_phone'] );
		}

		/*
		 * The custom registration checkbox is rendered outside the native
		 * WooCommerce checkout fields list. WooCommerce therefore does not put
		 * it into $data automatically. Without this explicit copy the order meta
		 * receives "no" and the account is never created, even when the buyer
		 * ticks the checkbox on checkout.
		 */
		$data['foneona_create_account'] = self::is_account_registration_requested_from_request() ? '1' : '';
		if ( isset( $_POST['foneona_points_amount'] ) ) {
			$data['foneona_points_amount'] = self::normalize_points_amount( wp_unslash( $_POST['foneona_points_amount'] ) );
		}

		return $data;
	}

	public function filter_order_button_text() {
		return 'оплатить заказ';
	}


	/* ================================================================= */
	/*  Shipping visibility and checkout card helpers                     */
	/* ================================================================= */

	public static function is_shipping_cards_enabled() {
		return '1' === (string) self::get_setting( 'shipping_cards_enabled', '1' );
	}

	/**
	 * True when the shipping template is being rendered for checkout.
	 * WooCommerce can render this block both on the normal checkout page and
	 * during wc-ajax=update_order_review, where is_checkout() is not always
	 * reliable. The custom cart-shipping.php template uses this method too.
	 */
	public static function is_checkout_shipping_render_context() {
		if ( ! self::is_shipping_cards_enabled() ) {
			return false;
		}

		if ( self::is_checkout_context() ) {
			return true;
		}

		$wc_ajax = isset( $_REQUEST['wc-ajax'] ) ? sanitize_key( wp_unslash( $_REQUEST['wc-ajax'] ) ) : '';
		$action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return in_array( $wc_ajax, array( 'update_order_review' ), true )
			|| in_array( $action, array( 'woocommerce_update_order_review' ), true );
	}

	public function filter_shipping_rates_by_settings( $rates, $package ) {
		if ( ! is_array( $rates ) ) {
			return $rates;
		}

		$show_yandex       = '1' === (string) self::get_setting( 'show_yandex_delivery', '1' );
		$show_russian_post = '1' === (string) self::get_setting( 'show_russian_post_delivery', '1' );

		foreach ( $rates as $rate_key => $rate ) {
			if ( ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
				continue;
			}

			$carrier = self::get_shipping_rate_carrier( $rate );

			if ( 'yandex' === $carrier && ! $show_yandex ) {
				unset( $rates[ $rate_key ] );
				continue;
			}

			if ( 'russian_post' === $carrier && ! $show_russian_post ) {
				unset( $rates[ $rate_key ] );
			}
		}

		return $rates;
	}

	private static function normalize_shipping_text( $value ) {
		$value = is_scalar( $value ) ? wp_strip_all_tags( (string) $value ) : '';
		$value = html_entity_decode( $value, ENT_QUOTES, get_bloginfo( 'charset' ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, get_bloginfo( 'charset' ) ) : strtolower( $value );
	}

	private static function shipping_text_has_any( $haystack, array $needles ) {
		$haystack = self::normalize_shipping_text( $haystack );

		foreach ( $needles as $needle ) {
			$needle = self::normalize_shipping_text( $needle );
			if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	public static function get_shipping_rate_carrier( $rate ) {
		if ( ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
			return 'other';
		}

		$method_id = method_exists( $rate, 'get_method_id' ) ? (string) $rate->get_method_id() : '';
		$rate_id   = method_exists( $rate, 'get_id' ) ? (string) $rate->get_id() : ( isset( $rate->id ) ? (string) $rate->id : '' );
		$label     = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '';
		$haystack  = $method_id . ' ' . $rate_id . ' ' . $label;

		if ( in_array( $method_id, array( 'wc_russian_post_courier', 'wc_russian_post_postal' ), true ) || self::shipping_text_has_any( $haystack, array( 'russian_post', 'russian post', 'почта россии', 'почтой россии', 'pocht', 'woodev-russian-post' ) ) ) {
			return 'russian_post';
		}

		if ( self::shipping_text_has_any( $haystack, array( 'yandex', 'яндекс', 'яндекс.доставка', 'яндекс доставка', 'wc_yandex', 'yandex_delivery', 'yandex-delivery' ) ) ) {
			return 'yandex';
		}

		return 'other';
	}

	public static function get_shipping_rate_service_type( $rate ) {
		if ( ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
			return 'other';
		}

		$method_id = method_exists( $rate, 'get_method_id' ) ? (string) $rate->get_method_id() : '';
		$rate_id   = method_exists( $rate, 'get_id' ) ? (string) $rate->get_id() : ( isset( $rate->id ) ? (string) $rate->id : '' );
		$label     = method_exists( $rate, 'get_label' ) ? (string) $rate->get_label() : '';
		$haystack  = $method_id . ' ' . $rate_id . ' ' . $label;

		if ( 'wc_russian_post_postal' === $method_id ) {
			return 'pickup';
		}

		if ( 'wc_russian_post_courier' === $method_id ) {
			return 'door';
		}

		if ( self::shipping_text_has_any( $haystack, array( 'пвз', 'пункт', 'выдач', 'pickup', 'pick-up', 'postal office', 'postamat', 'почтомат', 'отделени' ) ) ) {
			return 'pickup';
		}

		if ( self::shipping_text_has_any( $haystack, array( 'двер', 'курьер', 'courier', 'door' ) ) ) {
			return 'door';
		}

		return 'other';
	}

	public static function get_shipping_card_data( $rate ) {
		$carrier = self::get_shipping_rate_carrier( $rate );
		$type    = self::get_shipping_rate_service_type( $rate );
		$label   = is_a( $rate, 'WC_Shipping_Rate' ) && method_exists( $rate, 'get_label' ) ? trim( wp_strip_all_tags( (string) $rate->get_label() ) ) : '';

		$carrier_title = $label;
		$service_title = $label;
		$description   = '';

		if ( 'yandex' === $carrier ) {
			$carrier_title = 'Яндекс.Доставка';
			$description   = 'pickup' === $type ? 'Самовывоз из пункта выдачи' : ( 'door' === $type ? 'Курьерская доставка по адресу выше' : '' );
		} elseif ( 'russian_post' === $carrier ) {
			$carrier_title = 'Почта России';
			$description   = 'pickup' === $type ? 'Пункт выдачи, отделение или почтомат' : ( 'door' === $type ? 'Курьерская доставка по адресу выше' : '' );
		} else {
			$carrier_title = $label ? $label : 'Доставка';
		}

		if ( 'pickup' === $type ) {
			$service_title = 'До ПВЗ';
		} elseif ( 'door' === $type ) {
			$service_title = 'До двери';
		} else {
			$service_title = $label ? $label : 'Способ доставки';
		}

		return array(
			'carrier'       => $carrier,
			'type'          => $type,
			'carrier_title' => $carrier_title,
			'service_title' => $service_title,
			'description'   => $description,
			'price_html'    => self::get_shipping_rate_price_html( $rate ),
		);
	}

	public static function get_shipping_rate_price_html( $rate ) {
		if ( ! is_a( $rate, 'WC_Shipping_Rate' ) ) {
			return '';
		}

		$cost  = (float) $rate->get_cost();
		$taxes = array_sum( array_map( 'floatval', (array) $rate->get_taxes() ) );

		if ( $cost <= 0 && $taxes <= 0 ) {
			return 'Бесплатно!';
		}

		$display_cost = $cost;
		if ( WC()->cart && WC()->cart->display_prices_including_tax() ) {
			$display_cost += $taxes;
		}

		return wc_price( $display_cost );
	}

	public static function sort_shipping_rates_for_cards( $rates ) {
		if ( ! is_array( $rates ) ) {
			return $rates;
		}

		uasort(
			$rates,
			function( $a, $b ) {
				$priority = array(
					'yandex_pickup'       => 10,
					'yandex_door'         => 20,
					'russian_post_pickup' => 30,
					'russian_post_door'   => 40,
					'yandex_other'        => 50,
					'russian_post_other'  => 60,
					'other_pickup'        => 90,
					'other_door'          => 91,
					'other_other'         => 100,
				);

				$a_key = self::get_shipping_rate_carrier( $a ) . '_' . self::get_shipping_rate_service_type( $a );
				$b_key = self::get_shipping_rate_carrier( $b ) . '_' . self::get_shipping_rate_service_type( $b );

				return ( $priority[ $a_key ] ?? 100 ) <=> ( $priority[ $b_key ] ?? 100 );
			}
		);

		return $rates;
	}

	private static function get_russian_post_cart_total_for_widget() {
		$total = 0;

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $total;
		}

		foreach ( WC()->cart->get_cart_contents() as $values ) {
			if ( empty( $values['data'] ) || ! is_a( $values['data'], 'WC_Product' ) ) {
				continue;
			}

			$product = $values['data'];
			if ( $product->needs_shipping() ) {
				$total += (float) $product->get_price() * max( 1, (int) $values['quantity'] );
			}
		}

		return (int) round( $total );
	}

	public static function get_russian_post_pickup_button_html( $rate ) {
		if ( ! is_a( $rate, 'WC_Shipping_Rate' ) || 'russian_post' !== self::get_shipping_rate_carrier( $rate ) || 'pickup' !== self::get_shipping_rate_service_type( $rate ) ) {
			return '';
		}

		if ( ! class_exists( '\\Woodev\\Russian_Post\\Classes\\Shipping_Rate_Meta_Data' ) ) {
			return '';
		}

		try {
			$rate_meta_data = new \Woodev\Russian_Post\Classes\Shipping_Rate_Meta_Data( $rate );
			$normalized     = $rate_meta_data->get_normalize_address();

			if ( ! $normalized ) {
				return '';
			}

			$button_text    = 'Выбрать пункт выдачи';
			$button_classes = array( 'wc-russian-post-choose-delivery-point' );
			$chosen_html    = '';

			if ( class_exists( '\\Woodev\\Russian_Post\\Classes\\Customer_Delivery_Point_Data' ) ) {
				$customer_delivery_point = new \Woodev\Russian_Post\Classes\Customer_Delivery_Point_Data( get_current_user_id() );

				if ( $customer_delivery_point->get_fias() && $customer_delivery_point->get_fias() === $normalized->get_place_guid() ) {
					$chosen_html      = sprintf( '<div class="wc-russian-post-chosen-address"><strong>%s</strong></div>', esc_html( $customer_delivery_point->get_normalize_address()->get_short_formatted_address() ) );
					$button_text      = 'Изменить пункт выдачи';
					$button_classes[] = 'wc-russian-post-choose-delivery-point--chosen';
				}
			}

			$button = sprintf(
				'<button type="button" class="button %1$s" data-zip_code="%2$d" data-cart_total="%3$d" data-start_location="%4$s">%5$s</button>',
				esc_attr( implode( ' ', $button_classes ) ),
				(int) $normalized->get_index(),
				(int) self::get_russian_post_cart_total_for_widget(),
				esc_attr( $normalized->get_formatted_address() ),
				esc_html( $button_text )
			);

			return $chosen_html . $button;
		} catch ( Exception $e ) {
			return '';
		}
	}

	/* ================================================================= */
	/*  v1.7.0 — Secure guest account registration offer                  */
	/* ================================================================= */

	private static function is_account_registration_offer_enabled() {
		return '1' === (string) self::get_setting( 'account_registration_enabled', '1' );
	}

	private static function get_account_creation_timing() {
		$timing = sanitize_key( (string) self::get_setting( 'account_creation_timing', 'paid' ) );
		return in_array( $timing, array( 'paid', 'order_created' ), true ) ? $timing : 'paid';
	}

	private static function get_account_creation_statuses() {
		$raw      = (string) self::get_setting( 'account_creation_statuses', 'processing,completed' );
		$statuses = array_filter( array_map( 'sanitize_key', preg_split( '/[\s,;]+/', $raw ) ) );
		return ! empty( $statuses ) ? array_values( array_unique( $statuses ) ) : array( 'processing', 'completed' );
	}

	private static function is_account_registration_requested_from_request() {
		if ( isset( $_POST['foneona_create_account'] ) ) {
			$value = wc_clean( wp_unslash( $_POST['foneona_create_account'] ) );
			return in_array( (string) $value, array( '1', 'yes', 'on', 'true' ), true );
		}

		return false;
	}

	private static function is_account_registration_requested_from_array( $data ) {
		if ( is_array( $data ) && ! empty( $data['foneona_create_account'] ) ) {
			return true;
		}

		return self::is_account_registration_requested_from_request();
	}

	private static function get_registration_email_from_checkout_data( $data ) {
		$email = '';
		if ( is_array( $data ) && isset( $data['billing_email'] ) ) {
			$email = sanitize_email( wp_unslash( $data['billing_email'] ) );
		}
		return $email;
	}

	public function filter_native_checkout_registration_enabled( $enabled ) {
		if ( self::is_checkout_context() && self::is_account_registration_offer_enabled() ) {
			return false;
		}
		return $enabled;
	}

	public function filter_native_checkout_registration_required( $required ) {
		if ( self::is_checkout_context() && self::is_account_registration_offer_enabled() ) {
			return false;
		}
		return $required;
	}

	public function render_account_registration_offer( $checkout ) {
		if ( is_user_logged_in() || ! self::is_account_registration_offer_enabled() ) {
			return;
		}

		$text = (string) self::get_setting( 'account_registration_text', '' );
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			$text = 'Создать личный кабинет по данным заказа.';
		}

		$posted_checked = isset( $_POST['foneona_create_account'] );
		$default_checked = '1' === (string) self::get_setting( 'account_registration_default_checked', '0' );
		$checked = $posted_checked || ( ! isset( $_POST['woocommerce-process-checkout-nonce'] ) && $default_checked );
		$login_url = function_exists( 'wc_get_checkout_url' ) ? wp_login_url( wc_get_checkout_url() ) : wp_login_url();
		?>
		<div class="foneona-account-offer">
			<label class="foneona-account-offer__label" for="foneona_create_account">
				<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
					   name="foneona_create_account"
					   type="checkbox"
					   id="foneona_create_account"
					   value="1"
					   <?php checked( $checked ); ?> />
				<span><?php echo wp_kses_post( $text ); ?></span>
			</label>
			<div class="foneona-account-offer__note">
				Аккаунт будет создан только если email ещё не зарегистрирован. Если личный кабинет уже есть, <a href="<?php echo esc_url( $login_url ); ?>">войдите в него</a> перед оплатой, чтобы заказ, баллы и реферальные данные привязались корректно.
			</div>
		</div>
		<?php
	}

	public function validate_account_registration_request( $data, $errors ) {
		if ( is_user_logged_in() || ! self::is_account_registration_offer_enabled() || ! $errors instanceof WP_Error ) {
			return;
		}

		$email = self::get_registration_email_from_checkout_data( $data );
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}

		$requested = self::is_account_registration_requested_from_array( $data );
		$existing_user = get_user_by( 'email', $email );

		if ( ! $existing_user ) {
			return;
		}

		$mode = (string) self::get_setting( 'account_registration_existing_email_mode', 'block_guest' );
		$must_block = ( 'block_guest' === $mode ) || ( $requested && 'block_if_requested' === $mode );

		if ( ! $must_block ) {
			return;
		}

		$errors->add(
			'foneona_existing_account_email',
			'На этот email уже зарегистрирован личный кабинет. Войдите в аккаунт перед оформлением заказа или используйте другой email. Так заказ, баллы и реферальные данные не попадут в чужой профиль.'
		);
	}

	public function save_account_registration_order_meta( $order, $data ) {
		if ( ! $order instanceof WC_Order || is_user_logged_in() || ! self::is_account_registration_offer_enabled() ) {
			return;
		}

		$email     = self::get_registration_email_from_checkout_data( $data );
		$requested = self::is_account_registration_requested_from_array( $data );

		$order->update_meta_data( '_foneona_account_registration_requested', $requested ? 'yes' : 'no' );
		$order->update_meta_data( '_foneona_account_registration_email', $email );

		if ( $requested ) {
			$order->update_meta_data( '_foneona_account_registration_status', 'pending' );
			$order->update_meta_data( '_foneona_account_creation_timing', self::get_account_creation_timing() );
			$order->update_meta_data( '_foneona_account_registration_requested_at', current_time( 'mysql' ) );
		}

		if ( $email ) {
			$existing_user = get_user_by( 'email', $email );
			if ( $existing_user ) {
				$order->update_meta_data( '_foneona_guest_existing_user_id', absint( $existing_user->ID ) );
			}
		}
	}

	public function maybe_create_account_after_order_processed( $order_id, $posted_data, $order ) {
		if ( 'order_created' !== self::get_account_creation_timing() ) {
			return;
		}

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		$this->maybe_create_account_for_order( $order, 'order_created' );
	}

	public function maybe_create_account_for_paid_order( $order_id ) {
		if ( 'paid' !== self::get_account_creation_timing() ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$this->maybe_create_account_for_order( $order, 'payment_complete' );
	}

	public function maybe_create_account_after_status_change( $order_id, $from_status, $to_status, $order ) {
		if ( 'paid' !== self::get_account_creation_timing() ) {
			return;
		}

		$new_status = sanitize_key( $to_status );
		if ( ! in_array( $new_status, self::get_account_creation_statuses(), true ) ) {
			return;
		}

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		$this->maybe_create_account_for_order( $order, 'status_' . $new_status );
	}

	private function maybe_create_account_for_order( $order, $trigger = '' ) {
		if ( ! $order instanceof WC_Order || ! self::is_account_registration_offer_enabled() ) {
			return false;
		}

		if ( 'yes' !== $order->get_meta( '_foneona_account_registration_requested', true ) ) {
			return false;
		}

		if ( absint( $order->get_meta( '_foneona_account_created_user_id', true ) ) ) {
			return false;
		}

		$status = (string) $order->get_meta( '_foneona_account_registration_status', true );
		if ( in_array( $status, array( 'created', 'blocked_email_exists' ), true ) ) {
			return false;
		}

		if ( $order->get_customer_id() ) {
			$order->update_meta_data( '_foneona_account_registration_status', 'skipped_order_already_has_user' );
			$order->save();
			return false;
		}

		$email = sanitize_email( $order->get_billing_email() );
		if ( '' === $email || ! is_email( $email ) ) {
			$order->update_meta_data( '_foneona_account_registration_status', 'failed_invalid_email' );
			$order->add_order_note( 'Автоматическая регистрация не выполнена: в заказе нет корректного email.' );
			$order->save();
			return false;
		}

		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			$order->update_meta_data( '_foneona_account_registration_status', 'blocked_email_exists' );
			$order->update_meta_data( '_foneona_guest_existing_user_id', absint( $existing_user->ID ) );
			$order->add_order_note( 'Автоматическая регистрация не выполнена: email заказа уже принадлежит существующему аккаунту. Заказ не привязан к этому аккаунту без авторизации покупателя.' );
			$order->save();
			return false;
		}

		$username = $this->generate_unique_username_from_email( $email );
		$password = wp_generate_password( 20, false, false );
		$args     = array(
			'first_name'   => sanitize_text_field( $order->get_billing_first_name() ),
			'last_name'    => sanitize_text_field( $order->get_billing_last_name() ),
			'display_name' => trim( sanitize_text_field( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ),
			'role'         => 'customer',
		);

		if ( function_exists( 'wc_create_new_customer' ) ) {
			$user_id = wc_create_new_customer( $email, $username, $password, $args );
		} else {
			$user_id = wp_insert_user( array_merge( $args, array( 'user_login' => $username, 'user_email' => $email, 'user_pass' => $password ) ) );
		}

		if ( is_wp_error( $user_id ) ) {
			$order->update_meta_data( '_foneona_account_registration_status', 'failed_create_user' );
			$order->update_meta_data( '_foneona_account_registration_error', $user_id->get_error_message() );
			$order->add_order_note( 'Автоматическая регистрация не выполнена: ' . $user_id->get_error_message() );
			$order->save();
			return false;
		}

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			$order->update_meta_data( '_foneona_account_registration_status', 'failed_empty_user_id' );
			$order->add_order_note( 'Автоматическая регистрация не выполнена: WordPress не вернул ID пользователя.' );
			$order->save();
			return false;
		}

		$this->copy_order_customer_data_to_user( $user_id, $order );
		$order->set_customer_id( $user_id );
		$order->update_meta_data( '_foneona_account_registration_status', 'created' );
		$order->update_meta_data( '_foneona_account_created_user_id', $user_id );
		$order->update_meta_data( '_foneona_account_created_at', current_time( 'mysql' ) );
		$order->update_meta_data( '_foneona_account_creation_trigger', sanitize_key( $trigger ) );
		$order->add_order_note( sprintf( 'Создан аккаунт покупателя #%d по email заказа. Заказ привязан к новому аккаунту.', $user_id ) );

		$this->link_rrp_profile_to_user_by_email( $email, $user_id );
		$this->send_account_temporary_password_email( $user_id, $password, $order );

		$order->save();
		$password = null;
		return true;
	}

	private function send_account_temporary_password_email( $user_id, $temporary_password, $order ) {
		$user_id = absint( $user_id );
		$order   = $order instanceof WC_Order ? $order : null;

		if ( ! $user_id || ! $order || '1' !== (string) self::get_setting( 'account_password_email_enabled', '1' ) ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || empty( $user->user_email ) || '' === (string) $temporary_password ) {
			return false;
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$my_account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
		if ( ! $my_account_url ) {
			$my_account_url = wp_login_url();
		}

		$text_replacements = array(
			'{site_name}'            => $site_name,
			'{customer_first_name}'  => $order->get_billing_first_name() ? $order->get_billing_first_name() : $user->display_name,
			'{customer_name}'        => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'{customer_email}'       => $user->user_email,
			'{username}'             => $user->user_login,
			'{temporary_password}'   => (string) $temporary_password,
			'{order_number}'         => $order->get_order_number(),
			'{my_account_url}'       => esc_url_raw( $my_account_url ),
			'{change_password_note}' => 'После входа вы сможете изменить пароль в личном кабинете.',
		);

		$subject_template = (string) self::get_setting( 'account_password_email_subject', 'Ваш личный кабинет на сайте {site_name}' );
		$body_template    = (string) self::get_setting( 'account_password_email_message', '' );
		$css_template     = (string) self::get_setting( 'account_password_email_css', '' );

		$subject = wp_strip_all_tags( strtr( $subject_template, $text_replacements ) );
		$body    = self::build_account_password_email_html( $body_template, $css_template, $text_replacements );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $user->user_email, $subject, $body, $headers );

		if ( $sent ) {
			$order->update_meta_data( '_foneona_account_password_email_sent', 'yes' );
			$order->update_meta_data( '_foneona_account_password_email_sent_at', current_time( 'mysql' ) );
			$order->add_order_note( 'Покупателю отправлено письмо с временным паролем от созданного личного кабинета.' );
		} else {
			$order->update_meta_data( '_foneona_account_password_email_sent', 'no' );
			$order->add_order_note( 'Аккаунт создан, но письмо с временным паролем не было отправлено. Проверьте настройки wp_mail/SMTP.' );
		}

		return (bool) $sent;
	}

	private function generate_unique_username_from_email( $email ) {
		$email = sanitize_email( $email );
		$parts = explode( '@', $email );
		$base  = sanitize_user( isset( $parts[0] ) ? $parts[0] : 'customer', true );

		if ( '' === $base ) {
			$base = 'customer';
		}

		$username = $base;
		$index    = 1;

		while ( username_exists( $username ) ) {
			$username = $base . $index;
			$index++;
		}

		return $username;
	}

	private function copy_order_customer_data_to_user( $user_id, $order ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! $order instanceof WC_Order ) {
			return;
		}

		$first_name = sanitize_text_field( $order->get_billing_first_name() );
		$last_name  = sanitize_text_field( $order->get_billing_last_name() );

		wp_update_user( array(
			'ID'           => $user_id,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'display_name' => trim( $first_name . ' ' . $last_name ),
		) );

		$fields = array(
			'billing_first_name' => $order->get_billing_first_name(),
			'billing_last_name'  => $order->get_billing_last_name(),
			'billing_company'    => $order->get_billing_company(),
			'billing_address_1'  => $order->get_billing_address_1(),
			'billing_address_2'  => $order->get_billing_address_2(),
			'billing_city'       => $order->get_billing_city(),
			'billing_state'      => $order->get_billing_state(),
			'billing_postcode'   => $order->get_billing_postcode(),
			'billing_country'    => $order->get_billing_country(),
			'billing_email'      => $order->get_billing_email(),
			'billing_phone'      => $order->get_billing_phone(),
			'shipping_first_name'=> $order->get_shipping_first_name(),
			'shipping_last_name' => $order->get_shipping_last_name(),
			'shipping_company'   => $order->get_shipping_company(),
			'shipping_address_1' => $order->get_shipping_address_1(),
			'shipping_address_2' => $order->get_shipping_address_2(),
			'shipping_city'      => $order->get_shipping_city(),
			'shipping_state'     => $order->get_shipping_state(),
			'shipping_postcode'  => $order->get_shipping_postcode(),
			'shipping_country'   => $order->get_shipping_country(),
		);

		foreach ( $fields as $key => $value ) {
			if ( '' !== (string) $value ) {
				update_user_meta( $user_id, $key, wc_clean( $value ) );
			}
		}
	}

	private function link_rrp_profile_to_user_by_email( $email, $user_id ) {
		global $wpdb;

		$email   = sanitize_email( strtolower( (string) $email ) );
		$user_id = absint( $user_id );

		if ( ! $email || ! $user_id ) {
			return false;
		}

		$table = $wpdb->prefix . 'rrp_profiles';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return false;
		}

		$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ), ARRAY_A );
		if ( ! is_array( $profile ) ) {
			return false;
		}

		$profile_id      = absint( $profile['id'] ?? 0 );
		$profile_user_id = absint( $profile['user_id'] ?? 0 );

		if ( ! $profile_id || ( $profile_user_id && $profile_user_id !== $user_id ) ) {
			return false;
		}

		if ( ! $profile_user_id ) {
			$wpdb->update(
				$table,
				array( 'user_id' => $user_id, 'updated_at' => current_time( 'mysql' ) ),
				array( 'id' => $profile_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		update_user_meta( $user_id, '_rrp_profile_id', $profile_id );
		return true;
	}

	/* ================================================================= */
	/*  v1.5.0 — Three consent checkboxes                               */
	/*  checkbox1 = Персональные данные (обязательный)                   */
	/*  checkbox2 = Публичная оферта    (обязательный)                   */
	/*  checkbox3 = Рекламные рассылки  (необязательный)                 */
	/* ================================================================= */

	public function render_custom_privacy_checkboxes() {
		$checkboxes = array(
			array(
				'key'      => 'foneona_consent_personal_data',
				'setting'  => 'checkbox1_text',
				'required' => true,
			),
			array(
				'key'      => 'foneona_consent_offer',
				'setting'  => 'checkbox2_text',
				'required' => true,
			),
			array(
				'key'      => 'foneona_consent_marketing',
				'setting'  => 'checkbox3_text',
				'required' => false,
			),
		);

		foreach ( $checkboxes as $cb ) {
			$text = (string) self::get_setting( $cb['setting'], '' );
			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}

			$req_class = $cb['required'] ? ' validate-required' : '';
			$req_star  = $cb['required'] ? ' <abbr class="required" title="обязательно">*</abbr>' : '';
			?>
			<p class="form-row foneona-privacy-consent<?php echo esc_attr( $req_class ); ?>">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox" for="<?php echo esc_attr( $cb['key'] ); ?>">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
						   name="<?php echo esc_attr( $cb['key'] ); ?>"
						   type="checkbox"
						   id="<?php echo esc_attr( $cb['key'] ); ?>"
						   value="1"
						   <?php checked( ! empty( $_POST[ $cb['key'] ] ) ); ?> />
					<span><?php echo wp_kses_post( $text ); ?><?php echo $req_star; ?></span>
				</label>
			</p>
			<?php
		}
	}

	public function validate_custom_privacy_checkboxes() {
		$text1 = (string) self::get_setting( 'checkbox1_text', '' );
		if ( '' !== trim( wp_strip_all_tags( $text1 ) ) && empty( $_POST['foneona_consent_personal_data'] ) ) {
			wc_add_notice( 'Подтвердите согласие на обработку персональных данных.', 'error' );
		}

		$text2 = (string) self::get_setting( 'checkbox2_text', '' );
		if ( '' !== trim( wp_strip_all_tags( $text2 ) ) && empty( $_POST['foneona_consent_offer'] ) ) {
			wc_add_notice( 'Необходимо принять условия Публичной оферты.', 'error' );
		}
	}

	public function save_custom_privacy_checkboxes( $order, $data ) {
		if ( ! empty( $_POST['foneona_consent_personal_data'] ) ) {
			$order->update_meta_data( '_foneona_consent_personal_data', 'yes' );
		}
		if ( ! empty( $_POST['foneona_consent_offer'] ) ) {
			$order->update_meta_data( '_foneona_consent_offer', 'yes' );
		}
		if ( ! empty( $_POST['foneona_consent_marketing'] ) ) {
			$order->update_meta_data( '_foneona_consent_marketing', 'yes' );
		}
	}

	/* ================================================================= */
	/*  Admin settings                                                   */
	/* ================================================================= */

	public function register_admin_menu() {
		add_options_page(
			'Foneona Woo Layout',
			'Foneona Woo Layout',
			'manage_options',
			'foneona-woo-layout',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'foneona_woo_layout_settings_group',
			self::$settings_option,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'foneona_woo_layout_main_section',
			'Настройки checkout/cart',
			'__return_false',
			'foneona-woo-layout'
		);

		add_settings_field( 'checkbox1_text', 'Галочка 1 — Согласие на обработку ПД (обязательная)', array( $this, 'render_checkbox1_text_field' ), 'foneona-woo-layout', 'foneona_woo_layout_main_section' );
		add_settings_field( 'checkbox2_text', 'Галочка 2 — Публичная оферта (обязательная)', array( $this, 'render_checkbox2_text_field' ), 'foneona-woo-layout', 'foneona_woo_layout_main_section' );
		add_settings_field( 'checkbox3_text', 'Галочка 3 — Рекламные сообщения (необязательная)', array( $this, 'render_checkbox3_text_field' ), 'foneona-woo-layout', 'foneona_woo_layout_main_section' );
		add_settings_field( 'dadata_enabled', 'Включить DaData', array( $this, 'render_dadata_enabled_field' ), 'foneona-woo-layout', 'foneona_woo_layout_main_section' );
		add_settings_field( 'dadata_api_key', 'DaData API-ключ', array( $this, 'render_dadata_api_key_field' ), 'foneona-woo-layout', 'foneona_woo_layout_main_section' );
		add_settings_field( 'dadata_secret_key', 'DaData Secret key', array( $this, 'render_dadata_secret_key_field' ), 'foneona-woo-layout', 'foneona_woo_layout_main_section' );
		add_settings_field( 'dadata_placeholder', 'Плейсхолдер поля DaData', array( $this, 'render_dadata_placeholder_field' ), 'foneona-woo-layout', 'foneona_woo_layout_main_section' );

		add_settings_section(
			'foneona_woo_layout_shipping_section',
			'Карточки доставки на checkout',
			array( $this, 'render_shipping_cards_section_description' ),
			'foneona-woo-layout'
		);

		add_settings_field( 'shipping_cards_enabled', 'Визуальные карточки доставки', array( $this, 'render_shipping_cards_enabled_field' ), 'foneona-woo-layout', 'foneona_woo_layout_shipping_section' );
		add_settings_field( 'show_yandex_delivery', 'Яндекс.Доставка', array( $this, 'render_show_yandex_delivery_field' ), 'foneona-woo-layout', 'foneona_woo_layout_shipping_section' );
		add_settings_field( 'show_russian_post_delivery', 'Почта России', array( $this, 'render_show_russian_post_delivery_field' ), 'foneona-woo-layout', 'foneona_woo_layout_shipping_section' );

		add_settings_section(
			'foneona_woo_layout_account_section',
			'Регистрация аккаунта при оформлении заказа',
			array( $this, 'render_account_registration_section_description' ),
			'foneona-woo-layout'
		);

		add_settings_field( 'account_registration_enabled', 'Предлагать создать аккаунт', array( $this, 'render_account_registration_enabled_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_registration_default_checked', 'Галочка включена по умолчанию', array( $this, 'render_account_registration_default_checked_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_registration_text', 'Текст предложения', array( $this, 'render_account_registration_text_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_registration_existing_email_mode', 'Если email уже зарегистрирован', array( $this, 'render_account_registration_existing_email_mode_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_creation_timing', 'Когда создавать аккаунт', array( $this, 'render_account_creation_timing_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_creation_statuses', 'Статусы успешной оплаты', array( $this, 'render_account_creation_statuses_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_password_email_enabled', 'Письмо с временным паролем', array( $this, 'render_account_password_email_enabled_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_password_email_subject', 'Тема письма с паролем', array( $this, 'render_account_password_email_subject_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_password_email_message', 'HTML и визуальный редактор письма', array( $this, 'render_account_password_email_message_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
		add_settings_field( 'account_password_email_css', 'CSS письма и широкий предпросмотр', array( $this, 'render_account_password_email_css_field' ), 'foneona-woo-layout', 'foneona_woo_layout_account_section' );
	}

	public function sanitize_settings( $input ) {
		$sanitized = self::get_settings();
		$input     = is_array( $input ) ? $input : array();

		$sanitized['checkbox1_text']    = isset( $input['checkbox1_text'] ) ? wp_kses_post( wp_unslash( $input['checkbox1_text'] ) ) : $sanitized['checkbox1_text'];
		$sanitized['checkbox2_text']    = isset( $input['checkbox2_text'] ) ? wp_kses_post( wp_unslash( $input['checkbox2_text'] ) ) : $sanitized['checkbox2_text'];
		$sanitized['checkbox3_text']    = isset( $input['checkbox3_text'] ) ? wp_kses_post( wp_unslash( $input['checkbox3_text'] ) ) : $sanitized['checkbox3_text'];
		$sanitized['dadata_enabled']    = ! empty( $input['dadata_enabled'] ) ? '1' : '0';
		$sanitized['dadata_api_key']    = isset( $input['dadata_api_key'] ) ? sanitize_text_field( wp_unslash( $input['dadata_api_key'] ) ) : '';
		$sanitized['dadata_secret_key'] = isset( $input['dadata_secret_key'] ) ? sanitize_text_field( wp_unslash( $input['dadata_secret_key'] ) ) : '';
		$sanitized['dadata_placeholder']= isset( $input['dadata_placeholder'] ) ? sanitize_text_field( wp_unslash( $input['dadata_placeholder'] ) ) : 'Начните вводить адрес';
		$sanitized['shipping_cards_enabled']      = ! empty( $input['shipping_cards_enabled'] ) ? '1' : '0';
		$sanitized['show_yandex_delivery']        = ! empty( $input['show_yandex_delivery'] ) ? '1' : '0';
		$sanitized['show_russian_post_delivery']  = ! empty( $input['show_russian_post_delivery'] ) ? '1' : '0';

		$existing_email_modes = array( 'block_guest', 'block_if_requested' );
		$creation_timings     = array( 'paid', 'order_created' );

		$sanitized['account_registration_enabled'] = ! empty( $input['account_registration_enabled'] ) ? '1' : '0';
		$sanitized['account_registration_default_checked'] = ! empty( $input['account_registration_default_checked'] ) ? '1' : '0';
		$sanitized['account_registration_text'] = isset( $input['account_registration_text'] ) ? wp_kses_post( wp_unslash( $input['account_registration_text'] ) ) : $sanitized['account_registration_text'];

		$existing_email_mode = isset( $input['account_registration_existing_email_mode'] ) ? sanitize_key( wp_unslash( $input['account_registration_existing_email_mode'] ) ) : 'block_guest';
		$sanitized['account_registration_existing_email_mode'] = in_array( $existing_email_mode, $existing_email_modes, true ) ? $existing_email_mode : 'block_guest';

		$creation_timing = isset( $input['account_creation_timing'] ) ? sanitize_key( wp_unslash( $input['account_creation_timing'] ) ) : 'paid';
		$sanitized['account_creation_timing'] = in_array( $creation_timing, $creation_timings, true ) ? $creation_timing : 'paid';

		$statuses_raw = isset( $input['account_creation_statuses'] ) ? sanitize_text_field( wp_unslash( $input['account_creation_statuses'] ) ) : 'processing,completed';
		$statuses     = array_filter( array_map( 'sanitize_key', preg_split( '/[\s,;]+/', $statuses_raw ) ) );
		$sanitized['account_creation_statuses'] = ! empty( $statuses ) ? implode( ',', array_unique( $statuses ) ) : 'processing,completed';

		$sanitized['account_password_email_enabled'] = ! empty( $input['account_password_email_enabled'] ) ? '1' : '0';
		$sanitized['account_password_email_subject'] = isset( $input['account_password_email_subject'] ) ? sanitize_text_field( wp_unslash( $input['account_password_email_subject'] ) ) : $sanitized['account_password_email_subject'];
		$sanitized['account_password_email_message'] = isset( $input['account_password_email_message'] ) ? wp_kses_post( wp_unslash( $input['account_password_email_message'] ) ) : $sanitized['account_password_email_message'];
		$sanitized['account_password_email_css']     = isset( $input['account_password_email_css'] ) ? self::sanitize_email_css( wp_unslash( $input['account_password_email_css'] ) ) : $sanitized['account_password_email_css'];

		return $sanitized;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap foneona-admin-settings">
			<h1>Foneona Woo Layout</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'foneona_woo_layout_settings_group' );
				do_settings_sections( 'foneona-woo-layout' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_checkbox1_text_field() {
		$value = (string) self::get_setting( 'checkbox1_text', '' );
		?>
		<textarea name="<?php echo esc_attr( self::$settings_option ); ?>[checkbox1_text]" rows="4" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">Обязательная галочка. Можно использовать HTML-ссылки: <code>&lt;a href="URL"&gt;текст&lt;/a&gt;</code></p>
		<?php
	}

	public function render_checkbox2_text_field() {
		$value = (string) self::get_setting( 'checkbox2_text', '' );
		?>
		<textarea name="<?php echo esc_attr( self::$settings_option ); ?>[checkbox2_text]" rows="4" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">Обязательная галочка. Вставьте ссылку на страницу Публичной оферты.</p>
		<?php
	}

	public function render_checkbox3_text_field() {
		$value = (string) self::get_setting( 'checkbox3_text', '' );
		?>
		<textarea name="<?php echo esc_attr( self::$settings_option ); ?>[checkbox3_text]" rows="4" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">Необязательная галочка. Оставьте пустым, чтобы не показывать.</p>
		<?php
	}

	public function render_dadata_enabled_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::$settings_option ); ?>[dadata_enabled]" value="1" <?php checked( '1', (string) self::get_setting( 'dadata_enabled', '0' ) ); ?> />
			Включить быстрый поиск адреса через DaData
		</label>
		<p class="description">На Checkout, если Яндекс.Доставка имеет свой DaData-токен, Foneona DaData автоматически отключается для избежания конфликта.</p>
		<?php
	}

	public function render_dadata_api_key_field() {
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::$settings_option ); ?>[dadata_api_key]" value="<?php echo esc_attr( self::get_setting( 'dadata_api_key', '' ) ); ?>" autocomplete="off" />
		<?php
	}

	public function render_dadata_secret_key_field() {
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::$settings_option ); ?>[dadata_secret_key]" value="<?php echo esc_attr( self::get_setting( 'dadata_secret_key', '' ) ); ?>" autocomplete="off" />
		<p class="description">Необязательно.</p>
		<?php
	}

	public function render_dadata_placeholder_field() {
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::$settings_option ); ?>[dadata_placeholder]" value="<?php echo esc_attr( self::get_setting( 'dadata_placeholder', 'Начните вводить адрес' ) ); ?>" />
		<?php
	}

	public function render_shipping_cards_section_description() {
		echo '<p>Настройки управляют красивыми карточками способов доставки на странице оформления заказа и позволяют принудительно скрывать группы методов доставки, даже если соответствующий плагин активен.</p>';
	}

	public function render_shipping_cards_enabled_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::$settings_option ); ?>[shipping_cards_enabled]" value="1" <?php checked( '1', (string) self::get_setting( 'shipping_cards_enabled', '1' ) ); ?> />
			Показывать способы доставки карточками: 2 карточки в ряд на десктопе, 1 карточка в ряд на мобильных.
		</label>
		<?php
	}

	public function render_show_yandex_delivery_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::$settings_option ); ?>[show_yandex_delivery]" value="1" <?php checked( '1', (string) self::get_setting( 'show_yandex_delivery', '1' ) ); ?> />
			Показывать методы Яндекс.Доставки на checkout. Если снять галочку, все найденные методы Яндекс.Доставки будут скрыты из расчёта WooCommerce.
		</label>
		<?php
	}

	public function render_show_russian_post_delivery_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::$settings_option ); ?>[show_russian_post_delivery]" value="1" <?php checked( '1', (string) self::get_setting( 'show_russian_post_delivery', '1' ) ); ?> />
			Показывать методы Почты России/WooDev: до ПВЗ/отделения и курьером до двери. Если снять галочку, эти методы будут скрыты из расчёта WooCommerce.
		</label>
		<?php
	}

	public function render_account_registration_section_description() {
		echo '<p>Эти настройки добавляют собственное предложение создать личный кабинет на checkout и отключают стандартный блок регистрации WooCommerce, чтобы аккаунт не создавался до оплаты без контроля. По умолчанию аккаунт создаётся только после успешной оплаты.</p>';
	}

	public function render_account_registration_enabled_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::$settings_option ); ?>[account_registration_enabled]" value="1" <?php checked( '1', (string) self::get_setting( 'account_registration_enabled', '1' ) ); ?> />
			Показывать гостям предложение создать аккаунт на странице оформления заказа
		</label>
		<?php
	}

	public function render_account_registration_default_checked_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::$settings_option ); ?>[account_registration_default_checked]" value="1" <?php checked( '1', (string) self::get_setting( 'account_registration_default_checked', '0' ) ); ?> />
			Сразу отмечать галочку создания аккаунта
		</label>
		<p class="description">Безопаснее оставлять выключенной: регистрация будет только по явному согласию покупателя.</p>
		<?php
	}

	public function render_account_registration_text_field() {
		$value = (string) self::get_setting( 'account_registration_text', '' );
		?>
		<textarea name="<?php echo esc_attr( self::$settings_option ); ?>[account_registration_text]" rows="4" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">Можно использовать безопасные HTML-ссылки. Текст показывается рядом с галочкой регистрации.</p>
		<?php
	}

	public function render_account_registration_existing_email_mode_field() {
		$value = (string) self::get_setting( 'account_registration_existing_email_mode', 'block_guest' );
		?>
		<select name="<?php echo esc_attr( self::$settings_option ); ?>[account_registration_existing_email_mode]">
			<option value="block_guest" <?php selected( 'block_guest', $value ); ?>>Запрещать гостевой заказ с email существующего аккаунта</option>
			<option value="block_if_requested" <?php selected( 'block_if_requested', $value ); ?>>Запрещать только если покупатель просит создать аккаунт</option>
		</select>
		<p class="description">Для сайта с баллами и реферальной системой безопаснее первый вариант: покупатель должен войти в свой аккаунт, а не оформлять гостевой заказ под чужим email.</p>
		<?php
	}

	public function render_account_creation_timing_field() {
		$value = (string) self::get_setting( 'account_creation_timing', 'paid' );
		?>
		<select name="<?php echo esc_attr( self::$settings_option ); ?>[account_creation_timing]">
			<option value="paid" <?php selected( 'paid', $value ); ?>>После успешной оплаты / перехода заказа в оплаченный статус</option>
			<option value="order_created" <?php selected( 'order_created', $value ); ?>>Сразу после создания заказа</option>
		</select>
		<p class="description">Рекомендуется вариант после оплаты: он снижает риск фейковых аккаунтов и злоупотреблений баллами.</p>
		<?php
	}

	public function render_account_creation_statuses_field() {
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::$settings_option ); ?>[account_creation_statuses]" value="<?php echo esc_attr( self::get_setting( 'account_creation_statuses', 'processing,completed' ) ); ?>" />
		<p class="description">Через запятую, без префикса wc-. Например: processing,completed. Используется только для режима «после оплаты».</p>
		<?php
	}

	public function render_account_password_email_enabled_field() {
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::$settings_option ); ?>[account_password_email_enabled]" value="1" <?php checked( '1', (string) self::get_setting( 'account_password_email_enabled', '1' ) ); ?> />
			Отправлять покупателю отдельное письмо с логином и временным паролем после создания аккаунта
		</label>
		<p class="description">Пароль не сохраняется в метаполях заказа и используется только в момент отправки письма.</p>
		<?php
	}

	public function render_account_password_email_subject_field() {
		?>
		<input type="text" class="regular-text" name="<?php echo esc_attr( self::$settings_option ); ?>[account_password_email_subject]" value="<?php echo esc_attr( self::get_setting( 'account_password_email_subject', 'Ваш личный кабинет на сайте {site_name}' ) ); ?>" />
		<p class="description">Переменные: <code>{site_name}</code>, <code>{order_number}</code>, <code>{customer_email}</code>.</p>
		<?php
	}

	public function render_account_password_email_message_field() {
		$value = (string) self::get_setting( 'account_password_email_message', '' );
		?>
		<div class="foneona-email-editor">
			<?php
			wp_editor(
				$value,
				'foneona_account_password_email_message',
				array(
					'textarea_name' => self::$settings_option . '[account_password_email_message]',
					'textarea_rows' => 18,
					'editor_height' => 420,
					'media_buttons' => true,
					'teeny'         => false,
					'quicktags'     => true,
					'tinymce'       => array(
						'wpautop'       => true,
						'height'        => 420,
						'toolbar1'      => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,backcolor,removeformat,undo,redo',
						'toolbar2'      => 'table,hr,pastetext,charmap,outdent,indent,wp_more,spellchecker,fullscreen,wp_adv',
					),
				)
			);
			?>
		</div>
		<p class="description">Визуальный редактор редактирует HTML-шаблон письма. Переменные: <code>{site_name}</code>, <code>{customer_first_name}</code>, <code>{customer_name}</code>, <code>{customer_email}</code>, <code>{username}</code>, <code>{temporary_password}</code>, <code>{order_number}</code>, <code>{my_account_url}</code>, <code>{change_password_note}</code>.</p>
		<?php
	}

	public function render_account_password_email_css_field() {
		$css          = (string) self::get_setting( 'account_password_email_css', '' );
		$preview_html = self::build_account_password_email_html( (string) self::get_setting( 'account_password_email_message', '' ), $css, self::get_sample_email_replacements() );
		?>
		<div class="foneona-email-template-wide">
			<label for="foneona_account_password_email_css" class="foneona-email-template-wide__label">CSS шаблона письма</label>
			<textarea id="foneona_account_password_email_css" name="<?php echo esc_attr( self::$settings_option ); ?>[account_password_email_css]" rows="12" class="large-text code foneona-email-css-field"><?php echo esc_textarea( $css ); ?></textarea>
			<p class="description">CSS добавляется в <code>&lt;style&gt;</code> внутри HTML-письма. Для совместимости с почтовыми клиентами основные стили лучше задавать простыми селекторами и дублировать важные стили в HTML-элементах.</p>

			<div class="foneona-email-preview">
				<div class="foneona-email-preview__head">
					<strong>Широкий предпросмотр письма</strong>
					<span>Плейсхолдеры заменяются тестовыми данными, чтобы было видно реальный вид письма.</span>
				</div>
				<iframe id="foneona-email-preview-frame" title="Предпросмотр письма" class="foneona-email-preview__frame" srcdoc="<?php echo esc_attr( $preview_html ); ?>"></iframe>
			</div>
		</div>
		<?php
	}

	private static function sanitize_email_css( $css ) {
		$css = is_string( $css ) ? $css : '';
		$css = wp_strip_all_tags( $css, true );
		$css = str_replace( array( '<', '>' ), '', $css );
		return trim( $css );
	}

	private static function get_sample_email_replacements() {
		return array(
			'{site_name}'            => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{customer_first_name}'  => 'Анна',
			'{customer_name}'        => 'Анна Иванова',
			'{customer_email}'       => 'anna@example.com',
			'{username}'             => 'anna',
			'{temporary_password}'   => 'Temp-1234',
			'{order_number}'         => '12345',
			'{my_account_url}'       => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url(),
			'{change_password_note}' => 'После входа вы сможете изменить пароль в личном кабинете.',
		);
	}

	private static function build_account_password_email_html( $body_template, $css, $replacements ) {
		if ( '' === trim( wp_strip_all_tags( (string) $body_template ) ) ) {
			$body_template = '<div class="foneona-email-card"><p>Здравствуйте, {customer_first_name}!</p><p>Мы создали для вас личный кабинет по данным заказа № {order_number}.</p><p><strong>Логин:</strong> {customer_email}<br><strong>Временный пароль:</strong> {temporary_password}</p><p>{change_password_note}</p><p><a class="foneona-email-button" href="{my_account_url}">Войти в личный кабинет</a></p></div>';
		}

		$html_replacements = array();
		foreach ( (array) $replacements as $placeholder => $value ) {
			$html_replacements[ $placeholder ] = ( '{my_account_url}' === $placeholder ) ? esc_url( $value ) : esc_html( $value );
		}

		$body = strtr( (string) $body_template, $html_replacements );
		$body = wpautop( wp_kses_post( $body ) );
		$css  = self::sanitize_email_css( $css );

		return '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><style type="text/css">' . $css . '</style></head><body><div class="foneona-email-wrap">' . $body . '</div></body></html>';
	}

	/* ================================================================= */
	/*  DaData AJAX handler                                              */
	/* ================================================================= */

	public function ajax_dadata_suggest_address() {
		check_ajax_referer( 'foneona_dadata_nonce', 'nonce' );

		if ( ! self::is_dadata_enabled() ) {
			wp_send_json_error( array( 'message' => 'DaData is disabled.' ), 400 );
		}

		$query           = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$query           = trim( $query );
		$mode            = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'address';
		$location_city   = isset( $_POST['location_city'] ) ? sanitize_text_field( wp_unslash( $_POST['location_city'] ) ) : '';
		$location_region = isset( $_POST['location_region'] ) ? sanitize_text_field( wp_unslash( $_POST['location_region'] ) ) : '';

		if ( mb_strlen( $query ) < 3 ) {
			wp_send_json_success( array( 'suggestions' => array() ) );
		}

		$headers = array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Authorization' => 'Token ' . trim( (string) self::get_setting( 'dadata_api_key', '' ) ),
		);

		$secret_key = trim( (string) self::get_setting( 'dadata_secret_key', '' ) );
		if ( '' !== $secret_key ) {
			$headers['X-Secret'] = $secret_key;
		}

		$payload = array(
			'query' => $query,
			'count' => 7,
		);

		if ( 'address' === $mode ) {
			$locations = array();
			$base_location = array( 'country_iso_code' => self::get_fixed_country_code() );
			if ( '' !== $location_region ) {
				$base_location['region'] = $location_region;
			}
			if ( '' !== $location_city ) {
				$city_location = $base_location;
				$city_location['city'] = $location_city;
				$locations[] = $city_location;

				$settlement_location = $base_location;
				$settlement_location['settlement'] = $location_city;
				$locations[] = $settlement_location;
			} else {
				$locations[] = $base_location;
			}

			$payload['locations'] = $locations;
		}

		$response = wp_remote_post(
			'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address',
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ), 500 );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $body ) ) {
			wp_send_json_error( array( 'message' => 'Invalid DaData response.' ), 500 );
		}

		$suggestions = array();
		foreach ( (array) ( $body['suggestions'] ?? array() ) as $item ) {
			$data = isset( $item['data'] ) && is_array( $item['data'] ) ? $item['data'] : array();
			if ( ! empty( $data['country_iso_code'] ) && self::get_fixed_country_code() !== $data['country_iso_code'] ) {
				continue;
			}

			$has_city_level    = ! empty( $data['city'] ) || ! empty( $data['settlement'] );
			$has_address_level = ! empty( $data['street'] ) || ! empty( $data['house'] ) || ! empty( $data['flat'] ) || ! empty( $data['block'] );

			if ( 'city' === $mode && ( ! $has_city_level || $has_address_level ) ) {
				continue;
			}

			if ( 'address' === $mode && ! $has_address_level ) {
				continue;
			}

			$suggestions[] = array(
				'value'              => isset( $item['value'] ) ? (string) $item['value'] : '',
				'unrestricted_value' => isset( $item['unrestricted_value'] ) ? (string) $item['unrestricted_value'] : '',
				'data'               => array(
					'postal_code'          => (string) ( $data['postal_code'] ?? '' ),
					'country'              => (string) ( $data['country'] ?? '' ),
					'country_iso_code'     => (string) ( $data['country_iso_code'] ?? '' ),
					'region'               => (string) ( $data['region'] ?? '' ),
					'region_with_type'     => (string) ( $data['region_with_type'] ?? '' ),
					'area'                 => (string) ( $data['area'] ?? '' ),
					'area_with_type'       => (string) ( $data['area_with_type'] ?? '' ),
					'city'                 => (string) ( $data['city'] ?? '' ),
					'city_with_type'       => (string) ( $data['city_with_type'] ?? '' ),
					'settlement'           => (string) ( $data['settlement'] ?? '' ),
					'settlement_with_type' => (string) ( $data['settlement_with_type'] ?? '' ),
					'street'               => (string) ( $data['street'] ?? '' ),
					'street_with_type'     => (string) ( $data['street_with_type'] ?? '' ),
					'house'                => (string) ( $data['house'] ?? '' ),
					'block'                => (string) ( $data['block'] ?? '' ),
					'block_type'           => (string) ( $data['block_type'] ?? '' ),
					'flat'                 => (string) ( $data['flat'] ?? '' ),
					'flat_type'            => (string) ( $data['flat_type'] ?? '' ),
					'entrance'             => (string) ( $data['entrance'] ?? '' ),
					'floor'                => (string) ( $data['floor'] ?? '' ),
				),
			);
		}

		wp_send_json_success( array( 'suggestions' => array_values( $suggestions ) ) );
	}

	/* ================================================================= */
	/*  Shipping methods block                                           */
	/* ================================================================= */

	/**
	 * Some delivery plugins print pickup controls on
	 * woocommerce_review_order_after_shipping as table rows. In the custom
	 * checkout this block is no longer a table, so those rows are converted to
	 * safe div markup. Russian Post controls are skipped here because the postal
	 * card renders its own compatible button. Yandex/global pickup controls are
	 * preserved so the buyer does not lose the "Выбрать пункт выдачи" button.
	 */
	private static function normalize_shipping_after_actions_html( $html ) {
		$html = (string) $html;

		if ( '' === trim( $html ) ) {
			return '';
		}

		$html = preg_replace( '/<tr\b[^>]*class=(\"|\')[^\"\']*cart-delivery-points[^\"\']*\1[^>]*>.*?wc-russian-post-choose-delivery-point.*?<\/tr>/is', '', $html );
		$html = preg_replace( '/<th\b[^>]*>.*?<\/th>/is', '', $html );
		$html = preg_replace( '/<tr\b[^>]*>/i', '<div class="foneona-shipping-global-action">', $html );
		$html = preg_replace( '/<\/tr>/i', '</div>', $html );
		$html = preg_replace( '/<td\b[^>]*>/i', '<div class="foneona-shipping-global-action__content">', $html );
		$html = preg_replace( '/<\/td>/i', '</div>', $html );

		return trim( $html );
	}

	private static function get_after_shipping_actions_html_for_cards() {
		ob_start();
		do_action( 'woocommerce_review_order_after_shipping' );
		$html = ob_get_clean();

		return self::normalize_shipping_after_actions_html( $html );
	}

	/**
	 * v1.5.0 — The outer #foneona-shipping-methods div is ALWAYS rendered
	 * so AJAX fragment replacement always finds it.
	 */
	public static function get_shipping_methods_block_html() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '<div id="foneona-shipping-methods" class="foneona-shipping-methods"></div>';
		}

		ob_start();
		echo '<div id="foneona-shipping-methods" class="foneona-shipping-methods">';

		if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) {
			if ( self::is_checkout_shipping_render_context() ) {
				echo '<div class="foneona-shipping-methods__cards-render">';
				do_action( 'woocommerce_review_order_before_shipping' );
				wc_cart_totals_shipping_html();

				$after_shipping_html = self::get_after_shipping_actions_html_for_cards();
				if ( '' !== trim( wp_strip_all_tags( $after_shipping_html ) ) ) {
					echo '<div class="foneona-shipping-methods__global-actions" data-foneona-shipping-global-actions="1">';
					echo $after_shipping_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '</div>';
				}

				echo '</div>';
			} else {
				echo '<table class="shop_table foneona-shipping-methods__table" cellspacing="0"><tbody>';
				do_action( 'woocommerce_review_order_before_shipping' );
				wc_cart_totals_shipping_html();
				do_action( 'woocommerce_review_order_after_shipping' );
				echo '</tbody></table>';
			}
		} else {
			echo '<div class="foneona-shipping-methods__empty">';
			echo wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Введите ваш адрес, чтобы увидеть варианты доставки.', 'woocommerce' ) ) );
			echo '</div>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	/* ================================================================= */
	/*  Cart totals helpers                                              */
	/* ================================================================= */

	public static function get_cart_items_total_without_shipping_html() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		ob_start();
		wc_cart_totals_subtotal_html();
		return ob_get_clean();
	}

	/* ================================================================= */
	/*  Order formula                                                    */
	/* ================================================================= */

	public static function get_order_formula_parts() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return array();
		}
		$cart           = WC()->cart;
		$items_total    = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
		$discount_total = (float) $cart->get_discount_total() + (float) $cart->get_discount_tax();
		$shipping_total = (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
		$order_total    = (float) $cart->get_total( 'edit' );

		return array(
			'items_total'              => $items_total,
			'discount_total'           => $discount_total,
			'shipping_total'           => $shipping_total,
			'order_total'              => $order_total,
			'items_total_formatted'    => wc_price( $items_total ),
			'discount_total_formatted' => wc_price( $discount_total ),
			'shipping_total_formatted' => wc_price( $shipping_total ),
			'order_total_formatted'    => wc_price( $order_total ),
		);
	}

	public static function get_order_formula_html() {
		$parts = self::get_order_formula_parts();
		if ( empty( $parts ) ) {
			return '';
		}
		ob_start();
		?>
		<div class="foneona-order-formula" aria-label="<?php echo esc_attr__( 'Расшифровка итоговой стоимости', 'woocommerce' ); ?>">
			<span class="foneona-order-formula__label"><?php echo esc_html__( 'Товары', 'woocommerce' ); ?></span>
			<span class="foneona-order-formula__part"><?php echo wp_kses_post( $parts['items_total_formatted'] ); ?></span>
			<span class="foneona-order-formula__sign">−</span>
			<span class="foneona-order-formula__label"><?php echo esc_html__( 'Скидка', 'woocommerce' ); ?></span>
			<span class="foneona-order-formula__part"><?php echo wp_kses_post( $parts['discount_total_formatted'] ); ?></span>
			<span class="foneona-order-formula__sign">+</span>
			<span class="foneona-order-formula__label"><?php echo esc_html__( 'Доставка', 'woocommerce' ); ?></span>
			<span class="foneona-order-formula__part"><?php echo wp_kses_post( $parts['shipping_total_formatted'] ); ?></span>
			<span class="foneona-order-formula__sign">=</span>
			<span class="foneona-order-formula__part is-total"><?php echo wp_kses_post( $parts['order_total_formatted'] ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}


	/* ================================================================= */
	/*  Checkout points redemption (RRP integration)                    */
	/* ================================================================= */

	private static function is_checkout_request_context() {
		if ( self::is_checkout_context() ) {
			return true;
		}

		if ( wp_doing_ajax() && isset( $_REQUEST['wc-ajax'] ) ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['wc-ajax'] ) );
			return in_array( $action, array( 'update_order_review', 'checkout' ), true );
		}

		return false;
	}

	private static function get_checkout_points_session_key() {
		return 'foneona_rrp_checkout_points';
	}

	private static function get_points_fee_label() {
		return 'Скидка баллами';
	}

	private static function get_rrp_checkout_setting( $key, $default = null ) {
		$settings = get_option( 'rrp_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$defaults = array(
			'plugin_enabled'                        => 'yes',
			'points_enabled'                        => 'yes',
			'checkout_points_redeem_enabled'        => 'yes',
			'checkout_points_max_percent'           => '100',
			'checkout_points_include_shipping'      => 'yes',
			'checkout_points_allow_shipping_coupon' => 'yes',
		);

		if ( class_exists( 'RRP_Settings' ) && is_callable( array( 'RRP_Settings', 'defaults' ) ) ) {
			$rrp_defaults = RRP_Settings::defaults();
			if ( is_array( $rrp_defaults ) ) {
				$defaults = wp_parse_args( $rrp_defaults, $defaults );
			}
		}

		$settings = wp_parse_args( $settings, $defaults );

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	private static function is_checkout_points_redemption_enabled() {
		return 'yes' === (string) self::get_rrp_checkout_setting( 'checkout_points_redeem_enabled', 'yes' );
	}

	private static function get_checkout_points_max_percent() {
		$percent = (float) wc_format_decimal( self::get_rrp_checkout_setting( 'checkout_points_max_percent', '100' ), 4 );

		if ( $percent < 0 ) {
			$percent = 0;
		}

		if ( $percent > 100 ) {
			$percent = 100;
		}

		return $percent;
	}

	private static function should_include_shipping_in_points_limit() {
		return 'yes' === (string) self::get_rrp_checkout_setting( 'checkout_points_include_shipping', 'yes' );
	}

	private static function can_combine_shipping_coupon_with_points() {
		return 'yes' === (string) self::get_rrp_checkout_setting( 'checkout_points_allow_shipping_coupon', 'yes' );
	}

	private static function normalize_points_amount( $amount ) {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
		return max( 0, (float) wc_format_decimal( $amount, $decimals ) );
	}

	private static function get_session_points_amount() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return 0.0;
		}

		return self::normalize_points_amount( WC()->session->get( self::get_checkout_points_session_key(), 0 ) );
	}

	private static function set_session_points_amount( $amount ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$amount = self::normalize_points_amount( $amount );

		if ( $amount > 0 ) {
			WC()->session->set( self::get_checkout_points_session_key(), $amount );
		} else {
			WC()->session->__unset( self::get_checkout_points_session_key() );
		}
	}

	public function clear_checkout_points_session() {
		self::set_session_points_amount( 0 );
	}

	private static function is_rrp_points_plugin_enabled() {
		if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		if ( ! function_exists( 'rrp' ) && ! class_exists( 'RRP_Settings' ) ) {
			return false;
		}

		return ( 'yes' === (string) self::get_rrp_checkout_setting( 'plugin_enabled', 'yes' ) )
			&& ( 'yes' === (string) self::get_rrp_checkout_setting( 'points_enabled', 'yes' ) )
			&& self::is_checkout_points_redemption_enabled();
	}

	private static function get_rrp_profile_for_user( $user_id = 0 ) {
		global $wpdb;

		$user_id = absint( $user_id ? $user_id : get_current_user_id() );

		if ( ! $user_id ) {
			return null;
		}

		$table = $wpdb->prefix . 'rrp_profiles';
		$user  = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return null;
		}

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return null;
		}

		$profile_id = absint( get_user_meta( $user_id, '_rrp_profile_id', true ) );
		$profile    = null;

		if ( $profile_id ) {
			$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $profile_id ), ARRAY_A );
		}

		if ( ! $profile ) {
			$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A );
		}

		if ( ! $profile && ! empty( $user->user_email ) ) {
			$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", sanitize_email( strtolower( $user->user_email ) ) ), ARRAY_A );
		}

		return is_array( $profile ) ? $profile : null;
	}

	private static function format_points_number( $points ) {
		if ( class_exists( 'RRP_Utils' ) && is_callable( array( 'RRP_Utils', 'format_points' ) ) ) {
			return RRP_Utils::format_points( $points );
		}

		$points = (float) wc_format_decimal( $points, 2 );
		if ( floor( $points ) === $points ) {
			return number_format_i18n( $points, 0 );
		}

		return number_format_i18n( $points, 2 );
	}

	private static function get_checkout_total_before_points( $include_shipping = true ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		$cart  = WC()->cart;
		$total = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
		$total -= (float) $cart->get_discount_total() + (float) $cart->get_discount_tax();

		if ( $include_shipping ) {
			$total += (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax();
		}

		foreach ( (array) $cart->get_fees() as $fee ) {
			$fee_amount = isset( $fee->amount ) ? (float) $fee->amount : 0.0;
			$fee_tax    = isset( $fee->tax ) ? (float) $fee->tax : 0.0;

			if ( $fee_amount < 0 && isset( $fee->name ) && self::get_points_fee_label() === $fee->name ) {
				continue;
			}

			$total += $fee_amount + $fee_tax;
		}

		return max( 0, (float) wc_format_decimal( $total, wc_get_price_decimals() ) );
	}

	private static function is_shipping_only_coupon( $coupon ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return false;
		}

		if ( ! $coupon instanceof WC_Coupon ) {
			$coupon = new WC_Coupon( wc_format_coupon_code( (string) $coupon ) );
		}

		if ( ! $coupon instanceof WC_Coupon || ! $coupon->get_id() ) {
			return false;
		}

		$is_shipping_only = (bool) $coupon->get_free_shipping() && (float) $coupon->get_amount() <= 0;

		return (bool) apply_filters( 'foneona_rrp_is_shipping_only_coupon', $is_shipping_only, $coupon );
	}

	private static function get_coupon_points_conflict_context() {
		$context = array(
			'has_coupons'           => false,
			'has_blocking_coupon'   => false,
			'allow_shipping_coupon' => self::can_combine_shipping_coupon_with_points(),
			'blocking_codes'        => array(),
			'shipping_only_codes'   => array(),
			'message'               => '',
		);

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $context;
		}

		$codes = WC()->cart->get_applied_coupons();
		if ( empty( $codes ) ) {
			return $context;
		}

		$context['has_coupons'] = true;

		foreach ( $codes as $code ) {
			$code             = wc_format_coupon_code( $code );
			$is_shipping_only = self::is_shipping_only_coupon( $code );

			if ( $is_shipping_only ) {
				$context['shipping_only_codes'][] = $code;
			}

			if ( ! $is_shipping_only || ! $context['allow_shipping_coupon'] ) {
				$context['blocking_codes'][] = $code;
			}
		}

		$context['blocking_codes']      = array_values( array_unique( $context['blocking_codes'] ) );
		$context['shipping_only_codes'] = array_values( array_unique( $context['shipping_only_codes'] ) );
		$context['has_blocking_coupon'] = ! empty( $context['blocking_codes'] );

		if ( $context['has_blocking_coupon'] ) {
			$only_shipping_blocked = ! empty( $context['shipping_only_codes'] ) && count( $context['blocking_codes'] ) === count( $context['shipping_only_codes'] );

			if ( $only_shipping_blocked && ! $context['allow_shipping_coupon'] ) {
				$context['message'] = 'По настройкам магазина баллы нельзя использовать одновременно даже с купоном на бесплатную доставку.';
			} else {
				$context['message'] = 'Баллы нельзя использовать одновременно с промокодом. Удалите промокод, чтобы списать баллы, или оформите заказ без баллов.';
			}
		}

		return $context;
	}

	public static function get_checkout_discount_total() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		$discount_total = (float) WC()->cart->get_discount_total() + (float) WC()->cart->get_discount_tax();

		foreach ( (array) WC()->cart->get_fees() as $fee ) {
			$fee_amount = isset( $fee->amount ) ? (float) $fee->amount : 0.0;
			$fee_tax    = isset( $fee->tax ) ? (float) $fee->tax : 0.0;

			if ( $fee_amount < 0 ) {
				$discount_total += abs( $fee_amount ) + abs( min( 0, $fee_tax ) );
			}
		}

		return max( 0, (float) wc_format_decimal( $discount_total, wc_get_price_decimals() ) );
	}

	public static function get_rrp_points_checkout_context() {
		$active           = self::is_rrp_points_plugin_enabled();
		$logged_in        = is_user_logged_in();
		$profile          = null;
		$profile_id       = 0;
		$balance          = 0.0;
		$session_used     = self::get_session_points_amount();
		$include_shipping = self::should_include_shipping_in_points_limit();
		$max_percent      = self::get_checkout_points_max_percent();
		$coupon_context   = self::get_coupon_points_conflict_context();

		if ( $active && $logged_in ) {
			$profile = self::get_rrp_profile_for_user();
			if ( $profile ) {
				$profile_id = absint( $profile['id'] ?? 0 );
				$balance    = max( 0, (float) wc_format_decimal( $profile['points_balance'] ?? 0, wc_get_price_decimals() ) );
			}
		}

		$payable_total  = self::get_checkout_total_before_points( true );
		$limit_base     = self::get_checkout_total_before_points( $include_shipping );
		$percent_limit  = ( $limit_base * $max_percent ) / 100;
		$max_redeemable = ( $active && $logged_in && ! $coupon_context['has_blocking_coupon'] ) ? min( $balance, $payable_total, $percent_limit ) : 0.0;
		$max_redeemable = max( 0, (float) wc_format_decimal( $max_redeemable, wc_get_price_decimals() ) );
		$applied        = min( $session_used, $max_redeemable );

		if ( abs( $applied - $session_used ) > 0.0001 ) {
			self::set_session_points_amount( $applied );
		}

		return array(
			'active'                   => $active,
			'logged_in'                => $logged_in,
			'show_panel'               => $active,
			'profile_id'               => $profile_id,
			'balance'                  => $balance,
			'balance_formatted'        => self::format_points_number( $balance ),
			'max_redeemable'           => $max_redeemable,
			'max_redeemable_raw'       => wc_format_decimal( $max_redeemable, wc_get_price_decimals() ),
			'max_redeemable_formatted' => self::format_points_number( $max_redeemable ),
			'applied'                  => $applied,
			'applied_raw'              => wc_format_decimal( $applied, wc_get_price_decimals() ),
			'applied_formatted'        => self::format_points_number( $applied ),
			'can_use'                  => $active && $logged_in && ! $coupon_context['has_blocking_coupon'] && $max_redeemable > 0,
			'price_decimals'           => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
			'limit_percent'            => $max_percent,
			'limit_base'               => max( 0, (float) wc_format_decimal( $limit_base, wc_get_price_decimals() ) ),
			'include_shipping'         => $include_shipping,
			'coupon_conflict'          => $coupon_context['has_blocking_coupon'],
			'coupon_conflict_message'  => $coupon_context['message'],
			'coupon_context'           => $coupon_context,
		);
	}

	public function capture_checkout_points_amount( $posted_data ) {
		if ( ! self::is_rrp_points_plugin_enabled() ) {
			self::set_session_points_amount( 0 );
			return;
		}

		if ( ! is_string( $posted_data ) || '' === $posted_data ) {
			return;
		}

		parse_str( $posted_data, $data );

		if ( ! array_key_exists( 'foneona_points_amount', $data ) ) {
			return;
		}

		$requested = self::normalize_points_amount( $data['foneona_points_amount'] );
		$context   = self::get_rrp_points_checkout_context();
		$validated = ( $context['active'] && $context['logged_in'] && empty( $context['coupon_conflict'] ) ) ? min( $requested, (float) $context['max_redeemable'] ) : 0.0;

		self::set_session_points_amount( $validated );
	}

	public function apply_checkout_points_discount( $cart ) {
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		if ( ! self::is_checkout_request_context() ) {
			return;
		}

		$context = self::get_rrp_points_checkout_context();
		$amount  = (float) $context['applied'];

		if ( ! $context['can_use'] || $amount <= 0 ) {
			return;
		}

		$cart->add_fee( self::get_points_fee_label(), 0 - $amount, false );
	}

	public function validate_coupon_against_checkout_points( $valid, $coupon, $discount = null ) {
		if ( ! $valid || ! self::is_rrp_points_plugin_enabled() ) {
			return $valid;
		}

		if ( self::get_session_points_amount() <= 0 ) {
			return $valid;
		}

		if ( self::is_shipping_only_coupon( $coupon ) && self::can_combine_shipping_coupon_with_points() ) {
			return $valid;
		}

		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( 'Промокод нельзя использовать одновременно с баллами. Сначала сбросьте баллы или используйте заказ без промокода.', 'error' );
		}

		return false;
	}

	public function validate_checkout_points_amount( $data, $errors ) {
		if ( ! $errors instanceof WP_Error ) {
			return;
		}

		if ( ! self::is_rrp_points_plugin_enabled() ) {
			return;
		}

		$requested = isset( $data['foneona_points_amount'] ) ? self::normalize_points_amount( $data['foneona_points_amount'] ) : 0.0;
		$context   = self::get_rrp_points_checkout_context();
		$validated = ( $context['active'] && $context['logged_in'] && empty( $context['coupon_conflict'] ) ) ? min( $requested, (float) $context['max_redeemable'] ) : 0.0;

		self::set_session_points_amount( $validated );

		if ( $requested > 0 && ! $context['logged_in'] ) {
			$errors->add( 'foneona_points_login_required', 'Войдите в аккаунт, чтобы использовать накопленные баллы.' );
		}

		if ( $requested > 0 && ! empty( $context['coupon_conflict'] ) ) {
			$errors->add( 'foneona_points_coupon_conflict', ! empty( $context['coupon_conflict_message'] ) ? $context['coupon_conflict_message'] : 'Баллы нельзя использовать одновременно с промокодом.' );
		}
	}

	public function save_redeemed_points_order_meta( $order, $data ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$context = self::get_rrp_points_checkout_context();
		$amount  = (float) $context['applied'];

		if ( $amount <= 0 || ! $context['logged_in'] || ! $context['profile_id'] ) {
			return;
		}

		$order->update_meta_data( '_foneona_rrp_points_used', wc_format_decimal( $amount, wc_get_price_decimals() ) );
		$order->update_meta_data( '_foneona_rrp_profile_id', absint( $context['profile_id'] ) );
		$order->update_meta_data( '_foneona_rrp_points_reserved', 'no' );
		$order->update_meta_data( '_foneona_rrp_points_restored', 'no' );
	}

	private function add_rrp_points_ledger_entry( $profile_id, $points_delta, $order, $entry_type, $description ) {
		global $wpdb;

		$profile_id   = absint( $profile_id );
		$points_delta = (float) wc_format_decimal( $points_delta, 4 );
		$order        = $order instanceof WC_Order ? $order : null;

		if ( ! $profile_id || 0.0 === $points_delta ) {
			return false;
		}

		$profiles_table = $wpdb->prefix . 'rrp_profiles';
		$ledger_table   = $wpdb->prefix . 'rrp_points_ledger';

		$profiles_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $profiles_table ) );
		$ledger_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ledger_table ) );
		if ( $profiles_exists !== $profiles_table || $ledger_exists !== $ledger_table ) {
			return false;
		}

		$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$profiles_table} WHERE id = %d", $profile_id ), ARRAY_A );

		if ( ! is_array( $profile ) ) {
			return false;
		}

		$current_balance = isset( $profile['points_balance'] ) ? (float) $profile['points_balance'] : 0.0;
		$new_balance     = (float) wc_format_decimal( $current_balance + $points_delta, 4 );

		if ( $new_balance < -0.0001 ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$ledger_table,
			array(
				'profile_id'    => $profile_id,
				'user_id'       => ! empty( $profile['user_id'] ) ? absint( $profile['user_id'] ) : null,
				'email'         => isset( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '',
				'order_id'      => $order ? $order->get_id() : null,
				'referral_id'   => null,
				'entry_type'    => substr( sanitize_key( $entry_type ), 0, 30 ),
				'points'        => wc_format_decimal( $points_delta, 4 ),
				'balance_after' => wc_format_decimal( $new_balance, 4 ),
				'description'   => sanitize_text_field( $description ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%d', '%s', '%f', '%f', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$updated = $wpdb->update(
			$profiles_table,
			array(
				'points_balance' => wc_format_decimal( $new_balance, 4 ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $profile_id ),
			array( '%f', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	public function reserve_redeemed_points_for_order( $order_id, $posted_data, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$already_reserved = 'yes' === $order->get_meta( '_foneona_rrp_points_reserved', true );
		$points_used      = (float) wc_format_decimal( $order->get_meta( '_foneona_rrp_points_used', true ), wc_get_price_decimals() );
		$profile_id       = absint( $order->get_meta( '_foneona_rrp_profile_id', true ) );

		if ( $already_reserved || $points_used <= 0 || ! $profile_id ) {
			$this->clear_checkout_points_session();
			return;
		}

		$description = sprintf(
			/* translators: %s: order number. */
			__( 'Списание баллов при оформлении заказа #%s', 'woocommerce' ),
			$order->get_order_number()
		);

		$ok = $this->add_rrp_points_ledger_entry( $profile_id, 0 - $points_used, $order, 'checkout_redeem', $description );

		if ( $ok ) {
			$order->update_meta_data( '_foneona_rrp_points_reserved', 'yes' );
			$order->add_order_note( sprintf( 'Списано %s баллов по программе лояльности.', self::format_points_number( $points_used ) ) );
			$order->save();
		}

		$this->clear_checkout_points_session();
	}

	public function maybe_restore_redeemed_points_for_order( $order_id, $from_status, $to_status, $order ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$points_used = (float) wc_format_decimal( $order->get_meta( '_foneona_rrp_points_used', true ), wc_get_price_decimals() );
		$profile_id  = absint( $order->get_meta( '_foneona_rrp_profile_id', true ) );
		$reserved    = 'yes' === $order->get_meta( '_foneona_rrp_points_reserved', true );
		$restored    = 'yes' === $order->get_meta( '_foneona_rrp_points_restored', true );
		$new_status  = sanitize_key( $to_status );

		if ( ! $reserved || $restored || $points_used <= 0 || ! $profile_id ) {
			return;
		}

		if ( ! in_array( $new_status, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			return;
		}

		$description = sprintf(
			/* translators: %s: order number. */
			__( 'Возврат баллов по заказу #%s', 'woocommerce' ),
			$order->get_order_number()
		);

		$ok = $this->add_rrp_points_ledger_entry( $profile_id, $points_used, $order, 'checkout_restore', $description );

		if ( ! $ok ) {
			return;
		}

		$order->update_meta_data( '_foneona_rrp_points_restored', 'yes' );
		$order->add_order_note( sprintf( 'Баллы возвращены покупателю: %s.', self::format_points_number( $points_used ) ) );
		$order->save();
	}

	/* ================================================================= */
	/*  AJAX fragment update                                             */
	/* ================================================================= */

	public function add_custom_checkout_fragments( $fragments ) {
		if ( ! self::is_checkout_context() ) {
			return $fragments;
		}
		$fragments['#foneona-shipping-methods'] = self::get_shipping_methods_block_html();
		return $fragments;
	}
}

Foneona_Woo_Layout::instance();
