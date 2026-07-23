<?php
/**
 * Admin UI.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Admin {

	/**
	 * Settings.
	 *
	 * @var RRP_Settings
	 */
	protected $settings;

	/**
	 * Logger.
	 *
	 * @var RRP_Logger
	 */
	protected $logger;

	/**
	 * Profile manager.
	 *
	 * @var RRP_Profile_Manager
	 */
	protected $profile_manager;

	/**
	 * Points manager.
	 *
	 * @var RRP_Points_Manager
	 */
	protected $points_manager;

	/**
	 * Referral tracker.
	 *
	 * @var RRP_Referral_Tracker
	 */
	protected $tracker;

	/**
	 * Expiration manager.
	 *
	 * @var RRP_Expiration_Manager|null
	 */
	protected $expiration_manager;

	/**
	 * Constructor.
	 *
	 * @param RRP_Settings        $settings        Settings.
	 * @param RRP_Logger          $logger          Logger.
	 * @param RRP_Profile_Manager $profile_manager Profile manager.
	 * @param RRP_Points_Manager   $points_manager  Points manager.
	 * @param RRP_Referral_Tracker    $tracker            Referral tracker.
	 * @param RRP_Expiration_Manager|null $expiration_manager Expiration manager.
	 */
	public function __construct( $settings, $logger, $profile_manager, $points_manager, $tracker, $expiration_manager = null ) {
		$this->settings           = $settings;
		$this->logger             = $logger;
		$this->profile_manager    = $profile_manager;
		$this->points_manager     = $points_manager;
		$this->tracker            = $tracker;
		$this->expiration_manager = $expiration_manager;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		add_action( 'admin_post_rrp_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_rrp_manual_adjust', array( $this, 'handle_manual_adjust' ) );
		add_action( 'admin_post_rrp_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_rrp_clear_logs', array( $this, 'handle_clear_logs' ) );
		add_action( 'admin_post_rrp_cleanup', array( $this, 'handle_cleanup' ) );
		add_action( 'admin_post_rrp_recalculate_balances', array( $this, 'handle_recalculate_balances' ) );
		add_action( 'admin_post_rrp_run_points_expiration', array( $this, 'handle_run_points_expiration' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Referral & Points', 'relod-referral-points' ),
			__( 'Referral & Points', 'relod-referral-points' ),
			'manage_woocommerce',
			'rrp-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'rrp-settings' ) ) {
			return;
		}

		wp_enqueue_style( 'rrp-admin', RRP_PLUGIN_URL . 'assets/css/admin.css', array(), RRP_VERSION );
		wp_enqueue_editor();
		wp_enqueue_media();
		wp_enqueue_script( 'rrp-admin', RRP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), RRP_VERSION, true );
		wp_localize_script(
			'rrp-admin',
			'rrpEmailPreview',
			array(
				'placeholders' => $this->get_email_preview_placeholders(),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$settings = $this->settings->get_all();
		$tabs     = $this->get_tabs();

		echo '<div class="wrap rrp-admin-wrap">';
		echo '<h1>' . esc_html__( 'RELOD Referral Points for WooCommerce', 'relod-referral-points' ) . '</h1>';
		$this->render_notice();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$url = add_query_arg(
				array(
					'page' => 'rrp-settings',
					'tab'  => $key,
				),
				admin_url( 'admin.php' )
			);
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'general':
				$this->render_settings_form( 'general', $settings );
				break;
			case 'referral':
				$this->render_settings_form( 'referral', $settings );
				break;
			case 'points':
				$this->render_settings_form( 'points', $settings );
				break;
			case 'email':
				$this->render_settings_form( 'email', $settings );
				break;
			case 'account':
				$this->render_settings_form( 'account', $settings );
				break;
			case 'history':
				$this->render_referral_history_tab();
				break;
			case 'logs':
				$this->render_logs_tab();
				break;
			case 'tools':
				$this->render_tools_tab();
				break;
		}

		echo '</div>';
	}

	/**
	 * Get tabs.
	 *
	 * @return array
	 */
	protected function get_tabs() {
		return array(
			'general'  => __( 'Общие', 'relod-referral-points' ),
			'referral' => __( 'Реферальная система', 'relod-referral-points' ),
			'points'   => __( 'Баллы', 'relod-referral-points' ),
			'email'    => __( 'Email', 'relod-referral-points' ),
			'account'  => __( 'Личный кабинет', 'relod-referral-points' ),
			'history'  => __( 'История ссылок', 'relod-referral-points' ),
			'logs'     => __( 'Логи', 'relod-referral-points' ),
			'tools'    => __( 'Инструменты', 'relod-referral-points' ),
		);
	}

	/**
	 * Render standard settings form.
	 *
	 * @param string $tab      Current tab.
	 * @param array  $settings Settings.
	 * @return void
	 */
	protected function render_settings_form( $tab, $settings ) {
		$action = admin_url( 'admin-post.php' );

		echo '<form method="post" action="' . esc_url( $action ) . '" class="rrp-form">';
		echo '<input type="hidden" name="action" value="rrp_save_settings">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'rrp_save_settings_' . $tab );

		if ( 'email' === $tab ) {
			$this->render_email_templates_form( $settings );
			submit_button( __( 'Сохранить настройки писем', 'relod-referral-points' ) );
			echo '</form>';
			return;
		}

		echo '<table class="form-table" role="presentation">';

		switch ( $tab ) {
			case 'general':
				$this->render_checkbox_row( 'plugin_enabled', __( 'Включить плагин', 'relod-referral-points' ), $settings['plugin_enabled'] );
				$this->render_checkbox_row( 'logs_enabled', __( 'Включить логи', 'relod-referral-points' ), $settings['logs_enabled'] );
				break;

			case 'referral':
				$this->render_checkbox_row( 'referrals_enabled', __( 'Включить реферальную систему', 'relod-referral-points' ), $settings['referrals_enabled'] );
				$this->render_text_row( 'referral_url_param', __( 'Название URL-параметра', 'relod-referral-points' ), $settings['referral_url_param'], __( 'Например: ref', 'relod-referral-points' ) );
				$this->render_number_row( 'referral_cookie_days', __( 'Срок жизни привязки (дни)', 'relod-referral-points' ), $settings['referral_cookie_days'], 1, 365, __( 'Сколько дней хранить реферальную привязку.', 'relod-referral-points' ) );
				$this->render_checkbox_row( 'prevent_self_referral', __( 'Запретить самореферал', 'relod-referral-points' ), $settings['prevent_self_referral'] );
				$this->render_checkbox_row( 'allow_guest_participation', __( 'Разрешить участие гостей', 'relod-referral-points' ), $settings['allow_guest_participation'] );
				break;

			case 'points':
				$this->render_section_row( __( 'Реферальные начисления', 'relod-referral-points' ), __( 'Эти настройки применяются к баллам, которые получает реферер за заказ приглашённого клиента.', 'relod-referral-points' ) );
				$this->render_checkbox_row( 'points_enabled', __( 'Включить балльную систему', 'relod-referral-points' ), $settings['points_enabled'] );
				$this->render_text_row( 'reward_rate', __( 'Процент начисления за реферала', 'relod-referral-points' ), $settings['reward_rate'], __( 'Например: 10 = 10% от суммы заказа приглашённого клиента.', 'relod-referral-points' ) );
				$this->render_select_row( 'reward_order_status', __( 'Статус заказа для начисления за реферала', 'relod-referral-points' ), RRP_Utils::get_order_status_options(), $settings['reward_order_status'] );
				$this->render_checkbox_row( 'reverse_on_cancel', __( 'Списывать реферальные баллы при отмене заказа', 'relod-referral-points' ), $settings['reverse_on_cancel'] );
				$this->render_checkbox_row( 'reverse_on_refund', __( 'Списывать реферальные баллы при возврате заказа', 'relod-referral-points' ), $settings['reverse_on_refund'] );

				$this->render_section_row( __( 'Баллы за собственный заказ', 'relod-referral-points' ), __( 'Отдельные правила для начисления баллов самому покупателю за его заказ.', 'relod-referral-points' ) );
				$this->render_checkbox_row( 'self_points_enabled', __( 'Начислять баллы покупателю за его заказ', 'relod-referral-points' ), $settings['self_points_enabled'] );
				$this->render_select_row( 'self_points_reward_type', __( 'Способ расчёта', 'relod-referral-points' ), array( 'percent' => __( 'Процент от суммы заказа', 'relod-referral-points' ), 'fixed' => __( 'Фиксированное количество баллов', 'relod-referral-points' ) ), $settings['self_points_reward_type'] );
				$this->render_text_row( 'self_points_reward_value', __( 'Значение начисления', 'relod-referral-points' ), $settings['self_points_reward_value'], __( 'Для процента: 10 = 10% от суммы заказа. Для фиксированного режима: точное число баллов.', 'relod-referral-points' ) );
				$this->render_select_row( 'self_points_order_status', __( 'Статус заказа для начисления', 'relod-referral-points' ), RRP_Utils::get_order_status_options(), $settings['self_points_order_status'] );
				$this->render_text_row( 'self_points_min_order_total', __( 'Минимальная сумма заказа', 'relod-referral-points' ), $settings['self_points_min_order_total'], __( 'Если сумма заказа меньше указанной, баллы за свой заказ не начисляются. 0 = без ограничения.', 'relod-referral-points' ) );
				$this->render_text_row( 'self_points_max_per_order', __( 'Максимум баллов за один заказ', 'relod-referral-points' ), $settings['self_points_max_per_order'], __( '0 = без ограничения.', 'relod-referral-points' ) );
				$this->render_checkbox_row( 'self_points_require_user', __( 'Начислять только зарегистрированным пользователям', 'relod-referral-points' ), $settings['self_points_require_user'] );
				$this->render_checkbox_row( 'self_points_skip_if_referral', __( 'Не начислять за свой заказ, если этот заказ уже участвует в реферальной программе', 'relod-referral-points' ), $settings['self_points_skip_if_referral'] );
				$this->render_checkbox_row( 'self_points_reverse_on_cancel', __( 'Списывать баллы за свой заказ при отмене заказа', 'relod-referral-points' ), $settings['self_points_reverse_on_cancel'] );
				$this->render_checkbox_row( 'self_points_reverse_on_refund', __( 'Списывать баллы за свой заказ при возврате заказа', 'relod-referral-points' ), $settings['self_points_reverse_on_refund'] );

				$this->render_section_row( __( 'Списание баллов на checkout', 'relod-referral-points' ), __( 'Эти настройки ограничивают, какую часть заказа покупатель может оплатить накопленными баллами, и управляют конфликтом баллов с купонами WooCommerce.', 'relod-referral-points' ) );
				$this->render_checkbox_row( 'checkout_points_redeem_enabled', __( 'Разрешить списание баллов при оформлении заказа', 'relod-referral-points' ), $settings['checkout_points_redeem_enabled'] );
				$this->render_text_row( 'checkout_points_max_percent', __( 'Максимум оплаты баллами, % от заказа', 'relod-referral-points' ), $settings['checkout_points_max_percent'], __( 'Например: 30 = баллами можно покрыть не больше 30% выбранной базы расчёта. 100 = до полной суммы в пределах баланса.', 'relod-referral-points' ) );
				$this->render_checkbox_row( 'checkout_points_include_shipping', __( 'Учитывать доставку в базе расчёта лимита', 'relod-referral-points' ), $settings['checkout_points_include_shipping'] );
				$this->render_checkbox_row( 'checkout_points_allow_shipping_coupon', __( 'Разрешить баллы вместе с купоном только на бесплатную доставку', 'relod-referral-points' ), $settings['checkout_points_allow_shipping_coupon'] );

				$this->render_section_row( __( 'Сгорание баллов', 'relod-referral-points' ), __( 'Настройки срока действия начисленных баллов, ежедневной проверки и уведомления покупателя перед сгоранием.', 'relod-referral-points' ) );
				$this->render_checkbox_row( 'points_expiration_enabled', __( 'Включить сгорание баллов', 'relod-referral-points' ), $settings['points_expiration_enabled'] );
				$this->render_select_row( 'points_expiration_mode', __( 'Правило срока действия', 'relod-referral-points' ), array( 'rolling_days' => __( 'Через указанное количество дней после начисления', 'relod-referral-points' ), 'fixed_date' => __( 'В фиксированную дату каждый год', 'relod-referral-points' ) ), $settings['points_expiration_mode'] );
				$this->render_number_row( 'points_expiration_days', __( 'Срок действия баллов, дней', 'relod-referral-points' ), $settings['points_expiration_days'], 1, 3650, __( 'Используется для режима «через указанное количество дней». Например: 365.', 'relod-referral-points' ) );
				$this->render_number_row( 'points_expiration_fixed_day', __( 'День фиксированного сгорания', 'relod-referral-points' ), $settings['points_expiration_fixed_day'], 1, 31, __( 'Если в выбранном месяце меньше дней, будет использован последний день месяца.', 'relod-referral-points' ) );
				$this->render_number_row( 'points_expiration_fixed_month', __( 'Месяц фиксированного сгорания', 'relod-referral-points' ), $settings['points_expiration_fixed_month'], 1, 12, __( '1 = январь, 12 = декабрь.', 'relod-referral-points' ) );
				$this->render_number_row( 'points_expiration_notice_days', __( 'За сколько дней отправлять письмо', 'relod-referral-points' ), $settings['points_expiration_notice_days'], 0, 365, __( '0 = отправлять письмо в день сгорания, если проверка успеет пройти до списания.', 'relod-referral-points' ) );
				$this->render_number_row( 'points_expiration_run_hour', __( 'Час ежедневной проверки', 'relod-referral-points' ), $settings['points_expiration_run_hour'], 0, 23, __( 'По часовому поясу сайта. WP-Cron запускается при посещениях сайта или серверном cron.', 'relod-referral-points' ) );
				$this->render_number_row( 'points_expiration_batch_limit', __( 'Лимит операций за один запуск', 'relod-referral-points' ), $settings['points_expiration_batch_limit'], 10, 1000, __( 'Ограничивает нагрузку: сколько записей ledger обрабатывать за один запуск.', 'relod-referral-points' ) );
				break;

			case 'account':
				$this->render_text_row( 'account_endpoint', __( 'Слаг раздела в личном кабинете', 'relod-referral-points' ), $settings['account_endpoint'], __( 'Например: referrals', 'relod-referral-points' ) );
				$this->render_textarea_row( 'account_intro_text', __( 'Поясняющий текст в личном кабинете', 'relod-referral-points' ), $settings['account_intro_text'], 6, __( 'Показывается над ссылкой и историей начислений.', 'relod-referral-points' ) );
				break;
		}

		echo '</table>';
		submit_button( __( 'Сохранить настройки', 'relod-referral-points' ) );
		echo '</form>';
	}


	/**
	 * Render complete email templates editor.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	protected function render_email_templates_form( $settings ) {
		$templates   = isset( $settings['email_templates'] ) && is_array( $settings['email_templates'] ) ? $settings['email_templates'] : RRP_Settings::email_template_defaults();
		$definitions = RRP_Email_Manager::get_email_template_definitions();
		$defaults    = RRP_Settings::email_template_defaults();

		echo '<div class="rrp-card-admin rrp-email-global-card">';
		echo '<h2>' . esc_html__( 'Кастомные письма плагина', 'relod-referral-points' ) . '</h2>';
		echo '<p>' . esc_html__( 'Здесь редактируются все кастомные типы писем, которые отправляет этот плагин. У каждого письма есть тема, визуальный HTML-редактор, CSS, редактируемые системные блоки и широкий предпросмотр.', 'relod-referral-points' ) . '</p>';
		echo '<input type="hidden" name="rrp_settings[email_enabled]" value="no">';
		echo '<label class="rrp-email-toggle"><input type="checkbox" name="rrp_settings[email_enabled]" value="yes" ' . checked( 'yes', $settings['email_enabled'], false ) . '> ' . esc_html__( 'Включить отправку кастомных писем плагина', 'relod-referral-points' ) . '</label>';
		echo '</div>';

		foreach ( $definitions as $type => $definition ) {
			$default  = isset( $defaults[ $type ] ) ? $defaults[ $type ] : array();
			$template = isset( $templates[ $type ] ) && is_array( $templates[ $type ] ) ? $templates[ $type ] : array();
			$template = wp_parse_args( $template, $default );

			if ( empty( $template['blocks'] ) || ! is_array( $template['blocks'] ) ) {
				$template['blocks'] = array();
			}

			$template['blocks'] = wp_parse_args( $template['blocks'], isset( $default['blocks'] ) ? $default['blocks'] : array() );
			$this->render_email_template_card( $type, $definition, $template );
		}
	}

	/**
	 * Render one email template card.
	 *
	 * @param string $type       Template type.
	 * @param array  $definition Template definition.
	 * @param array  $template   Template settings.
	 * @return void
	 */
	protected function render_email_template_card( $type, $definition, $template ) {
		$type            = sanitize_key( $type );
		$label           = isset( $definition['label'] ) ? $definition['label'] : $type;
		$description     = isset( $definition['description'] ) ? $definition['description'] : '';
		$supports_order  = ! empty( $definition['supports_order_details'] );
		$supports_ref    = ! empty( $definition['supports_referral_link'] );
		$body_editor_id  = 'rrp_email_body_' . $type;
		$css_id          = 'rrp_email_css_' . $type;
		$order_table_id  = 'rrp_email_order_table_' . $type;
		$order_row_id    = 'rrp_email_order_row_' . $type;
		$referral_id     = 'rrp_email_referral_block_' . $type;
		$append_order_id = 'rrp_email_append_order_' . $type;
		$append_ref_id   = 'rrp_email_append_referral_' . $type;

		echo '<div class="rrp-card-admin rrp-email-template-card" data-template-id="' . esc_attr( $type ) . '" data-editor-id="' . esc_attr( $body_editor_id ) . '" data-css-id="' . esc_attr( $css_id ) . '" data-order-table-id="' . esc_attr( $order_table_id ) . '" data-order-row-id="' . esc_attr( $order_row_id ) . '" data-referral-id="' . esc_attr( $referral_id ) . '" data-append-order-id="' . esc_attr( $append_order_id ) . '" data-append-referral-id="' . esc_attr( $append_ref_id ) . '">';
		echo '<div class="rrp-email-template-header">';
		echo '<div><h2>' . esc_html( $label ) . '</h2>';
		if ( $description ) {
			echo '<p>' . esc_html( $description ) . '</p>';
		}
		echo '</div>';
		echo '<div class="rrp-email-type-badge">' . esc_html( $type ) . '</div>';
		echo '</div>';

		echo '<div class="rrp-email-fields-grid">';
		echo '<div class="rrp-email-field">';
		echo '<input type="hidden" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][enabled]" value="no">';
		echo '<label><input type="checkbox" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][enabled]" value="yes" ' . checked( 'yes', $template['enabled'], false ) . '> ' . esc_html__( 'Включить это письмо', 'relod-referral-points' ) . '</label>';
		echo '</div>';

		if ( ! empty( $definition['supports_order_status'] ) ) {
			echo '<div class="rrp-email-field">';
			echo '<label for="rrp_email_status_' . esc_attr( $type ) . '"><strong>' . esc_html__( 'Когда отправлять', 'relod-referral-points' ) . '</strong></label>';
			echo '<select id="rrp_email_status_' . esc_attr( $type ) . '" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][send_order_status]">';
			foreach ( $this->get_email_send_status_options() as $option_value => $option_label ) {
				echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $option_value, $template['send_order_status'], false ) . '>' . esc_html( $option_label ) . '</option>';
			}
			echo '</select>';
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="rrp-email-field rrp-email-field-full">';
		echo '<label for="rrp_email_subject_' . esc_attr( $type ) . '"><strong>' . esc_html__( 'Тема письма', 'relod-referral-points' ) . '</strong></label>';
		echo '<input type="text" id="rrp_email_subject_' . esc_attr( $type ) . '" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][subject]" value="' . esc_attr( $template['subject'] ) . '" class="large-text rrp-email-subject">';
		echo '<p class="description">' . esc_html__( 'Можно использовать переменные из подсказки ниже.', 'relod-referral-points' ) . '</p>';
		echo '</div>';

		echo '<div class="rrp-email-editor-layout">';
		echo '<div class="rrp-email-editor-main">';
		echo '<h3>' . esc_html__( 'Визуальный редактор письма', 'relod-referral-points' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Редактируйте письмо визуально или переключайтесь на вкладку «Текст», чтобы менять HTML-разметку вручную.', 'relod-referral-points' ) . '</p>';
		wp_editor(
			$template['body'],
			$body_editor_id,
			array(
				'textarea_name' => 'rrp_settings[email_templates][' . $type . '][body]',
				'textarea_rows' => 16,
				'media_buttons' => true,
				'teeny'         => false,
				'tinymce'       => true,
				'quicktags'     => true,
			)
		);
		echo '</div>';

		echo '<div class="rrp-email-preview-panel">';
		echo '<h3>' . esc_html__( 'Широкий предпросмотр письма', 'relod-referral-points' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Предпросмотр обновляется при изменении текста, HTML, CSS и системных блоков.', 'relod-referral-points' ) . '</p>';
		echo '<iframe class="rrp-email-preview-frame" sandbox="" title="' . esc_attr__( 'Предпросмотр письма', 'relod-referral-points' ) . '"></iframe>';
		echo '</div>';
		echo '</div>';

		echo '<div class="rrp-email-field rrp-email-field-full">';
		echo '<label for="' . esc_attr( $css_id ) . '"><strong>' . esc_html__( 'CSS письма', 'relod-referral-points' ) . '</strong></label>';
		echo '<textarea id="' . esc_attr( $css_id ) . '" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][css]" rows="8" class="large-text code rrp-email-css">' . RRP_Utils::admin_textarea( $template['css'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Введите только CSS без тега <style>. Перед отправкой плагин попытается встроить CSS inline для совместимости с почтовыми клиентами.', 'relod-referral-points' ) . '</p>';
		echo '</div>';

		if ( $supports_order || $supports_ref ) {
			echo '<div class="rrp-email-block-toggles">';
			echo '<input type="hidden" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][append_order_details]" value="no">';
			if ( $supports_order ) {
				echo '<label><input type="checkbox" id="' . esc_attr( $append_order_id ) . '" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][append_order_details]" value="yes" ' . checked( 'yes', $template['append_order_details'], false ) . '> ' . esc_html__( 'Автоматически добавлять блок деталей заказа, если в шаблоне нет {order_details_table}', 'relod-referral-points' ) . '</label>';
			}
			echo '<input type="hidden" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][append_referral_block]" value="no">';
			if ( $supports_ref ) {
				echo '<label><input type="checkbox" id="' . esc_attr( $append_ref_id ) . '" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][append_referral_block]" value="yes" ' . checked( 'yes', $template['append_referral_block'], false ) . '> ' . esc_html__( 'Автоматически добавлять блок реферальной ссылки, если в шаблоне нет {referral_link_block}', 'relod-referral-points' ) . '</label>';
			}
			echo '</div>';
		}

		if ( ! empty( $template['blocks'] ) && ( $supports_order || $supports_ref ) ) {
			echo '<details class="rrp-email-advanced-blocks" open>';
			echo '<summary>' . esc_html__( 'Редактирование системных HTML-блоков письма', 'relod-referral-points' ) . '</summary>';
			echo '<p class="description">' . esc_html__( 'Эти блоки являются частью письма. Их можно вставлять в основной редактор переменными {order_details_table} и {referral_link_block} или подключать автоматически чекбоксами выше.', 'relod-referral-points' ) . '</p>';
			echo '<div class="rrp-email-block-grid">';
			if ( $supports_order && isset( $template['blocks']['order_details_table'], $template['blocks']['order_item_row'] ) ) {
				$this->render_email_block_textarea( $type, 'order_details_table', $order_table_id, __( 'HTML блока деталей заказа', 'relod-referral-points' ), $template['blocks']['order_details_table'], 9 );
				$this->render_email_block_textarea( $type, 'order_item_row', $order_row_id, __( 'HTML одной строки товара', 'relod-referral-points' ), $template['blocks']['order_item_row'], 5 );
			}
			if ( $supports_ref && isset( $template['blocks']['referral_link_block'] ) ) {
				$this->render_email_block_textarea( $type, 'referral_link_block', $referral_id, __( 'HTML блока реферальной ссылки', 'relod-referral-points' ), $template['blocks']['referral_link_block'], 5 );
			}
			echo '</div>';
			echo '</details>';
		}

		$this->render_email_variables_box();
		echo '</div>';
	}

	/**
	 * Render a textarea for an editable email block.
	 *
	 * @param string $type       Template type.
	 * @param string $block_key  Block key.
	 * @param string $id         Field ID.
	 * @param string $label      Field label.
	 * @param string $value      Field value.
	 * @param int    $rows       Textarea rows.
	 * @return void
	 */
	protected function render_email_block_textarea( $type, $block_key, $id, $label, $value, $rows = 6 ) {
		echo '<div class="rrp-email-block-field">';
		echo '<label for="' . esc_attr( $id ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
		echo '<textarea id="' . esc_attr( $id ) . '" name="rrp_settings[email_templates][' . esc_attr( $type ) . '][blocks][' . esc_attr( $block_key ) . ']" rows="' . esc_attr( $rows ) . '" class="large-text code rrp-email-block-template" data-block-key="' . esc_attr( $block_key ) . '">' . RRP_Utils::admin_textarea( $value ) . '</textarea>';
		echo '</div>';
	}

	/**
	 * Render email variables help box for the wide email editor.
	 *
	 * @return void
	 */
	protected function render_email_variables_box() {
		$variables = $this->get_email_template_variables();

		echo '<div class="rrp-email-help rrp-email-help-wide">';
		echo '<h3>' . esc_html__( 'Переменные письма', 'relod-referral-points' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Переменные можно использовать в теме, визуальном HTML-шаблоне, CSS и системных HTML-блоках.', 'relod-referral-points' ) . '</p>';
		echo '<table class="widefat striped rrp-vars-table"><tbody>';

		foreach ( $variables as $variable => $description ) {
			echo '<tr><td><code>' . esc_html( $variable ) . '</code></td><td>' . esc_html( $description ) . '</td></tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	/**
	 * Variables available in email templates.
	 *
	 * @return array
	 */
	protected function get_email_template_variables() {
		return array(
			'{customer_name}'        => __( 'Имя и фамилия покупателя', 'relod-referral-points' ),
			'{customer_first_name}'  => __( 'Имя покупателя', 'relod-referral-points' ),
			'{customer_last_name}'   => __( 'Фамилия покупателя', 'relod-referral-points' ),
			'{customer_email}'       => __( 'Email покупателя', 'relod-referral-points' ),
			'{order_number}'         => __( 'Номер заказа', 'relod-referral-points' ),
			'{order_total}'          => __( 'Итоговая сумма заказа', 'relod-referral-points' ),
			'{order_date}'           => __( 'Дата заказа', 'relod-referral-points' ),
			'{referral_link}'        => __( 'Персональная реферальная ссылка', 'relod-referral-points' ),
			'{site_name}'            => __( 'Название сайта', 'relod-referral-points' ),
			'{order_details_table}'  => __( 'Готовый HTML-блок деталей заказа', 'relod-referral-points' ),
			'{referral_link_block}'  => __( 'Готовый HTML-блок реферальной ссылки', 'relod-referral-points' ),
			'{order_items_rows}'     => __( 'Строки товаров внутри HTML-блока деталей заказа', 'relod-referral-points' ),
			'{item_name}'            => __( 'Название товара в строке товара', 'relod-referral-points' ),
			'{item_quantity}'        => __( 'Количество товара в строке товара', 'relod-referral-points' ),
			'{item_subtotal}'        => __( 'Сумма по строке товара', 'relod-referral-points' ),
			'{label_order_details}'  => __( 'Заголовок «Детали заказа»', 'relod-referral-points' ),
			'{label_order_number}'   => __( 'Подпись «Номер заказа»', 'relod-referral-points' ),
			'{label_order_date}'     => __( 'Подпись «Дата»', 'relod-referral-points' ),
			'{label_order_total}'    => __( 'Подпись «Итого»', 'relod-referral-points' ),
			'{label_product}'        => __( 'Подпись «Товар»', 'relod-referral-points' ),
			'{label_quantity}'       => __( 'Подпись «Количество»', 'relod-referral-points' ),
			'{label_amount}'         => __( 'Подпись «Сумма»', 'relod-referral-points' ),
			'{label_referral_link}'  => __( 'Заголовок «Ваша реферальная ссылка»', 'relod-referral-points' ),
			'{loyalty_balance}'      => __( 'Текущий баланс баллов покупателя', 'relod-referral-points' ),
			'{expiring_points}'      => __( 'Количество баллов, которые скоро сгорят', 'relod-referral-points' ),
			'{points_value}'         => __( 'Сумма скидки в баллах, которые скоро сгорят', 'relod-referral-points' ),
			'{expire_date}'          => __( 'Дата сгорания баллов', 'relod-referral-points' ),
			'{expire_datetime}'      => __( 'Дата и время сгорания баллов', 'relod-referral-points' ),
			'{days_left}'            => __( 'Сколько дней осталось до сгорания', 'relod-referral-points' ),
			'{shop_url}'             => __( 'Ссылка на каталог магазина', 'relod-referral-points' ),
			'{loyalty_rules_url}'    => __( 'Ссылка на правила программы лояльности', 'relod-referral-points' ),
		);
	}

	/**
	 * Sample values for JavaScript email preview.
	 *
	 * @return array
	 */
	protected function get_email_preview_placeholders() {
		return array(
			'{customer_name}'       => 'Анна Иванова',
			'{customer_first_name}' => 'Анна',
			'{customer_last_name}'  => 'Иванова',
			'{customer_email}'      => 'anna@example.com',
			'{order_number}'        => '12345',
			'{order_total}'         => '10 ₽',
			'{order_date}'          => date_i18n( get_option( 'date_format' ) ),
			'{referral_link}'       => home_url( '/?ref=DEMO123' ),
			'{site_name}'           => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{item_name}'           => 'Демонстрационный товар',
			'{item_quantity}'       => '1',
			'{item_subtotal}'       => '10 ₽',
			'{label_order_details}' => __( 'Детали заказа', 'relod-referral-points' ),
			'{label_order_number}'  => __( 'Номер заказа', 'relod-referral-points' ),
			'{label_order_date}'    => __( 'Дата', 'relod-referral-points' ),
			'{label_order_total}'   => __( 'Итого', 'relod-referral-points' ),
			'{label_product}'       => __( 'Товар', 'relod-referral-points' ),
			'{label_quantity}'      => __( 'Количество', 'relod-referral-points' ),
			'{label_amount}'        => __( 'Сумма', 'relod-referral-points' ),
			'{label_referral_link}' => __( 'Ваша реферальная ссылка', 'relod-referral-points' ),
			'{loyalty_balance}'     => '320',
			'{expiring_points}'     => '120',
			'{points_value}'        => '120',
			'{expire_date}'         => date_i18n( get_option( 'date_format' ), strtotime( '+7 days', current_time( 'timestamp' ) ) ),
			'{expire_datetime}'     => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( '+7 days', current_time( 'timestamp' ) ) ),
			'{days_left}'           => '7',
			'{shop_url}'            => wc_get_page_permalink( 'shop' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
			'{loyalty_rules_url}'   => home_url( '/informacziya/#ue-tab2' ),
		);
	}



	/**
	 * Get email sending status options.
	 *
	 * @return array
	 */
	protected function get_email_send_status_options() {
		$options = array(
			'paid' => __( 'После успешной оплаты: Processing/Completed', 'relod-referral-points' ),
		);

		foreach ( RRP_Utils::get_order_status_options() as $status => $label ) {
			$options[ $status ] = $label;
		}

		return $options;
	}

	/**
	 * Render referral links history tab.
	 *
	 * @return void
	 */
	protected function render_referral_history_tab() {
		$stats  = $this->tracker->get_referral_link_stats( 100 );
		$clicks = $this->tracker->get_recent_clicks( 200 );

		echo '<div class="rrp-card-admin">';
		echo '<h2>' . esc_html__( 'Сводка по реферальным ссылкам', 'relod-referral-points' ) . '</h2>';
		echo '<p>' . esc_html__( 'Здесь видны все созданные персональные ссылки: владелец, код, сама ссылка, количество переходов и количество заказов после перехода. Ссылки без переходов тоже отображаются.', 'relod-referral-points' ) . '</p>';

		if ( empty( $stats ) ) {
			echo '<p>' . esc_html__( 'Персональные реферальные ссылки пока не созданы.', 'relod-referral-points' ) . '</p>';
		} else {
			echo '<div class="rrp-table-scroll"><table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Владелец ссылки', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Код', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Ссылка', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Ссылка создана', 'relod-referral-points' ) . '</th>';
				echo '<th>' . esc_html__( 'Переходов', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Заказов после перехода', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Последний переход', 'relod-referral-points' ) . '</th>';
			echo '<th>' . esc_html__( 'Последний заказ', 'relod-referral-points' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $stats as $row ) {
				$profile = array(
					'user_id' => isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0,
					'email'   => isset( $row['email'] ) ? $row['email'] : '',
				);
				$link    = $this->profile_manager->build_referral_link( array( 'referral_code' => $row['referral_code'] ) );

				echo '<tr>';
				echo '<td>' . esc_html( $this->profile_manager->get_profile_label( $profile ) ) . '</td>';
				echo '<td><code>' . esc_html( $row['referral_code'] ) . '</code></td>';
				echo '<td><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $link ) . '</a></td>';
				echo '<td>' . esc_html( ! empty( $row['profile_created_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['profile_created_at'] ) : '—' ) . '</td>';
				echo '<td>' . esc_html( absint( $row['clicks_count'] ) ) . '</td>';
				echo '<td>' . esc_html( absint( $row['conversions_count'] ) ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['last_click_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['last_click_at'] ) : '—' ) . '</td>';
				echo '<td>' . esc_html( ! empty( $row['last_conversion_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['last_conversion_at'] ) : '—' ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table></div>';
		}

		echo '</div>';

		echo '<div class="rrp-card-admin">';
		echo '<h2>' . esc_html__( 'Последние переходы', 'relod-referral-points' ) . '</h2>';
		echo '<p>' . esc_html__( 'Ниже отображаются последние 200 переходов с привязкой к заказу, если после перехода был оформлен заказ.', 'relod-referral-points' ) . '</p>';

		if ( empty( $clicks ) ) {
			echo '<p>' . esc_html__( 'История переходов пуста.', 'relod-referral-points' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="rrp-table-scroll"><table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Когда', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Владелец ссылки', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Код', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Статус', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Заказ', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Страница перехода', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Источник', 'relod-referral-points' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $clicks as $row ) {
			$profile = array(
				'user_id' => isset( $row['referrer_user_id'] ) ? absint( $row['referrer_user_id'] ) : 0,
				'email'   => isset( $row['email'] ) ? $row['email'] : '',
			);
			$order_id = ! empty( $row['converted_order_id'] ) ? absint( $row['converted_order_id'] ) : 0;

			echo '<tr>';
			echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( $this->profile_manager->get_profile_label( $profile ) ) . '</td>';
			echo '<td><code>' . esc_html( $row['referral_code'] ) . '</code></td>';
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			if ( $order_id ) {
				$order     = wc_get_order( $order_id );
				$order_url = $order ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $order_id . '&action=edit' );
				echo '<td><a href="' . esc_url( $order_url ) . '">#' . esc_html( $order_id ) . '</a></td>';
			} else {
				echo '<td>—</td>';
			}
			echo '<td>' . ( ! empty( $row['landing_url'] ) ? '<a href="' . esc_url( $row['landing_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_html_excerpt( $row['landing_url'], 80, '…' ) ) . '</a>' : '—' ) . '</td>';
			echo '<td>' . ( ! empty( $row['referrer_url'] ) ? '<a href="' . esc_url( $row['referrer_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( wp_html_excerpt( $row['referrer_url'], 80, '…' ) ) . '</a>' : '—' ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '</div>';
	}

	/**
	 * Render logs tab.
	 *
	 * @return void
	 */
	protected function render_logs_tab() {
		$logs = $this->logger->get_logs( 200 );

		echo '<div class="rrp-card-admin">';
		echo '<h2>' . esc_html__( 'Логи', 'relod-referral-points' ) . '</h2>';
		echo '<p>' . esc_html__( 'Ниже отображаются последние 200 записей.', 'relod-referral-points' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:16px;">';
		echo '<input type="hidden" name="action" value="rrp_clear_logs">';
		wp_nonce_field( 'rrp_clear_logs' );
		submit_button( __( 'Очистить логи', 'relod-referral-points' ), 'delete', 'submit', false );
		echo '</form>';

		if ( empty( $logs ) ) {
			echo '<p>' . esc_html__( 'Логов пока нет.', 'relod-referral-points' ) . '</p>';
			echo '</div>';
			return;
		}

		echo '<div class="rrp-log-table-wrap"><table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Время', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Уровень', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Сообщение', 'relod-referral-points' ) . '</th>';
		echo '<th>' . esc_html__( 'Контекст', 'relod-referral-points' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log['time'] ) . '</td>';
			echo '<td><code>' . esc_html( $log['level'] ) . '</code></td>';
			echo '<td>' . esc_html( $log['message'] ) . '</td>';
			echo '<td><pre>' . esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '</div>';
	}

	/**
	 * Render tools tab.
	 *
	 * @return void
	 */
	protected function render_tools_tab() {
		$action = admin_url( 'admin-post.php' );

		echo '<div class="rrp-tools-grid">';

		echo '<div class="rrp-card-admin">';
		echo '<h2>' . esc_html__( 'Ручная корректировка баллов', 'relod-referral-points' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $action ) . '">';
		echo '<input type="hidden" name="action" value="rrp_manual_adjust">';
		wp_nonce_field( 'rrp_manual_adjust' );
		echo '<table class="form-table"><tbody>';
		$this->render_custom_input_row( __( 'ID пользователя или email', 'relod-referral-points' ), '<input type="text" name="identifier" class="regular-text" required>');
		$this->render_custom_input_row( __( 'Баллы (+/-)', 'relod-referral-points' ), '<input type="number" step="0.01" name="points" class="small-text" required>');
		$this->render_custom_input_row( __( 'Комментарий', 'relod-referral-points' ), '<input type="text" name="description" class="regular-text">');
		echo '</tbody></table>';
		submit_button( __( 'Скорректировать баланс', 'relod-referral-points' ) );
		echo '</form>';
		echo '</div>';

		echo '<div class="rrp-card-admin">';
		echo '<h2>' . esc_html__( 'Экспорт данных', 'relod-referral-points' ) . '</h2>';
		echo '<p>' . esc_html__( 'Можно экспортировать профили, реферальные конверсии и ledger операций.', 'relod-referral-points' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $action ) . '">';
		echo '<input type="hidden" name="action" value="rrp_export">';
		wp_nonce_field( 'rrp_export' );
		echo '<select name="export_type">';
		echo '<option value="profiles">' . esc_html__( 'Профили', 'relod-referral-points' ) . '</option>';
		echo '<option value="referrals">' . esc_html__( 'Рефералы', 'relod-referral-points' ) . '</option>';
		echo '<option value="ledger">' . esc_html__( 'Операции баллов', 'relod-referral-points' ) . '</option>';
		echo '<option value="clicks">' . esc_html__( 'История переходов', 'relod-referral-points' ) . '</option>';
		echo '</select> ';
		submit_button( __( 'Скачать CSV', 'relod-referral-points' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';

		echo '<div class="rrp-card-admin">';
		echo '<h2>' . esc_html__( 'Служебные действия', 'relod-referral-points' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $action ) . '" style="margin-bottom:12px;">';
		echo '<input type="hidden" name="action" value="rrp_recalculate_balances">';
		wp_nonce_field( 'rrp_recalculate_balances' );
		submit_button( __( 'Пересчитать все балансы', 'relod-referral-points' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( $action ) . '" style="margin-bottom:12px;">';
		echo '<input type="hidden" name="action" value="rrp_run_points_expiration">';
		wp_nonce_field( 'rrp_run_points_expiration' );
		submit_button( __( 'Запустить проверку сгорания баллов сейчас', 'relod-referral-points' ), 'secondary', 'submit', false );
		echo '<p class="description" style="margin-top:8px;">' . esc_html__( 'Отправит письма по текущему окну уведомлений и спишет баллы, срок которых уже истёк.', 'relod-referral-points' ) . '</p>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( $action ) . '">';
		echo '<input type="hidden" name="action" value="rrp_cleanup">';
		wp_nonce_field( 'rrp_cleanup' );
		submit_button( __( 'Очистить служебные данные', 'relod-referral-points' ), 'delete', 'submit', false );
		echo '<p class="description" style="margin-top:8px;">' . esc_html__( 'Удаляются временные user meta, очищаются логи и сбрасываются служебные флаги браузерного трекинга на серверной стороне.', 'relod-referral-points' ) . '</p>';
		echo '</form>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		check_admin_referer( 'rrp_save_settings_' . $tab );

		$raw            = isset( $_POST['rrp_settings'] ) && is_array( $_POST['rrp_settings'] ) ? wp_unslash( $_POST['rrp_settings'] ) : array();
		$raw            = $this->apply_missing_checkbox_defaults( $raw, $tab );
		$current        = $this->settings->get_all();
		$merged         = array_merge( $current, $raw );
		$previous_slug  = $current['account_endpoint'];
		$updated        = $this->settings->update( $merged );
		$current_slug   = $updated['account_endpoint'];

		if ( $this->expiration_manager instanceof RRP_Expiration_Manager ) {
			$this->expiration_manager->ensure_schedule();
		}

		if ( $previous_slug !== $current_slug ) {
			flush_rewrite_rules();
		}

		$this->redirect_with_notice( 'settings_saved', $tab );
	}

	/**
	 * In tabbed forms unchecked checkboxes are not submitted by browsers.
	 * Add explicit "no" values only for checkboxes that belong to the currently saved tab.
	 *
	 * @param array  $raw Raw posted settings for the current tab.
	 * @param string $tab Current settings tab.
	 * @return array
	 */
	protected function apply_missing_checkbox_defaults( $raw, $tab ) {
		$checkboxes_by_tab = array(
			'general' => array(
				'plugin_enabled',
				'logs_enabled',
				'referrals_enabled',
				'prevent_self_referral',
				'allow_guest_participation',
			),
			'points'  => array(
				'points_enabled',
				'reverse_on_cancel',
				'reverse_on_refund',
				'self_points_enabled',
				'self_points_require_user',
				'self_points_skip_if_referral',
				'self_points_reverse_on_cancel',
				'self_points_reverse_on_refund',
				'checkout_points_redeem_enabled',
				'checkout_points_include_shipping',
				'checkout_points_allow_shipping_coupon',
				'points_expiration_enabled',
			),
			'email'   => array(
				'email_enabled',
			),
		);

		if ( ! isset( $checkboxes_by_tab[ $tab ] ) ) {
			return $raw;
		}

		foreach ( $checkboxes_by_tab[ $tab ] as $key ) {
			if ( ! array_key_exists( $key, $raw ) ) {
				$raw[ $key ] = 'no';
			}
		}

		return $raw;
	}

	/**
	 * Handle manual adjustment.
	 *
	 * @return void
	 */
	public function handle_manual_adjust() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		check_admin_referer( 'rrp_manual_adjust' );

		$identifier  = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		$points      = isset( $_POST['points'] ) ? wp_unslash( $_POST['points'] ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';

		$result = $this->points_manager->adjust_points_by_identifier( $identifier, $points, $description, get_current_user_id() );
		$this->redirect_with_notice( $result['success'] ? 'adjust_success' : 'adjust_error', 'tools', $result['message'] );
	}

	/**
	 * Handle export.
	 *
	 * @return void
	 */
	public function handle_export() {
		global $wpdb;

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		check_admin_referer( 'rrp_export' );

		$type = isset( $_POST['export_type'] ) ? sanitize_key( wp_unslash( $_POST['export_type'] ) ) : 'profiles';

		$map = array(
			'profiles'  => $wpdb->prefix . 'rrp_profiles',
			'referrals' => $wpdb->prefix . 'rrp_referrals',
			'ledger'    => $wpdb->prefix . 'rrp_points_ledger',
			'clicks'    => $wpdb->prefix . 'rrp_referral_clicks',
		);

		if ( ! isset( $map[ $type ] ) ) {
			wp_die( esc_html__( 'Неизвестный тип экспорта.', 'relod-referral-points' ) );
		}

		$table = $map[ $type ];
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $type . '-' . gmdate( 'Y-m-d-H-i-s' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		if ( ! empty( $rows ) ) {
			fputcsv( $output, array_keys( $rows[0] ) );
			foreach ( $rows as $row ) {
				fputcsv( $output, $row );
			}
		} else {
			fputcsv( $output, array( 'message' ) );
			fputcsv( $output, array( 'No rows found' ) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle clear logs.
	 *
	 * @return void
	 */
	public function handle_clear_logs() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		check_admin_referer( 'rrp_clear_logs' );
		$this->logger->clear_logs();
		$this->redirect_with_notice( 'logs_cleared', 'logs' );
	}

	/**
	 * Handle cleanup.
	 *
	 * @return void
	 */
	public function handle_cleanup() {
		global $wpdb;

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		check_admin_referer( 'rrp_cleanup' );

		$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('_rrp_pending_referrer_profile_id','_rrp_pending_referral_code','_rrp_pending_referral_click_id')" );
		$this->logger->clear_logs();
		$this->redirect_with_notice( 'cleanup_done', 'tools' );
	}

	/**
	 * Handle balance recalculation.
	 *
	 * @return void
	 */
	public function handle_recalculate_balances() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		check_admin_referer( 'rrp_recalculate_balances' );
		$count = $this->points_manager->recalculate_all_balances();
		$this->redirect_with_notice( 'recalculated', 'tools', sprintf( __( 'Пересчитано профилей: %d', 'relod-referral-points' ), $count ) );
	}


	/**
	 * Handle manual points expiration run.
	 *
	 * @return void
	 */
	public function handle_run_points_expiration() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'relod-referral-points' ) );
		}

		check_admin_referer( 'rrp_run_points_expiration' );

		if ( ! $this->expiration_manager || ! is_callable( array( $this->expiration_manager, 'run_daily' ) ) ) {
			$this->redirect_with_notice( 'expiration_error', 'tools', __( 'Менеджер сгорания баллов недоступен.', 'relod-referral-points' ) );
		}

		$summary = $this->expiration_manager->run_daily();

		if ( ! is_array( $summary ) || ! empty( $summary['skipped'] ) ) {
			$this->redirect_with_notice( 'expiration_error', 'tools', __( 'Проверка не выполнена: включите систему баллов и функцию сгорания баллов в настройках.', 'relod-referral-points' ) );
		}

		$message = sprintf(
			/* translators: 1: backfilled rows, 2: sent emails, 3: expired entries. */
			__( 'Проверка выполнена. Даты сгорания проставлены: %1$d. Писем отправлено: %2$d. Операций сгорания выполнено: %3$d.', 'relod-referral-points' ),
			isset( $summary['backfilled'] ) ? absint( $summary['backfilled'] ) : 0,
			isset( $summary['emails'] ) ? absint( $summary['emails'] ) : 0,
			isset( $summary['expired'] ) ? absint( $summary['expired'] ) : 0
		);

		$this->redirect_with_notice( 'expiration_ran', 'tools', $message );
	}

	/**
	 * Redirect back to plugin page with notice.
	 *
	 * @param string $notice Notice code.
	 * @param string $tab    Tab.
	 * @param string $text   Optional text.
	 * @return void
	 */
	protected function redirect_with_notice( $notice, $tab, $text = '' ) {
		$url = add_query_arg(
			array(
				'page'       => 'rrp-settings',
				'tab'        => $tab,
				'rrp_notice' => $notice,
				'rrp_text'   => rawurlencode( $text ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render admin notice.
	 *
	 * @return void
	 */
	protected function render_notice() {
		if ( empty( $_GET['rrp_notice'] ) ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['rrp_notice'] ) );
		$text   = ! empty( $_GET['rrp_text'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['rrp_text'] ) ) ) : '';
		$class  = 'notice notice-success';
		$map    = array(
			'settings_saved' => __( 'Настройки сохранены.', 'relod-referral-points' ),
			'adjust_success' => $text ? $text : __( 'Баланс скорректирован.', 'relod-referral-points' ),
			'adjust_error'   => $text ? $text : __( 'Не удалось скорректировать баланс.', 'relod-referral-points' ),
			'logs_cleared'   => __( 'Логи очищены.', 'relod-referral-points' ),
			'cleanup_done'   => __( 'Служебные данные очищены.', 'relod-referral-points' ),
			'recalculated'   => $text ? $text : __( 'Балансы пересчитаны.', 'relod-referral-points' ),
			'expiration_ran' => $text ? $text : __( 'Проверка сгорания баллов выполнена.', 'relod-referral-points' ),
			'expiration_error' => $text ? $text : __( 'Не удалось выполнить проверку сгорания баллов.', 'relod-referral-points' ),
		);

		if ( in_array( $notice, array( 'adjust_error', 'expiration_error' ), true ) ) {
			$class = 'notice notice-error';
		}

		if ( isset( $map[ $notice ] ) ) {
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $map[ $notice ] ) . '</p></div>';
		}
	}

	/**
	 * Render section separator row.
	 *
	 * @param string $title Section title.
	 * @param string $description Optional section description.
	 * @return void
	 */
	protected function render_section_row( $title, $description = '' ) {
		echo '<tr class="rrp-section-row">';
		echo '<th scope="row" colspan="2" style="padding-top:20px;">';
		echo '<div><strong>' . esc_html( $title ) . '</strong>';
		if ( $description ) {
			echo '<p class="description" style="margin:6px 0 0;">' . esc_html( $description ) . '</p>';
		}
		echo '</div>';
		echo '</th>';
		echo '</tr>';
	}

	/**
	 * Render checkbox row.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @return void
	 */
	protected function render_checkbox_row( $key, $label, $value ) {
		$checked = checked( 'yes', $value, false );
		$this->render_custom_input_row( $label, '<label><input type="checkbox" name="rrp_settings[' . esc_attr( $key ) . ']" value="1" ' . $checked . '> ' . esc_html__( 'Да', 'relod-referral-points' ) . '</label>' );
	}

	/**
	 * Render text row.
	 *
	 * @param string $key         Key.
	 * @param string $label       Label.
	 * @param string $value       Value.
	 * @param string $description Description.
	 * @return void
	 */
	protected function render_text_row( $key, $label, $value, $description = '' ) {
		$html  = '<input type="text" name="rrp_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text">';
		if ( $description ) {
			$html .= '<p class="description">' . esc_html( $description ) . '</p>';
		}
		$this->render_custom_input_row( $label, $html );
	}

	/**
	 * Render number row.
	 *
	 * @param string $key         Key.
	 * @param string $label       Label.
	 * @param mixed  $value       Value.
	 * @param int    $min         Min.
	 * @param int    $max         Max.
	 * @param string $description Description.
	 * @return void
	 */
	protected function render_number_row( $key, $label, $value, $min = 0, $max = 999999, $description = '' ) {
		$html  = '<input type="number" name="rrp_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" class="small-text">';
		if ( $description ) {
			$html .= '<p class="description">' . esc_html( $description ) . '</p>';
		}
		$this->render_custom_input_row( $label, $html );
	}

	/**
	 * Render select row.
	 *
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $options Options.
	 * @param string $value   Value.
	 * @return void
	 */
	protected function render_select_row( $key, $label, $options, $value ) {
		$html = '<select name="rrp_settings[' . esc_attr( $key ) . ']">';
		foreach ( $options as $option_value => $option_label ) {
			$html .= '<option value="' . esc_attr( $option_value ) . '" ' . selected( $option_value, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		$html .= '</select>';
		$this->render_custom_input_row( $label, $html );
	}

	/**
	 * Render textarea row.
	 *
	 * @param string $key         Key.
	 * @param string $label       Label.
	 * @param string $value       Value.
	 * @param int    $rows        Rows.
	 * @param string $description Description.
	 * @return void
	 */
	protected function render_textarea_row( $key, $label, $value, $rows = 6, $description = '' ) {
		$html  = '<textarea name="rrp_settings[' . esc_attr( $key ) . ']" rows="' . esc_attr( $rows ) . '" class="large-text code">' . RRP_Utils::admin_textarea( $value ) . '</textarea>';
		if ( $description ) {
			$html .= '<p class="description">' . esc_html( $description ) . '</p>';
		}
		$this->render_custom_input_row( $label, $html );
	}

	/**
	 * Render generic row.
	 *
	 * @param string $label Field label.
	 * @param string $html  Field html.
	 * @return void
	 */
	protected function render_custom_input_row( $label, $html ) {
		echo '<tr>';
		echo '<th scope="row"><label>' . esc_html( $label ) . '</label></th>';
		echo '<td>' . $html . '</td>';
		echo '</tr>';
	}
}
