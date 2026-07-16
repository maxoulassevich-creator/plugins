<?php
/**
 * Plugin Name: Amaressence Account Suite
 * Description: Авторизация, регистрация и единый кабинет покупателя WooCommerce на шорткодах.
 * Version: 2.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Site Team
 * Text Domain: amaressence-account-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Amaressence_Account_Suite' ) ) {
    final class Amaressence_Account_Suite {
        private const USER_AGREEMENT_URL = 'https://amaressence.ru/informacziya/#soglasie-s-obrabotkoj-pdn';
        private const LOYALTY_PROGRAM_URL = 'https://amaressence.ru/informacziya/#programma-loyalnosti';
        private const YANDEX_ACCEPTED_STATUS = 'SORTING_CENTER_AT_START';
        private const EMAIL_LOGO_URL = 'https://eimage.sendsay.ru/image/x_177486673298052/logo%2Dwhite2.png';
        private const OPTION_KEY = 'amaressence_account_suite_options';
        private const ADMIN_EMAIL_NONCE_ACTION = 'ama_account_suite_email_settings_save';
        private const ADMIN_EMAIL_NONCE_NAME = 'ama_account_suite_email_settings_nonce';


        private static $instance = null;
        private $notices = array();
        private $active_tab = 'login';

        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function __construct() {
            add_action( 'init', array( $this, 'handle_auth_postbacks' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

            add_shortcode( 'ama_auth_form', array( $this, 'render_auth_shortcode' ) );
            add_shortcode( 'ama_customer_dashboard', array( $this, 'render_dashboard_shortcode' ) );
            add_shortcode( 'relod_auth_form', array( $this, 'render_auth_shortcode' ) );
            add_shortcode( 'relod_customer_dashboard', array( $this, 'render_dashboard_shortcode' ) );

            add_action( 'wp_ajax_ama_login', array( $this, 'ajax_login' ) );
            add_action( 'wp_ajax_nopriv_ama_login', array( $this, 'ajax_login' ) );

            add_action( 'wp_ajax_ama_register', array( $this, 'ajax_register' ) );
            add_action( 'wp_ajax_nopriv_ama_register', array( $this, 'ajax_register' ) );

            add_action( 'wp_ajax_ama_save_profile', array( $this, 'ajax_save_profile' ) );
            add_action( 'wp_ajax_ama_save_addresses', array( $this, 'ajax_save_addresses' ) );
            add_action( 'wp_ajax_ama_get_order', array( $this, 'ajax_get_order' ) );
            add_action( 'wp_ajax_ama_cancel_order', array( $this, 'ajax_cancel_order' ) );

            if ( is_admin() ) {
                add_action( 'admin_menu', array( $this, 'add_admin_settings_page' ), 90 );
                add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_plugin_action_links' ) );
            }
        }

        public function register_assets() {
            wp_register_style(
                'ama-account-suite',
                plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css',
                array(),
                '2.2.0'
            );

            wp_register_script(
                'ama-account-suite',
                plugin_dir_url( __FILE__ ) . 'assets/js/frontend.js',
                array(),
                '2.2.0',
                true
            );
        }

        private function enqueue_assets() {
            wp_enqueue_style( 'ama-account-suite' );
            wp_enqueue_script( 'ama-account-suite' );

            wp_localize_script(
                'ama-account-suite',
                'AmaAccountSuite',
                array(
                    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'ama_account_suite_nonce' ),
                    'homeUrl'  => home_url( '/' ),
                    'messages' => array(
                        'saving'           => __( 'Сохраняем…', 'amaressence-account-suite' ),
                        'loading'          => __( 'Загружаем…', 'amaressence-account-suite' ),
                        'error'            => __( 'Не удалось выполнить действие. Попробуйте ещё раз.', 'amaressence-account-suite' ),
                        'passwordMismatch' => __( 'Новые пароли не совпадают.', 'amaressence-account-suite' ),
                        'copied'           => __( 'Ссылка скопирована.', 'amaressence-account-suite' ),
                        'copyError'        => __( 'Не удалось скопировать ссылку автоматически. Выделите и скопируйте её вручную.', 'amaressence-account-suite' ),
                    ),
                )
            );
        }

        public function handle_auth_postbacks() {
            if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
                return;
            }

            if ( empty( $_POST['ama_form_action'] ) ) {
                return;
            }

            $action = sanitize_key( wp_unslash( $_POST['ama_form_action'] ) );

            if ( ! isset( $_POST['ama_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ama_nonce'] ) ), 'ama_auth_submit' ) ) {
                $this->add_notice( 'error', __( 'Не удалось проверить форму. Обновите страницу и попробуйте снова.', 'amaressence-account-suite' ) );
                return;
            }

            if ( 'login' === $action ) {
                $this->active_tab = 'login';
                $result = $this->process_login_request( $_POST );
                if ( ! is_wp_error( $result ) ) {
                    wp_safe_redirect( home_url( '/' ) );
                    exit;
                }
                $this->add_notice( 'error', $result->get_error_message() );
            }

            if ( 'register' === $action ) {
                $this->active_tab = 'register';
                $result = $this->process_register_request( $_POST );
                if ( ! is_wp_error( $result ) ) {
                    wp_safe_redirect( home_url( '/' ) );
                    exit;
                }
                $this->add_notice( 'error', $result->get_error_message() );
            }
        }

        private function add_notice( $type, $message ) {
            $type = in_array( $type, array( 'success', 'error', 'info' ), true ) ? $type : 'info';
            $this->notices[] = array(
                'type'    => $type,
                'message' => $message,
            );
        }

        private function get_notices_markup() {
            if ( empty( $this->notices ) ) {
                return '';
            }

            ob_start();
            echo '<div class="ama-notices" aria-live="polite">';
            foreach ( $this->notices as $notice ) {
                printf(
                    '<div class="ama-notice ama-notice--%1$s">%2$s</div>',
                    esc_attr( $notice['type'] ),
                    esc_html( $notice['message'] )
                );
            }
            echo '</div>';

            return (string) ob_get_clean();
        }

        private function error( $message, $code = 'ama_error' ) {
            return new WP_Error( $code, wp_strip_all_tags( (string) $message ) );
        }

        private function process_login_request( $payload ) {
            $login_raw = isset( $payload['ama_login'] ) ? sanitize_text_field( wp_unslash( $payload['ama_login'] ) ) : '';
            $password  = isset( $payload['ama_password'] ) ? (string) wp_unslash( $payload['ama_password'] ) : '';
            $remember  = ! empty( $payload['ama_remember'] );

            if ( '' === $login_raw || '' === $password ) {
                return $this->error( __( 'Введите email или логин и пароль.', 'amaressence-account-suite' ) );
            }

            $user_login = $login_raw;
            if ( is_email( $login_raw ) ) {
                $user = get_user_by( 'email', $login_raw );
                if ( $user instanceof WP_User ) {
                    $user_login = $user->user_login;
                }
            }

            $signed_on = wp_signon(
                array(
                    'user_login'    => $user_login,
                    'user_password' => $password,
                    'remember'      => $remember,
                ),
                is_ssl()
            );

            if ( is_wp_error( $signed_on ) ) {
                return $this->error( $this->map_auth_error( $signed_on ) );
            }

            wp_set_current_user( $signed_on->ID, $signed_on->user_login );
            return $signed_on;
        }

        private function process_register_request( $payload ) {
            $first_name = isset( $payload['ama_first_name'] ) ? sanitize_text_field( wp_unslash( $payload['ama_first_name'] ) ) : '';
            $last_name  = isset( $payload['ama_last_name'] ) ? sanitize_text_field( wp_unslash( $payload['ama_last_name'] ) ) : '';
            $email      = isset( $payload['ama_email'] ) ? sanitize_email( wp_unslash( $payload['ama_email'] ) ) : '';
            $phone      = isset( $payload['ama_phone'] ) ? sanitize_text_field( wp_unslash( $payload['ama_phone'] ) ) : '';
            $password   = isset( $payload['ama_reg_password'] ) ? (string) wp_unslash( $payload['ama_reg_password'] ) : '';
            $confirm    = isset( $payload['ama_reg_password_confirm'] ) ? (string) wp_unslash( $payload['ama_reg_password_confirm'] ) : '';
            $consent    = ! empty( $payload['ama_privacy'] );

            if ( '' === $first_name ) {
                return $this->error( __( 'Укажите имя.', 'amaressence-account-suite' ) );
            }

            if ( '' === $email || ! is_email( $email ) ) {
                return $this->error( __( 'Укажите корректный email.', 'amaressence-account-suite' ) );
            }

            if ( email_exists( $email ) ) {
                return $this->error( __( 'Аккаунт с таким email уже существует.', 'amaressence-account-suite' ) );
            }

            if ( strlen( $password ) < 8 ) {
                return $this->error( __( 'Пароль должен содержать минимум 8 символов.', 'amaressence-account-suite' ) );
            }

            if ( $password !== $confirm ) {
                return $this->error( __( 'Пароли не совпадают.', 'amaressence-account-suite' ) );
            }

            if ( ! $consent ) {
                return $this->error( __( 'Подтвердите согласие с условиями магазина и политикой конфиденциальности.', 'amaressence-account-suite' ) );
            }

            $username = $this->generate_username_from_email( $email );
            $display  = trim( $first_name . ' ' . $last_name );

            $user_id = wp_insert_user(
                array(
                    'user_login'   => $username,
                    'user_pass'    => $password,
                    'user_email'   => $email,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'display_name' => '' !== $display ? $display : $first_name,
                    'role'         => 'customer',
                )
            );

            if ( is_wp_error( $user_id ) ) {
                return $this->error( $user_id->get_error_message() );
            }

            if ( '' !== $phone ) {
                update_user_meta( $user_id, 'billing_phone', $phone );
            }

            $user = get_user_by( 'id', $user_id );
            if ( ! $user instanceof WP_User ) {
                return $this->error( __( 'Аккаунт создан, но не удалось автоматически авторизовать пользователя.', 'amaressence-account-suite' ) );
            }

            wp_set_current_user( $user->ID, $user->user_login );
            wp_set_auth_cookie( $user->ID, true );
            do_action( 'wp_login', $user->user_login, $user );

            if ( function_exists( 'wc_set_customer_auth_cookie' ) ) {
                wc_set_customer_auth_cookie( $user->ID );
            }

            return $user;
        }

        private function generate_username_from_email( $email ) {
            $email = strtolower( $email );
            $base  = sanitize_user( current( explode( '@', $email ) ), true );
            if ( '' === $base ) {
                $base = 'customer';
            }
            $username = $base;
            $suffix   = 1;
            while ( username_exists( $username ) ) {
                $username = $base . $suffix;
                $suffix++;
            }
            return $username;
        }

        private function map_auth_error( WP_Error $error ) {
            $codes = $error->get_error_codes();
            if ( in_array( 'invalid_username', $codes, true ) || in_array( 'incorrect_password', $codes, true ) ) {
                return __( 'Неверный логин/email или пароль.', 'amaressence-account-suite' );
            }
            if ( in_array( 'empty_username', $codes, true ) || in_array( 'empty_password', $codes, true ) ) {
                return __( 'Заполните обязательные поля.', 'amaressence-account-suite' );
            }
            $message = $error->get_error_message();
            return $message ? wp_strip_all_tags( $message ) : __( 'Не удалось выполнить вход.', 'amaressence-account-suite' );
        }

        private function ensure_ajax_nonce() {
            check_ajax_referer( 'ama_account_suite_nonce', 'nonce' );
        }

        public function ajax_login() {
            $this->ensure_ajax_nonce();
            $result = $this->process_login_request( $_POST );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error(
                    array(
                        'message' => $result->get_error_message(),
                    ),
                    400
                );
            }

            wp_send_json_success(
                array(
                    'message'  => __( 'Вход выполнен успешно.', 'amaressence-account-suite' ),
                    'redirect' => home_url( '/' ),
                )
            );
        }

        public function ajax_register() {
            $this->ensure_ajax_nonce();
            $result = $this->process_register_request( $_POST );
            if ( is_wp_error( $result ) ) {
                wp_send_json_error(
                    array(
                        'message' => $result->get_error_message(),
                    ),
                    400
                );
            }

            wp_send_json_success(
                array(
                    'message'  => __( 'Аккаунт создан.', 'amaressence-account-suite' ),
                    'redirect' => home_url( '/' ),
                )
            );
        }

        private function current_customer_id() {
            return get_current_user_id();
        }

        private function require_logged_in_customer() {
            if ( ! is_user_logged_in() ) {
                wp_send_json_error(
                    array(
                        'message' => __( 'Нужно выполнить вход.', 'amaressence-account-suite' ),
                    ),
                    401
                );
            }
        }

        public function ajax_save_profile() {
            $this->ensure_ajax_nonce();
            $this->require_logged_in_customer();

            $user_id       = $this->current_customer_id();
            $current_user  = wp_get_current_user();
            $first_name    = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
            $last_name     = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
            $display_name  = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
            $email         = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
            $phone         = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
            $current_pass  = isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '';
            $new_pass      = isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '';
            $confirm_pass  = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : '';

            if ( '' === $first_name ) {
                wp_send_json_error( array( 'message' => __( 'Укажите имя.', 'amaressence-account-suite' ) ), 400 );
            }

            if ( '' === $display_name ) {
                $display_name = trim( $first_name . ' ' . $last_name );
            }

            if ( '' === $email || ! is_email( $email ) ) {
                wp_send_json_error( array( 'message' => __( 'Укажите корректный email.', 'amaressence-account-suite' ) ), 400 );
            }

            $email_owner = email_exists( $email );
            if ( $email_owner && (int) $email_owner !== (int) $user_id ) {
                wp_send_json_error( array( 'message' => __( 'Этот email уже используется.', 'amaressence-account-suite' ) ), 400 );
            }

            $update = wp_update_user(
                array(
                    'ID'           => $user_id,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'display_name' => $display_name,
                    'user_email'   => $email,
                )
            );

            if ( is_wp_error( $update ) ) {
                wp_send_json_error( array( 'message' => wp_strip_all_tags( $update->get_error_message() ) ), 400 );
            }

            update_user_meta( $user_id, 'billing_phone', $phone );

            if ( '' !== $new_pass || '' !== $confirm_pass ) {
                if ( '' === $current_pass ) {
                    wp_send_json_error( array( 'message' => __( 'Чтобы сменить пароль, введите текущий пароль.', 'amaressence-account-suite' ) ), 400 );
                }

                if ( ! wp_check_password( $current_pass, $current_user->user_pass, $user_id ) ) {
                    wp_send_json_error( array( 'message' => __( 'Текущий пароль указан неверно.', 'amaressence-account-suite' ) ), 400 );
                }

                if ( strlen( $new_pass ) < 8 ) {
                    wp_send_json_error( array( 'message' => __( 'Новый пароль должен содержать минимум 8 символов.', 'amaressence-account-suite' ) ), 400 );
                }

                if ( $new_pass !== $confirm_pass ) {
                    wp_send_json_error( array( 'message' => __( 'Новые пароли не совпадают.', 'amaressence-account-suite' ) ), 400 );
                }

                wp_set_password( $new_pass, $user_id );
                $user = get_user_by( 'id', $user_id );
                if ( $user instanceof WP_User ) {
                    wp_set_current_user( $user->ID, $user->user_login );
                    wp_set_auth_cookie( $user->ID, true );
                }
            }

            wp_send_json_success(
                array(
                    'message' => __( 'Профиль обновлён.', 'amaressence-account-suite' ),
                )
            );
        }

        public function ajax_save_addresses() {
            $this->ensure_ajax_nonce();
            $this->require_logged_in_customer();

            $user_id = $this->current_customer_id();
            $fields  = array(
                'billing_first_name',
                'billing_last_name',
                'billing_company',
                'billing_phone',
                'billing_country',
                'billing_state',
                'billing_postcode',
                'billing_city',
                'billing_address_1',
                'billing_address_2',
            );

            foreach ( $fields as $field ) {
                $value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
                update_user_meta( $user_id, $field, $value );
            }

            wp_send_json_success(
                array(
                    'message'        => __( 'Платёжный адрес обновлён.', 'amaressence-account-suite' ),
                    'billingPreview' => $this->format_address_preview( 'billing', $user_id ),
                )
            );
        }

        public function ajax_get_order() {
            $this->ensure_ajax_nonce();
            $this->require_logged_in_customer();

            if ( ! function_exists( 'wc_get_order' ) ) {
                wp_send_json_error( array( 'message' => __( 'WooCommerce не активирован.', 'amaressence-account-suite' ) ), 400 );
            }

            $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
            $order    = wc_get_order( $order_id );

            if ( ! $order || (int) $order->get_customer_id() !== (int) $this->current_customer_id() ) {
                wp_send_json_error( array( 'message' => __( 'Заказ не найден.', 'amaressence-account-suite' ) ), 404 );
            }

            wp_send_json_success(
                array(
                    'html' => $this->get_order_details_markup( $order ),
                )
            );
        }

        public function ajax_cancel_order() {
            $this->ensure_ajax_nonce();
            $this->require_logged_in_customer();

            if ( ! function_exists( 'wc_get_order' ) ) {
                wp_send_json_error( array( 'message' => __( 'WooCommerce не активирован.', 'amaressence-account-suite' ) ), 400 );
            }

            $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
            $order    = wc_get_order( $order_id );

            if ( ! $order || (int) $order->get_customer_id() !== (int) $this->current_customer_id() ) {
                wp_send_json_error( array( 'message' => __( 'Заказ не найден.', 'amaressence-account-suite' ) ), 404 );
            }

            if ( ! $this->can_cancel_order( $order ) ) {
                wp_send_json_error( array( 'message' => __( 'Этот заказ уже нельзя отменить из кабинета: доставка принята на обработку или заказ находится в финальном статусе.', 'amaressence-account-suite' ) ), 400 );
            }

            $cancel_result = $this->cancel_yandex_delivery_request_if_possible( $order );

            if ( is_wp_error( $cancel_result ) ) {
                wp_send_json_error( array( 'message' => $cancel_result->get_error_message() ), 400 );
            }

            $order = wc_get_order( $order_id );

            if ( ! $order instanceof WC_Order ) {
                wp_send_json_error( array( 'message' => __( 'Заказ не найден после отмены.', 'amaressence-account-suite' ) ), 404 );
            }

            if ( ! $order->has_status( 'cancelled' ) ) {
                $order->update_status( 'cancelled', __( 'Заказ отменён покупателем из личного кабинета.', 'amaressence-account-suite' ), true );
            } else {
                $order->add_order_note( __( 'Покупатель повторно подтвердил отмену заказа из личного кабинета.', 'amaressence-account-suite' ) );
                $order->save();
            }

            $this->send_customer_cancelled_admin_email( $order );

            wp_send_json_success(
                array(
                    'message' => __( 'Заказ отменён. Мы отправили уведомление администратору магазина.', 'amaressence-account-suite' ),
                    'status'  => wc_get_order_status_name( $order->get_status() ),
                    'badge'   => sanitize_html_class( $order->get_status() ),
                )
            );
        }

        public function render_auth_shortcode( $atts = array() ) {
            $this->enqueue_assets();

            $atts = shortcode_atts(
                array(
                    'title'       => 'Аккаунт',
                    'subtitle'    => 'Войдите или создайте аккаунт, чтобы управлять заказами и данными профиля.',
                    'terms_url'          => self::USER_AGREEMENT_URL,
                    'privacy_url'        => '',
                    'user_agreement_url' => self::USER_AGREEMENT_URL,
                    'loyalty_url'        => self::LOYALTY_PROGRAM_URL,
                ),
                $atts,
                'ama_auth_form'
            );

            if ( is_user_logged_in() ) {
                $user = wp_get_current_user();
                ob_start();
                ?>
                <section class="ama-shell ama-shell--narrow">
                    <div class="ama-auth-card ama-auth-card--compact">
                        <div class="ama-headline-row">
                            <div>
                                <div class="ama-eyebrow"><?php echo esc_html__( 'Аккаунт', 'amaressence-account-suite' ); ?></div>
                                <h2 class="ama-title"><?php echo esc_html( sprintf( __( 'Вы уже вошли, %s', 'amaressence-account-suite' ), $user->display_name ) ); ?></h2>
                                <p class="ama-subtitle"><?php echo esc_html__( 'Можно сразу перейти в кабинет или продолжить покупки.', 'amaressence-account-suite' ); ?></p>
                            </div>
                        </div>
                        <div class="ama-inline-actions">
                            <a class="ama-btn ama-btn--primary" href="#ama-dashboard-anchor"><?php echo esc_html__( 'Открыть кабинет', 'amaressence-account-suite' ); ?></a>
                            <a class="ama-btn ama-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'На главную', 'amaressence-account-suite' ); ?></a>
                        </div>
                    </div>
                </section>
                <?php
                return (string) ob_get_clean();
            }

            ob_start();
            ?>
            <section class="ama-shell ama-shell--narrow">
                <div class="ama-auth-card" data-ama-auth data-active-tab="<?php echo esc_attr( $this->active_tab ); ?>">
                    <?php echo $this->get_notices_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                    <div class="ama-headline-row">
                        <div>
                            <div class="ama-eyebrow"><?php echo esc_html( $atts['title'] ); ?></div>
                            <h2 class="ama-title"><?php echo esc_html__( 'Вход и регистрация', 'amaressence-account-suite' ); ?></h2>
                            <p class="ama-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
                        </div>
                    </div>

                    <div class="ama-tab-switcher" role="tablist" aria-label="<?php echo esc_attr__( 'Вход и регистрация', 'amaressence-account-suite' ); ?>">
                        <button class="ama-tab-button <?php echo 'login' === $this->active_tab ? 'is-active' : ''; ?>" type="button" data-tab="login" role="tab" aria-selected="<?php echo 'login' === $this->active_tab ? 'true' : 'false'; ?>"><?php echo esc_html__( 'Вход', 'amaressence-account-suite' ); ?></button>
                        <button class="ama-tab-button <?php echo 'register' === $this->active_tab ? 'is-active' : ''; ?>" type="button" data-tab="register" role="tab" aria-selected="<?php echo 'register' === $this->active_tab ? 'true' : 'false'; ?>"><?php echo esc_html__( 'Регистрация', 'amaressence-account-suite' ); ?></button>
                    </div>

                    <div class="ama-tab-panel <?php echo 'login' === $this->active_tab ? 'is-active' : ''; ?>" data-panel="login">
                        <form method="post" class="ama-form" data-ama-ajax-form="login" novalidate>
                            <?php wp_nonce_field( 'ama_auth_submit', 'ama_nonce' ); ?>
                            <input type="hidden" name="ama_form_action" value="login">

                            <label class="ama-field">
                                <span><?php echo esc_html__( 'Email или логин', 'amaressence-account-suite' ); ?></span>
                                <input type="text" name="ama_login" autocomplete="username" required>
                            </label>

                            <label class="ama-field ama-field--password">
                                <span><?php echo esc_html__( 'Пароль', 'amaressence-account-suite' ); ?></span>
                                <input type="password" name="ama_password" autocomplete="current-password" required>
                                <button class="ama-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-account-suite' ); ?>"></button>
                            </label>

                            <div class="ama-form-meta">
                                <label class="ama-check">
                                    <input type="checkbox" name="ama_remember" value="1">
                                    <span><?php echo esc_html__( 'Запомнить меня', 'amaressence-account-suite' ); ?></span>
                                </label>
                                <a class="ama-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html__( 'Забыли пароль?', 'amaressence-account-suite' ); ?></a>
                            </div>

                            <div class="ama-ajax-response" data-form-message></div>
                            <button class="ama-btn ama-btn--primary ama-btn--full" type="submit"><?php echo esc_html__( 'Войти', 'amaressence-account-suite' ); ?></button>
                        </form>
                    </div>

                    <div class="ama-tab-panel <?php echo 'register' === $this->active_tab ? 'is-active' : ''; ?>" data-panel="register">
                        <form method="post" class="ama-form" data-ama-ajax-form="register" novalidate>
                            <?php wp_nonce_field( 'ama_auth_submit', 'ama_nonce' ); ?>
                            <input type="hidden" name="ama_form_action" value="register">

                            <div class="ama-grid ama-grid--2">
                                <label class="ama-field">
                                    <span><?php echo esc_html__( 'Имя', 'amaressence-account-suite' ); ?></span>
                                    <input type="text" name="ama_first_name" autocomplete="given-name" required>
                                </label>
                                <label class="ama-field">
                                    <span><?php echo esc_html__( 'Фамилия', 'amaressence-account-suite' ); ?></span>
                                    <input type="text" name="ama_last_name" autocomplete="family-name">
                                </label>
                            </div>

                            <label class="ama-field">
                                <span><?php echo esc_html__( 'Email', 'amaressence-account-suite' ); ?></span>
                                <input type="email" name="ama_email" autocomplete="email" required>
                            </label>

                            <label class="ama-field">
                                <span><?php echo esc_html__( 'Телефон', 'amaressence-account-suite' ); ?></span>
                                <input type="tel" name="ama_phone" autocomplete="tel">
                            </label>

                            <div class="ama-grid ama-grid--2">
                                <label class="ama-field ama-field--password">
                                    <span><?php echo esc_html__( 'Пароль', 'amaressence-account-suite' ); ?></span>
                                    <input type="password" name="ama_reg_password" autocomplete="new-password" required>
                                    <button class="ama-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-account-suite' ); ?>"></button>
                                </label>
                                <label class="ama-field ama-field--password">
                                    <span><?php echo esc_html__( 'Повторите пароль', 'amaressence-account-suite' ); ?></span>
                                    <input type="password" name="ama_reg_password_confirm" autocomplete="new-password" required>
                                    <button class="ama-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-account-suite' ); ?>"></button>
                                </label>
                            </div>

                            <?php
                            $user_agreement_url = ! empty( $atts['user_agreement_url'] ) ? $atts['user_agreement_url'] : $atts['terms_url'];
                            $loyalty_url        = ! empty( $atts['loyalty_url'] ) ? $atts['loyalty_url'] : self::LOYALTY_PROGRAM_URL;
                            ?>
                            <div class="ama-consents">
                                <label class="ama-check ama-check--block">
                                    <input type="checkbox" name="ama_marketing_consent" value="1" checked>
                                    <span><?php echo esc_html__( 'Подтверждаю согласие на получение информации о новинках и акциях. Ваш подарок — промокод на скидку 15% на 90 дней', 'amaressence-account-suite' ); ?></span>
                                </label>

                                <label class="ama-check ama-check--block">
                                    <input type="checkbox" name="ama_privacy" value="1" required>
                                    <span>
                                        <?php echo esc_html__( 'Подтверждаю своё согласие на обработку и хранение моих персональных данных в соответствии с', 'amaressence-account-suite' ); ?>
                                        <a class="ama-link" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $user_agreement_url ); ?>"><?php echo esc_html__( 'пользовательским соглашением', 'amaressence-account-suite' ); ?></a>
                                    </span>
                                </label>

                                <label class="ama-check ama-check--block">
                                    <input type="checkbox" name="ama_loyalty_consent" value="1" checked>
                                    <span>
                                        <?php echo esc_html__( 'Соглашаюсь с условиями', 'amaressence-account-suite' ); ?>
                                        <a class="ama-link" target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( $loyalty_url ); ?>"><?php echo esc_html__( 'программы лояльности', 'amaressence-account-suite' ); ?></a>
                                    </span>
                                </label>
                            </div>

                            <div class="ama-ajax-response" data-form-message></div>
                            <button class="ama-btn ama-btn--primary ama-btn--full" type="submit"><?php echo esc_html__( 'Создать аккаунт', 'amaressence-account-suite' ); ?></button>
                        </form>
                    </div>
                </div>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        public function render_dashboard_shortcode( $atts = array() ) {
            $this->enqueue_assets();

            $atts = shortcode_atts(
                array(
                    'title'         => 'Личный кабинет',
                    'auth_page_url' => '',
                ),
                $atts,
                'ama_customer_dashboard'
            );

            if ( ! function_exists( 'wc_get_orders' ) ) {
                return '<div class="ama-shell"><div class="ama-auth-card"><div class="ama-notice ama-notice--error">' . esc_html__( 'Для работы кабинета нужен WooCommerce.', 'amaressence-account-suite' ) . '</div></div></div>';
            }

            if ( ! is_user_logged_in() ) {
                $auth_url = $atts['auth_page_url'] ? $atts['auth_page_url'] : get_permalink();
                ob_start();
                ?>
                <section class="ama-shell">
                    <div class="ama-auth-card ama-auth-card--compact">
                        <div class="ama-headline-row">
                            <div>
                                <div class="ama-eyebrow"><?php echo esc_html__( 'Личный кабинет', 'amaressence-account-suite' ); ?></div>
                                <h2 class="ama-title"><?php echo esc_html__( 'Нужно выполнить вход', 'amaressence-account-suite' ); ?></h2>
                                <p class="ama-subtitle"><?php echo esc_html__( 'После входа здесь появятся заказы, платёжный адрес и настройки профиля.', 'amaressence-account-suite' ); ?></p>
                            </div>
                        </div>
                        <div class="ama-inline-actions">
                            <a class="ama-btn ama-btn--primary" href="<?php echo esc_url( $auth_url ); ?>"><?php echo esc_html__( 'Перейти ко входу', 'amaressence-account-suite' ); ?></a>
                        </div>
                    </div>
                </section>
                <?php
                return (string) ob_get_clean();
            }

            $user            = wp_get_current_user();
            $user_id         = $user->ID;
            $recent_orders   = $this->get_recent_orders( $user_id, 10 );
            $total_orders    = $this->get_total_orders( $user_id );
            $billing_address   = $this->format_address_preview( 'billing', $user_id );
            $avatar_letter     = $this->get_avatar_letter( $user );
            $phone             = get_user_meta( $user_id, 'billing_phone', true );
            $referral_profile  = $this->get_relod_referral_profile_for_user( $user_id );
            $referral_link     = $this->build_relod_referral_link( $referral_profile );
            $points_balance    = $this->format_points_balance( $this->get_points_balance( $user_id, $referral_profile ) );

            ob_start();
            ?>
            <section class="ama-shell ama-dashboard-shell" id="ama-dashboard-anchor">
                <div class="ama-dashboard ama-dashboard--editorial" data-ama-dashboard>
                    <aside class="ama-dashboard-sidebar">
                        <div class="ama-dashboard-sidebar__brand">
                            <div class="ama-eyebrow"><?php echo esc_html__( 'Кабинет клиента', 'amaressence-account-suite' ); ?></div>
                            <div class="ama-dashboard-sidebar__line" aria-hidden="true"></div>
                        </div>

                        <div class="ama-profile-block">
                            <div class="ama-avatar"><?php echo esc_html( $avatar_letter ); ?></div>
                            <div class="ama-profile-block__content">
                                <h2 class="ama-title"><?php echo esc_html( $atts['title'] ); ?></h2>
                                <div class="ama-profile-name"><?php echo esc_html( $user->display_name ); ?></div>
                                <div class="ama-profile-email"><?php echo esc_html( $user->user_email ); ?></div>
                            </div>
                        </div>

                        <div class="ama-dashboard-nav" role="tablist" aria-label="<?php echo esc_attr__( 'Разделы кабинета', 'amaressence-account-suite' ); ?>">
                            <button class="ama-dash-tab is-active" type="button" data-dashboard-tab="overview"><?php echo esc_html__( 'Главная', 'amaressence-account-suite' ); ?></button>
                            <button class="ama-dash-tab" type="button" data-dashboard-tab="orders"><?php echo esc_html__( 'Заказы', 'amaressence-account-suite' ); ?></button>
                            <button class="ama-dash-tab" type="button" data-dashboard-tab="profile"><?php echo esc_html__( 'Профиль', 'amaressence-account-suite' ); ?></button>
                            <button class="ama-dash-tab" type="button" data-dashboard-tab="addresses"><?php echo esc_html__( 'Адреса', 'amaressence-account-suite' ); ?></button>
                        </div>

                        <div class="ama-dashboard-sidebar__footer">
                            <a class="ama-btn ama-btn--ghost ama-btn--full" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php echo esc_html__( 'Выйти', 'amaressence-account-suite' ); ?></a>
                        </div>
                    </aside>

                    <div class="ama-dashboard-main">
                        <div class="ama-dashboard-main__head">
                            <div>
                                <div class="ama-eyebrow"><?php echo esc_html__( 'Личный кабинет', 'amaressence-account-suite' ); ?></div>
                                <h3 class="ama-dashboard-main__title"><?php echo esc_html__( 'Управление аккаунтом', 'amaressence-account-suite' ); ?></h3>
                                <p class="ama-subtitle"><?php echo esc_html__( 'Заказы, платёжный адрес и личные данные собраны в одном интерфейсе.', 'amaressence-account-suite' ); ?></p>
                            </div>
                        </div>

                        <div class="ama-dashboard-stage" data-dashboard-stage>
                            <div class="ama-dashboard-panel is-active" data-dashboard-panel="overview">
                                <div class="ama-content-stack">
                                    <div class="ama-page-heading">
                                        <div>
                                            <div class="ama-eyebrow"><?php echo esc_html__( 'Главная', 'amaressence-account-suite' ); ?></div>
                                            <h3><?php echo esc_html__( 'Обзор аккаунта', 'amaressence-account-suite' ); ?></h3>
                                            <p><?php echo esc_html__( 'Краткая сводка по заказам, бонусам и основным данным профиля.', 'amaressence-account-suite' ); ?></p>
                                        </div>
                                    </div>

                                    <div class="ama-stats-grid ama-stats-grid--compact">
                                        <div class="ama-stat-card">
                                            <span class="ama-stat-label"><?php echo esc_html__( 'Всего заказов', 'amaressence-account-suite' ); ?></span>
                                            <strong class="ama-stat-value"><?php echo esc_html( (string) $total_orders ); ?></strong>
                                        </div>
                                        <div class="ama-stat-card ama-stat-card--accent">
                                            <span class="ama-stat-label"><?php echo esc_html__( 'Всего баллов', 'amaressence-account-suite' ); ?></span>
                                            <strong class="ama-stat-value"><?php echo esc_html( $points_balance ); ?></strong>
                                        </div>
                                    </div>

                                    <div class="ama-panel ama-referral-card">
                                        <div class="ama-panel-head">
                                            <div>
                                                <h3><?php echo esc_html__( 'Ваша реферальная ссылка', 'amaressence-account-suite' ); ?></h3>
                                                <p><?php echo esc_html__( 'Поделитесь ссылкой с новым покупателем. Баллы появятся после заказа, если условия реферальной программы выполнены.', 'amaressence-account-suite' ); ?></p>
                                            </div>
                                        </div>
                                        <?php if ( $referral_link ) : ?>
                                            <div class="ama-referral-link-field">
                                                <input type="text" readonly value="<?php echo esc_attr( $referral_link ); ?>" aria-label="<?php echo esc_attr__( 'Личная реферальная ссылка', 'amaressence-account-suite' ); ?>">
                                                <button class="ama-btn ama-btn--primary ama-btn--small" type="button" data-copy-to-clipboard="<?php echo esc_attr( $referral_link ); ?>" data-copy-feedback="ama-referral-copy-feedback"><?php echo esc_html__( 'Скопировать', 'amaressence-account-suite' ); ?></button>
                                            </div>
                                            <p class="ama-referral-code"><?php echo esc_html__( 'Код:', 'amaressence-account-suite' ); ?> <code><?php echo esc_html( $referral_profile['referral_code'] ); ?></code></p>
                                            <p class="ama-referral-copy-feedback" aria-live="polite"></p>
                                        <?php else : ?>
                                            <div class="ama-empty-box ama-empty-box--soft">
                                                <p><?php echo esc_html__( 'Пока реферальной ссылки нет. Если хотите получить, оформите свой первый заказ.', 'amaressence-account-suite' ); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="ama-overview-grid ama-overview-grid--refined">
                                        <div class="ama-panel ama-panel--wide">
                                            <div class="ama-panel-head">
                                                <div>
                                                    <h3><?php echo esc_html__( 'Последние заказы', 'amaressence-account-suite' ); ?></h3>
                                                    <p><?php echo esc_html__( 'Краткий обзор последних покупок без переходов на другие страницы.', 'amaressence-account-suite' ); ?></p>
                                                </div>
                                                <button class="ama-link-button" type="button" data-open-dashboard-tab="orders"><?php echo esc_html__( 'Открыть раздел', 'amaressence-account-suite' ); ?></button>
                                            </div>
                                            <?php echo $this->get_orders_table_markup( array_slice( $recent_orders, 0, 4 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </div>

                                        <div class="ama-panel">
                                            <div class="ama-panel-head">
                                                <div>
                                                    <h3><?php echo esc_html__( 'Платёжный адрес', 'amaressence-account-suite' ); ?></h3>
                                                    <p><?php echo esc_html__( 'Адрес, который используется при оформлении заказов.', 'amaressence-account-suite' ); ?></p>
                                                </div>
                                                <button class="ama-link-button" type="button" data-open-dashboard-tab="addresses"><?php echo esc_html__( 'Изменить', 'amaressence-account-suite' ); ?></button>
                                            </div>
                                            <div class="ama-address-preview" data-billing-preview><?php echo wp_kses_post( $billing_address ); ?></div>
                                        </div>

                                        <div class="ama-panel">
                                            <div class="ama-panel-head">
                                                <div>
                                                    <h3><?php echo esc_html__( 'Данные аккаунта', 'amaressence-account-suite' ); ?></h3>
                                                    <p><?php echo esc_html__( 'Основная информация для связи и входа в кабинет.', 'amaressence-account-suite' ); ?></p>
                                                </div>
                                                <button class="ama-link-button" type="button" data-open-dashboard-tab="profile"><?php echo esc_html__( 'Редактировать', 'amaressence-account-suite' ); ?></button>
                                            </div>
                                            <div class="ama-summary-list">
                                                <div class="ama-summary-list__item">
                                                    <span class="ama-summary-list__label"><?php echo esc_html__( 'Имя', 'amaressence-account-suite' ); ?></span>
                                                    <strong class="ama-summary-list__value"><?php echo esc_html( $user->display_name ); ?></strong>
                                                </div>
                                                <div class="ama-summary-list__item">
                                                    <span class="ama-summary-list__label"><?php echo esc_html__( 'Email', 'amaressence-account-suite' ); ?></span>
                                                    <strong class="ama-summary-list__value"><?php echo esc_html( $user->user_email ); ?></strong>
                                                </div>
                                                <div class="ama-summary-list__item">
                                                    <span class="ama-summary-list__label"><?php echo esc_html__( 'Телефон', 'amaressence-account-suite' ); ?></span>
                                                    <strong class="ama-summary-list__value"><?php echo esc_html( $phone ? $phone : '—' ); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="ama-dashboard-panel" data-dashboard-panel="orders">
                                <div class="ama-content-stack">
                                    <div class="ama-page-heading">
                                        <div>
                                            <div class="ama-eyebrow"><?php echo esc_html__( 'Заказы', 'amaressence-account-suite' ); ?></div>
                                            <h3><?php echo esc_html__( 'История заказов', 'amaressence-account-suite' ); ?></h3>
                                            <p><?php echo esc_html__( 'Просмотр деталей заказа и отмена доступных заказов прямо внутри кабинета.', 'amaressence-account-suite' ); ?></p>
                                        </div>
                                    </div>
                                    <div class="ama-panel">
                                        <?php echo $this->get_orders_table_markup( $recent_orders, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    </div>
                                </div>
                            </div>

                            <div class="ama-dashboard-panel" data-dashboard-panel="profile">
                                <div class="ama-content-stack">
                                    <div class="ama-page-heading">
                                        <div>
                                            <div class="ama-eyebrow"><?php echo esc_html__( 'Профиль', 'amaressence-account-suite' ); ?></div>
                                            <h3><?php echo esc_html__( 'Личные данные', 'amaressence-account-suite' ); ?></h3>
                                            <p><?php echo esc_html__( 'Изменяйте контактные данные и пароль без перезагрузки страницы.', 'amaressence-account-suite' ); ?></p>
                                        </div>
                                    </div>

                                    <div class="ama-panel">
                                        <form class="ama-form" data-ama-dashboard-form="profile" novalidate>
                                            <div class="ama-grid ama-grid--2">
                                                <label class="ama-field">
                                                    <span><?php echo esc_html__( 'Имя', 'amaressence-account-suite' ); ?></span>
                                                    <input type="text" name="first_name" required value="<?php echo esc_attr( get_user_meta( $user_id, 'first_name', true ) ); ?>">
                                                </label>
                                                <label class="ama-field">
                                                    <span><?php echo esc_html__( 'Фамилия', 'amaressence-account-suite' ); ?></span>
                                                    <input type="text" name="last_name" value="<?php echo esc_attr( get_user_meta( $user_id, 'last_name', true ) ); ?>">
                                                </label>
                                            </div>
                                            <div class="ama-grid ama-grid--2">
                                                <label class="ama-field">
                                                    <span><?php echo esc_html__( 'Отображаемое имя', 'amaressence-account-suite' ); ?></span>
                                                    <input type="text" name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>">
                                                </label>
                                                <label class="ama-field">
                                                    <span><?php echo esc_html__( 'Телефон', 'amaressence-account-suite' ); ?></span>
                                                    <input type="tel" name="phone" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_phone', true ) ); ?>">
                                                </label>
                                            </div>
                                            <label class="ama-field">
                                                <span><?php echo esc_html__( 'Email', 'amaressence-account-suite' ); ?></span>
                                                <input type="email" name="email" required value="<?php echo esc_attr( $user->user_email ); ?>">
                                            </label>
                                            <div class="ama-section-sep"></div>
                                            <div class="ama-grid ama-grid--3">
                                                <label class="ama-field ama-field--password">
                                                    <span><?php echo esc_html__( 'Текущий пароль', 'amaressence-account-suite' ); ?></span>
                                                    <input type="password" name="current_password" autocomplete="current-password">
                                                    <button class="ama-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-account-suite' ); ?>"></button>
                                                </label>
                                                <label class="ama-field ama-field--password">
                                                    <span><?php echo esc_html__( 'Новый пароль', 'amaressence-account-suite' ); ?></span>
                                                    <input type="password" name="new_password" autocomplete="new-password">
                                                    <button class="ama-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-account-suite' ); ?>"></button>
                                                </label>
                                                <label class="ama-field ama-field--password">
                                                    <span><?php echo esc_html__( 'Повторите новый пароль', 'amaressence-account-suite' ); ?></span>
                                                    <input type="password" name="confirm_password" autocomplete="new-password">
                                                    <button class="ama-password-toggle" type="button" data-toggle-password aria-label="<?php echo esc_attr__( 'Показать или скрыть пароль', 'amaressence-account-suite' ); ?>"></button>
                                                </label>
                                            </div>
                                            <div class="ama-ajax-response" data-form-message></div>
                                            <button class="ama-btn ama-btn--primary" type="submit"><?php echo esc_html__( 'Сохранить профиль', 'amaressence-account-suite' ); ?></button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="ama-dashboard-panel" data-dashboard-panel="addresses">
                                <div class="ama-content-stack">
                                    <div class="ama-page-heading">
                                        <div>
                                            <div class="ama-eyebrow"><?php echo esc_html__( 'Адреса', 'amaressence-account-suite' ); ?></div>
                                            <h3><?php echo esc_html__( 'Платёжный адрес', 'amaressence-account-suite' ); ?></h3>
                                            <p><?php echo esc_html__( 'В этом разделе доступно редактирование только платёжного адреса.', 'amaressence-account-suite' ); ?></p>
                                        </div>
                                    </div>

                                    <div class="ama-panel">
                                        <form class="ama-form" data-ama-dashboard-form="addresses" novalidate>
                                            <div class="ama-addresses-grid ama-addresses-grid--single">
                                                <div class="ama-address-box">
                                                    <div class="ama-address-box-head">
                                                        <h4><?php echo esc_html__( 'Платёжный адрес', 'amaressence-account-suite' ); ?></h4>
                                                        <button class="ama-link-button" type="button" data-clear-address="billing"><?php echo esc_html__( 'Очистить', 'amaressence-account-suite' ); ?></button>
                                                    </div>
                                                    <?php echo $this->render_address_fields( 'billing', $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                                </div>
                                                <div class="ama-address-box ama-address-box--preview">
                                                    <div class="ama-address-box-head">
                                                        <h4><?php echo esc_html__( 'Текущий адрес', 'amaressence-account-suite' ); ?></h4>
                                                    </div>
                                                    <div class="ama-address-preview" data-billing-preview><?php echo wp_kses_post( $billing_address ); ?></div>
                                                </div>
                                            </div>
                                            <div class="ama-ajax-response" data-form-message></div>
                                            <button class="ama-btn ama-btn--primary" type="submit"><?php echo esc_html__( 'Сохранить адрес', 'amaressence-account-suite' ); ?></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ama-order-modal" data-order-modal hidden>
                    <div class="ama-order-modal__backdrop" data-close-order-modal></div>
                    <div class="ama-order-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Детали заказа', 'amaressence-account-suite' ); ?>">
                        <button class="ama-order-modal__close" type="button" data-close-order-modal aria-label="<?php echo esc_attr__( 'Закрыть', 'amaressence-account-suite' ); ?>">×</button>
                        <div class="ama-order-modal__body" data-order-modal-body></div>
                    </div>
                </div>
            </section>
            <?php
            return (string) ob_get_clean();
        }

        private function get_recent_orders( $user_id, $limit = 10 ) {
            $orders = wc_get_orders(
                array(
                    'customer_id' => $user_id,
                    'limit'       => $limit,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'return'      => 'objects',
                )
            );

            return is_array( $orders ) ? $orders : array();
        }

        private function get_total_orders( $user_id ) {
            return function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $user_id ) : 0;
        }

        private function get_total_spent( $user_id ) {
            if ( function_exists( 'wc_get_customer_total_spent' ) ) {
                $total = wc_get_customer_total_spent( $user_id );
                return function_exists( 'wc_price' ) ? wc_price( (float) $total ) : esc_html( (string) $total );
            }
            return '—';
        }

        private function get_points_balance( $user_id, $referral_profile = null ) {
            $filtered_balance = apply_filters( 'ama_account_suite_points_balance', null, $user_id );
            if ( null !== $filtered_balance && '' !== $filtered_balance ) {
                return $this->normalize_points_balance( $filtered_balance );
            }

            if ( is_array( $referral_profile ) && isset( $referral_profile['points_balance'] ) && is_numeric( $referral_profile['points_balance'] ) ) {
                return $this->normalize_points_balance( $referral_profile['points_balance'] );
            }

            $relod_balance = $this->get_relod_referral_points_balance( $user_id );
            if ( null !== $relod_balance ) {
                return $this->normalize_points_balance( $relod_balance );
            }

            $meta_keys = apply_filters(
                'ama_account_suite_points_meta_keys',
                array(
                    'ama_referral_points_balance',
                    'ama_referral_points_total',
                    'ama_points_balance',
                    'ama_points_total',
                    'referral_points_balance',
                    'referral_points_total',
                    'relod_points_balance',
                    'relod_referral_points',
                    'reward_points_balance',
                    'reward_points',
                    'loyalty_points_balance',
                    'loyalty_points',
                    'bonus_points_balance',
                    'bonus_points',
                    'points_balance',
                    'user_points',
                ),
                $user_id
            );

            foreach ( $meta_keys as $meta_key ) {
                $value = get_user_meta( $user_id, $meta_key, true );
                if ( '' !== $value && null !== $value && is_numeric( $value ) ) {
                    return $this->normalize_points_balance( $value );
                }
            }

            return $this->detect_points_balance_from_user_meta( $user_id );
        }

        private function get_relod_referral_points_balance( $user_id ) {
            $profile = $this->get_relod_referral_profile_for_user( $user_id );

            if ( is_array( $profile ) && isset( $profile['points_balance'] ) && is_numeric( $profile['points_balance'] ) ) {
                return $this->normalize_points_balance( $profile['points_balance'] );
            }

            return null;
        }

        private function get_relod_referral_profile_for_user( $user_id ) {
            global $wpdb;

            $user_id = absint( $user_id );

            if ( ! $user_id || ! function_exists( 'get_userdata' ) ) {
                return null;
            }

            $profiles_table = $wpdb->prefix . 'rrp_profiles';

            if ( ! $this->database_table_exists( $profiles_table ) ) {
                return null;
            }

            $user = get_userdata( $user_id );
            if ( ! $user instanceof WP_User ) {
                return null;
            }

            $email      = ! empty( $user->user_email ) ? strtolower( trim( (string) $user->user_email ) ) : '';
            $profile_id = absint( get_user_meta( $user_id, '_rrp_profile_id', true ) );

            if ( $profile_id ) {
                $profile = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$profiles_table} WHERE id = %d AND (user_id = %d OR email = %s) LIMIT 1",
                        $profile_id,
                        $user_id,
                        $email
                    ),
                    ARRAY_A
                );

                if ( is_array( $profile ) && ! empty( $profile['referral_code'] ) ) {
                    $this->maybe_link_relod_profile_to_user( $profile, $user_id, $email );
                    return $profile;
                }
            }

            $profile = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$profiles_table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                    $user_id
                ),
                ARRAY_A
            );

            if ( is_array( $profile ) && ! empty( $profile['referral_code'] ) ) {
                if ( $profile_id !== absint( $profile['id'] ) ) {
                    update_user_meta( $user_id, '_rrp_profile_id', absint( $profile['id'] ) );
                }

                return $profile;
            }

            if ( $email ) {
                $profile = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM {$profiles_table} WHERE email = %s ORDER BY id DESC LIMIT 1",
                        $email
                    ),
                    ARRAY_A
                );

                if ( is_array( $profile ) && ! empty( $profile['referral_code'] ) ) {
                    $this->maybe_link_relod_profile_to_user( $profile, $user_id, $email );
                    return $profile;
                }
            }

            return null;
        }

        private function maybe_link_relod_profile_to_user( $profile, $user_id, $email ) {
            global $wpdb;

            if ( ! is_array( $profile ) || empty( $profile['id'] ) || ! $user_id ) {
                return;
            }

            $profiles_table = $wpdb->prefix . 'rrp_profiles';
            $profile_email  = isset( $profile['email'] ) ? strtolower( trim( (string) $profile['email'] ) ) : '';
            $profile_user   = isset( $profile['user_id'] ) ? absint( $profile['user_id'] ) : 0;
            $profile_id     = absint( $profile['id'] );

            if ( $email && $profile_email === $email && 0 === $profile_user ) {
                $wpdb->update(
                    $profiles_table,
                    array(
                        'user_id'    => $user_id,
                        'updated_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $profile_id ),
                    array( '%d', '%s' ),
                    array( '%d' )
                );
            }

            update_user_meta( $user_id, '_rrp_profile_id', $profile_id );
        }

        private function build_relod_referral_link( $profile ) {
            if ( ! is_array( $profile ) || empty( $profile['referral_code'] ) ) {
                return '';
            }

            $settings = get_option( 'rrp_settings', array() );
            $param    = 'ref';

            if ( is_array( $settings ) && ! empty( $settings['referral_url_param'] ) ) {
                $param = sanitize_key( $settings['referral_url_param'] );
            }

            if ( '' === $param ) {
                $param = 'ref';
            }

            return add_query_arg(
                array(
                    $param => rawurlencode( sanitize_text_field( $profile['referral_code'] ) ),
                ),
                home_url( '/' )
            );
        }

        private function database_table_exists( $table_name ) {
            global $wpdb;

            $table_name = (string) $table_name;

            if ( '' === $table_name ) {
                return false;
            }

            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

            return $table_name === $found;
        }

        private function detect_points_balance_from_user_meta( $user_id ) {
            global $wpdb;

            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND (meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s)",
                    $user_id,
                    '%point%',
                    '%bonus%',
                    '%reward%',
                    '%loyalty%',
                    '%referral%'
                ),
                ARRAY_A
            );

            if ( empty( $rows ) ) {
                return 0;
            }

            $candidates = array();

            foreach ( $rows as $row ) {
                if ( empty( $row['meta_key'] ) ) {
                    continue;
                }

                $value = maybe_unserialize( $row['meta_value'] );
                if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
                    continue;
                }

                $meta_key = strtolower( (string) $row['meta_key'] );
                $score    = 0;

                if ( false !== strpos( $meta_key, 'balance' ) ) {
                    $score += 8;
                }
                if ( false !== strpos( $meta_key, 'total' ) ) {
                    $score += 6;
                }
                if ( false !== strpos( $meta_key, 'point' ) ) {
                    $score += 5;
                }
                if ( false !== strpos( $meta_key, 'bonus' ) ) {
                    $score += 4;
                }
                if ( false !== strpos( $meta_key, 'reward' ) ) {
                    $score += 4;
                }
                if ( false !== strpos( $meta_key, 'loyalty' ) ) {
                    $score += 4;
                }
                if ( false !== strpos( $meta_key, 'referral' ) ) {
                    $score += 3;
                }
                if ( false !== strpos( $meta_key, 'used' ) || false !== strpos( $meta_key, 'spent' ) ) {
                    $score -= 8;
                }

                $candidates[] = array(
                    'score' => $score,
                    'value' => $this->normalize_points_balance( $value ),
                );
            }

            if ( empty( $candidates ) ) {
                return 0;
            }

            usort(
                $candidates,
                static function ( $left, $right ) {
                    if ( $left['score'] === $right['score'] ) {
                        return 0;
                    }

                    return ( $left['score'] > $right['score'] ) ? -1 : 1;
                }
            );

            return isset( $candidates[0]['value'] ) ? $candidates[0]['value'] : 0;
        }

        private function normalize_points_balance( $value ) {
            if ( is_string( $value ) ) {
                $value = str_replace( array( ' ', ',' ), array( '', '.' ), $value );
            }

            return is_numeric( $value ) ? (float) $value : 0;
        }

        private function format_points_balance( $value ) {
            $value    = $this->normalize_points_balance( $value );
            $decimals = ( abs( $value - floor( $value ) ) > 0.00001 ) ? 2 : 0;

            return number_format_i18n( $value, $decimals );
        }

        private function get_last_order_date( $orders ) {
            if ( empty( $orders ) || ! isset( $orders[0] ) || ! $orders[0] instanceof WC_Order ) {
                return '—';
            }
            $date = $orders[0]->get_date_created();
            return $date ? $date->date_i18n( get_option( 'date_format' ) ) : '—';
        }

        private function can_cancel_order( WC_Order $order ) {
            if ( $order->has_status( array( 'cancelled', 'completed', 'refunded' ) ) ) {
                return false;
            }

            if ( $this->is_yandex_delivery_order( $order ) ) {
                $state_status = $this->get_yandex_delivery_state_status( $order );

                if ( $state_status && in_array( $state_status, $this->get_yandex_customer_cancel_blocked_statuses(), true ) ) {
                    return false;
                }

                if ( $state_status ) {
                    return true;
                }
            }

            return in_array( $order->get_status(), array( 'pending', 'failed', 'on-hold' ), true );
        }

        private function get_yandex_delivery_state_status( WC_Order $order ) {
            return strtoupper( trim( (string) $order->get_meta( '_yandex_delivery_state_status', true ) ) );
        }

        private function is_yandex_delivery_order( WC_Order $order ) {
            $meta_keys = array(
                '_yandex_delivery_request_id',
                '_yandex_delivery_state_status',
                '_yandex_delivery_sharing_url',
                '_yandex_delivery_destination_station_id',
            );

            foreach ( $meta_keys as $meta_key ) {
                if ( '' !== (string) $order->get_meta( $meta_key, true ) ) {
                    return true;
                }
            }

            foreach ( $order->get_shipping_methods() as $shipping_method ) {
                $method_id    = strtolower( (string) $shipping_method->get_method_id() );
                $method_title = strtolower( (string) $shipping_method->get_name() );

                if ( false !== strpos( $method_id, 'yandex' ) || false !== strpos( $method_id, 'yad' ) || false !== strpos( $method_title, 'яндекс' ) || false !== strpos( $method_title, 'yandex' ) ) {
                    return true;
                }
            }

            return false;
        }

        private function get_yandex_customer_cancel_blocked_statuses() {
            $statuses = array(
                self::YANDEX_ACCEPTED_STATUS,
                'SORTING_CENTER_PREPARED',
                'SORTING_CENTER_TRANSMITTED',
                'DELIVERY_AT_START',
                'DELIVERY_AT_START_SORT',
                'DELIVERY_TRANSPORTATION',
                'DELIVERY_TRANSPORTATION_RECIPIENT',
                'DELIVERY_TRANSMITTED_TO_RECIPIENT',
                'DELIVERY_ATTEMPT_FAILED',
                'DELIVERY_ARRIVED_PICKUP_POINT',
                'DELIVERY_DELIVERED',
                'CANCELLED',
                'CANCELLED_BY_RECIPIENT',
                'CANCELLED_USER',
                'CANCELLED_IN_PLATFORM',
                'SORTING_CENTER_CANCELLED',
                'SORTING_CENTER_RETURN_PREPARING',
                'SORTING_CENTER_RETURN_PREPARING_SENDER',
                'SORTING_CENTER_RETURN_ARRIVED',
                'SORTING_CENTER_RETURN_RETURNED',
                'RETURN_PREPARING',
                'RETURN_TRANSPORTATION_STARTED',
                'RETURN_TRANSPORTATION',
                'RETURN_ARRIVED_DELIVERY',
                'RETURN_TRANSMITTED_FULFILMENT',
                'RETURN_READY_FOR_PICKUP',
                'RETURN_RETURNED',
            );

            return array_values( array_unique( array_map( 'strtoupper', apply_filters( 'ama_account_suite_yandex_cancel_blocked_statuses', $statuses ) ) ) );
        }

        private function cancel_yandex_delivery_request_if_possible( WC_Order $order ) {
            if ( ! $this->is_yandex_delivery_order( $order ) ) {
                return true;
            }

            $request_id = (string) $order->get_meta( '_yandex_delivery_request_id', true );

            if ( '' === $request_id ) {
                return true;
            }

            if ( ! class_exists( 'WC_Yandex_Delivery_Order' ) ) {
                return new WP_Error( 'ama_yandex_delivery_missing_order_class', __( 'Не удалось отменить заказ в Яндекс.Доставке: основной плагин доставки сейчас недоступен.', 'amaressence-account-suite' ) );
            }

            try {
                $yandex_order = new WC_Yandex_Delivery_Order( $order->get_id() );

                if ( ! method_exists( $yandex_order, 'cancel_action' ) ) {
                    return new WP_Error( 'ama_yandex_delivery_cancel_method_missing', __( 'Не удалось отменить заказ в Яндекс.Доставке: метод отмены недоступен.', 'amaressence-account-suite' ) );
                }

                if ( ! $yandex_order->cancel_action() ) {
                    return new WP_Error( 'ama_yandex_delivery_cancel_failed', __( 'Не удалось отменить заказ в Яндекс.Доставке. Попробуйте позже или свяжитесь с магазином.', 'amaressence-account-suite' ) );
                }
            } catch ( Throwable $e ) {
                return new WP_Error( 'ama_yandex_delivery_cancel_exception', sprintf( __( 'Не удалось отменить заказ в Яндекс.Доставке: %s', 'amaressence-account-suite' ), $e->getMessage() ) );
            }

            return true;
        }

        private function send_customer_cancelled_admin_email( WC_Order $order ) {
            $settings = $this->get_admin_cancel_email_settings();

            if ( 'yes' !== ( $settings['enabled'] ?? 'yes' ) ) {
                $order->add_order_note( __( 'Письмо администратору об отмене не отправлено: письмо отключено в настройках Amaressence Account Suite.', 'amaressence-account-suite' ) );
                $order->save();
                return false;
            }

            $recipient = trim( (string) ( $settings['recipient'] ?? '' ) );
            if ( '' === $recipient ) {
                $recipient = $this->get_wc_admin_email_recipients( $order );
            }

            if ( ! $recipient ) {
                $order->add_order_note( __( 'Не удалось отправить письмо администратору об отмене: не найден email получателя.', 'amaressence-account-suite' ) );
                $order->save();
                return false;
            }

            $subject_template = (string) ( $settings['subject'] ?? '' );
            if ( '' === trim( $subject_template ) ) {
                $subject_template = 'Покупатель отменил заказ №{order_number}';
            }

            $subject = wp_strip_all_tags( $this->replace_admin_cancel_email_placeholders( $subject_template, $order ) );
            $html    = $this->build_customer_cancelled_admin_email_html( $order );
            $headers = array( 'Content-Type: text/html; charset=UTF-8' );

            if ( function_exists( 'WC' ) && WC() && WC()->mailer() ) {
                $sent = WC()->mailer()->send( $recipient, $subject, $html, $headers, array() );
            } else {
                $sent = wp_mail( $recipient, $subject, $html, $headers );
            }

            if ( $sent ) {
                $order->add_order_note( sprintf( 'Отправлено письмо администратору об отмене заказа покупателем. Получатель: %s.', $recipient ) );
            } else {
                $order->add_order_note( sprintf( 'Не удалось отправить письмо администратору об отмене заказа покупателем. Получатель: %s.', $recipient ) );
            }

            $order->save();

            return (bool) $sent;
        }

        private function get_wc_admin_email_recipients( WC_Order $order ) {
            $recipient = '';

            if ( function_exists( 'WC' ) && WC() && WC()->mailer() ) {
                $emails = WC()->mailer()->get_emails();

                if ( isset( $emails['WC_Email_New_Order'] ) && method_exists( $emails['WC_Email_New_Order'], 'get_recipient' ) ) {
                    $recipient = (string) $emails['WC_Email_New_Order']->get_recipient();
                }
            }

            if ( ! $recipient ) {
                $new_order_settings = get_option( 'woocommerce_new_order_settings', array() );
                if ( is_array( $new_order_settings ) && ! empty( $new_order_settings['recipient'] ) ) {
                    $recipient = (string) $new_order_settings['recipient'];
                }
            }

            if ( ! $recipient ) {
                $recipient = (string) get_option( 'admin_email' );
            }

            return apply_filters( 'ama_account_suite_customer_cancel_admin_email_recipient', $recipient, $order );
        }

        public function add_admin_settings_page() {
            add_submenu_page(
                'woocommerce',
                'Кабинет Amaressence: письма',
                'Кабинет Amaressence: письма',
                'manage_woocommerce',
                'amaressence-account-suite-emails',
                array( $this, 'render_admin_email_settings_page' )
            );
        }

        public function add_plugin_action_links( $links ) {
            $settings_url = admin_url( 'admin.php?page=amaressence-account-suite-emails' );
            array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">Письма</a>' );
            return $links;
        }

        public function render_admin_email_settings_page() {
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( esc_html__( 'Недостаточно прав.', 'amaressence-account-suite' ) );
            }

            $message = '';
            $settings = $this->get_admin_cancel_email_settings();

            if ( isset( $_POST[ self::ADMIN_EMAIL_NONCE_NAME ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::ADMIN_EMAIL_NONCE_NAME ] ) ), self::ADMIN_EMAIL_NONCE_ACTION ) ) {
                $settings['enabled'] = isset( $_POST['admin_cancel_email_enabled'] ) ? 'yes' : 'no';
                $settings['recipient'] = isset( $_POST['admin_cancel_email_recipient'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_cancel_email_recipient'] ) ) : '';
                $settings['subject'] = isset( $_POST['admin_cancel_email_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_cancel_email_subject'] ) ) : '';
                $settings['html'] = isset( $_POST['admin_cancel_email_html'] ) ? $this->sanitize_email_template_html( wp_unslash( $_POST['admin_cancel_email_html'] ) ) : '';
                $settings['css'] = isset( $_POST['admin_cancel_email_css'] ) ? $this->sanitize_email_template_css( wp_unslash( $_POST['admin_cancel_email_css'] ) ) : '';

                $options = get_option( self::OPTION_KEY, array() );
                if ( ! is_array( $options ) ) {
                    $options = array();
                }
                $options['admin_cancel_email'] = $settings;
                update_option( self::OPTION_KEY, $options, false );
                $message = 'Настройки письма сохранены.';
            }

            $settings = $this->get_admin_cancel_email_settings();
            $preview_order = $this->create_preview_order_for_admin_email();
            $preview_html = $this->build_customer_cancelled_admin_email_html( $preview_order );
            ?>
            <div class="wrap ama-account-email-settings">
                <h1>Кабинет Amaressence: письмо администратору об отмене</h1>
                <?php if ( $message ) : ?>
                    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
                <?php endif; ?>

                <p>Здесь редактируется кастомное HTML-письмо, которое этот плагин отправляет администратору, когда покупатель отменяет заказ из личного кабинета.</p>
                <form method="post">
                    <?php wp_nonce_field( self::ADMIN_EMAIL_NONCE_ACTION, self::ADMIN_EMAIL_NONCE_NAME ); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Включить письмо</th>
                            <td><label><input type="checkbox" name="admin_cancel_email_enabled" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?>> Включено</label></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="admin_cancel_email_recipient">Получатель</label></th>
                            <td>
                                <input type="text" class="regular-text" id="admin_cancel_email_recipient" name="admin_cancel_email_recipient" value="<?php echo esc_attr( $settings['recipient'] ); ?>">
                                <p class="description">Можно оставить пустым — тогда будут использованы получатели письма WooCommerce «Новый заказ», а если они не найдены — admin_email сайта. Несколько адресов можно указать через запятую.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="admin_cancel_email_subject">Тема письма</label></th>
                            <td><input type="text" class="large-text" id="admin_cancel_email_subject" name="admin_cancel_email_subject" value="<?php echo esc_attr( $settings['subject'] ); ?>"></td>
                        </tr>
                    </table>

                    <h2>HTML и визуальный редактор письма</h2>
                    <p>Доступные переменные: <code>{site_name}</code> <code>{home_url}</code> <code>{logo_url}</code> <code>{order_number}</code> <code>{order_admin_url}</code> <code>{customer_name}</code> <code>{customer_email}</code> <code>{customer_phone}</code> <code>{order_date}</code> <code>{order_total}</code> <code>{wc_status}</code> <code>{yandex_status}</code> <code>{order_details_table}</code> <code>{email_css}</code>.</p>
                    <?php
                    wp_editor(
                        $settings['html'],
                        'ama_admin_cancel_email_html',
                        array(
                            'textarea_name' => 'admin_cancel_email_html',
                            'textarea_rows' => 24,
                            'media_buttons' => true,
                            'teeny'         => false,
                        )
                    );
                    ?>

                    <h2>CSS шаблона письма</h2>
                    <textarea class="ama-account-email-css" name="admin_cancel_email_css"><?php echo esc_textarea( $settings['css'] ); ?></textarea>

                    <h2>Широкий предпросмотр сохранённого письма</h2>
                    <iframe class="ama-account-email-preview" srcdoc="<?php echo esc_attr( $preview_html ); ?>"></iframe>

                    <p class="submit"><button type="submit" class="button button-primary">Сохранить шаблон письма</button></p>
                </form>
            </div>
            <style>
                .ama-account-email-settings .ama-account-email-css{width:100%;min-height:220px;font-family:Consolas,Monaco,monospace;}
                .ama-account-email-settings .ama-account-email-preview{width:100%;height:760px;background:#f7f3ea;border:1px solid #ccd0d4;border-radius:4px;}
            </style>
            <?php
        }

        private function get_admin_cancel_email_settings() {
            $defaults = array(
                'enabled'   => 'yes',
                'recipient' => '',
                'subject'   => 'Покупатель отменил заказ №{order_number}',
                'html'      => $this->get_default_admin_cancel_email_template_html(),
                'css'       => $this->get_default_admin_cancel_email_template_css(),
            );

            $options = get_option( self::OPTION_KEY, array() );
            if ( ! is_array( $options ) ) {
                $options = array();
            }

            $settings = isset( $options['admin_cancel_email'] ) && is_array( $options['admin_cancel_email'] ) ? $options['admin_cancel_email'] : array();
            return array_merge( $defaults, $settings );
        }

        private function create_preview_order_for_admin_email() {
            $order = new WC_Order();
            $order->set_billing_first_name( 'Анна' );
            $order->set_billing_last_name( 'Иванова' );
            $order->set_billing_email( 'client@example.com' );
            $order->set_billing_phone( '+7 900 000-00-00' );
            $order->set_date_created( new WC_DateTime( current_time( 'mysql' ) ) );
            $order->set_total( 12500 );
            $order->update_meta_data( '_yandex_delivery_state_status', 'CANCELLED' );

            $item = new WC_Order_Item_Product();
            $item->set_name( 'Тестовый товар' );
            $item->set_quantity( 1 );
            $item->set_subtotal( 12500 );
            $item->set_total( 12500 );
            $order->add_item( $item );

            return $order;
        }

        private function replace_admin_cancel_email_placeholders( $template, WC_Order $order ) {
            $customer_name = trim( $order->get_formatted_billing_full_name() );
            $customer_name = $customer_name ? $customer_name : 'Покупатель';
            $yandex_status = $this->get_yandex_delivery_state_status( $order );
            $settings = $this->get_admin_cancel_email_settings();

            $placeholders = array(
                '{email_css}'          => (string) $settings['css'],
                '{site_name}'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
                '{home_url}'           => home_url( '/' ),
                '{logo_url}'           => self::EMAIL_LOGO_URL,
                '{order_number}'       => $order->get_order_number(),
                '{order_id}'           => (string) $order->get_id(),
                '{order_admin_url}'    => $order->get_id() ? admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) : admin_url( 'edit.php?post_type=shop_order' ),
                '{customer_name}'      => $customer_name,
                '{customer_email}'     => $order->get_billing_email(),
                '{customer_phone}'     => $order->get_billing_phone() ? $order->get_billing_phone() : '—',
                '{order_date}'         => $order->get_date_created() ? wc_format_datetime( $order->get_date_created(), wc_date_format() ) : '',
                '{order_total}'        => wp_strip_all_tags( $order->get_formatted_order_total() ),
                '{wc_status}'          => wc_get_order_status_name( $order->get_status() ),
                '{yandex_status}'      => $yandex_status ? $this->get_yandex_delivery_status_label( $yandex_status ) : 'Не указан',
                '{order_details_table}' => $this->get_email_order_details_table( $order ),
            );

            return strtr( (string) $template, $placeholders );
        }

        private function get_default_admin_cancel_email_template_html() {
            return '<!doctype html>
<html lang="ru">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<style>{email_css}</style>
</head>
<body style="margin:0;padding:0;background-color:#e8e3d8;">
<div class="ama-email">
    <div class="ama-preheader">Покупатель отменил заказ №{order_number}</div>
    <table class="ama-outer" role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center">
            <table class="ama-card" role="presentation" width="600" cellpadding="0" cellspacing="0">
                <tr><td class="ama-header" align="center"><a href="{home_url}" target="_blank" class="ama-logo-link"><img src="{logo_url}" alt="{site_name}" class="ama-logo"></a></td></tr>
                <tr><td class="ama-status" align="center"><div class="ama-status-icon">!</div><h1 class="ama-title">Покупатель отменил заказ</h1><p class="ama-subtitle">Заказ № {order_number}</p></td></tr>
                <tr><td class="ama-content"><p class="ama-text">Здравствуйте!</p><p class="ama-text">Покупатель отменил заказ из личного кабинета. Проверьте заказ в админке и дальнейшие действия по доставке или возврату оплаты, если они требуются.</p><p style="margin:18px 0 0;"><a href="{order_admin_url}" target="_blank" class="ama-button">Открыть заказ</a></p></td></tr>
                <tr><td class="ama-divider-wrap"><div class="ama-divider"></div></td></tr>
                <tr><td class="ama-content"><p class="ama-section-label">Информация о заказе</p><table class="ama-info-table" role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr><td class="ama-info-label">Покупатель</td><td class="ama-info-value">{customer_name}</td></tr>
                    <tr><td class="ama-info-label">Email</td><td class="ama-info-value">{customer_email}</td></tr>
                    <tr><td class="ama-info-label">Телефон</td><td class="ama-info-value">{customer_phone}</td></tr>
                    <tr><td class="ama-info-label">Номер заказа</td><td class="ama-info-value ama-strong">{order_number}</td></tr>
                    <tr><td class="ama-info-label">Дата заказа</td><td class="ama-info-value">{order_date}</td></tr>
                    <tr><td class="ama-info-label">Сумма заказа</td><td class="ama-info-value ama-strong">{order_total}</td></tr>
                    <tr><td class="ama-info-label">Статус WooCommerce</td><td class="ama-info-value">{wc_status}</td></tr>
                    <tr><td class="ama-info-label">Статус Яндекс.Доставки</td><td class="ama-info-value">{yandex_status}</td></tr>
                    <tr><td class="ama-info-label">Источник отмены</td><td class="ama-info-value">Личный кабинет покупателя</td></tr>
                </table></td></tr>
                <tr><td class="ama-content ama-content-top"><p class="ama-section-label">Состав заказа</p><div class="ama-plugin-table">{order_details_table}</div></td></tr>
                <tr><td class="ama-content ama-content-bottom"><table class="ama-support-card" role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td class="ama-support-cell"><p class="ama-support-title">Важно</p><p class="ama-support-text">Кнопка отмены скрывается для покупателя после статуса Яндекс.Доставки «Поступил на приём».</p></td></tr></table></td></tr>
                <tr><td class="ama-footer" align="center"><img src="{logo_url}" alt="{site_name}" class="ama-footer-logo"><p class="ama-footer-text">Системное уведомление сайта {site_name}.</p><p class="ama-footer-copy">© {site_name}. Все права защищены.</p></td></tr>
            </table>
        </td></tr>
    </table>
</div>
</body>
</html>';
        }

        private function get_default_admin_cancel_email_template_css() {
            return '.ama-email,.ama-email *{box-sizing:border-box}.ama-preheader{display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#e8e3d8}.ama-outer{width:100%;background-color:#e8e3d8;padding:40px 16px;border-collapse:collapse;font-family:Arial,Helvetica,sans-serif}.ama-card{width:100%;max-width:600px;background-color:#f7f3ea;border-radius:4px;border-collapse:separate;overflow:hidden}.ama-header{background-color:#662132;padding:24px 40px;text-align:center}.ama-logo-link{display:inline-block;text-decoration:none}.ama-logo{display:block;width:160px;max-width:160px;height:auto;border:0;outline:none}.ama-status{background-color:#f7f3ea;padding:40px 40px 4px;text-align:center}.ama-status-icon{width:54px;height:54px;margin:0 auto 18px;border-radius:50%;background-color:#d0e3ff;color:#662132;font-family:Arial,Helvetica,sans-serif;font-size:26px;line-height:54px;font-weight:700;text-align:center}.ama-title{margin:0 0 10px;font-family:Georgia,"Times New Roman",serif;font-size:26px;font-weight:400;color:#662132;letter-spacing:.02em;line-height:1.2}.ama-subtitle{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#9a8880;line-height:1.6}.ama-content{padding:28px 40px 0}.ama-content-top{padding-top:22px}.ama-content-bottom{padding-bottom:36px}.ama-text{margin:0 0 10px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#3d3028;line-height:1.75}.ama-divider-wrap{padding:24px 40px 0}.ama-divider{height:1px;background-color:rgba(102,33,50,.12);line-height:1px;font-size:1px}.ama-section-label{margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:.14em;color:#662132;text-transform:uppercase;line-height:1}.ama-info-table{width:100%;background-color:#fff;border-radius:4px;border:1px solid rgba(102,33,50,.1);border-collapse:separate;overflow:hidden}.ama-info-label,.ama-info-value{padding:12px 20px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.45;border-bottom:1px solid rgba(102,33,50,.07);vertical-align:top}.ama-info-label{width:44%;color:#9a8880}.ama-info-value{color:#3d3028}.ama-strong{font-weight:700}.ama-plugin-table{background-color:#fff;border-radius:4px;border:1px solid rgba(102,33,50,.1);overflow:hidden;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#3d3028;line-height:1.5}.ama-plugin-table table{width:100%;border-collapse:collapse}.ama-plugin-table th,.ama-plugin-table td{padding:12px 16px;border-bottom:1px solid rgba(102,33,50,.08);font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#3d3028;line-height:1.5}.ama-plugin-table th{color:#662132;font-weight:700;text-align:left}.ama-button{display:inline-block;background-color:#662132;color:#f7f3ea!important;font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:700;letter-spacing:.1em;text-decoration:none;text-transform:uppercase;padding:13px 28px;border-radius:2px;text-align:center}.ama-support-card{width:100%;background-color:#fff;border-radius:4px;border:1px solid rgba(102,33,50,.1);border-collapse:separate}.ama-support-cell{padding:18px 22px}.ama-support-title{margin:0 0 4px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;color:#3d3028}.ama-support-text{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#9a8880;line-height:1.65}.ama-footer{background-color:#662132;padding:28px 40px;text-align:center;border-radius:0 0 4px 4px}.ama-footer-logo{display:block;width:120px;max-width:120px;height:auto;margin:0 auto 16px;border:0;outline:none;opacity:.9}.ama-footer-text{margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(247,243,234,.5);line-height:1.65}.ama-footer-copy{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:10px;color:rgba(247,243,234,.35);line-height:1.5}@media only screen and (max-width:620px){.ama-outer{padding:0!important}.ama-card{border-radius:0!important}.ama-header,.ama-status,.ama-content,.ama-divider-wrap,.ama-footer{padding-left:20px!important;padding-right:20px!important}.ama-button{display:block!important;width:100%!important}.ama-info-label,.ama-info-value{display:block!important;width:100%!important}.ama-info-label{padding-bottom:4px!important;border-bottom:0!important}.ama-info-value{padding-top:0!important}}';
        }

        private function build_customer_cancelled_admin_email_html( WC_Order $order ) {
            $settings = $this->get_admin_cancel_email_settings();
            $template = trim( (string) $settings['html'] ) ? (string) $settings['html'] : $this->get_default_admin_cancel_email_template_html();
            return $this->replace_admin_cancel_email_placeholders( $template, $order );
        }

        private function sanitize_email_template_html( $html ) {
            return is_string( $html ) ? $html : '';
        }

        private function sanitize_email_template_css( $css ) {
            $css = is_string( $css ) ? $css : '';
            return str_replace( array( '<script', '</script' ), array( '&lt;script', '&lt;/script' ), $css );
        }

        private function get_email_order_details_table( WC_Order $order ) {
            $rows = '';

            foreach ( $order->get_items() as $item ) {
                $product_name = $item->get_name();
                $qty          = $item->get_quantity();
                $total        = $order->get_formatted_line_subtotal( $item );

                $rows .= '<tr><td>' . esc_html( $product_name ) . '</td><td align="center">' . esc_html( (string) $qty ) . '</td><td align="right">' . wp_kses_post( $total ) . '</td></tr>';
            }

            if ( '' === $rows ) {
                return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tbody><tr><td>Состав заказа не найден.</td></tr></tbody></table>';
            }

            return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><thead><tr><th>Товар</th><th>Кол-во</th><th>Сумма</th></tr></thead><tbody>' . $rows . '</tbody></table>';
        }

        private function get_yandex_delivery_status_label( $status ) {
            $status = strtoupper( trim( (string) $status ) );

            if ( function_exists( 'wc_yandex_delivery_get_order_status_name' ) ) {
                return wc_yandex_delivery_get_order_status_name( $status );
            }

            $labels = array(
                'NEW'                                => 'Новый',
                'EXPORTING_PROCESS'                  => 'Экспортируется',
                'MARK_AS_CANCEL'                     => 'Помечен к отмене',
                'EXPORT_INVALID'                     => 'Ошибка экспорта',
                'DRAFT'                              => 'Черновик',
                'VALIDATING'                         => 'Проверяется',
                'VALIDATING_ERROR'                   => 'Ошибка проверки',
                'CREATED'                            => 'Создан',
                'DELIVERY_PROCESSING_STARTED'        => 'Обработка началась',
                'DELIVERY_TRACK_RECIEVED'            => 'Трек получен',
                'SORTING_CENTER_PROCESSING_STARTED'  => 'Обработка сортировочным центром началась',
                'SORTING_CENTER_TRACK_RECEIVED'      => 'Трек сортировочного центра получен',
                'SORTING_CENTER_TRACK_LOADED'        => 'Трек сортировочного центра загружен',
                'DELIVERY_LOADED'                    => 'Загружен в доставку',
                'SORTING_CENTER_LOADED'              => 'Загружен в сортировочный центр',
                'SORTING_CENTER_AT_START'            => 'Поступил на приём',
                'SORTING_CENTER_PREPARED'            => 'Подготовлен сортировочным центром',
                'SORTING_CENTER_TRANSMITTED'         => 'Передан сортировочным центром',
                'DELIVERY_AT_START'                  => 'Готовится к отправке',
                'DELIVERY_AT_START_SORT'             => 'Готовится к отправке',
                'DELIVERY_TRANSPORTATION'            => 'Доставляется',
                'DELIVERY_TRANSPORTATION_RECIPIENT'  => 'Доставляется получателю',
                'DELIVERY_TRANSMITTED_TO_RECIPIENT'  => 'Вручен клиенту',
                'DELIVERY_ATTEMPT_FAILED'            => 'Неудачная попытка доставки',
                'DELIVERY_ARRIVED_PICKUP_POINT'      => 'Ждёт в пункте выдачи',
                'DELIVERY_DELIVERED'                 => 'Вручен клиенту',
                'CANCELLED'                          => 'Отменён',
                'CANCELLED_BY_RECIPIENT'             => 'Отменён получателем',
                'CANCELLED_USER'                     => 'Отменён пользователем',
                'CANCELLED_IN_PLATFORM'              => 'Отменён на платформе',
                'SORTING_CENTER_CANCELLED'           => 'Отменён сортировочным центром',
                'RETURN_PREPARING'                   => 'Возврат готовится',
                'RETURN_TRANSPORTATION_STARTED'      => 'Возврат начат',
                'RETURN_TRANSPORTATION'              => 'Возвращается',
                'RETURN_ARRIVED_DELIVERY'            => 'Возврат прибыл в доставку',
                'RETURN_TRANSMITTED_FULFILMENT'      => 'Возврат передан на склад',
                'RETURN_READY_FOR_PICKUP'            => 'Возврат готов к получению',
                'RETURN_RETURNED'                    => 'Заказ возвращён',
            );

            return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
        }

        private function get_orders_table_markup( $orders, $show_actions = false ) {
            if ( empty( $orders ) ) {
                return '<div class="ama-empty-box"><p>' . esc_html__( 'Пока нет заказов.', 'amaressence-account-suite' ) . '</p></div>';
            }

            ob_start();
            ?>
            <div class="ama-orders-table">
                <div class="ama-orders-head <?php echo $show_actions ? 'has-actions' : ''; ?>">
                    <span><?php echo esc_html__( 'Заказ', 'amaressence-account-suite' ); ?></span>
                    <span><?php echo esc_html__( 'Дата', 'amaressence-account-suite' ); ?></span>
                    <span><?php echo esc_html__( 'Статус', 'amaressence-account-suite' ); ?></span>
                    <span><?php echo esc_html__( 'Сумма', 'amaressence-account-suite' ); ?></span>
                    <?php if ( $show_actions ) : ?>
                        <span><?php echo esc_html__( 'Действия', 'amaressence-account-suite' ); ?></span>
                    <?php endif; ?>
                </div>
                <?php foreach ( $orders as $order ) : ?>
                    <?php if ( ! $order instanceof WC_Order ) { continue; } ?>
                    <div class="ama-order-row <?php echo $show_actions ? 'has-actions' : ''; ?>" data-order-row="<?php echo esc_attr( $order->get_id() ); ?>">
                        <span data-label="<?php echo esc_attr__( 'Заказ', 'amaressence-account-suite' ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></span>
                        <span data-label="<?php echo esc_attr__( 'Дата', 'amaressence-account-suite' ); ?>"><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '—' ); ?></span>
                        <span data-label="<?php echo esc_attr__( 'Статус', 'amaressence-account-suite' ); ?>"><mark class="ama-status ama-status--<?php echo esc_attr( sanitize_html_class( $order->get_status() ) ); ?>" data-order-status><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></mark></span>
                        <span data-label="<?php echo esc_attr__( 'Сумма', 'amaressence-account-suite' ); ?>"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                        <?php if ( $show_actions ) : ?>
                            <span class="ama-order-actions-cell" data-label="<?php echo esc_attr__( 'Действия', 'amaressence-account-suite' ); ?>">
                                <button class="ama-btn ama-btn--small ama-btn--ghost" type="button" data-order-details="<?php echo esc_attr( $order->get_id() ); ?>"><?php echo esc_html__( 'Подробнее', 'amaressence-account-suite' ); ?></button>
                                <?php if ( $this->can_cancel_order( $order ) ) : ?>
                                    <button class="ama-btn ama-btn--small ama-btn--danger" type="button" data-order-cancel="<?php echo esc_attr( $order->get_id() ); ?>"><?php echo esc_html__( 'Отменить заказ', 'amaressence-account-suite' ); ?></button>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        private function get_downloads( $user_id ) {
            if ( function_exists( 'wc_get_customer_available_downloads' ) ) {
                $downloads = wc_get_customer_available_downloads( $user_id );
                return is_array( $downloads ) ? $downloads : array();
            }
            return array();
        }

        private function get_downloads_markup( $downloads ) {
            if ( empty( $downloads ) ) {
                return '<div class="ama-empty-box"><p>' . esc_html__( 'Доступных загрузок пока нет.', 'amaressence-account-suite' ) . '</p></div>';
            }

            ob_start();
            echo '<div class="ama-downloads-grid">';
            foreach ( $downloads as $download ) {
                $name = isset( $download['download_name'] ) && $download['download_name'] ? $download['download_name'] : ( $download['product_name'] ?? __( 'Файл', 'amaressence-account-suite' ) );
                $product_name = $download['product_name'] ?? '';
                $link = $download['download_url'] ?? '';
                echo '<div class="ama-download-card">';
                echo '<strong>' . esc_html( $name ) . '</strong>';
                if ( $product_name ) {
                    echo '<span>' . esc_html( $product_name ) . '</span>';
                }
                if ( $link ) {
                    echo '<a class="ama-btn ama-btn--ghost ama-btn--small" href="' . esc_url( $link ) . '">' . esc_html__( 'Скачать', 'amaressence-account-suite' ) . '</a>';
                }
                echo '</div>';
            }
            echo '</div>';
            return (string) ob_get_clean();
        }

        private function render_address_fields( $prefix, $user_id ) {
            $is_billing = 'billing' === $prefix;
            $country_label = $is_billing ? __( 'Страна', 'amaressence-account-suite' ) : __( 'Страна', 'amaressence-account-suite' );

            ob_start();
            ?>
            <div class="ama-grid ama-grid--2">
                <label class="ama-field"><span><?php echo esc_html__( 'Имя', 'amaressence-account-suite' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>_first_name" data-address-group="<?php echo esc_attr( $prefix ); ?>" value="<?php echo esc_attr( get_user_meta( $user_id, $prefix . '_first_name', true ) ); ?>"></label>
                <label class="ama-field"><span><?php echo esc_html__( 'Фамилия', 'amaressence-account-suite' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>_last_name" data-address-group="<?php echo esc_attr( $prefix ); ?>" value="<?php echo esc_attr( get_user_meta( $user_id, $prefix . '_last_name', true ) ); ?>"></label>
            </div>
            <div class="ama-grid ama-grid--2">
                <label class="ama-field"><span><?php echo esc_html__( 'Компания', 'amaressence-account-suite' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>_company" data-address-group="<?php echo esc_attr( $prefix ); ?>" value="<?php echo esc_attr( get_user_meta( $user_id, $prefix . '_company', true ) ); ?>"></label>
                <?php if ( $is_billing ) : ?>
                    <label class="ama-field"><span><?php echo esc_html__( 'Телефон', 'amaressence-account-suite' ); ?></span><input type="tel" name="billing_phone" data-address-group="billing" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_phone', true ) ); ?>"></label>
                <?php else : ?>
                    <label class="ama-field"><span><?php echo esc_html( $country_label ); ?></span><input type="text" name="shipping_country" data-address-group="shipping" value="<?php echo esc_attr( get_user_meta( $user_id, 'shipping_country', true ) ); ?>"></label>
                <?php endif; ?>
            </div>
            <?php if ( $is_billing ) : ?>
                <div class="ama-grid ama-grid--3">
                    <label class="ama-field"><span><?php echo esc_html__( 'Страна', 'amaressence-account-suite' ); ?></span><input type="text" name="billing_country" data-address-group="billing" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_country', true ) ); ?>"></label>
                    <label class="ama-field"><span><?php echo esc_html__( 'Регион', 'amaressence-account-suite' ); ?></span><input type="text" name="billing_state" data-address-group="billing" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_state', true ) ); ?>"></label>
                    <label class="ama-field"><span><?php echo esc_html__( 'Индекс', 'amaressence-account-suite' ); ?></span><input type="text" name="billing_postcode" data-address-group="billing" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_postcode', true ) ); ?>"></label>
                </div>
            <?php else : ?>
                <div class="ama-grid ama-grid--2">
                    <label class="ama-field"><span><?php echo esc_html__( 'Регион', 'amaressence-account-suite' ); ?></span><input type="text" name="shipping_state" data-address-group="shipping" value="<?php echo esc_attr( get_user_meta( $user_id, 'shipping_state', true ) ); ?>"></label>
                    <label class="ama-field"><span><?php echo esc_html__( 'Индекс', 'amaressence-account-suite' ); ?></span><input type="text" name="shipping_postcode" data-address-group="shipping" value="<?php echo esc_attr( get_user_meta( $user_id, 'shipping_postcode', true ) ); ?>"></label>
                </div>
            <?php endif; ?>
            <div class="ama-grid ama-grid--2">
                <label class="ama-field"><span><?php echo esc_html__( 'Город', 'amaressence-account-suite' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>_city" data-address-group="<?php echo esc_attr( $prefix ); ?>" value="<?php echo esc_attr( get_user_meta( $user_id, $prefix . '_city', true ) ); ?>"></label>
                <label class="ama-field"><span><?php echo esc_html__( 'Улица, дом', 'amaressence-account-suite' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>_address_1" data-address-group="<?php echo esc_attr( $prefix ); ?>" value="<?php echo esc_attr( get_user_meta( $user_id, $prefix . '_address_1', true ) ); ?>"></label>
            </div>
            <label class="ama-field"><span><?php echo esc_html__( 'Квартира, офис, подъезд', 'amaressence-account-suite' ); ?></span><input type="text" name="<?php echo esc_attr( $prefix ); ?>_address_2" data-address-group="<?php echo esc_attr( $prefix ); ?>" value="<?php echo esc_attr( get_user_meta( $user_id, $prefix . '_address_2', true ) ); ?>"></label>
            <?php
            return (string) ob_get_clean();
        }

        private function format_address_preview( $type, $user_id ) {
            $lines = array();
            $keys  = array(
                $type . '_first_name',
                $type . '_last_name',
                $type . '_company',
                $type . '_address_1',
                $type . '_address_2',
                $type . '_city',
                $type . '_state',
                $type . '_postcode',
                $type . '_country',
            );

            $name_parts = array_filter(
                array(
                    get_user_meta( $user_id, $type . '_first_name', true ),
                    get_user_meta( $user_id, $type . '_last_name', true ),
                )
            );
            if ( ! empty( $name_parts ) ) {
                $lines[] = implode( ' ', $name_parts );
            }

            $company = get_user_meta( $user_id, $type . '_company', true );
            if ( $company ) {
                $lines[] = $company;
            }

            $address_1 = get_user_meta( $user_id, $type . '_address_1', true );
            if ( $address_1 ) {
                $lines[] = $address_1;
            }

            $address_2 = get_user_meta( $user_id, $type . '_address_2', true );
            if ( $address_2 ) {
                $lines[] = $address_2;
            }

            $city_line = implode( ', ', array_filter( array(
                get_user_meta( $user_id, $type . '_city', true ),
                get_user_meta( $user_id, $type . '_state', true ),
                get_user_meta( $user_id, $type . '_postcode', true ),
            ) ) );
            if ( $city_line ) {
                $lines[] = $city_line;
            }

            $country = get_user_meta( $user_id, $type . '_country', true );
            if ( $country ) {
                $lines[] = $country;
            }

            if ( 'billing' === $type ) {
                $phone = get_user_meta( $user_id, 'billing_phone', true );
                if ( $phone ) {
                    $lines[] = $phone;
                }
            }

            if ( empty( array_filter( array_map( 'trim', $lines ) ) ) ) {
                return esc_html__( 'Адрес пока не заполнен.', 'amaressence-account-suite' );
            }

            return implode( '<br>', array_map( 'esc_html', $lines ) );
        }

        private function get_order_details_markup( WC_Order $order ) {
            ob_start();
            ?>
            <div class="ama-order-detail">
                <div class="ama-order-detail__head">
                    <div>
                        <div class="ama-eyebrow"><?php echo esc_html__( 'Заказ', 'amaressence-account-suite' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?></div>
                        <h3><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '—' ); ?></h3>
                    </div>
                    <mark class="ama-status ama-status--<?php echo esc_attr( sanitize_html_class( $order->get_status() ) ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></mark>
                </div>
                <div class="ama-order-detail__items">
                    <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                        <div class="ama-order-item">
                            <div>
                                <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                <span><?php echo esc_html( sprintf( __( 'Количество: %d', 'amaressence-account-suite' ), $item->get_quantity() ) ); ?></span>
                            </div>
                            <div><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="ama-order-detail__meta-grid">
                    <div class="ama-order-meta-box">
                        <strong><?php echo esc_html__( 'Оплата', 'amaressence-account-suite' ); ?></strong>
                        <span><?php echo esc_html( $order->get_payment_method_title() ? $order->get_payment_method_title() : '—' ); ?></span>
                    </div>
                    <div class="ama-order-meta-box">
                        <strong><?php echo esc_html__( 'Доставка', 'amaressence-account-suite' ); ?></strong>
                        <span><?php echo esc_html( $order->get_shipping_method() ? $order->get_shipping_method() : '—' ); ?></span>
                    </div>
                    <div class="ama-order-meta-box">
                        <strong><?php echo esc_html__( 'Итого', 'amaressence-account-suite' ); ?></strong>
                        <span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                    </div>
                </div>
                <?php if ( $order->get_customer_note() ) : ?>
                    <div class="ama-order-note">
                        <strong><?php echo esc_html__( 'Комментарий к заказу', 'amaressence-account-suite' ); ?></strong>
                        <p><?php echo esc_html( $order->get_customer_note() ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        private function get_avatar_letter( WP_User $user ) {
            $source = $user->display_name ? $user->display_name : $user->user_email;
            $source = trim( (string) $source );
            if ( '' === $source ) {
                return 'A';
            }
            return function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $source, 0, 1 ) ) : strtoupper( substr( $source, 0, 1 ) );
        }
    }

    Amaressence_Account_Suite::instance();
}
