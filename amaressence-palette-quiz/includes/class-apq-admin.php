<?php
/**
 * Админка плагина: подробные настройки, результаты, инструменты, логи.
 * Паттерн как в RRP_Admin: подменю WooCommerce, табы, admin_post-обработчики, nonce.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Admin {

	const CAP  = 'manage_woocommerce';
	const SLUG = 'apq-quiz';

	/** @var APQ_Settings */
	protected $settings;

	/** @var APQ_Logger */
	protected $logger;

	/** @var APQ_Results */
	protected $results;

	/** @var APQ_Points */
	protected $points;

	public function __construct( $settings, $logger, $results, $points ) {
		$this->settings = $settings;
		$this->logger   = $logger;
		$this->results  = $results;
		$this->points   = $points;

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_apq_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_apq_reset_attempt', array( $this, 'handle_reset_attempt' ) );
		add_action( 'admin_post_apq_revoke_result', array( $this, 'handle_revoke_result' ) );
		add_action( 'admin_post_apq_export_results', array( $this, 'handle_export_results' ) );
		add_action( 'admin_post_apq_clear_logs', array( $this, 'handle_clear_logs' ) );
	}

	public function register_menu() {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';

		add_submenu_page(
			$parent,
			__( 'Палитра-квиз', 'amaressence-palette-quiz' ),
			__( 'Палитра-квиз', 'amaressence-palette-quiz' ),
			self::CAP,
			self::SLUG,
			array( $this, 'render_page' )
		);
	}

	protected function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::SLUG,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'amaressence-palette-quiz' ) );
		}

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'general' => __( 'Общие', 'amaressence-palette-quiz' ),
			'points'  => __( 'Баллы', 'amaressence-palette-quiz' ),
			'account' => __( 'Кнопка и письмо', 'amaressence-palette-quiz' ),
			'texts'   => __( 'Тексты квиза', 'amaressence-palette-quiz' ),
			'content' => __( 'Вопросы и цвета', 'amaressence-palette-quiz' ),
			'results' => __( 'Результаты', 'amaressence-palette-quiz' ),
			'tools'   => __( 'Инструменты', 'amaressence-palette-quiz' ),
			'logs'    => __( 'Логи', 'amaressence-palette-quiz' ),
		);

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Amaressence Palette Quiz', 'amaressence-palette-quiz' ) . '</h1>';

		$this->render_notice();

		if ( ! $this->points->rrp_available() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Плагин RELOD Referral Points не найден (нет его таблиц) — баллы за квиз начисляться не будут, остальной функционал работает.', 'amaressence-palette-quiz' ) . '</p></div>';
		}

		echo '<nav class="nav-tab-wrapper">';

		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%s" class="nav-tab %s">%s</a>',
				esc_url( $this->tab_url( $key ) ),
				$tab === $key ? 'nav-tab-active' : '',
				esc_html( $label )
			);
		}

		echo '</nav>';

		$settings = $this->settings->get_all();

		switch ( $tab ) {
			case 'results':
				$this->render_results_tab();
				break;
			case 'tools':
				$this->render_tools_tab();
				break;
			case 'logs':
				$this->render_logs_tab();
				break;
			default:
				$this->render_settings_form( $tab, $settings );
		}

		echo '</div>';
	}

	protected function render_notice() {
		if ( empty( $_GET['apq_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['apq_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map    = array(
			'saved'       => array( 'success', __( 'Настройки сохранены.', 'amaressence-palette-quiz' ) ),
			'reset_ok'    => array( 'success', __( 'Попытка прохождения сброшена — пользователь может пройти квиз заново.', 'amaressence-palette-quiz' ) ),
			'reset_fail'  => array( 'error', __( 'Результат с таким email не найден.', 'amaressence-palette-quiz' ) ),
			'revoke_ok'   => array( 'success', __( 'Баллы обнулены (начисленные списаны из RRP) — пользователь может пройти квиз заново и получить баллы.', 'amaressence-palette-quiz' ) ),
			'revoke_fail' => array( 'error', __( 'Не удалось обнулить баллы. Подробности — на вкладке «Логи».', 'amaressence-palette-quiz' ) ),
			'logs_clear'  => array( 'success', __( 'Логи очищены.', 'amaressence-palette-quiz' ) ),
		);

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $map[ $notice ][0] ),
			esc_html( $map[ $notice ][1] )
		);
	}

	/* ============================ НАСТРОЙКИ ============================ */

	protected function render_settings_form( $tab, $settings ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apq_save_settings">
			<input type="hidden" name="apq_tab" value="<?php echo esc_attr( $tab ); ?>">
			<?php wp_nonce_field( 'apq_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<?php
				if ( 'general' === $tab ) {
					$this->render_section( __( 'Работа плагина', 'amaressence-palette-quiz' ), '' );
					$this->checkbox_row( 'plugin_enabled', __( 'Включить плагин', 'amaressence-palette-quiz' ), $settings['plugin_enabled'], __( 'Глобальный выключатель: квиз, попап и начисления.', 'amaressence-palette-quiz' ) );
					$this->checkbox_row( 'logs_enabled', __( 'Включить логи', 'amaressence-palette-quiz' ), $settings['logs_enabled'], __( 'Журнал событий на вкладке «Логи» (хранится 500 последних записей).', 'amaressence-palette-quiz' ) );

					$this->render_section( __( 'Страница квиза и доступ', 'amaressence-palette-quiz' ), __( 'Разместите шорткод [amaressence_quiz] на отдельной странице и выберите её ниже. Квиз открыт для всех: гости проходят свободно, а email для зачисления баллов указывают на финальном экране.', 'amaressence-palette-quiz' ) );
					$this->page_select_row( 'quiz_page_id', __( 'Страница с шорткодом квиза', 'amaressence-palette-quiz' ), (int) $settings['quiz_page_id'], __( 'Страница, на которой размещён шорткод [amaressence_quiz].', 'amaressence-palette-quiz' ) );
					$this->checkbox_row( 'one_attempt_per_email', __( 'Одно зачисление на аккаунт (email)', 'amaressence-palette-quiz' ), $settings['one_attempt_per_email'], __( 'На один email баллы начисляются только один раз. Обнулить баллы и открыть повторное прохождение можно на вкладке «Результаты».', 'amaressence-palette-quiz' ) );
				} elseif ( 'points' === $tab ) {
					$this->render_section( __( 'Начисление баллов', 'amaressence-palette-quiz' ), __( 'Баллы записываются в систему RELOD Referral Points (таблицы rrp_profiles и rrp_points_ledger, тип записи quiz_reward) и видны в личном кабинете там же, где остальные баллы.', 'amaressence-palette-quiz' ) );
					$this->text_row( 'points_amount', __( 'Сколько баллов начислять', 'amaressence-palette-quiz' ), $settings['points_amount'], __( 'Например: 400. Укажите 0, чтобы квиз работал без подарка.', 'amaressence-palette-quiz' ) );
					$this->select_row(
						'points_award_mode',
						__( 'Когда начислять', 'amaressence-palette-quiz' ),
						array(
							'after_first_order' => __( 'После первого заказа (когда заказ достигнет статуса ниже)', 'amaressence-palette-quiz' ),
							'immediate'         => __( 'Сразу после прохождения квиза', 'amaressence-palette-quiz' ),
							'none'              => __( 'Не начислять (только собирать результаты)', 'amaressence-palette-quiz' ),
						),
						$settings['points_award_mode'],
						__( 'Режим «после первого заказа» соответствует тексту квиза «баллы появятся после первой покупки» и защищает от регистраций ради баллов.', 'amaressence-palette-quiz' )
					);
					$this->select_row( 'points_order_status', __( 'Статус заказа для начисления', 'amaressence-palette-quiz' ), $this->order_status_options(), $settings['points_order_status'], __( 'Используется только в режиме «после первого заказа».', 'amaressence-palette-quiz' ) );
					$this->text_row( 'points_description', __( 'Описание операции в выписке', 'amaressence-palette-quiz' ), $settings['points_description'], __( 'Так начисление будет подписано в истории баллов личного кабинета.', 'amaressence-palette-quiz' ) );
				} elseif ( 'account' === $tab ) {
					$this->render_section( __( 'Кнопка-триггер квиза', 'amaressence-palette-quiz' ), __( 'Как подключить к кнопке Elementor: в стандартном виджете «Кнопка» укажите Ссылку (Link) равной триггеру ниже, например #apq-quiz. Клик сразу ведёт на страницу квиза (прохождение свободное). Альтернатива — добавить кнопке CSS-класс apq-quiz-trigger. Попап авторизации больше не используется: если у гостя нет аккаунта, он создаётся автоматически по email.', 'amaressence-palette-quiz' ) );
					$this->checkbox_row( 'popup_enabled', __( 'Включить кнопку-триггер', 'amaressence-palette-quiz' ), $settings['popup_enabled'], __( 'Небольшой скрипт в подвале сайта: клик по триггеру ведёт на страницу квиза.', 'amaressence-palette-quiz' ) );
					$this->text_row( 'popup_trigger', __( 'Триггер (ссылка кнопки)', 'amaressence-palette-quiz' ), $settings['popup_trigger'], __( 'Например: #apq-quiz. Это значение вставьте в поле «Ссылка» кнопки Elementor на главной странице.', 'amaressence-palette-quiz' ) );

					$this->render_section( __( 'Автосоздание аккаунта и письмо с временным паролем', 'amaressence-palette-quiz' ), __( 'Если гость указал на финальном экране email, которого ещё нет в базе, аккаунт покупателя создаётся автоматически, а на почту уходит письмо с временным паролем. Оформление письма повторяет плагин «Foneona Woo Layout». Доступные плейсхолдеры: {site_name}, {customer_email}, {username}, {temporary_password}, {my_account_url}, {change_password_note}.', 'amaressence-palette-quiz' ) );
					$this->checkbox_row( 'account_email_enabled', __( 'Отправлять письмо с временным паролем', 'amaressence-palette-quiz' ), $settings['account_email_enabled'], __( 'Если выключить — аккаунт всё равно создаётся, но письмо с паролем не отправляется.', 'amaressence-palette-quiz' ) );
					$this->text_row( 'account_email_subject', __( 'Тема письма', 'amaressence-palette-quiz' ), $settings['account_email_subject'], '' );
					$this->textarea_row( 'account_email_message', __( 'HTML письма', 'amaressence-palette-quiz' ), $settings['account_email_message'], __( 'HTML тела письма с плейсхолдерами.', 'amaressence-palette-quiz' ) );
					$this->textarea_row( 'account_email_css', __( 'CSS письма', 'amaressence-palette-quiz' ), $settings['account_email_css'], __( 'CSS встраивается в письмо. Классы .foneona-email-wrap / .foneona-email-card / .foneona-email-button — как в Foneona Woo Layout.', 'amaressence-palette-quiz' ) );
					$this->text_row( 'account_created_note', __( 'Подпись на экране «Готово» после автосоздания аккаунта', 'amaressence-palette-quiz' ), $settings['account_created_note'], __( 'Показывается гостю, для которого только что создали аккаунт по email.', 'amaressence-palette-quiz' ) );
				} elseif ( 'texts' === $tab ) {
					$this->render_section( __( 'Экран приветствия', 'amaressence-palette-quiz' ), __( 'Плейсхолдер {points} в любом поле будет заменён на число баллов из вкладки «Баллы».', 'amaressence-palette-quiz' ) );
					$this->text_row( 'logo_url', __( 'URL логотипа (необязательно)', 'amaressence-palette-quiz' ), $settings['logo_url'], __( 'Если пусто — показывается фирменный SVG-значок с надписью amarèssence.', 'amaressence-palette-quiz' ) );
					$this->text_row( 'intro_headline', __( 'Заголовок (можно HTML: <br>, <em>)', 'amaressence-palette-quiz' ), $settings['intro_headline'], '' );
					$this->text_row( 'intro_sub', __( 'Подзаголовок', 'amaressence-palette-quiz' ), $settings['intro_sub'], '' );
					$this->text_row( 'intro_time_text', __( 'Вторая «пилюля» (время)', 'amaressence-palette-quiz' ), $settings['intro_time_text'], __( 'Первая пилюля с числом вопросов считается автоматически.', 'amaressence-palette-quiz' ) );
					$this->text_row( 'reward_card_label', __( 'Карточка подарка: подпись', 'amaressence-palette-quiz' ), $settings['reward_card_label'], '' );
					$this->text_row( 'reward_card_text', __( 'Карточка подарка: текст', 'amaressence-palette-quiz' ), $settings['reward_card_text'], '' );

					$this->render_section( __( 'Экран результата', 'amaressence-palette-quiz' ), '' );
					$this->text_row( 'gift_badge', __( 'Бейдж подарка', 'amaressence-palette-quiz' ), $settings['gift_badge'], '' );
					$this->text_row( 'gift_title', __( 'Заголовок подарка', 'amaressence-palette-quiz' ), $settings['gift_title'], '' );
					$this->text_row( 'gift_desc', __( 'Описание подарка', 'amaressence-palette-quiz' ), $settings['gift_desc'], '' );
					$this->text_row( 'gift_button', __( 'Текст кнопки получения', 'amaressence-palette-quiz' ), $settings['gift_button'], '' );

					$this->render_section( __( 'Поле email для гостей', 'amaressence-palette-quiz' ), __( 'Показывается на экране результата неавторизованным посетителям: гость указывает email. Если аккаунта с таким email нет, он создаётся автоматически, а на почту уходит письмо с временным паролем (настройки — на вкладке «Кнопка и письмо»).', 'amaressence-palette-quiz' ) );
					$this->text_row( 'claim_email_label', __( 'Подпись над полем', 'amaressence-palette-quiz' ), $settings['claim_email_label'], '' );
					$this->text_row( 'claim_email_placeholder', __( 'Плейсхолдер поля', 'amaressence-palette-quiz' ), $settings['claim_email_placeholder'], '' );
					$this->text_row( 'claim_email_hint', __( 'Подсказка под полем', 'amaressence-palette-quiz' ), $settings['claim_email_hint'], '' );

					$this->render_section( __( 'Финальный экран', 'amaressence-palette-quiz' ), '' );
					$this->text_row( 'success_title', __( 'Заголовок успеха', 'amaressence-palette-quiz' ), $settings['success_title'], '' );
					$this->text_row( 'success_desc', __( 'Текст успеха (режим «после первого заказа»)', 'amaressence-palette-quiz' ), $settings['success_desc'], '' );
					$this->text_row( 'success_desc_awarded', __( 'Текст успеха (режим «сразу»)', 'amaressence-palette-quiz' ), $settings['success_desc_awarded'], '' );
					$this->text_row( 'shop_button_text', __( 'Текст кнопки «в магазин»', 'amaressence-palette-quiz' ), $settings['shop_button_text'], '' );
					$this->text_row( 'shop_button_url', __( 'Ссылка кнопки «в магазин»', 'amaressence-palette-quiz' ), $settings['shop_button_url'], '' );

					$this->render_section( __( 'Экран «уже проходила»', 'amaressence-palette-quiz' ), '' );
					$this->text_row( 'already_title', __( 'Заголовок', 'amaressence-palette-quiz' ), $settings['already_title'], '' );
					$this->text_row( 'already_desc', __( 'Описание', 'amaressence-palette-quiz' ), $settings['already_desc'], '' );
					$this->text_row( 'already_replay_text', __( 'Кнопка повторного прохождения (без баллов)', 'amaressence-palette-quiz' ), $settings['already_replay_text'], __( 'Позволяет пройти квиз ещё раз ради интереса — результат не сохраняется и баллы повторно не начисляются.', 'amaressence-palette-quiz' ) );
				} elseif ( 'content' === $tab ) {
					$this->render_content_tab( $settings );
				}
				?>
			</table>
			<?php submit_button( __( 'Сохранить настройки', 'amaressence-palette-quiz' ) ); ?>
		</form>
		<?php
	}

	protected function order_status_options() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array( 'completed' => __( 'Выполнен', 'amaressence-palette-quiz' ) );
		}

		$options = array();

		foreach ( wc_get_order_statuses() as $key => $label ) {
			$options[ str_replace( 'wc-', '', $key ) ] = $label;
		}

		return $options;
	}

	protected function render_section( $title, $desc ) {
		echo '<tr><th colspan="2" style="padding-bottom:0;"><h2 style="margin-bottom:4px;">' . esc_html( $title ) . '</h2>';

		if ( $desc ) {
			echo '<p class="description" style="font-weight:normal;">' . esc_html( $desc ) . '</p>';
		}

		echo '</th></tr>';
	}

	protected function checkbox_row( $key, $label, $value, $desc = '' ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="yes" <?php checked( 'yes', $value ); ?>> <?php esc_html_e( 'Да', 'amaressence-palette-quiz' ); ?></label>
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	protected function text_row( $key, $label, $value, $desc = '' ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" class="regular-text" style="width:100%;max-width:640px;" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	protected function textarea_row( $key, $label, $value, $desc = '' ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="8" style="width:100%;max-width:640px;font-family:monospace;"><?php echo esc_textarea( $value ); ?></textarea>
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	protected function select_row( $key, $label, $options, $value, $desc = '' ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>">
					<?php foreach ( $options as $opt_key => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_key ); ?>" <?php selected( $opt_key, $value ); ?>><?php echo esc_html( $opt_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	protected function page_select_row( $key, $label, $value, $desc = '' ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => $key,
						'id'                => $key,
						'selected'          => $value,
						'show_option_none'  => __( '— Не выбрана —', 'amaressence-palette-quiz' ),
						'option_none_value' => 0,
					)
				);
				?>
				<?php if ( $value && get_permalink( $value ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $value ) ); ?>" target="_blank" class="button" style="margin-left:8px;"><?php esc_html_e( 'Открыть страницу', 'amaressence-palette-quiz' ); ?></a>
				<?php endif; ?>
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
			</td>
		</tr>
		<?php
	}

	protected function json_row( $key, $label, $value, $placeholder ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="10" style="width:100%;max-width:640px;font-family:monospace;" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			</td>
		</tr>
		<?php
	}


	/**
	 * Простая настройка вопросов и вариантов без ручного JSON.
	 */
	protected function render_content_tab( $settings ) {
		$data     = $this->settings->get_quiz_data();
		$families = $this->color_family_options();

		$this->render_section(
			__( 'Вопросы квиза', 'amaressence-palette-quiz' ),
			__( 'Минимум 6 вопросов. Старые сохранённые наборы из меньшего количества автоматически дополняются вопросами из исходного index.html. Новые вопросы можно добавить кнопкой ниже.', 'amaressence-palette-quiz' )
		);
		?>
		<tr>
			<td colspan="2">
				<div class="apq-admin-repeater" data-apq-repeater="values">
					<div class="apq-admin-repeater-head">
						<strong><?php esc_html_e( 'Вопросы / темы', 'amaressence-palette-quiz' ); ?></strong>
						<button type="button" class="button" data-apq-add="values"><?php esc_html_e( 'Добавить вопрос', 'amaressence-palette-quiz' ); ?></button>
					</div>
					<div data-apq-rows="values">
						<?php foreach ( $data['values'] as $index => $row ) : ?>
							<?php $this->render_value_editor_row( (int) $index, $row ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</td>
		</tr>
		<?php

		$this->render_section(
			__( 'Варианты ответа: цвета', 'amaressence-palette-quiz' ),
			__( 'Каждый цвет будет показан как круглый свотч. Группа цвета нужна для расчёта профиля результата.', 'amaressence-palette-quiz' )
		);
		?>
		<tr>
			<td colspan="2">
				<div class="apq-admin-repeater" data-apq-repeater="colors">
					<div class="apq-admin-repeater-head">
						<strong><?php esc_html_e( 'Цвета / варианты', 'amaressence-palette-quiz' ); ?></strong>
						<button type="button" class="button" data-apq-add="colors"><?php esc_html_e( 'Добавить цвет', 'amaressence-palette-quiz' ); ?></button>
					</div>
					<div data-apq-rows="colors">
						<?php foreach ( $data['colors'] as $index => $row ) : ?>
							<?php $this->render_color_editor_row( (int) $index, $row, $families ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</td>
		</tr>
		<?php

		$this->render_section(
			__( 'Сценарии итогового результата', 'amaressence-palette-quiz' ),
			__( 'Здесь редактируются заголовок, подпись и описание для всех логических вариантов выборки: один цвет, одна группа, 3+3, 2+2+2, доминирующий цвет, семейные профили, все цвета разные и смешанный результат. Системное условие сценария не редактируется, чтобы не сломать расчёт.', 'amaressence-palette-quiz' )
		);
		$this->render_result_rules_editor( $data['result_rules'] );

		$this->render_section(
			__( 'Старые профили результата — только совместимость', 'amaressence-palette-quiz' ),
			__( 'Поле ниже оставлено для старых сохранённых результатов. Новый расчёт использует сценарии итогового результата выше.', 'amaressence-palette-quiz' )
		);
		$this->json_row( 'quiz_profiles_json', __( 'Legacy-профили', 'amaressence-palette-quiz' ), wp_json_encode( $data['profiles'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ), wp_json_encode( APQ_Settings::legacy_profiles(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );

		$this->render_content_tab_templates( $families );
	}

	protected function render_value_editor_row( $index, $row ) {
		$id          = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
		$title       = isset( $row['title'] ) ? (string) $row['title'] : '';
		$description = isset( $row['description'] ) ? (string) $row['description'] : '';
		?>
		<div class="apq-admin-row apq-admin-row--value" data-apq-row>
			<input type="hidden" name="apq_values[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" data-apq-field="id">
			<label>
				<span><?php esc_html_e( 'Вопрос', 'amaressence-palette-quiz' ); ?></span>
				<input type="text" name="apq_values[<?php echo esc_attr( (string) $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>" data-apq-field="title" placeholder="<?php echo esc_attr__( 'Например: Мягкая сила', 'amaressence-palette-quiz' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Описание', 'amaressence-palette-quiz' ); ?></span>
				<input type="text" name="apq_values[<?php echo esc_attr( (string) $index ); ?>][description]" value="<?php echo esc_attr( $description ); ?>" data-apq-field="description" placeholder="<?php echo esc_attr__( 'Короткое пояснение под вопросом', 'amaressence-palette-quiz' ); ?>">
			</label>
			<button type="button" class="button-link-delete" data-apq-remove><?php esc_html_e( 'Удалить', 'amaressence-palette-quiz' ); ?></button>
		</div>
		<?php
	}

	protected function render_color_editor_row( $index, $row, $families ) {
		$id     = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
		$label  = isset( $row['label'] ) ? (string) $row['label'] : '';
		$hex    = isset( $row['hex'] ) ? (string) $row['hex'] : '#000000';
		$family = isset( $row['family'] ) ? sanitize_key( $row['family'] ) : 'neutral';
		?>
		<div class="apq-admin-row apq-admin-row--color" data-apq-row>
			<input type="hidden" name="apq_colors[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>" data-apq-field="id">
			<label>
				<span><?php esc_html_e( 'Название', 'amaressence-palette-quiz' ); ?></span>
				<input type="text" name="apq_colors[<?php echo esc_attr( (string) $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" data-apq-field="label" placeholder="<?php echo esc_attr__( 'Например: Чёрный', 'amaressence-palette-quiz' ); ?>">
			</label>
			<label class="apq-admin-color-field">
				<span>HEX</span>
				<input type="color" name="apq_colors[<?php echo esc_attr( (string) $index ); ?>][hex]" value="<?php echo esc_attr( sanitize_hex_color( $hex ) ? $hex : '#000000' ); ?>" data-apq-field="hex">
			</label>
			<label>
				<span><?php esc_html_e( 'Группа', 'amaressence-palette-quiz' ); ?></span>
				<select name="apq_colors[<?php echo esc_attr( (string) $index ); ?>][family]" data-apq-field="family">
					<?php foreach ( $families as $key => $name ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $family ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<button type="button" class="button-link-delete" data-apq-remove><?php esc_html_e( 'Удалить', 'amaressence-palette-quiz' ); ?></button>
		</div>
		<?php
	}


	protected function render_result_rules_editor( $rules ) {
		$rules = APQ_Settings::normalize_result_rules( $rules, APQ_Settings::default_result_rules() );
		?>
		<tr>
			<td colspan="2">
				<div class="apq-admin-repeater apq-admin-repeater--rules" data-apq-repeater="result-rules">
					<div class="apq-admin-repeater-head">
						<strong><?php esc_html_e( 'Редактируемые сценарии', 'amaressence-palette-quiz' ); ?></strong>
						<span class="description"><?php esc_html_e( 'Плейсхолдеры: {top_color}, {second_color}, {top_family}, {second_family}, {selected_count}, {unique_colors}, {unique_families}.', 'amaressence-palette-quiz' ); ?></span>
					</div>
					<div data-apq-rows="result-rules">
						<?php foreach ( $rules as $index => $row ) : ?>
							<?php $this->render_result_rule_editor_row( (int) $index, $row ); ?>
						<?php endforeach; ?>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	protected function render_result_rule_editor_row( $index, $row ) {
		$id          = isset( $row['id'] ) ? sanitize_key( $row['id'] ) : '';
		$label       = isset( $row['label'] ) ? (string) $row['label'] : $id;
		$title       = isset( $row['title'] ) ? (string) $row['title'] : '';
		$tagline     = isset( $row['tagline'] ) ? (string) $row['tagline'] : '';
		$description = isset( $row['description'] ) ? (string) $row['description'] : '';
		?>
		<div class="apq-admin-rule-row" data-apq-rule-row>
			<input type="hidden" name="apq_result_rules[<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
			<div class="apq-admin-rule-condition">
				<strong><?php echo esc_html( $label ); ?></strong>
				<code><?php echo esc_html( $id ); ?></code>
			</div>
			<label>
				<span><?php esc_html_e( 'Заголовок', 'amaressence-palette-quiz' ); ?></span>
				<input type="text" name="apq_result_rules[<?php echo esc_attr( (string) $index ); ?>][title]" value="<?php echo esc_attr( $title ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Подпись', 'amaressence-palette-quiz' ); ?></span>
				<input type="text" name="apq_result_rules[<?php echo esc_attr( (string) $index ); ?>][tagline]" value="<?php echo esc_attr( $tagline ); ?>">
			</label>
			<label class="apq-admin-rule-desc">
				<span><?php esc_html_e( 'Описание', 'amaressence-palette-quiz' ); ?></span>
				<textarea name="apq_result_rules[<?php echo esc_attr( (string) $index ); ?>][description]" rows="3"><?php echo esc_textarea( $description ); ?></textarea>
			</label>
		</div>
		<?php
	}

	protected function color_family_options() {
		return APQ_Settings::color_family_labels();
	}

	protected function render_content_tab_templates( $families ) {
		ob_start();
		$this->render_value_editor_row( '__i__', array( 'id' => '', 'title' => '', 'description' => '' ) );
		$value_template = ob_get_clean();

		ob_start();
		$this->render_color_editor_row( '__i__', array( 'id' => '', 'label' => '', 'hex' => '#000000', 'family' => 'neutral' ), $families );
		$color_template = ob_get_clean();
		?>
		<tr><td colspan="2">
		<script type="text/template" id="apq-value-row-template"><?php echo $value_template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
		<script type="text/template" id="apq-color-row-template"><?php echo $color_template; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></script>
		<style>
			.apq-admin-repeater{max-width:980px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;margin:0 0 14px}.apq-admin-repeater-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.apq-admin-row{display:grid;grid-template-columns:minmax(220px,1fr) minmax(280px,1.4fr) auto;gap:10px;align-items:end;padding:12px 0;border-top:1px solid #f0f0f1}.apq-admin-row:first-child{border-top:none}.apq-admin-row--color{grid-template-columns:minmax(220px,1fr) 92px minmax(180px,.7fr) auto}.apq-admin-row label{display:flex;flex-direction:column;gap:4px;font-weight:600}.apq-admin-row label span,.apq-admin-rule-row label span{font-size:12px;color:#646970}.apq-admin-row input[type="text"],.apq-admin-row select{width:100%;max-width:none}.apq-admin-color-field input[type="color"]{width:64px;height:36px;padding:2px}.apq-admin-row [data-apq-remove][disabled]{color:#a7aaad;cursor:not-allowed;text-decoration:none}.apq-admin-repeater--rules{max-width:1180px}.apq-admin-rule-row{display:grid;grid-template-columns:minmax(230px,.85fr) minmax(190px,.75fr) minmax(210px,.85fr) minmax(320px,1.4fr);gap:12px;align-items:start;padding:14px 0;border-top:1px solid #f0f0f1}.apq-admin-rule-row:first-child{border-top:none}.apq-admin-rule-row label{display:flex;flex-direction:column;gap:4px;font-weight:600}.apq-admin-rule-row input[type="text"],.apq-admin-rule-row textarea{width:100%;max-width:none}.apq-admin-rule-condition{display:flex;flex-direction:column;gap:5px}.apq-admin-rule-condition code{width:max-content;max-width:100%;white-space:normal}.apq-admin-rule-desc textarea{min-height:82px}@media (max-width:782px){.apq-admin-row,.apq-admin-row--color,.apq-admin-rule-row{grid-template-columns:1fr}.apq-admin-repeater-head{align-items:flex-start;flex-direction:column}}
		</style>
		<script>
		(function(){
			function rows(type){return document.querySelector('[data-apq-rows="'+type+'"]');}
			function updateNames(row,type,index){
				row.querySelectorAll('[data-apq-field]').forEach(function(field){
					var key=field.getAttribute('data-apq-field');
					field.name=(type==='values'?'apq_values':'apq_colors')+'['+index+']['+key+']';
				});
			}
			function refresh(type){
				var box=rows(type); if(!box) return;
				var list=Array.prototype.slice.call(box.querySelectorAll('[data-apq-row]'));
				list.forEach(function(row,index){updateNames(row,type,index);});
				if(type==='values'){
					list.forEach(function(row){var btn=row.querySelector('[data-apq-remove]'); if(btn) btn.disabled=list.length<=6;});
				}
			}
			document.querySelectorAll('[data-apq-add]').forEach(function(btn){
				btn.addEventListener('click',function(){
					var type=btn.getAttribute('data-apq-add');
					var tpl=document.getElementById(type==='values'?'apq-value-row-template':'apq-color-row-template');
					var box=rows(type); if(!tpl||!box) return;
					var wrap=document.createElement('div');
					wrap.innerHTML=tpl.innerHTML.replace(/__i__/g, Date.now());
					var row=wrap.firstElementChild; box.appendChild(row); refresh(type);
				});
			});
			document.addEventListener('click',function(e){
				var btn=e.target.closest('[data-apq-remove]'); if(!btn||btn.disabled) return;
				var row=btn.closest('[data-apq-row]'); var repeater=btn.closest('[data-apq-repeater]'); if(!row||!repeater) return;
				var type=repeater.getAttribute('data-apq-repeater'); row.remove(); refresh(type);
			});
			refresh('values'); refresh('colors');
		})();
		</script>
		</td></tr>
		<?php
	}

	protected function prepare_content_settings_from_post( $data ) {
		$values       = isset( $data['apq_values'] ) && is_array( $data['apq_values'] ) ? $data['apq_values'] : array();
		$colors       = isset( $data['apq_colors'] ) && is_array( $data['apq_colors'] ) ? $data['apq_colors'] : array();
		$result_rules = isset( $data['apq_result_rules'] ) && is_array( $data['apq_result_rules'] ) ? $data['apq_result_rules'] : array();
		$defaults     = APQ_Settings::default_quiz_data();

		$values       = APQ_Settings::normalize_values( $values, $defaults['values'] );
		$colors       = APQ_Settings::normalize_colors( $colors, $defaults['colors'] );
		$result_rules = APQ_Settings::normalize_result_rules( $result_rules, $defaults['result_rules'] );

		$data['quiz_values_json']       = wp_json_encode( $values, JSON_UNESCAPED_UNICODE );
		$data['quiz_colors_json']       = wp_json_encode( $colors, JSON_UNESCAPED_UNICODE );
		$data['quiz_result_rules_json'] = wp_json_encode( $result_rules, JSON_UNESCAPED_UNICODE );

		if ( isset( $data['quiz_profiles_json'] ) ) {
			$profiles = json_decode( (string) $data['quiz_profiles_json'], true );
			$data['quiz_profiles_json'] = wp_json_encode( APQ_Settings::normalize_profiles( $profiles, $defaults['profiles'] ), JSON_UNESCAPED_UNICODE );
		}

		unset( $data['apq_values'], $data['apq_colors'], $data['apq_result_rules'] );

		return $data;
	}

	public function handle_save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'amaressence-palette-quiz' ) );
		}

		check_admin_referer( 'apq_save_settings' );

		// Чекбоксы: на «не своих» вкладках отсутствуют в POST — не затираем их.
		$current   = $this->settings->get_all();
		$tab       = isset( $_POST['apq_tab'] ) ? sanitize_key( wp_unslash( $_POST['apq_tab'] ) ) : 'general';
		$tab_boxes = array(
			'general' => array( 'plugin_enabled', 'logs_enabled', 'one_attempt_per_email' ),
			'account' => array( 'popup_enabled', 'account_email_enabled' ),
		);
		$data = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- сан-ция в APQ_Settings::update().

		if ( 'content' === $tab ) {
			$data = $this->prepare_content_settings_from_post( $data );
		}

		foreach ( array( 'plugin_enabled', 'logs_enabled', 'one_attempt_per_email', 'popup_enabled', 'account_email_enabled' ) as $box ) {
			$on_this_tab = isset( $tab_boxes[ $tab ] ) && in_array( $box, $tab_boxes[ $tab ], true );

			if ( ! $on_this_tab && ! isset( $data[ $box ] ) ) {
				$data[ $box ] = $current[ $box ];
			}
		}

		$this->settings->update( $data );
		$this->logger->log( 'info', 'Настройки квиза сохранены.', array( 'tab' => $tab ) );

		wp_safe_redirect( add_query_arg( 'apq_notice', 'saved', $this->tab_url( $tab ) ) );
		exit;
	}

	/* ============================ РЕЗУЛЬТАТЫ ============================ */

	protected function render_results_tab() {
		$search = isset( $_GET['apq_search'] ) ? sanitize_text_field( wp_unslash( $_GET['apq_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows   = $this->results->get_results( 200, 0, $search );
		$total  = $this->results->count_results( $search );
		$stats  = $this->results->get_stats();

		$quiz_data      = $this->settings->get_quiz_data();
		$profile_titles = $this->build_profile_titles_map( $quiz_data );
		$values_map     = array();
		$colors_map     = array();

		foreach ( $quiz_data['values'] as $value ) {
			$values_map[ $value['id'] ] = $value['title'];
		}

		foreach ( $quiz_data['colors'] as $color ) {
			$colors_map[ $color['id'] ] = $color;
		}

		$export_url = wp_nonce_url(
			add_query_arg( 'action', 'apq_export_results', admin_url( 'admin-post.php' ) ),
			'apq_export_results'
		);
		?>
		<style>
			.apq-stats{display:flex;flex-wrap:wrap;gap:12px;margin:16px 0}
			.apq-stat-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 18px;min-width:150px}
			.apq-stat-card .num{font-size:22px;font-weight:600;line-height:1.2}
			.apq-stat-card .lbl{font-size:12px;color:#646970}
			.apq-status-badge{display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;white-space:nowrap}
			.apq-status-awarded{background:#e6f4ea;color:#1e6b34}
			.apq-status-pending{background:#fdf3e0;color:#8a5a10}
			.apq-status-revoked{background:#f0f0f1;color:#646970;text-decoration:line-through}
			.apq-status-failed{background:#fbeaea;color:#a33030}
			.apq-status-none{background:#f0f0f1;color:#646970}
			.apq-answers-list{margin:6px 0 0;padding:0;list-style:none;font-size:12px}
			.apq-answers-list li{display:flex;align-items:center;gap:6px;padding:2px 0}
			.apq-answer-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0;box-shadow:inset 0 0 0 1px rgba(0,0,0,.15)}
			.apq-results-table td{vertical-align:top}
			.apq-search-form{display:inline-flex;gap:6px;margin-left:12px;vertical-align:middle}
		</style>

		<div class="apq-stats">
			<div class="apq-stat-card"><div class="num"><?php echo (int) $stats['total']; ?></div><div class="lbl"><?php esc_html_e( 'Всего прохождений', 'amaressence-palette-quiz' ); ?></div></div>
			<div class="apq-stat-card"><div class="num"><?php echo (int) $stats['awarded']; ?></div><div class="lbl"><?php esc_html_e( 'С начисленными баллами', 'amaressence-palette-quiz' ); ?></div></div>
			<div class="apq-stat-card"><div class="num"><?php echo (int) $stats['pending']; ?></div><div class="lbl"><?php esc_html_e( 'Ждут первого заказа', 'amaressence-palette-quiz' ); ?></div></div>
			<div class="apq-stat-card"><div class="num"><?php echo (int) $stats['revoked']; ?></div><div class="lbl"><?php esc_html_e( 'Обнулено', 'amaressence-palette-quiz' ); ?></div></div>
			<div class="apq-stat-card"><div class="num"><?php echo esc_html( $this->format_points( $stats['points_awarded'] ) ); ?></div><div class="lbl"><?php esc_html_e( 'Баллов начислено всего', 'amaressence-palette-quiz' ); ?></div></div>
		</div>

		<p>
			<?php echo esc_html( sprintf( __( 'Найдено: %d. Показаны последние 200.', 'amaressence-palette-quiz' ), $total ) ); ?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Экспорт CSV', 'amaressence-palette-quiz' ); ?></a>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="apq-search-form">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<input type="hidden" name="tab" value="results">
				<input type="search" name="apq_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Поиск по email…', 'amaressence-palette-quiz' ); ?>">
				<button class="button"><?php esc_html_e( 'Найти', 'amaressence-palette-quiz' ); ?></button>
				<?php if ( $search ) : ?>
					<a class="button" href="<?php echo esc_url( $this->tab_url( 'results' ) ); ?>"><?php esc_html_e( 'Сбросить', 'amaressence-palette-quiz' ); ?></a>
				<?php endif; ?>
			</form>
		</p>

		<table class="widefat striped apq-results-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Email', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Пользователь', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Показанный итог', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Ответы', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Баллы', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Статус баллов', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Пройден', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Действия', 'amaressence-palette-quiz' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'Пока никто не прошёл квиз.', 'amaressence-palette-quiz' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo (int) $row['id']; ?></td>
							<td><?php echo esc_html( $row['email'] ); ?></td>
							<td>
								<?php
								if ( ! empty( $row['user_id'] ) ) {
									$user = get_user_by( 'id', (int) $row['user_id'] );

									if ( $user ) {
										printf( '<a href="%s">%s</a> (#%d)', esc_url( get_edit_user_link( $user->ID ) ), esc_html( $user->display_name ), (int) $row['user_id'] );
									} else {
										echo '#' . (int) $row['user_id'];
									}
								} else {
									echo '—';
								}
								?>
							</td>
							<td>
								<?php
								$profile_id = (string) $row['profile_id'];
								echo esc_html( isset( $profile_titles[ $profile_id ] ) ? $profile_titles[ $profile_id ] : $profile_id );
								?>
								<br><code style="font-size:11px;"><?php echo esc_html( $profile_id ); ?></code>
							</td>
							<td><?php $this->render_answers_cell( $row, $values_map, $colors_map ); ?></td>
							<td><?php echo esc_html( $this->format_points( $row['points'] ) ); ?></td>
							<td>
								<span class="apq-status-badge apq-status-<?php echo esc_attr( $row['points_status'] ); ?>"><?php echo esc_html( $this->points_status_label( $row['points_status'] ) ); ?></span>
								<?php if ( $row['awarded_at'] ) : ?>
									<br><small><?php echo esc_html( $row['awarded_at'] ); ?></small>
								<?php endif; ?>
								<?php if ( ! empty( $row['awarded_order_id'] ) ) : ?>
									<br><small><a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $row['awarded_order_id'] ) . '&action=edit' ) ); ?>"><?php echo esc_html( sprintf( __( 'заказ #%d', 'amaressence-palette-quiz' ), (int) $row['awarded_order_id'] ) ); ?></a></small>
								<?php endif; ?>
								<?php if ( ! empty( $row['revoked_at'] ) ) : ?>
									<br><small><?php echo esc_html( sprintf( __( 'обнулено %s', 'amaressence-palette-quiz' ), $row['revoked_at'] ) ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['created_at'] ); ?></td>
							<td>
								<?php if ( 'revoked' !== $row['points_status'] ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Обнулить баллы? Начисленные будут списаны, пользователь сможет пройти квиз заново.', 'amaressence-palette-quiz' ) ); ?>');">
										<input type="hidden" name="action" value="apq_revoke_result">
										<input type="hidden" name="result_id" value="<?php echo (int) $row['id']; ?>">
										<?php wp_nonce_field( 'apq_revoke_result' ); ?>
										<button class="button button-small"><?php esc_html_e( 'Обнулить баллы', 'amaressence-palette-quiz' ); ?></button>
									</form>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Карта id → человекочитаемый заголовок итога (сценарии + legacy-профили).
	 */
	protected function build_profile_titles_map( $quiz_data ) {
		$map = array();

		foreach ( (array) $quiz_data['result_rules'] as $rule ) {
			if ( is_array( $rule ) && ! empty( $rule['id'] ) && ! empty( $rule['title'] ) ) {
				$map[ $rule['id'] ] = $rule['title'];
			}
		}

		foreach ( (array) $quiz_data['profiles'] as $profile ) {
			if ( is_array( $profile ) && ! empty( $profile['id'] ) && ! empty( $profile['title'] ) && ! isset( $map[ $profile['id'] ] ) ) {
				$map[ $profile['id'] ] = $profile['title'];
			}
		}

		return $map;
	}

	/**
	 * Ячейка «Ответы»: раскрывающийся список «вопрос → цвет» со свотчами.
	 */
	protected function render_answers_cell( $row, $values_map, $colors_map ) {
		$answers = ! empty( $row['answers'] ) ? json_decode( (string) $row['answers'], true ) : array();

		if ( ! is_array( $answers ) || empty( $answers ) ) {
			echo '—';

			return;
		}

		echo '<details><summary>' . esc_html( sprintf( __( '%d ответов', 'amaressence-palette-quiz' ), count( $answers ) ) ) . '</summary><ul class="apq-answers-list">';

		foreach ( $answers as $value_id => $color_id ) {
			$value_title = isset( $values_map[ $value_id ] ) ? $values_map[ $value_id ] : $value_id;
			$color       = isset( $colors_map[ $color_id ] ) ? $colors_map[ $color_id ] : null;
			$color_label = $color ? wp_strip_all_tags( str_replace( '<br>', ' ', (string) $color['label'] ) ) : $color_id;
			$hex         = $color && ! empty( $color['hex'] ) ? $color['hex'] : '#cccccc';

			printf(
				'<li><span class="apq-answer-dot" style="background:%s"></span>%s → %s</li>',
				esc_attr( $hex ),
				esc_html( $value_title ),
				esc_html( $color_label )
			);
		}

		echo '</ul></details>';
	}

	public function handle_revoke_result() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'amaressence-palette-quiz' ) );
		}

		check_admin_referer( 'apq_revoke_result' );

		$result_id = isset( $_POST['result_id'] ) ? absint( $_POST['result_id'] ) : 0;
		$result    = $result_id ? $this->results->revoke_result( $result_id ) : array( 'success' => false );

		wp_safe_redirect( add_query_arg( 'apq_notice', ! empty( $result['success'] ) ? 'revoke_ok' : 'revoke_fail', $this->tab_url( 'results' ) ) );
		exit;
	}

	protected function format_points( $points ) {
		$points = (float) $points;

		return ( floor( $points ) == $points ) ? number_format_i18n( $points, 0 ) : number_format_i18n( $points, 2 );
	}

	protected function points_status_label( $status ) {
		$map = array(
			'pending' => __( 'Ждут первого заказа', 'amaressence-palette-quiz' ),
			'awarded' => __( 'Начислены', 'amaressence-palette-quiz' ),
			'failed'  => __( 'Ошибка начисления', 'amaressence-palette-quiz' ),
			'none'    => __( 'Без баллов', 'amaressence-palette-quiz' ),
			'revoked' => __( 'Обнулены', 'amaressence-palette-quiz' ),
		);

		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	public function handle_export_results() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'amaressence-palette-quiz' ) );
		}

		check_admin_referer( 'apq_export_results' );

		$rows = $this->results->get_results( 100000 );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=apq-quiz-results-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// BOM для корректной кириллицы в Excel.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'id', 'email', 'user_id', 'profile', 'answers', 'points', 'points_status', 'created_at', 'awarded_at', 'awarded_order_id', 'revoked_at' ), ';' );

		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row['id'],
					$row['email'],
					$row['user_id'],
					$row['profile_id'],
					$row['answers'],
					$row['points'],
					$row['points_status'],
					$row['created_at'],
					$row['awarded_at'],
					$row['awarded_order_id'],
					isset( $row['revoked_at'] ) ? $row['revoked_at'] : '',
				),
				';'
			);
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ============================ ИНСТРУМЕНТЫ ============================ */

	protected function render_tools_tab() {
		$quiz_page_id = absint( $this->settings->get( 'quiz_page_id', 0 ) );
		$quiz_url     = $quiz_page_id ? get_permalink( $quiz_page_id ) : '';
		?>
		<h2><?php esc_html_e( 'Шпаргалка по подключению', 'amaressence-palette-quiz' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Создайте страницу и вставьте шорткод:', 'amaressence-palette-quiz' ); ?> <code>[amaressence_quiz]</code></li>
			<li><?php esc_html_e( 'Выберите эту страницу на вкладке «Общие».', 'amaressence-palette-quiz' ); ?></li>
			<li><?php echo wp_kses_post( sprintf( __( 'В кнопке Elementor на главной укажите ссылку <code>%s</code> (или класс <code>apq-quiz-trigger</code>) — клик ведёт на страницу квиза; проходить могут и гости.', 'amaressence-palette-quiz' ), esc_html( $this->settings->get( 'popup_trigger', '#apq-quiz' ) ) ) ); ?></li>
		</ol>
		<?php if ( $quiz_url ) : ?>
			<p><strong><?php esc_html_e( 'Текущая ссылка на квиз:', 'amaressence-palette-quiz' ); ?></strong> <a href="<?php echo esc_url( $quiz_url ); ?>" target="_blank"><?php echo esc_html( $quiz_url ); ?></a></p>
		<?php endif; ?>

		<hr>

		<h2><?php esc_html_e( 'Сбросить попытку прохождения (жёстко, с удалением истории)', 'amaressence-palette-quiz' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Полностью удаляет результаты по email — пользователь сможет пройти квиз заново, но запись пропадёт из истории, а уже начисленные баллы НЕ списываются. Рекомендуемый способ — кнопка «Обнулить баллы» на вкладке «Результаты»: она списывает начисленные баллы, сохраняет историю и тоже открывает повторное прохождение.', 'amaressence-palette-quiz' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;max-width:520px;">
			<input type="hidden" name="action" value="apq_reset_attempt">
			<?php wp_nonce_field( 'apq_reset_attempt' ); ?>
			<input type="email" name="apq_email" class="regular-text" placeholder="email@example.com" required>
			<?php submit_button( __( 'Сбросить', 'amaressence-palette-quiz' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	public function handle_reset_attempt() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'amaressence-palette-quiz' ) );
		}

		check_admin_referer( 'apq_reset_attempt' );

		$email = isset( $_POST['apq_email'] ) ? $this->points->normalize_email( wp_unslash( $_POST['apq_email'] ) ) : '';
		$ok    = $email ? $this->results->delete_by_email( $email ) : false;

		wp_safe_redirect( add_query_arg( 'apq_notice', $ok ? 'reset_ok' : 'reset_fail', $this->tab_url( 'tools' ) ) );
		exit;
	}

	/* ============================ ЛОГИ ============================ */

	protected function render_logs_tab() {
		$logs = $this->logger->get_logs( 200 );

		$clear_url = wp_nonce_url(
			add_query_arg( 'action', 'apq_clear_logs', admin_url( 'admin-post.php' ) ),
			'apq_clear_logs'
		);
		?>
		<p><a href="<?php echo esc_url( $clear_url ); ?>" class="button"><?php esc_html_e( 'Очистить логи', 'amaressence-palette-quiz' ); ?></a></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:160px;"><?php esc_html_e( 'Время', 'amaressence-palette-quiz' ); ?></th>
					<th style="width:90px;"><?php esc_html_e( 'Уровень', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Сообщение', 'amaressence-palette-quiz' ); ?></th>
					<th><?php esc_html_e( 'Контекст', 'amaressence-palette-quiz' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Логи пусты.', 'amaressence-palette-quiz' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['time'] ); ?></td>
							<td><?php echo esc_html( $log['level'] ); ?></td>
							<td><?php echo esc_html( $log['message'] ); ?></td>
							<td><code style="font-size:11px;"><?php echo esc_html( wp_json_encode( $log['context'], JSON_UNESCAPED_UNICODE ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	public function handle_clear_logs() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Недостаточно прав.', 'amaressence-palette-quiz' ) );
		}

		check_admin_referer( 'apq_clear_logs' );
		$this->logger->clear_logs();

		wp_safe_redirect( add_query_arg( 'apq_notice', 'logs_clear', $this->tab_url( 'logs' ) ) );
		exit;
	}
}
