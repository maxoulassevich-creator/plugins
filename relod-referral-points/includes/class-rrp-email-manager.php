<?php
/**
 * Email manager.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Email_Manager {

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
	 * Constructor.
	 *
	 * @param RRP_Settings        $settings        Settings.
	 * @param RRP_Logger          $logger          Logger.
	 * @param RRP_Profile_Manager $profile_manager Profile manager.
	 */
	public function __construct( $settings, $logger, $profile_manager ) {
		$this->settings        = $settings;
		$this->logger          = $logger;
		$this->profile_manager = $profile_manager;
	}

	/**
	 * Get all custom email types sent by this plugin.
	 *
	 * @return array
	 */
	public static function get_email_template_definitions() {
		return array(
			'first_order_referral' => array(
				'label'                    => __( 'Письмо после первого оплаченного заказа', 'relod-referral-points' ),
				'description'              => __( 'Отправляется покупателю после первого заказа, когда заказ достиг выбранного статуса. В письме можно показать реферальную ссылку, данные заказа и любые дополнительные блоки.', 'relod-referral-points' ),
				'supports_order_status'    => true,
				'supports_order_details'   => true,
				'supports_referral_link'   => true,
			),
			'points_expiring' => array(
				'label'                    => __( 'Письмо о скором сгорании баллов', 'relod-referral-points' ),
				'description'              => __( 'Отправляется покупателю перед датой сгорания баллов по настройкам раздела «Баллы → Сгорание баллов». В письме доступны переменные баланса, даты сгорания и количества дней до сгорания.', 'relod-referral-points' ),
				'supports_order_status'    => false,
				'supports_order_details'   => false,
				'supports_referral_link'   => false,
			),
		);
	}

	/**
	 * Get normalized settings for a concrete email template.
	 *
	 * @param string $type Email template type.
	 * @return array
	 */
	public function get_template( $type ) {
		$type      = sanitize_key( $type );
		$templates = $this->settings->get( 'email_templates', array() );
		$defaults  = RRP_Settings::email_template_defaults();

		if ( empty( $defaults[ $type ] ) ) {
			return array();
		}

		$template = isset( $templates[ $type ] ) && is_array( $templates[ $type ] ) ? $templates[ $type ] : array();
		$template = wp_parse_args( $template, $defaults[ $type ] );

		if ( empty( $template['blocks'] ) || ! is_array( $template['blocks'] ) ) {
			$template['blocks'] = array();
		}

		$template['blocks'] = wp_parse_args( $template['blocks'], $defaults[ $type ]['blocks'] );

		return $template;
	}

	/**
	 * Whether custom email type is enabled.
	 *
	 * @param string $type Email template type.
	 * @return bool
	 */
	public function is_enabled( $type = 'first_order_referral' ) {
		$template = $this->get_template( $type );

		return 'yes' === $this->settings->get( 'plugin_enabled', 'yes' )
			&& 'yes' === $this->settings->get( 'email_enabled', 'yes' )
			&& ! empty( $template )
			&& 'yes' === $template['enabled'];
	}

	/**
	 * Send first-order email with referral link.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public function send_first_order_email( $order ) {
		$type = 'first_order_referral';

		if ( ! $order instanceof WC_Order || ! $this->is_enabled( $type ) ) {
			return false;
		}

		if ( 'yes' === $order->get_meta( '_rrp_first_order_email_sent', true ) ) {
			return true;
		}

		$email = sanitize_email( $order->get_billing_email() );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$profile = $this->profile_manager->get_or_create_profile_for_order( $order );

		if ( ! $profile ) {
			return false;
		}

		$template      = $this->get_template( $type );
		$referral_link = $this->profile_manager->build_referral_link( $profile );
		$subject       = $this->replace_placeholders( $template['subject'], $order, $referral_link );
		$body          = isset( $template['body'] ) ? $template['body'] : '';
		$content       = $this->build_email_content( $order, $body, $referral_link, $template );
		$headers       = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( function_exists( 'WC' ) && WC()->mailer() && ! $this->looks_like_full_email_document( $content ) ) {
			$mailer  = WC()->mailer();
			$message = $mailer->wrap_message( $subject, $content );
		} else {
			$message = $content;
		}

		$sent = wp_mail( $email, $subject, $message, $headers );

		if ( $sent ) {
			$order->update_meta_data( '_rrp_first_order_email_sent', 'yes' );
			$order->save();

			$this->logger->log(
				'info',
				'Отправлено письмо после первого заказа.',
				array(
					'order_id' => $order->get_id(),
					'email'    => $email,
				)
			);
		}

		return (bool) $sent;
	}

	/**
	 * Send points-expiring email.
	 *
	 * @param array  $profile         Profile row.
	 * @param string $expiring_points Points that will expire.
	 * @param string $expire_date     Expire date in MySQL format.
	 * @param int    $days_left       Days left before expiration.
	 * @param array  $context         Additional context.
	 * @return bool
	 */
	public function send_points_expiring_email( $profile, $expiring_points, $expire_date, $days_left, $context = array() ) {
		$type = 'points_expiring';

		if ( ! is_array( $profile ) || ! $this->is_enabled( $type ) ) {
			return false;
		}

		$email = isset( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '';

		if ( ! is_email( $email ) ) {
			return false;
		}

		$template = $this->get_template( $type );
		$replacements = $this->get_points_expiration_replacements( $profile, $expiring_points, $expire_date, $days_left, $context );
		$subject = $this->replace_generic_placeholders( isset( $template['subject'] ) ? $template['subject'] : '', $replacements );
		$content = $this->build_points_expiring_email_content( isset( $template['body'] ) ? $template['body'] : '', $template, $replacements );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		if ( function_exists( 'WC' ) && WC()->mailer() && ! $this->looks_like_full_email_document( $content ) ) {
			$mailer  = WC()->mailer();
			$message = $mailer->wrap_message( $subject, $content );
		} else {
			$message = $content;
		}

		$sent = wp_mail( $email, $subject, $message, $headers );

		if ( $sent ) {
			$this->logger->log(
				'info',
				'Отправлено письмо о скором сгорании баллов.',
				array(
					'profile_id'      => isset( $profile['id'] ) ? absint( $profile['id'] ) : 0,
					'email'           => $email,
					'expiring_points' => $expiring_points,
					'expire_date'     => $expire_date,
				)
			);
		}

		return (bool) $sent;
	}

	/**
	 * Build replacements for points-expiring email.
	 *
	 * @param array  $profile Profile row.
	 * @param string $expiring_points Expiring points.
	 * @param string $expire_date Expire date.
	 * @param int    $days_left Days left.
	 * @param array  $context Extra context.
	 * @return array
	 */
	protected function get_points_expiration_replacements( $profile, $expiring_points, $expire_date, $days_left, $context = array() ) {
		$email = isset( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '';
		$user  = ! empty( $profile['user_id'] ) ? get_user_by( 'id', absint( $profile['user_id'] ) ) : false;
		$first_name = $user ? trim( (string) get_user_meta( $user->ID, 'first_name', true ) ) : '';
		$last_name  = $user ? trim( (string) get_user_meta( $user->ID, 'last_name', true ) ) : '';
		$customer_name = trim( $first_name . ' ' . $last_name );

		if ( '' === $customer_name && $user ) {
			$customer_name = $user->display_name;
		}

		if ( '' === $first_name ) {
			$first_name = $customer_name ? $customer_name : __( 'покупатель', 'relod-referral-points' );
		}

		if ( '' === $customer_name ) {
			$customer_name = $first_name;
		}

		$expire_timestamp = $expire_date ? strtotime( $expire_date ) : false;
		$formatted_expire_date = $expire_timestamp ? date_i18n( get_option( 'date_format' ), $expire_timestamp ) : '';
		$balance = isset( $context['loyalty_balance'] ) ? $context['loyalty_balance'] : ( isset( $profile['points_balance'] ) ? $profile['points_balance'] : $expiring_points );

		$replacements = array(
			'{customer_name}'       => esc_html( $customer_name ),
			'{customer_first_name}' => esc_html( $first_name ),
			'{customer_last_name}'  => esc_html( $last_name ),
			'{customer_email}'      => esc_html( $email ),
			'{loyalty_balance}'     => esc_html( RRP_Utils::format_points( $balance ) ),
			'{expiring_points}'     => esc_html( RRP_Utils::format_points( $expiring_points ) ),
			'{points_value}'        => esc_html( RRP_Utils::format_points( $expiring_points ) ),
			'{expire_date}'         => esc_html( $formatted_expire_date ),
			'{expire_datetime}'     => esc_html( $expire_timestamp ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expire_timestamp ) : '' ),
			'{days_left}'           => esc_html( absint( $days_left ) ),
			'{shop_url}'            => esc_url( wc_get_page_permalink( 'shop' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ),
			'{loyalty_rules_url}'   => esc_url( home_url( '/informacziya/#ue-tab2' ) ),
			'{site_name}'           => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
		);

		foreach ( $context as $key => $value ) {
			$placeholder = '{' . sanitize_key( $key ) . '}';

			if ( isset( $replacements[ $placeholder ] ) ) {
				continue;
			}

			$replacements[ $placeholder ] = esc_html( (string) $value );
		}

		return $replacements;
	}

	/**
	 * Build final HTML for points-expiring email.
	 *
	 * @param string $body Template body.
	 * @param array  $template Template settings.
	 * @param array  $replacements Placeholder replacements.
	 * @return string
	 */
	protected function build_points_expiring_email_content( $body, $template, $replacements ) {
		$content = $this->replace_generic_placeholders( (string) $body, $replacements );
		$content = $this->sanitize_rendered_email_html( $content );

		$css = $this->replace_generic_placeholders( isset( $template['css'] ) ? $template['css'] : '', $replacements );

		if ( trim( $css ) ) {
			$content = $this->inline_email_css( $content, $css );
		}

		return $content;
	}

	/**
	 * Replace simple placeholders.
	 *
	 * @param string $text Text.
	 * @param array  $replacements Replacements.
	 * @return string
	 */
	protected function replace_generic_placeholders( $text, $replacements ) {
		return strtr( (string) $text, is_array( $replacements ) ? $replacements : array() );
	}

	/**
	 * Replace placeholders in templates.
	 *
	 * @param string   $text          Template text.
	 * @param WC_Order $order         Order.
	 * @param string   $referral_link Referral link.
	 * @param array    $extra         Extra replacements.
	 * @return string
	 */
	protected function replace_placeholders( $text, $order, $referral_link, $extra = array() ) {
		$customer_first_name = $order->get_billing_first_name();
		$customer_last_name  = $order->get_billing_last_name();
		$customer_name       = trim( $customer_first_name . ' ' . $customer_last_name );

		if ( '' === $customer_name ) {
			$customer_name = $order->get_formatted_billing_full_name();
		}

		if ( '' === $customer_name ) {
			$customer_name = __( 'покупатель', 'relod-referral-points' );
		}

		$date_created = $order->get_date_created();

		$replacements = array(
			'{customer_name}'       => esc_html( $customer_name ),
			'{customer_first_name}' => esc_html( $customer_first_name ),
			'{customer_last_name}'  => esc_html( $customer_last_name ),
			'{customer_email}'      => esc_html( $order->get_billing_email() ),
			'{order_number}'        => esc_html( $order->get_order_number() ),
			'{order_total}'         => wp_kses_post( $order->get_formatted_order_total() ),
			'{order_date}'          => $date_created ? esc_html( wc_format_datetime( $date_created ) ) : '',
			'{referral_link}'       => esc_url( $referral_link ),
			'{site_name}'           => esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
		);

		foreach ( $extra as $key => $value ) {
			$replacements[ '{' . sanitize_key( $key ) . '}' ] = (string) $value;
		}

		return strtr( (string) $text, $replacements );
	}

	/**
	 * Build final HTML email content.
	 *
	 * @param WC_Order $order         Order.
	 * @param string   $body          Admin-configured email body.
	 * @param string   $referral_link Referral link.
	 * @param array    $template      Email template settings.
	 * @return string
	 */
	protected function build_email_content( $order, $body, $referral_link, $template = array() ) {
		$template    = wp_parse_args( $template, RRP_Settings::email_template_defaults()['first_order_referral'] );
		$order_block = $this->build_order_details_table( $order, $template );
		$link_block  = $this->build_referral_link_block( $referral_link, $template );
		$css         = $this->replace_placeholders( isset( $template['css'] ) ? $template['css'] : '', $order, $referral_link );
		$has_order_details_placeholder  = false !== strpos( $body, '{order_details_table}' );
		$has_referral_block_placeholder = false !== strpos( $body, '{referral_link_block}' );
		$order_token                    = '%%RRP_ORDER_DETAILS_TABLE%%';
		$referral_token                 = '%%RRP_REFERRAL_LINK_BLOCK%%';

		// Keep big HTML blocks out of wpautop() so WordPress does not wrap tables/headings in broken <p> tags.
		$body = str_replace(
			array( '{order_details_table}', '{referral_link_block}' ),
			array( $order_token, $referral_token ),
			$body
		);

		$body = $this->replace_placeholders( $body, $order, $referral_link );

		if ( $this->looks_like_full_html_template( $body ) ) {
			$content = $this->sanitize_rendered_email_html( $body );
		} else {
			$content = $this->sanitize_rendered_email_html( wpautop( $body ) );
		}

		$content = str_replace(
			array( '<p>' . $order_token . '</p>', '<p>' . $referral_token . '</p>', $order_token, $referral_token ),
			array( $order_block, $link_block, $order_block, $link_block ),
			$content
		);

		if ( 'yes' === $template['append_order_details'] && ! $has_order_details_placeholder ) {
			$content .= $order_block;
		}

		if ( 'yes' === $template['append_referral_block'] && ! $has_referral_block_placeholder ) {
			$content .= $link_block;
		}

		if ( trim( $css ) ) {
			$content = $this->inline_email_css( $content, $css );
		}

		return $content;
	}

	/**
	 * Remove unsafe active content while preserving email HTML, inline styles and full document markup.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	protected function sanitize_rendered_email_html( $html ) {
		$html = (string) $html;
		$html = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $html );
		$html = preg_replace( '/\son[a-z]+\s*=\s*("|\').*?\1/isu', '', $html );
		$html = preg_replace( '/\s(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/isu', ' $1="#"', $html );
		return $html;
	}

	/**
	 * Detect full HTML email document.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	protected function looks_like_full_email_document( $html ) {
		return (bool) preg_match( '/<(?:!doctype\s+html|html|body)\b/i', (string) $html );
	}

	/**
	 * Detect whether a template is already full table/div HTML and should not be wpautop'ed.
	 *
	 * @param string $body Template body.
	 * @return bool
	 */
	protected function looks_like_full_html_template( $body ) {
		return (bool) preg_match( '/<(table|tbody|tr|td|div|section|article|header|footer|img|h1|h2|h3)\b/i', (string) $body );
	}

	/**
	 * Inline CSS into HTML so Gmail and other email clients do not show raw CSS text.
	 *
	 * @param string $html HTML content.
	 * @param string $css  CSS rules.
	 * @return string
	 */
	protected function inline_email_css( $html, $css ) {
		$html = (string) $html;
		$css  = trim( wp_strip_all_tags( (string) $css ) );

		if ( '' === $html || '' === $css ) {
			return $html;
		}

		if ( class_exists( '\\Pelago\\Emogrifier\\CssInliner' ) ) {
			try {
				return \Pelago\Emogrifier\CssInliner::fromHtml( $html )->inlineCss( $css )->render();
			} catch ( \Throwable $e ) {
				$this->logger->log( 'warning', 'Emogrifier не смог встроить CSS письма, используется встроенный fallback.', array( 'error' => $e->getMessage() ) );
			}
		}

		return $this->inline_email_css_fallback( $html, $css );
	}

	/**
	 * Lightweight CSS inliner for common email selectors.
	 *
	 * Supports class selectors, tag selectors and simple descendant chains like
	 * .wrapper table, .wrapper .child, .wrapper td.
	 *
	 * @param string $html HTML content.
	 * @param string $css  CSS rules.
	 * @return string
	 */
	protected function inline_email_css_fallback( $html, $css ) {
		$rules = $this->parse_email_css_rules( $css );

		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
			return $this->inline_email_css_regex_fallback( $html, $rules );
		}

		if ( empty( $rules ) ) {
			return $html;
		}

		$dom = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		$wrapped = '<div id="rrp-email-inline-root">' . $html . '</div>';
		$loaded  = $dom->loadHTML( '<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return $html;
		}

		$xpath = new DOMXPath( $dom );

		foreach ( $rules as $rule ) {
			foreach ( $rule['selectors'] as $selector ) {
				$xpath_query = $this->email_css_selector_to_xpath( $selector );

				if ( ! $xpath_query ) {
					continue;
				}

				$nodes = $xpath->query( $xpath_query );

				if ( ! $nodes ) {
					continue;
				}

				foreach ( $nodes as $node ) {
					if ( $node instanceof DOMElement ) {
						$this->append_inline_style( $node, $rule['declarations'] );
					}
				}
			}
		}

		$root = $dom->getElementById( 'rrp-email-inline-root' );

		if ( ! $root ) {
			return $html;
		}

		return $this->dom_inner_html( $dom, $root );
	}


	/**
	 * Regex fallback for hosts without DOM extension.
	 *
	 * @param string $html  HTML content.
	 * @param array  $rules Parsed CSS rules.
	 * @return string
	 */
	protected function inline_email_css_regex_fallback( $html, $rules ) {
		$html = (string) $html;

		if ( empty( $rules ) ) {
			return $html;
		}

		foreach ( $rules as $rule ) {
			foreach ( $rule['selectors'] as $selector ) {
				$target = $this->email_css_selector_target( $selector );

				if ( ! $target ) {
					continue;
				}

				$html = $this->append_style_to_matching_tags( $html, $target, $rule['declarations'] );
			}
		}

		return $html;
	}

	/**
	 * Extract a practical target from a simple selector.
	 *
	 * @param string $selector CSS selector.
	 * @return array|null
	 */
	protected function email_css_selector_target( $selector ) {
		$selector = trim( (string) $selector );

		if ( '' === $selector || false !== strpos( $selector, '#' ) || false !== strpos( $selector, '[' ) || false !== strpos( $selector, '+' ) || false !== strpos( $selector, '~' ) ) {
			return null;
		}

		if ( preg_match( '/:(last-child|first-child|nth-child\([^)]*\)|hover|active|focus|visited)/i', $selector ) ) {
			return null;
		}

		$selector = preg_replace( '/:(last-child|first-child|nth-child\([^)]*\)|hover|active|focus|visited)/i', '', $selector );
		$selector = preg_replace( '/\s*>\s*/', ' ', $selector );
		$selector = preg_replace( '/\s+/', ' ', $selector );
		$tokens   = array_values( array_filter( explode( ' ', $selector ) ) );

		if ( empty( $tokens ) ) {
			return null;
		}

		$token = end( $tokens );

		if ( '*' === $token ) {
			return null;
		}

		$tag     = '*';
		$classes = array();

		if ( preg_match( '/^([a-z][a-z0-9_-]*)?((?:\.[a-z0-9_-]+)+)$/i', $token, $match ) ) {
			if ( ! empty( $match[1] ) ) {
				$tag = strtolower( $match[1] );
			}
			$classes = array_values( array_filter( explode( '.', $match[2] ) ) );
		} elseif ( preg_match( '/^[a-z][a-z0-9_-]*$/i', $token ) ) {
			if ( count( $tokens ) > 1 ) {
				return null;
			}
			$tag = strtolower( $token );
		} else {
			return null;
		}

		return array(
			'tag'     => $tag,
			'classes' => $classes,
		);
	}

	/**
	 * Append style to matching opening HTML tags.
	 *
	 * @param string $html         HTML content.
	 * @param array  $target       Target definition.
	 * @param string $declarations CSS declarations.
	 * @return string
	 */
	protected function append_style_to_matching_tags( $html, $target, $declarations ) {
		$tag_pattern = '*' === $target['tag'] ? '[a-z][a-z0-9:-]*' : preg_quote( $target['tag'], '/' );
		$classes     = isset( $target['classes'] ) && is_array( $target['classes'] ) ? $target['classes'] : array();
		$style_attr  = htmlspecialchars( trim( (string) $declarations ), ENT_QUOTES, 'UTF-8' );

		if ( '' === $style_attr ) {
			return $html;
		}

		return preg_replace_callback(
			'/<(' . $tag_pattern . ')(\s[^<>]*?)?>/i',
			function( $match ) use ( $classes, $style_attr ) {
				$tag   = $match[1];
				$attrs = isset( $match[2] ) ? $match[2] : '';

				if ( ! empty( $classes ) ) {
					if ( ! preg_match( '/\sclass=("|\')(.*?)\1/i', $attrs, $class_match ) ) {
						return $match[0];
					}

					$existing_classes = preg_split( '/\s+/', trim( $class_match[2] ) );

					foreach ( $classes as $class ) {
						if ( ! in_array( $class, $existing_classes, true ) ) {
							return $match[0];
						}
					}
				}

				if ( preg_match( '/\sstyle=("|\')(.*?)\1/i', $attrs, $style_match ) ) {
					$current   = trim( html_entity_decode( $style_match[2], ENT_QUOTES, 'UTF-8' ) );
					$current   = '' !== $current && ';' !== substr( $current, -1 ) ? $current . ';' : $current;
					$new_style = htmlspecialchars( trim( $current . ' ' . html_entity_decode( $style_attr, ENT_QUOTES, 'UTF-8' ) ), ENT_QUOTES, 'UTF-8' );
					$attrs     = preg_replace( '/\sstyle=("|\')(.*?)\1/i', ' style="' . $new_style . '"', $attrs, 1 );
					return '<' . $tag . $attrs . '>';
				}

				return '<' . $tag . $attrs . ' style="' . $style_attr . '">';
			},
			$html
		);
	}

	/**
	 * Parse CSS into selector/declaration rules.
	 *
	 * @param string $css CSS.
	 * @return array
	 */
	protected function parse_email_css_rules( $css ) {
		$css = preg_replace( '#/\*.*?\*/#s', '', (string) $css );
		$css = preg_replace( '/@media[^{}]+\{(?:[^{}]|\{[^{}]*\})*\}/is', '', $css );
		$css = preg_replace( '/@[^{}]+\{[^{}]*\}/is', '', $css );
		$rules = array();

		if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $matches, PREG_SET_ORDER ) ) {
			return $rules;
		}

		foreach ( $matches as $match ) {
			$selectors    = array_filter( array_map( 'trim', explode( ',', $match[1] ) ) );
			$declarations = $this->sanitize_inline_css_declarations( $match[2] );

			if ( empty( $selectors ) || '' === $declarations ) {
				continue;
			}

			$rules[] = array(
				'selectors'    => $selectors,
				'declarations' => $declarations,
			);
		}

		return $rules;
	}

	/**
	 * Sanitize inline CSS declarations.
	 *
	 * @param string $declarations CSS declarations.
	 * @return string
	 */
	protected function sanitize_inline_css_declarations( $declarations ) {
		$declarations = trim( wp_strip_all_tags( (string) $declarations ) );
		$declarations = str_replace( array( "\r", "\n", "\t" ), ' ', $declarations );
		$declarations = preg_replace( '/\s+/', ' ', $declarations );

		if ( function_exists( 'safecss_filter_attr' ) ) {
			$declarations = safecss_filter_attr( $declarations );
		}

		$declarations = trim( $declarations );

		if ( '' !== $declarations && ';' !== substr( $declarations, -1 ) ) {
			$declarations .= ';';
		}

		return $declarations;
	}

	/**
	 * Convert a simple CSS selector to XPath.
	 *
	 * @param string $selector CSS selector.
	 * @return string
	 */
	protected function email_css_selector_to_xpath( $selector ) {
		$selector = trim( (string) $selector );
		if ( preg_match( '/:(last-child|first-child|nth-child\([^)]*\)|hover|active|focus|visited)/i', $selector ) ) {
			return '';
		}
		$selector = preg_replace( '/:(last-child|first-child|nth-child\([^)]*\)|hover|active|focus|visited)/i', '', $selector );
		$selector = preg_replace( '/\s*>\s*/', ' ', $selector );
		$selector = preg_replace( '/\s+/', ' ', $selector );

		if ( '' === $selector || false !== strpos( $selector, '#' ) || false !== strpos( $selector, '[' ) || false !== strpos( $selector, '+' ) || false !== strpos( $selector, '~' ) ) {
			return '';
		}

		$tokens = explode( ' ', $selector );
		$xpath  = '';

		foreach ( $tokens as $index => $token ) {
			$token = trim( $token );

			if ( '' === $token ) {
				continue;
			}

			$step = $this->email_css_token_to_xpath_step( $token );

			if ( '' === $step ) {
				return '';
			}

			$xpath .= 0 === $index ? '//' . $step : '//' . $step;
		}

		return $xpath;
	}

	/**
	 * Convert a simple selector token to XPath step.
	 *
	 * @param string $token CSS selector token.
	 * @return string
	 */
	protected function email_css_token_to_xpath_step( $token ) {
		if ( '*' === $token ) {
			return '*';
		}

		$tag     = '*';
		$classes = array();

		if ( preg_match( '/^([a-z][a-z0-9_-]*)?((?:\.[a-z0-9_-]+)+)$/i', $token, $match ) ) {
			if ( ! empty( $match[1] ) ) {
				$tag = strtolower( $match[1] );
			}
			$classes = array_filter( explode( '.', $match[2] ) );
		} elseif ( preg_match( '/^[a-z][a-z0-9_-]*$/i', $token ) ) {
			$tag = strtolower( $token );
		} else {
			return '';
		}

		$conditions = array();
		foreach ( $classes as $class ) {
			$conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')";
		}

		if ( empty( $conditions ) ) {
			return $tag;
		}

		return $tag . '[' . implode( ' and ', $conditions ) . ']';
	}

	/**
	 * Append a CSS declaration block to a DOM node style attribute.
	 *
	 * @param DOMElement $node         DOM node.
	 * @param string     $declarations CSS declarations.
	 * @return void
	 */
	protected function append_inline_style( $node, $declarations ) {
		$current = trim( (string) $node->getAttribute( 'style' ) );
		$current = '' !== $current && ';' !== substr( $current, -1 ) ? $current . ';' : $current;
		$node->setAttribute( 'style', trim( $current . ' ' . $declarations ) );
	}

	/**
	 * Get inner HTML of a DOM element.
	 *
	 * @param DOMDocument $dom  DOM document.
	 * @param DOMElement  $node DOM element.
	 * @return string
	 */
	protected function dom_inner_html( $dom, $node ) {
		$html = '';

		foreach ( $node->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Build order details table HTML.
	 *
	 * @param WC_Order $order    Order.
	 * @param array    $template Email template settings.
	 * @return string
	 */
	protected function build_order_details_table( $order, $template = array() ) {
		$defaults = RRP_Settings::email_template_defaults()['first_order_referral'];
		$template = wp_parse_args( $template, $defaults );
		$blocks   = isset( $template['blocks'] ) && is_array( $template['blocks'] ) ? wp_parse_args( $template['blocks'], $defaults['blocks'] ) : $defaults['blocks'];

		$row_template = isset( $blocks['order_item_row'] ) ? $blocks['order_item_row'] : $defaults['blocks']['order_item_row'];
		$items_html   = '';

		foreach ( $order->get_items() as $item ) {
			$items_html .= strtr(
				$row_template,
				array(
					'{item_name}'     => esc_html( $item->get_name() ),
					'{item_quantity}' => esc_html( $item->get_quantity() ),
					'{item_subtotal}' => wp_kses_post( $order->get_formatted_line_subtotal( $item ) ),
				)
			);
		}

		$date_created = $order->get_date_created();
		$block        = isset( $blocks['order_details_table'] ) ? $blocks['order_details_table'] : $defaults['blocks']['order_details_table'];

		return strtr(
			$block,
			array(
				'{label_order_details}' => esc_html__( 'Детали заказа', 'relod-referral-points' ),
				'{label_order_number}'  => esc_html__( 'Номер заказа', 'relod-referral-points' ),
				'{label_order_date}'    => esc_html__( 'Дата', 'relod-referral-points' ),
				'{label_order_total}'   => esc_html__( 'Итого', 'relod-referral-points' ),
				'{label_product}'       => esc_html__( 'Товар', 'relod-referral-points' ),
				'{label_quantity}'      => esc_html__( 'Количество', 'relod-referral-points' ),
				'{label_amount}'        => esc_html__( 'Сумма', 'relod-referral-points' ),
				'{order_number}'        => esc_html( $order->get_order_number() ),
				'{order_date}'          => $date_created ? esc_html( wc_format_datetime( $date_created ) ) : '',
				'{order_total}'         => wp_kses_post( $order->get_formatted_order_total() ),
				'{order_items_rows}'    => $items_html,
			)
		);
	}

	/**
	 * Build referral link block HTML.
	 *
	 * @param string $referral_link Referral link.
	 * @param array  $template      Email template settings.
	 * @return string
	 */
	protected function build_referral_link_block( $referral_link, $template = array() ) {
		$defaults = RRP_Settings::email_template_defaults()['first_order_referral'];
		$template = wp_parse_args( $template, $defaults );
		$blocks   = isset( $template['blocks'] ) && is_array( $template['blocks'] ) ? wp_parse_args( $template['blocks'], $defaults['blocks'] ) : $defaults['blocks'];
		$block    = isset( $blocks['referral_link_block'] ) ? $blocks['referral_link_block'] : $defaults['blocks']['referral_link_block'];

		return strtr(
			$block,
			array(
				'{label_referral_link}' => esc_html__( 'Ваша реферальная ссылка', 'relod-referral-points' ),
				'{referral_link}'       => esc_url( $referral_link ),
			)
		);
	}
}
