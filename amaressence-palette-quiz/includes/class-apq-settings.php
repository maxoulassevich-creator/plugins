<?php
/**
 * Settings storage and result logic.
 *
 * @package AmaressencePaletteQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APQ_Settings {

	protected $option_key = 'apq_settings';

	public static function defaults() {
		return array(
			// Общие.
			'plugin_enabled'         => 'yes',
			'logs_enabled'           => 'yes',

			// Страница квиза и доступ.
			'quiz_page_id'           => 0,
			'one_attempt_per_email'  => 'yes',

			// Баллы.
			'points_amount'          => '400',
			'points_award_mode'      => 'after_first_order', // immediate | after_first_order | none.
			'points_order_status'    => 'completed',
			'points_description'     => 'Бонус за прохождение квиза «Палитра amarèssence»',

			// Попап.
			'popup_enabled'          => 'yes',
			'popup_trigger'          => '#apq-quiz',
			'popup_login_title'      => 'Авторизация',
			'popup_register_title'   => 'Регистрация',
			'popup_subtitle'         => 'Войдите или создайте аккаунт, чтобы пройти квиз и получить баллы.',
			'user_agreement_url'     => 'https://amaressence.ru/informacziya/#soglasie-s-obrabotkoj-pdn',
			'loyalty_url'            => 'https://amaressence.ru/informacziya/#programma-loyalnosti',
			'marketing_consent_text' => 'Подтверждаю согласие на получение информации о новинках и акциях. Ваш подарок — промокод на скидку 15% на 90 дней',

			// Тексты квиза.
			'intro_headline'         => 'Какая палитра<br><em>у твоей</em> amarèssence?',
			'intro_sub'              => 'Выбери цвет для каждой ценности бренда — и узнай свой палитровый профиль',
			'intro_time_text'        => '2 минуты',
			'reward_card_label'      => 'Подарок после прохождения',
			'reward_card_text'       => '{points} баллов на первую покупку — они появятся на твоём аккаунте автоматически',
			'gift_badge'             => '🎁 Подарок для тебя',
			'gift_title'             => '{points} баллов на первую покупку',
			'gift_desc'              => 'Нажми кнопку — и баллы будут закреплены за твоим аккаунтом',
			'gift_button'            => 'Получить {points} баллов',
			'success_title'          => 'Готово!',
			'success_desc'           => 'Твой профиль сохранён. Баллы появятся на аккаунте после первой покупки.',
			'success_desc_awarded'   => 'Твой профиль сохранён, баллы уже начислены на твой аккаунт.',
			'already_title'          => 'Ты уже проходила квиз',
			'already_desc'           => 'Подарок за квиз начисляется только один раз. Твой профиль сохранён — баллы закреплены за твоим аккаунтом.',
			'already_replay_text'    => 'Пройти ещё раз — просто ради палитры',
			'shop_button_text'       => 'Перейти в магазин',
			'shop_button_url'        => 'https://amaressence.ru/',
			'logo_url'               => '',

			// Поле email на финальном экране (для неавторизованных).
			'claim_email_label'      => 'Куда начислить баллы?',
			'claim_email_placeholder' => 'Эл. почта твоего аккаунта',
			'claim_email_hint'       => 'Укажи email, на который зарегистрирован аккаунт amarèssence, — подарок будет закреплён за ним.',
			'no_account_text'        => 'Аккаунт с таким email не найден. Создай аккаунт за минуту — и баллы сразу будут закреплены за ним.',
			'no_account_button'      => 'Создать аккаунт',

			// Контент квиза (JSON, редактируемый в админке).
			'quiz_values_json'       => '',
			'quiz_colors_json'       => '',
			'quiz_profiles_json'     => '', // legacy: оставлено для совместимости старых настроек.
			'quiz_result_rules_json' => '',
		);
	}

	/**
	 * Группы цветов для расчёта результата.
	 */
	public static function color_family_labels() {
		return array(
			'neutral' => 'Нейтральная',
			'earth'   => 'Земляная',
			'calm'    => 'Спокойная',
			'bold'    => 'Яркая',
			'deep'    => 'Глубокая',
			'soft'    => 'Мягкая',
			'fresh'   => 'Свежая',
			'bright'  => 'Светлая/акцентная',
		);
	}

	/**
	 * Дефолтные данные квиза.
	 */
	public static function default_quiz_data() {
		return array(
			'values'       => array(
				array( 'id' => 'soft_strength', 'title' => 'Мягкая сила', 'description' => 'Мягкость в прикосновении и собранность в движении.' ),
				array( 'id' => 'confidence', 'title' => 'Уверенность', 'description' => 'Как ты выглядишь, когда чувствуешь себя на своём месте.' ),
				array( 'id' => 'movement', 'title' => 'Свобода движения', 'description' => 'Лёгкость, пластика, дыхание тела. Когда тело в потоке.' ),
				array( 'id' => 'ease', 'title' => 'Лёгкость на каждый день', 'description' => 'Цвет базы, которую хочется надевать снова и снова.' ),
				array( 'id' => 'femininity', 'title' => 'Женственность', 'description' => 'Форма, красота и мягкая выразительность. Без объяснений.' ),
				array( 'id' => 'balance', 'title' => 'Баланс', 'description' => 'Между энергией и спокойствием. Всё на своём месте.' ),
			),
			'colors'       => array(
				array( 'id' => 'black', 'label' => 'Чёрный', 'hex' => '#1C1C1C', 'family' => 'neutral' ),
				array( 'id' => 'chocolate', 'label' => 'Шоколад', 'hex' => '#5A3A2E', 'family' => 'earth' ),
				array( 'id' => 'graphite', 'label' => 'Графит', 'hex' => '#5A5A5A', 'family' => 'neutral' ),
				array( 'id' => 'taupe', 'label' => 'Тауп', 'hex' => '#A89888', 'family' => 'neutral' ),
				array( 'id' => 'cream', 'label' => 'Сливочный', 'hex' => '#F0E8D5', 'family' => 'neutral' ),
				array( 'id' => 'olive', 'label' => 'Оливковый', 'hex' => '#8C9A6E', 'family' => 'calm' ),
				array( 'id' => 'red', 'label' => 'Красный', 'hex' => '#C83232', 'family' => 'bold' ),
				array( 'id' => 'burgundy', 'label' => 'Бордовый', 'hex' => '#7A1F3D', 'family' => 'deep' ),
				array( 'id' => 'light_pink', 'label' => 'Нежно-<br>розовый', 'hex' => '#F5C0CE', 'family' => 'soft' ),
				array( 'id' => 'hot_pink', 'label' => 'Ярко-<br>розовый', 'hex' => '#FF4FA3', 'family' => 'bold' ),
				array( 'id' => 'navy', 'label' => 'Темно-<br>синий', 'hex' => '#1E2F6B', 'family' => 'deep' ),
				array( 'id' => 'sea_wave', 'label' => 'Морская волна', 'hex' => '#2E8B8B', 'family' => 'fresh' ),
				array( 'id' => 'sky', 'label' => 'Небесный', 'hex' => '#8CC7F2', 'family' => 'fresh' ),
				array( 'id' => 'cornflower', 'label' => 'Васильковый', 'hex' => '#6B8DD6', 'family' => 'fresh' ),
				array( 'id' => 'coral', 'label' => 'Коралл', 'hex' => '#FF6F61', 'family' => 'bold' ),
				array( 'id' => 'lemon', 'label' => 'Лимонный', 'hex' => '#F0DC5C', 'family' => 'bright' ),
			),
			'profiles'     => self::legacy_profiles(),
			'result_rules' => self::default_result_rules(),
		);
	}

	/**
	 * Старые профили оставлены для показа уже сохранённых результатов, если нет answers.
	 */
	public static function legacy_profiles() {
		return array(
			array(
				'id'          => 'grounded_essence',
				'families'    => array( 'neutral', 'earth' ),
				'title'       => 'Земляная суть',
				'tagline'     => 'Сдержанная. Уверенная. Настоящая.',
				'description' => 'Твоя палитра — это сила корней. Ты выбираешь глубину и надёжность, классику и естественность. Для тебя amarèssence — это не мода, а основа. База, которая всегда с тобой.',
				'colors'      => array( 'black', 'chocolate', 'taupe' ),
			),
			array(
				'id'          => 'soft_balance',
				'families'    => array( 'soft', 'calm' ),
				'title'       => 'Мягкий баланс',
				'tagline'     => 'Нежная. Гармоничная. Тихая сила.',
				'description' => 'Ты выбираешь нежность и внутреннюю гармонию. Твоя энергия спокойна и устойчива. amarèssence для тебя — ощущение заботы о себе и комфорта в каждом движении.',
				'colors'      => array( 'light_pink', 'olive', 'cream' ),
			),
			array(
				'id'          => 'bold_feminine',
				'families'    => array( 'bold', 'deep' ),
				'title'       => 'Смелая женственность',
				'tagline'     => 'Яркая. Выразительная. Притягивающая.',
				'description' => 'Твоя палитра — это выразительность и уверенность в каждом движении. Ты не боишься быть заметной. Для тебя amarèssence — это форма, красота и смелый выбор.',
				'colors'      => array( 'hot_pink', 'burgundy', 'coral' ),
			),
			array(
				'id'          => 'fluid_motion',
				'families'    => array( 'fresh', 'bright' ),
				'title'       => 'Свободное движение',
				'tagline'     => 'Лёгкая. Открытая. В потоке.',
				'description' => 'Ты выбираешь свежесть и пространство. Для тебя важна свобода — в движении и в жизни. amarèssence — это лёгкость каждого дня и дыхание в каждом шаге.',
				'colors'      => array( 'sky', 'cornflower', 'sea_wave' ),
			),
			array(
				'id'          => 'magnetic_duality',
				'families'    => array(),
				'title'       => 'Магнетизм контрастов',
				'tagline'     => 'Многогранная. Непредсказуемая. Своя.',
				'description' => 'Твоя палитра — это многогранность. Ты сочетаешь противоположности и создаёшь из них свою энергию. amarèssence для тебя — пространство для самовыражения.',
				'colors'      => array( 'graphite', 'navy', 'light_pink' ),
			),
		);
	}

	/**
	 * Редактируемые сценарии результата. match/families/priority — системные поля.
	 */
	public static function default_result_rules() {
		return array(
			array(
				'id'          => 'one_color_all',
				'match'       => 'one_color_all',
				'label'       => 'Все ответы — один и тот же цвет',
				'title'       => 'Чистая линия',
				'tagline'     => 'Цельная. Спокойная. Собранная.',
				'description' => 'Ты выбрала один цвет во всех ответах — {top_color}. Такой выбор говорит о ясности, внутренней собранности и стремлении к цельному образу без лишних акцентов.',
			),
			array(
				'id'          => 'one_family_all',
				'match'       => 'one_family_all',
				'label'       => 'Все ответы из одной цветовой группы',
				'title'       => 'Единая палитра',
				'tagline'     => 'Выверенная. Устойчивая. Цельная.',
				'description' => 'Все выбранные оттенки относятся к группе «{top_family}». Палитра выглядит собранной: в ней есть разные нюансы, но общее настроение остаётся единым.',
			),
			array(
				'id'          => 'two_color_tie',
				'match'       => 'two_color_tie',
				'label'       => 'Два цвета выбраны поровну и закрывают все ответы, например 3+3',
				'title'       => 'Двойная опора',
				'tagline'     => 'Сбалансированная. Собранная. Контрастная.',
				'description' => 'Твоя палитра держится на двух равных цветах: {top_color} и {second_color}. В таком выборе есть устойчивость и мягкий контраст — два настроения работают вместе.',
			),
			array(
				'id'          => 'three_color_tie',
				'match'       => 'three_color_tie',
				'label'       => 'Три цвета выбраны поровну и закрывают все ответы, например 2+2+2',
				'title'       => 'Три грани баланса',
				'tagline'     => 'Разная. Собранная. Гармоничная.',
				'description' => 'Выбор распределился между тремя равными оттенками. Такая палитра не уходит в один акцент: она строится на балансе нескольких сторон характера.',
			),
			array(
				'id'          => 'dominant_color',
				'match'       => 'dominant_color',
				'label'       => 'Один цвет выбран больше чем в половине ответов',
				'title'       => 'Главный акцент',
				'tagline'     => 'Решительная. Узнаваемая. С характером.',
				'description' => 'В твоих ответах чаще всего повторяется {top_color}. Этот оттенок становится центром палитры и задаёт основное настроение образа.',
			),
			array(
				'id'          => 'grounded_essence',
				'match'       => 'family_cluster',
				'label'       => 'Преобладают нейтральные и земляные оттенки',
				'families'    => array( 'neutral', 'earth' ),
				'title'       => 'Земляная суть',
				'tagline'     => 'Сдержанная. Уверенная. Настоящая.',
				'description' => 'Твоя палитра — это сила корней. Ты выбираешь глубину и надёжность, классику и естественность. Для тебя amarèssence — не мода, а основа, которая всегда с тобой.',
			),
			array(
				'id'          => 'soft_balance',
				'match'       => 'family_cluster',
				'label'       => 'Преобладают мягкие и спокойные оттенки',
				'families'    => array( 'soft', 'calm' ),
				'title'       => 'Мягкий баланс',
				'tagline'     => 'Нежная. Гармоничная. Тихая сила.',
				'description' => 'Ты выбираешь нежность и внутреннюю гармонию. Твоя энергия спокойна и устойчива, а палитра создаёт ощущение заботы о себе и комфорта в каждом движении.',
			),
			array(
				'id'          => 'bold_feminine',
				'match'       => 'family_cluster',
				'label'       => 'Преобладают яркие и глубокие оттенки',
				'families'    => array( 'bold', 'deep' ),
				'title'       => 'Смелая женственность',
				'tagline'     => 'Яркая. Выразительная. Притягивающая.',
				'description' => 'Твоя палитра — это выразительность и уверенность в каждом движении. Ты не боишься быть заметной: цвет помогает подчеркнуть форму, красоту и смелый выбор.',
			),
			array(
				'id'          => 'fluid_motion',
				'match'       => 'family_cluster',
				'label'       => 'Преобладают свежие и светлые акцентные оттенки',
				'families'    => array( 'fresh', 'bright' ),
				'title'       => 'Свободное движение',
				'tagline'     => 'Лёгкая. Открытая. В потоке.',
				'description' => 'Ты выбираешь свежесть и пространство. Для тебя важна свобода — в движении и в жизни. Палитра даёт ощущение лёгкости каждого дня.',
			),
			array(
				'id'          => 'two_family_tie',
				'match'       => 'two_family_tie',
				'label'       => 'Две цветовые группы выбраны поровну',
				'title'       => 'Баланс двух сторон',
				'tagline'     => 'Гибкая. Цельная. Уравновешенная.',
				'description' => 'В выборе одинаково сильны две группы: «{top_family}» и «{second_family}». Палитра не спорит сама с собой — она соединяет два разных настроения в один образ.',
			),
			array(
				'id'          => 'three_family_tie',
				'match'       => 'three_family_tie',
				'label'       => 'Три цветовые группы выбраны поровну',
				'title'       => 'Три стороны характера',
				'tagline'     => 'Многогранная. Живая. Сбалансированная.',
				'description' => 'В палитре равномерно встретились три направления. Такой результат показывает разносторонний выбор без жёсткого доминирования одного настроения.',
			),
			array(
				'id'          => 'paired_mosaic',
				'match'       => 'paired_mosaic',
				'label'       => 'Есть повторы, но нет одного лидера',
				'title'       => 'Ритм повторений',
				'tagline'     => 'Многогранная. Системная. Своя.',
				'description' => 'В ответах есть повторяющиеся оттенки, но ни один из них не забирает всё внимание. Палитра выглядит живой и при этом не случайной: в ней есть свой ритм.',
			),
			array(
				'id'          => 'all_unique',
				'match'       => 'all_unique',
				'label'       => 'Все выбранные цвета разные',
				'title'       => 'Свободная мозаика',
				'tagline'     => 'Открытая. Любопытная. Непредсказуемая.',
				'description' => 'Ты выбрала {selected_count} разных оттенков без повторов. Такой результат говорит о свободе выбора: палитра собирается не вокруг одного цвета, а вокруг множества настроений.',
			),
			array(
				'id'          => 'diverse_mosaic',
				'match'       => 'fallback',
				'label'       => 'Смешанная выборка без точного совпадения с другими сценариями',
				'title'       => 'Магнетизм контрастов',
				'tagline'     => 'Многогранная. Непредсказуемая. Своя.',
				'description' => 'Твоя палитра получилась смешанной: в ней есть несколько направлений, но без единственного лидера. Такой выбор хорошо передаёт многогранность и право быть разной.',
			),
		);
	}

	public function get_all() {
		$stored = get_option( $this->option_key, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	public function get( $key, $default = null ) {
		$settings = $this->get_all();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	public function maybe_add_defaults() {
		if ( false === get_option( $this->option_key, false ) ) {
			add_option( $this->option_key, self::defaults() );
			return;
		}

		$stored = get_option( $this->option_key, array() );

		if ( is_array( $stored ) ) {
			$changed = false;

			foreach ( self::defaults() as $key => $value ) {
				if ( ! array_key_exists( $key, $stored ) ) {
					$stored[ $key ] = $value;
					$changed       = true;
				}
			}

			if ( $changed ) {
				update_option( $this->option_key, $stored );
			}
		}
	}

	/**
	 * Актуальные данные квиза: JSON из настроек или дефолт.
	 */
	public function get_quiz_data() {
		$defaults = self::default_quiz_data();
		$data     = $defaults;

		foreach ( array( 'values' => 'quiz_values_json', 'colors' => 'quiz_colors_json', 'profiles' => 'quiz_profiles_json', 'result_rules' => 'quiz_result_rules_json' ) as $part => $key ) {
			$json = trim( (string) $this->get( $key, '' ) );

			if ( '' === $json ) {
				continue;
			}

			$decoded = json_decode( $json, true );

			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				$data[ $part ] = $decoded;
			}
		}

		$data['values']       = self::normalize_values( $data['values'], $defaults['values'] );
		$data['colors']       = self::normalize_colors( $data['colors'], $defaults['colors'] );
		$data['profiles']     = self::normalize_profiles( $data['profiles'], $defaults['profiles'] );
		$data['result_rules'] = self::normalize_result_rules( $data['result_rules'], $defaults['result_rules'] );

		return $data;
	}

	/**
	 * Нормализовать вопросы: минимум 1 вопрос, максимум не ограничен.
	 */
	public static function normalize_values( $values, $defaults = array() ) {
		$defaults = ! empty( $defaults ) ? $defaults : self::default_quiz_data()['values'];
		$clean    = array();
		$seen     = array();

		foreach ( (array) $values as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = isset( $row['title'] ) ? trim( wp_strip_all_tags( (string) $row['title'] ) ) : '';

			if ( '' === $title ) {
				continue;
			}

			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';

			if ( '' === $id ) {
				$id = sanitize_key( remove_accents( $title ) );
			}

			if ( '' === $id ) {
				$id = 'question_' . ( count( $clean ) + 1 );
			}

			$base_id = $id;
			$suffix  = 2;

			while ( isset( $seen[ $id ] ) ) {
				$id = $base_id . '_' . $suffix;
				$suffix++;
			}

			$seen[ $id ] = true;

			$clean[] = array(
				'id'          => $id,
				'title'       => $title,
				'description' => isset( $row['description'] ) ? sanitize_text_field( (string) $row['description'] ) : '',
			);
		}

		foreach ( $defaults as $row ) {
			if ( ! empty( $clean ) ) {
				break;
			}

			$id = sanitize_key( $row['id'] );

			if ( isset( $seen[ $id ] ) ) {
				continue;
			}

			$clean[]     = $row;
			$seen[ $id ] = true;
		}

		return $clean;
	}

	/**
	 * Нормализовать варианты цветов. Если список сломан или пустой, возвращаем исходные 16 цветов.
	 */
	public static function normalize_colors( $colors, $defaults = array() ) {
		$defaults = ! empty( $defaults ) ? $defaults : self::default_quiz_data()['colors'];
		$clean    = array();
		$seen     = array();

		foreach ( (array) $colors as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = isset( $row['label'] ) ? trim( wp_kses( (string) $row['label'], array( 'br' => array() ) ) ) : '';
			$hex   = isset( $row['hex'] ) ? sanitize_hex_color( (string) $row['hex'] ) : '';

			if ( '' === $label || ! $hex ) {
				continue;
			}

			$id = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';

			if ( '' === $id ) {
				$id = sanitize_key( remove_accents( wp_strip_all_tags( $label ) ) );
			}

			if ( '' === $id ) {
				$id = 'color_' . ( count( $clean ) + 1 );
			}

			$base_id = $id;
			$suffix  = 2;

			while ( isset( $seen[ $id ] ) ) {
				$id = $base_id . '_' . $suffix;
				$suffix++;
			}

			$seen[ $id ] = true;

			$clean[] = array(
				'id'     => $id,
				'label'  => $label,
				'hex'    => $hex,
				'family' => isset( $row['family'] ) ? sanitize_key( (string) $row['family'] ) : 'neutral',
			);
		}

		return ! empty( $clean ) ? $clean : $defaults;
	}

	/**
	 * Нормализовать старые профили результата.
	 */
	public static function normalize_profiles( $profiles, $defaults = array() ) {
		$defaults = ! empty( $defaults ) ? $defaults : self::legacy_profiles();
		$clean    = array();

		foreach ( (array) $profiles as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$id    = isset( $row['id'] ) ? sanitize_key( (string) $row['id'] ) : '';
			$title = isset( $row['title'] ) ? trim( wp_strip_all_tags( (string) $row['title'] ) ) : '';

			if ( '' === $id || '' === $title ) {
				continue;
			}

			$clean[] = array(
				'id'          => $id,
				'families'    => array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['families'] ?? array() ) ) ) ),
				'title'       => $title,
				'tagline'     => isset( $row['tagline'] ) ? sanitize_text_field( (string) $row['tagline'] ) : '',
				'description' => isset( $row['description'] ) ? sanitize_text_field( (string) $row['description'] ) : '',
				'colors'      => array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['colors'] ?? array() ) ) ) ),
			);
		}

		return ! empty( $clean ) ? $clean : $defaults;
	}

	/**
	 * Нормализовать сценарии результата: системные условия сохраняем из дефолта, тексты берём из настроек.
	 */
	public static function normalize_result_rules( $rules, $defaults = array() ) {
		$defaults = ! empty( $defaults ) ? $defaults : self::default_result_rules();
		$by_id    = array();

		foreach ( (array) $rules as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$by_id[ sanitize_key( (string) $row['id'] ) ] = $row;
			}
		}

		$clean = array();

		foreach ( $defaults as $default ) {
			$id    = sanitize_key( $default['id'] );
			$row   = isset( $by_id[ $id ] ) ? $by_id[ $id ] : array();
			$title = isset( $row['title'] ) ? trim( wp_strip_all_tags( (string) $row['title'] ) ) : '';

			$clean[] = array(
				'id'          => $id,
				'match'       => sanitize_key( $default['match'] ),
				'label'       => sanitize_text_field( $default['label'] ),
				'families'    => isset( $default['families'] ) ? array_values( array_filter( array_map( 'sanitize_key', (array) $default['families'] ) ) ) : array(),
				'title'       => '' !== $title ? $title : sanitize_text_field( $default['title'] ),
				'tagline'     => isset( $row['tagline'] ) && '' !== trim( (string) $row['tagline'] ) ? sanitize_text_field( (string) $row['tagline'] ) : sanitize_text_field( $default['tagline'] ),
				'description' => isset( $row['description'] ) && '' !== trim( (string) $row['description'] ) ? sanitize_text_field( (string) $row['description'] ) : sanitize_text_field( $default['description'] ),
			);
		}

		return $clean;
	}

	public static function calculate_result( $answers, $quiz_data ) {
		$values        = isset( $quiz_data['values'] ) ? (array) $quiz_data['values'] : array();
		$colors        = isset( $quiz_data['colors'] ) ? (array) $quiz_data['colors'] : array();
		$result_rules  = isset( $quiz_data['result_rules'] ) ? self::normalize_result_rules( $quiz_data['result_rules'] ) : self::default_result_rules();
		$color_map     = array();
		$family_labels = self::color_family_labels();

		foreach ( $colors as $color ) {
			if ( is_array( $color ) && ! empty( $color['id'] ) ) {
				$color_map[ sanitize_key( $color['id'] ) ] = $color;
			}
		}

		$selected_colors = array();
		$families        = array();
		$color_counts    = array();
		$family_counts   = array();
		$first_seen      = array( 'colors' => array(), 'families' => array() );

		foreach ( $values as $value_index => $value ) {
			$value_id = is_array( $value ) && isset( $value['id'] ) ? sanitize_key( $value['id'] ) : '';

			if ( '' === $value_id || ! isset( $answers[ $value_id ] ) ) {
				continue;
			}

			$color_id = sanitize_key( (string) $answers[ $value_id ] );

			if ( ! isset( $color_map[ $color_id ] ) ) {
				continue;
			}

			$family = isset( $color_map[ $color_id ]['family'] ) ? sanitize_key( $color_map[ $color_id ]['family'] ) : 'neutral';

			$selected_colors[] = $color_id;
			$families[]        = $family;

			if ( ! isset( $color_counts[ $color_id ] ) ) {
				$color_counts[ $color_id ]       = 0;
				$first_seen['colors'][ $color_id ] = $value_index;
			}

			if ( ! isset( $family_counts[ $family ] ) ) {
				$family_counts[ $family ]       = 0;
				$first_seen['families'][ $family ] = $value_index;
			}

			$color_counts[ $color_id ]++;
			$family_counts[ $family ]++;
		}

		$total = count( $selected_colors );

		if ( 0 === $total ) {
			$rule = self::find_result_rule( 'diverse_mosaic', $result_rules );
			return self::build_result_payload( $rule, array(), array(), array(), array(), $color_map, $family_labels );
		}

		$ranked_colors   = self::rank_counts( $color_counts, $first_seen['colors'] );
		$ranked_families = self::rank_counts( $family_counts, $first_seen['families'] );
		$top_color       = $ranked_colors[0]['id'];
		$top_color_count = (int) $ranked_colors[0]['count'];
		$top_family      = $ranked_families[0]['id'];
		$top_family_count= (int) $ranked_families[0]['count'];
		$unique_colors   = count( $ranked_colors );
		$unique_families = count( $ranked_families );

		$rule_id = 'diverse_mosaic';

		if ( $top_color_count === $total ) {
			$rule_id = 'one_color_all';
		} elseif ( $top_family_count === $total ) {
			$rule_id = 'one_family_all';
		} elseif ( 2 === $unique_colors && self::all_counts_equal( $ranked_colors ) ) {
			$rule_id = 'two_color_tie';
		} elseif ( 3 === $unique_colors && self::all_counts_equal( $ranked_colors ) ) {
			$rule_id = 'three_color_tie';
		} elseif ( $top_color_count > ( $total / 2 ) ) {
			$rule_id = 'dominant_color';
		} else {
			$cluster_rule = self::match_family_cluster_rule( $result_rules, $family_counts, $total );

			if ( $cluster_rule ) {
				$rule_id = $cluster_rule['id'];
			} elseif ( 2 === $unique_families && self::all_counts_equal( $ranked_families ) ) {
				$rule_id = 'two_family_tie';
			} elseif ( 3 === $unique_families && self::all_counts_equal( $ranked_families ) ) {
				$rule_id = 'three_family_tie';
			} elseif ( $top_color_count > 1 ) {
				$rule_id = 'paired_mosaic';
			} elseif ( $unique_colors === $total ) {
				$rule_id = 'all_unique';
			}
		}

		$rule = self::find_result_rule( $rule_id, $result_rules );

		return self::build_result_payload( $rule, $selected_colors, $ranked_colors, $ranked_families, $family_counts, $color_map, $family_labels );
	}

	protected static function rank_counts( $counts, $first_seen ) {
		$ranked = array();

		foreach ( $counts as $id => $count ) {
			$ranked[] = array(
				'id'    => $id,
				'count' => (int) $count,
				'first' => isset( $first_seen[ $id ] ) ? (int) $first_seen[ $id ] : 999999,
			);
		}

		usort(
			$ranked,
			function( $a, $b ) {
				if ( $a['count'] === $b['count'] ) {
					return $a['first'] <=> $b['first'];
				}

				return $b['count'] <=> $a['count'];
			}
		);

		return $ranked;
	}

	protected static function all_counts_equal( $ranked ) {
		if ( empty( $ranked ) ) {
			return false;
		}

		$first = (int) $ranked[0]['count'];

		foreach ( $ranked as $row ) {
			if ( (int) $row['count'] !== $first ) {
				return false;
			}
		}

		return true;
	}

	protected static function match_family_cluster_rule( $rules, $family_counts, $total ) {
		$best       = null;
		$best_score = 0;
		$threshold  = max( 1, (int) floor( $total / 2 ) + 1 );

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || 'family_cluster' !== ( $rule['match'] ?? '' ) ) {
				continue;
			}

			$score = 0;

			foreach ( (array) ( $rule['families'] ?? array() ) as $family ) {
				$family = sanitize_key( $family );
				$score += isset( $family_counts[ $family ] ) ? (int) $family_counts[ $family ] : 0;
			}

			if ( $score >= $threshold && $score > $best_score ) {
				$best       = $rule;
				$best_score = $score;
			}
		}

		return $best;
	}

	protected static function find_result_rule( $id, $rules ) {
		foreach ( $rules as $rule ) {
			if ( is_array( $rule ) && isset( $rule['id'] ) && $id === $rule['id'] ) {
				return $rule;
			}
		}

		$defaults = self::default_result_rules();

		foreach ( $defaults as $rule ) {
			if ( $id === $rule['id'] ) {
				return $rule;
			}
		}

		return end( $defaults );
	}

	protected static function build_result_payload( $rule, $selected_colors, $ranked_colors, $ranked_families, $family_counts, $color_map, $family_labels ) {
		$context = array(
			'top_color'       => self::color_label_from_rank( $ranked_colors, 0, $color_map ),
			'second_color'    => self::color_label_from_rank( $ranked_colors, 1, $color_map ),
			'top_family'      => self::family_label_from_rank( $ranked_families, 0, $family_labels ),
			'second_family'   => self::family_label_from_rank( $ranked_families, 1, $family_labels ),
			'selected_count'  => (string) count( $selected_colors ),
			'unique_colors'   => (string) count( $ranked_colors ),
			'unique_families' => (string) count( $ranked_families ),
		);

		return array(
			'id'              => isset( $rule['id'] ) ? sanitize_key( $rule['id'] ) : 'diverse_mosaic',
			'title'           => self::replace_result_tokens( isset( $rule['title'] ) ? $rule['title'] : '', $context ),
			'tagline'         => self::replace_result_tokens( isset( $rule['tagline'] ) ? $rule['tagline'] : '', $context ),
			'description'     => self::replace_result_tokens( isset( $rule['description'] ) ? $rule['description'] : '', $context ),
			'selected_colors' => array_values( $selected_colors ),
			'context'         => $context,
		);
	}

	protected static function color_label_from_rank( $ranked, $index, $color_map ) {
		if ( ! isset( $ranked[ $index ] ) ) {
			return '';
		}

		$id = $ranked[ $index ]['id'];

		if ( isset( $color_map[ $id ]['label'] ) ) {
			return trim( wp_strip_all_tags( str_replace( '<br>', ' ', (string) $color_map[ $id ]['label'] ) ) );
		}

		return $id;
	}

	protected static function family_label_from_rank( $ranked, $index, $family_labels ) {
		if ( ! isset( $ranked[ $index ] ) ) {
			return '';
		}

		$id = $ranked[ $index ]['id'];

		return isset( $family_labels[ $id ] ) ? $family_labels[ $id ] : $id;
	}

	protected static function replace_result_tokens( $text, $context ) {
		$replace = array();

		foreach ( $context as $key => $value ) {
			$replace[ '{' . $key . '}' ] = $value;
		}

		return strtr( (string) $text, $replace );
	}

	public function update( $data ) {
		$current = $this->get_all();
		$updated = array(
			'plugin_enabled'         => $this->sanitize_checkbox( $data['plugin_enabled'] ?? '' ),
			'logs_enabled'           => $this->sanitize_checkbox( $data['logs_enabled'] ?? '' ),
			'quiz_page_id'           => absint( $data['quiz_page_id'] ?? $current['quiz_page_id'] ),
			'one_attempt_per_email'  => $this->sanitize_checkbox( $data['one_attempt_per_email'] ?? '' ),
			'points_amount'          => $this->sanitize_points( $data['points_amount'] ?? $current['points_amount'] ),
			'points_award_mode'      => $this->sanitize_award_mode( $data['points_award_mode'] ?? $current['points_award_mode'] ),
			'points_order_status'    => $this->sanitize_order_status( $data['points_order_status'] ?? $current['points_order_status'] ),
			'points_description'     => sanitize_text_field( $data['points_description'] ?? $current['points_description'] ),
			'popup_enabled'          => $this->sanitize_checkbox( $data['popup_enabled'] ?? '' ),
			'popup_trigger'          => $this->sanitize_trigger( $data['popup_trigger'] ?? $current['popup_trigger'] ),
			'popup_login_title'      => sanitize_text_field( $data['popup_login_title'] ?? $current['popup_login_title'] ),
			'popup_register_title'   => sanitize_text_field( $data['popup_register_title'] ?? $current['popup_register_title'] ),
			'popup_subtitle'         => sanitize_text_field( $data['popup_subtitle'] ?? $current['popup_subtitle'] ),
			'user_agreement_url'     => $this->sanitize_url( $data['user_agreement_url'] ?? $current['user_agreement_url'], self::defaults()['user_agreement_url'] ),
			'loyalty_url'            => $this->sanitize_url( $data['loyalty_url'] ?? $current['loyalty_url'], self::defaults()['loyalty_url'] ),
			'marketing_consent_text' => sanitize_text_field( $data['marketing_consent_text'] ?? $current['marketing_consent_text'] ),
			'intro_headline'         => wp_kses_post( $data['intro_headline'] ?? $current['intro_headline'] ),
			'intro_sub'              => sanitize_text_field( $data['intro_sub'] ?? $current['intro_sub'] ),
			'intro_time_text'        => sanitize_text_field( $data['intro_time_text'] ?? $current['intro_time_text'] ),
			'reward_card_label'      => sanitize_text_field( $data['reward_card_label'] ?? $current['reward_card_label'] ),
			'reward_card_text'       => sanitize_text_field( $data['reward_card_text'] ?? $current['reward_card_text'] ),
			'gift_badge'             => sanitize_text_field( $data['gift_badge'] ?? $current['gift_badge'] ),
			'gift_title'             => sanitize_text_field( $data['gift_title'] ?? $current['gift_title'] ),
			'gift_desc'              => sanitize_text_field( $data['gift_desc'] ?? $current['gift_desc'] ),
			'gift_button'            => sanitize_text_field( $data['gift_button'] ?? $current['gift_button'] ),
			'success_title'          => sanitize_text_field( $data['success_title'] ?? $current['success_title'] ),
			'success_desc'           => sanitize_text_field( $data['success_desc'] ?? $current['success_desc'] ),
			'success_desc_awarded'   => sanitize_text_field( $data['success_desc_awarded'] ?? $current['success_desc_awarded'] ),
			'already_title'          => sanitize_text_field( $data['already_title'] ?? $current['already_title'] ),
			'already_desc'           => sanitize_text_field( $data['already_desc'] ?? $current['already_desc'] ),
			'already_replay_text'    => sanitize_text_field( $data['already_replay_text'] ?? $current['already_replay_text'] ),
			'claim_email_label'      => sanitize_text_field( $data['claim_email_label'] ?? $current['claim_email_label'] ),
			'claim_email_placeholder' => sanitize_text_field( $data['claim_email_placeholder'] ?? $current['claim_email_placeholder'] ),
			'claim_email_hint'       => sanitize_text_field( $data['claim_email_hint'] ?? $current['claim_email_hint'] ),
			'no_account_text'        => sanitize_text_field( $data['no_account_text'] ?? $current['no_account_text'] ),
			'no_account_button'      => sanitize_text_field( $data['no_account_button'] ?? $current['no_account_button'] ),
			'shop_button_text'       => sanitize_text_field( $data['shop_button_text'] ?? $current['shop_button_text'] ),
			'shop_button_url'        => $this->sanitize_url( $data['shop_button_url'] ?? $current['shop_button_url'], self::defaults()['shop_button_url'] ),
			'logo_url'               => esc_url_raw( (string) ( $data['logo_url'] ?? $current['logo_url'] ) ),
			'quiz_values_json'       => $this->sanitize_quiz_json( $data['quiz_values_json'] ?? $current['quiz_values_json'] ),
			'quiz_colors_json'       => $this->sanitize_quiz_json( $data['quiz_colors_json'] ?? $current['quiz_colors_json'] ),
			'quiz_profiles_json'     => $this->sanitize_quiz_json( $data['quiz_profiles_json'] ?? $current['quiz_profiles_json'] ),
			'quiz_result_rules_json' => $this->sanitize_quiz_json( $data['quiz_result_rules_json'] ?? $current['quiz_result_rules_json'] ),
		);

		update_option( $this->option_key, $updated );

		return $updated;
	}

	protected function sanitize_checkbox( $value ) {
		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( '1', 'yes', 'on', 'true' ), true ) ? 'yes' : 'no';
	}

	protected function sanitize_url( $value, $default ) {
		$value = esc_url_raw( trim( (string) $value ) );

		return '' === $value ? $default : $value;
	}

	protected function sanitize_points( $value ) {
		$value = (float) str_replace( ',', '.', (string) $value );

		if ( $value < 0 ) {
			$value = 0;
		}

		return (string) $value;
	}

	protected function sanitize_award_mode( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'immediate', 'after_first_order', 'none' ), true ) ? $value : 'after_first_order';
	}

	protected function sanitize_order_status( $value ) {
		$value = sanitize_key( str_replace( 'wc-', '', (string) $value ) );

		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$allowed = array_map(
				function( $status ) {
					return str_replace( 'wc-', '', $status );
				},
				array_keys( wc_get_order_statuses() )
			);

			if ( ! in_array( $value, $allowed, true ) ) {
				$value = 'completed';
			}
		}

		return $value ? $value : 'completed';
	}

	protected function sanitize_trigger( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = trim( $value );

		return '' === $value ? self::defaults()['popup_trigger'] : substr( $value, 0, 100 );
	}

	protected function sanitize_quiz_json( $value ) {
		$value = trim( (string) wp_unslash( $value ) );

		if ( '' === $value ) {
			return '';
		}

		$decoded = json_decode( $value, true );

		// Невалидный JSON не сохраняем — вернётся дефолт.
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		return wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE );
	}
}
