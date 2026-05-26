<?php
/**
 * Frontend: шорткод, таб на странице товара, AJAX.
 *
 * Шорткод: [ssc_calculator id="calc_xxx"]
 * Хук для таба: do_action( $calc['hook_name'] )
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Forbidden' );
}

class SSC_Frontend {

	/** @var SSC_Frontend|null */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp', array( $this, 'register_calculator_hooks' ) );

		// AJAX
		add_action( 'wp_ajax_ssc_load_products', array( $this, 'ajax_load_products' ) );
		add_action( 'wp_ajax_nopriv_ssc_load_products', array( $this, 'ajax_load_products' ) );
		add_action( 'wp_ajax_ssc_send_kp', array( $this, 'ajax_send_kp' ) );
		add_action( 'wp_ajax_nopriv_ssc_send_kp', array( $this, 'ajax_send_kp' ) );
	}

	/**
	 * Регистрируем шорткод.
	 */
	public function register_shortcode() {
		add_shortcode( 'ssc_calculator', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Регистрируем хуки для каждого калькулятора (для табов страницы товара).
	 */
	public function register_calculator_hooks() {
		if ( ! is_product() ) {
			return;
		}

		global $post;
		if ( ! $post ) {
			return;
		}

		$calculators = ssc_get_calculators();
		foreach ( $calculators as $calc ) {
			if ( empty( $calc['hook_name'] ) ) {
				continue;
			}
			// Регистрируем хук: при вызове передаём ID калькулятора и текущий товар
			add_action(
				sanitize_key( $calc['hook_name'] ),
				function () use ( $calc, $post ) {
					$this->render_product_calculator( $calc, $post->ID );
				}
			);
		}
	}

	/**
	 * Подключение стилей и скриптов.
	 */
	public function enqueue_assets() {
		// Подключаем только если на странице товара, категории или есть шорткод
		if ( ! is_product() && ! is_product_category() && ! $this->page_has_calculator_shortcode() ) {
			return;
		}

		// На странице категории нужны только стили карточки — скрипты и pdfmake не нужны
		$category_only = is_product_category() && ! is_product() && ! $this->page_has_calculator_shortcode();
		if ( $category_only ) {
			wp_enqueue_style( 'ssc-calculator', SSC_PLUGIN_URL . 'assets/css/calculator.css', array(), SSC_VERSION );
			return;
		}

		// pdfmake — подключаем из темы trava2 (если есть) или из плагина
		$pdfmake_src = SSC_PLUGIN_URL . 'assets/pdfmake/pdfmake.min.js';
		$vfs_src     = SSC_PLUGIN_URL . 'assets/pdfmake/vfs_fonts.js';

		// Fallback: попробуем найти в теме
		$theme_pdfmake = get_template_directory() . '/pdfmake/pdfmake.min.js';
		if ( file_exists( $theme_pdfmake ) ) {
			$pdfmake_src = get_template_directory_uri() . '/pdfmake/pdfmake.min.js';
			$vfs_src     = get_template_directory_uri() . '/pdfmake/vfs_fonts.js';
		}

		wp_enqueue_script( 'pdfmake', $pdfmake_src, array(), '0.2.10', true );
		wp_enqueue_script( 'pdfmake-vfs', $vfs_src, array( 'pdfmake' ), '0.2.10', true );

		// Magnific Popup — пробуем из темы, если нет — CDN
		$theme_mfp = get_template_directory_uri() . '/assets/js/jquery.magnific-popup.min.js';
		if ( @file_get_contents( get_template_directory() . '/assets/js/jquery.magnific-popup.min.js' ) ) {
			wp_enqueue_script( 'magnific-popup', $theme_mfp, array( 'jquery' ), null, true );
		} else {
			wp_enqueue_script( 'magnific-popup', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/jquery.magnific-popup.min.js', array( 'jquery' ), '1.1.0', true );
		}
		wp_enqueue_style( 'magnific-popup', 'https://cdnjs.cloudflare.com/ajax/libs/magnific-popup.js/1.1.0/magnific-popup.min.css', array(), '1.1.0' );

		wp_enqueue_style( 'ssc-calculator', SSC_PLUGIN_URL . 'assets/css/calculator.css', array(), SSC_VERSION );
		wp_enqueue_script(
			'ssc-calculator',
			SSC_PLUGIN_URL . 'assets/js/calculator.js',
			array( 'jquery', 'pdfmake-vfs' ),
			SSC_VERSION,
			true
		);

		wp_localize_script(
			'ssc-calculator',
			'sscCalc',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ssc_frontend_nonce' ),
				'siteUrl' => home_url(),
				'strings' => array(
					'selectProduct'    => __( 'Выберите товар', 'ssc' ),
					'selectWidth'      => __( 'Выберите ширину рулона', 'ssc' ),
					'enterDimensions'  => __( 'Укажите размеры площадки', 'ssc' ),
					'area'             => __( 'Площадь:', 'ssc' ),
					'rollCount'        => __( 'Количество рулонов:', 'ssc' ),
					'seamCount'        => __( 'Длина швов:', 'ssc' ),
					'glueCount'        => __( 'Клей:', 'ssc' ),
					'tapeCount'        => __( 'Шовная лента:', 'ssc' ),
					'sandCount'        => __( 'Кварцевый песок:', 'ssc' ),
					'rubberCount'      => __( 'Резиновая крошка:', 'ssc' ),
					'total'            => __( 'Итого:', 'ssc' ),
					'rubles'           => __( 'руб.', 'ssc' ),
					'sqm'              => __( 'м²', 'ssc' ),
					'units'            => __( 'шт', 'ssc' ),
					'meterLinear'      => __( 'м.п.', 'ssc' ),
					'kg'               => __( 'кг', 'ssc' ),
					'errorFillForm'    => __( 'Заполните форму для скачивания КП', 'ssc' ),
					'sending'          => __( 'Отправка...', 'ssc' ),
					'kpSent'           => __( 'Ваша заявка отправлена. КП загружается.', 'ssc' ),
					'noProductsFound'  => __( 'Товары не найдены', 'ssc' ),
					'loading'          => __( 'Загрузка...', 'ssc' ),
				),
			)
		);
	}

	/**
	 * Проверить, есть ли шорткод ssc_calculator на текущей странице.
	 */
	private function page_has_calculator_shortcode() {
		global $post;
		if ( ! $post ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'ssc_calculator' );
	}

	/**
	 * Шорткод [ssc_calculator id="calc_xxx"].
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'       => '',
				'category' => '',
			),
			$atts,
			'ssc_calculator'
		);

		// Найти калькулятор
		$calc = null;
		if ( ! empty( $atts['id'] ) ) {
			$calc = ssc_get_calculator( sanitize_key( $atts['id'] ) );
		} elseif ( ! empty( $atts['category'] ) ) {
			$calc = ssc_get_calculator_by_category( sanitize_title( $atts['category'] ) );
		} else {
			// Попробовать определить из текущего архива/категории
			if ( is_product_category() ) {
				$term = get_queried_object();
				$calc = ssc_get_calculator_by_category( $term->slug );
			}
		}

		if ( ! $calc ) {
			return '<p class="ssc-error">' . esc_html__( 'Калькулятор не найден или не настроен.', 'ssc' ) . '</p>';
		}

		ob_start();
		$this->render_category_calculator( $calc );
		return ob_get_clean();
	}

	/**
	 * Рендер калькулятора для страницы категории (шорткод).
	 * Показывает выбор подкатегории → фильтр атрибутов → список товаров → расчёт.
	 * Двухколоночная раскладка: слева — категория, фильтры, параметры рулона, разметка; справа — размеры, результаты.
	 *
	 * @param array $calc
	 */
	private function render_category_calculator( $calc ) {
		$calc_id     = esc_attr( $calc['id'] );
		$calc_json   = wp_json_encode( $this->get_calc_js_config( $calc, null ) );

		$is_linoleum = in_array( $calc['calculator_type'] ?? 'grass', array( 'linoleum', 'sceniclinoleum', 'simple' ), true );

		// Подкатегории из настроек калькулятора
		$subcategory_slugs = ! empty( $calc['subcategory_slugs'] ) ? $calc['subcategory_slugs'] : array();
		$subcategories = array();
		foreach ( $subcategory_slugs as $subcat_slug ) {
			$term = get_term_by( 'slug', $subcat_slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$subcategories[] = $term;
			}
		}

		$filter_html = ''; // Рендерим фильтры пустыми, загрузим через AJAX
		$has_sand     = ! empty( $calc['sand_enabled'] );
		$has_rubber   = ! empty( $calc['rubber_enabled'] );
		$has_markup   = ! empty( $calc['markup_enabled'] );
		$canvas_image = $this->make_absolute_url( $calc['canvas_image'] ?? '' );
		$length_value = 0;
		$width_values = array();
		$product_name = '';
		?>
		<div class="ssc-calculator ssc-calculator--category"
			data-calc-id="<?php echo $calc_id; ?>"
			data-config="<?php echo esc_attr( $calc_json ); ?>">

			<?php if ( ! $is_linoleum ) : ?>
			<!-- Шаг 0: Выбор подкатегории -->
			<div class="ssc-step ssc-step--subcategory">
				<h3 class="ssc-section-title"><?php esc_html_e( 'Выберите категорию', 'ssc' ); ?></h3>
				<div class="ssc-step__content">
					<div class="ssc-subcategory-wrap">
						<select class="ssc-subcategory-select" id="ssc-subcategory-<?php echo $calc_id; ?>">
							<option value=""><?php esc_html_e( '— Выберите категорию —', 'ssc' ); ?></option>
							<?php foreach ( $subcategories as $subcat ) : ?>
								<option value="<?php echo esc_attr( $subcat->slug ); ?>">
									<?php echo esc_html( $subcat->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Шаг 1: Фильтр атрибутов + кнопка выбора покрытия -->
			<div class="ssc-step ssc-step--filter" id="ssc-filter-step-<?php echo $calc_id; ?>">
				<h3 class="ssc-section-title" id="ssc-filter-title-<?php echo $calc_id; ?>"><?php esc_html_e( 'Параметры покрытия', 'ssc' ); ?></h3>
				<div class="ssc-step__placeholder" id="ssc-filter-placeholder-<?php echo $calc_id; ?>"<?php echo $is_linoleum ? ' style="display:none"' : ''; ?>>
					<p class="ssc-placeholder-text"><?php esc_html_e( 'Сначала выберите категорию', 'ssc' ); ?></p>
				</div>
				<div class="ssc-step__content" id="ssc-filter-content-<?php echo $calc_id; ?>"<?php echo $is_linoleum ? '' : ' style="display:none"'; ?>>
					<div class="ssc-filter-wrap" id="ssc-filter-wrap-<?php echo $calc_id; ?>">
						<?php echo $filter_html; ?>
					</div>

					<button type="button" class="ssc-select-product-btn" id="ssc-select-product-btn-<?php echo $calc_id; ?>" style="display:none">
						<span class="ssc-select-product-btn__label">
							<span class="ssc-select-product-btn__text"><?php esc_html_e( 'Выберите покрытие', 'ssc' ); ?></span>
							<span class="ssc-select-product-btn__count" id="ssc-product-count-<?php echo $calc_id; ?>">0</span>
						</span>
						<span class="ssc-select-product-btn__arrow"></span>
					</button>

					<!-- Список товаров (скрыт, показывается в popup) -->
					<div class="ssc-products-list" id="ssc-products-<?php echo $calc_id; ?>" style="display:none">
						<p class="ssc-products-loading"><?php esc_html_e( 'Выберите категорию для загрузки товаров', 'ssc' ); ?></p>
					</div>
				</div>
			</div>

			<?php if ( $calc['calculator_type'] !== 'simple' || ! empty( $calc['simple_rolls_enabled'] ) ) : ?>
			<!-- Параметры рулона -->
			<div class="ssc-roll-params-wrap ssc-step ssc-step--roll">
				<h3 class="ssc-section-title"><?php esc_html_e( 'Параметры рулона', 'ssc' ); ?></h3>
				<div class="ssc-step__content">
					<div class="ssc-roll-params">
						<div class="ssc-roll-row">
							<span class="ssc-roll-row__label" id="ssc-roll-label-<?php echo $calc_id; ?>" style="display:none"><?php esc_html_e( 'Ширина рулона:', 'ssc' ); ?></span>
							<div class="ssc-width-list" id="ssc-width-<?php echo $calc_id; ?>">
								<span class="ssc-placeholder"><?php esc_html_e( 'Сначала выберите покрытие', 'ssc' ); ?></span>
							</div>
						</div>
						<div class="ssc-roll-row ssc-length-field" style="display:none">
							<span class="ssc-roll-row__label" id="ssc-length-label-<?php echo $calc_id; ?>" style="display:none"><?php esc_html_e( 'Длина рулона:', 'ssc' ); ?></span>
							<span class="ssc-length-val" id="ssc-length-<?php echo $calc_id; ?>">—</span>
							<input type="hidden" id="ssc-length-input-<?php echo $calc_id; ?>" value="<?php echo esc_attr( $length_value ); ?>">
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Размеры площадки + canvas -->
			<div class="ssc-dimensions-wrap ssc-step ssc-step--dims">
				<h3 class="ssc-section-title"><?php esc_html_e( 'Размеры площадки', 'ssc' ); ?></h3>
				<div class="ssc-step__content">
					<div class="ssc-dims-and-canvas">
						<div class="ssc-dims-fields">
							<div class="ssc-field-group">
								<label><?php esc_html_e( 'Длина (м)', 'ssc' ); ?></label>
								<div class="ssc-counter">
									<button type="button" class="ssc-counter__btn ssc-counter__minus">−</button>
									<input type="number" class="ssc-counter__val ssc-area-length"
										name="ssc_area_length_<?php echo $calc_id; ?>" value="0" min="0" step="1">
									<button type="button" class="ssc-counter__btn ssc-counter__plus">+</button>
								</div>
							</div>
							<div class="ssc-dims-x">×</div>
							<div class="ssc-field-group">
								<label><?php esc_html_e( 'Ширина (м)', 'ssc' ); ?></label>
								<div class="ssc-counter">
									<button type="button" class="ssc-counter__btn ssc-counter__minus">−</button>
									<input type="number" class="ssc-counter__val ssc-area-width"
										name="ssc_area_width_<?php echo $calc_id; ?>" value="0" min="0" step="1">
									<button type="button" class="ssc-counter__btn ssc-counter__plus">+</button>
								</div>
							</div>
							<div class="ssc-dims-eq">
								= <span class="ssc-area-result">0</span> м²
							</div>
						</div>

						<div class="ssc-canvas-wrap">
							<?php if ( $canvas_image ) : ?>
								<img class="ssc-canvas-bg" src="<?php echo $canvas_image; ?>" alt="">
							<?php endif; ?>
							<canvas class="ssc-canvas" width="730" height="365"></canvas>
						</div>
					</div>
				</div>
			</div>

			<?php if ( $has_markup ) : ?>
			<!-- Тип разметки -->
			<div class="ssc-markup-type-wrap ssc-step ssc-step--markup">
				<h3 class="ssc-section-title"><?php esc_html_e( 'Разметка поля', 'ssc' ); ?></h3>
				<div class="ssc-step__content">
					<?php $ssc_img = home_url( '/wp-content/calculator/' ); ?>
				<div class="ssc-markup-type-list">
						<label class="ssc-radio-btn ssc-radio-btn--active">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="none" checked>
							<span class="ssc-markup-icon ssc-markup-icon--none">✕</span>
							<span><?php esc_html_e( 'Без разметки', 'ssc' ); ?></span>
						</label>
						<?php if ( $is_linoleum ) : ?>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="volleyball">
							<img src="<?php echo esc_url( $ssc_img . 'volleyball.png' ); ?>" class="ssc-markup-icon" alt="">
							<span><?php esc_html_e( 'Волейбол', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="basketball">
							<img src="<?php echo esc_url( $ssc_img . 'basketball.png' ); ?>" class="ssc-markup-icon" alt="">
							<span><?php esc_html_e( 'Баскетбол', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="mini-football">
							<img src="<?php echo esc_url( $ssc_img . 'mini-football.png' ); ?>" class="ssc-markup-icon" alt="">
							<span><?php esc_html_e( 'Мини-футбол', 'ssc' ); ?></span>
						</label>
						<?php else : ?>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="football">
							<img src="<?php echo esc_url( $ssc_img . 'football.png' ); ?>" class="ssc-markup-icon" alt="">
							<span><?php esc_html_e( 'Футбол', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="mini-football">
							<img src="<?php echo esc_url( $ssc_img . 'mini-football.png' ); ?>" class="ssc-markup-icon" alt="">
							<span><?php esc_html_e( 'Мини-футбол', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="tennis">
							<img src="<?php echo esc_url( $ssc_img . 'tennis.png' ); ?>" class="ssc-markup-icon" alt="">
							<span><?php esc_html_e( 'Теннис', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="hockey">
							<img src="<?php echo esc_url( $ssc_img . 'hockey.png' ); ?>" class="ssc-markup-icon" alt="">
							<span><?php esc_html_e( 'Хоккей', 'ssc' ); ?></span>
						</label>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- Итоги расчёта -->
			<div class="ssc-results-wrap ssc-step ssc-step--results">
				<h3 class="ssc-section-title"><?php esc_html_e( 'Результаты расчёта', 'ssc' ); ?></h3>
				<div class="ssc-step__content">
					<div class="ssc-results ssc-results--table" id="ssc-results-<?php echo $calc_id; ?>">
					<?php echo $this->render_result_rows( $calc, $calc_id ); ?>
					</div>

					<div class="ssc-kp-btn-wrap">
						<button type="button" class="ssc-kp-btn"><?php esc_html_e( 'Оформить КП (PDF)', 'ssc' ); ?></button>
						<button type="button" class="ssc-kp-test-btn" style="margin-top:8px;padding:6px 16px;background:#eee;color:#333;border:1px solid #ccc;border-radius:3px;font-size:12px;cursor:pointer;"><?php esc_html_e( 'Скачать PDF (тест)', 'ssc' ); ?></button>
						<div class="ssc-kp-error" style="display:none"><?php esc_html_e( 'Введите данные для расчёта', 'ssc' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Модальное окно КП -->
			<div class="ssc-modal" id="ssc-modal-<?php echo $calc_id; ?>" style="display:none">
				<div class="ssc-modal__overlay ssc-modal-close"></div>
				<div class="ssc-modal__box">
					<button type="button" class="ssc-modal__close ssc-modal-close">✕</button>
					<h3><?php esc_html_e( 'Получить коммерческое предложение', 'ssc' ); ?></h3>
					<p class="ssc-modal__desc"><?php esc_html_e( 'Заполните форму — мы отправим вам КП с подробным расчётом.', 'ssc' ); ?></p>

					<form class="ssc-kp-form">
						<input type="hidden" name="ssc_calc_id" value="<?php echo $calc_id; ?>">
						<input type="hidden" name="ssc_nonce" value="<?php echo wp_create_nonce( 'ssc_frontend_nonce' ); ?>">
						<input type="hidden" class="ssc-form-product-name" name="product_name" value="<?php echo esc_attr( $product_name ); ?>">
						<input type="hidden" class="ssc-form-area" name="area" value="">
						<input type="hidden" class="ssc-form-rolls" name="rolls" value="">
						<input type="hidden" class="ssc-form-seams" name="seams" value="">
						<input type="hidden" class="ssc-form-glue" name="glue" value="">
						<input type="hidden" class="ssc-form-tape" name="tape" value="">
						<input type="hidden" class="ssc-form-total" name="total" value="">
						<!-- UTM -->
						<input type="hidden" name="utm_source" value="<?php echo isset( $_COOKIE['utm_source'] ) ? esc_attr( $_COOKIE['utm_source'] ) : ''; ?>">
						<input type="hidden" name="utm_medium" value="<?php echo isset( $_COOKIE['utm_medium'] ) ? esc_attr( $_COOKIE['utm_medium'] ) : ''; ?>">
						<input type="hidden" name="utm_campaign" value="<?php echo isset( $_COOKIE['utm_campaign'] ) ? esc_attr( $_COOKIE['utm_campaign'] ) : ''; ?>">

						<div class="ssc-form-field">
							<input type="text" name="client_name" placeholder="<?php esc_attr_e( 'Ваше имя', 'ssc' ); ?>" required class="ssc-input">
						</div>
						<div class="ssc-form-field">
							<input type="tel" name="client_phone" placeholder="<?php esc_attr_e( 'Телефон', 'ssc' ); ?>" required class="ssc-input">
						</div>
						<div class="ssc-form-field">
							<input type="email" name="client_email" placeholder="<?php esc_attr_e( 'Email', 'ssc' ); ?>" required class="ssc-input">
						</div>

						<button type="submit" class="ssc-submit-btn"><?php esc_html_e( 'Скачать КП (PDF)', 'ssc' ); ?></button>
						<div class="ssc-form-success" style="display:none"><?php esc_html_e( 'Заявка отправлена! КП загружается.', 'ssc' ); ?></div>
						<p class="ssc-form-privacy">
							<?php
							printf(
								esc_html__( 'Отправляя данные, вы соглашаетесь с %sполитикой конфиденциальности%s', 'ssc' ),
								'<a href="' . esc_url( home_url( '/politika/' ) ) . '" target="_blank">',
								'</a>'
							);
							?>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Рендер калькулятора для страницы товара (таб).
	 *
	 * @param array $calc
	 * @param int   $product_id
	 */
	public function render_product_calculator( $calc, $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$calc_id   = esc_attr( $calc['id'] );
		$calc_json = wp_json_encode( $this->get_calc_js_config( $calc, $product ) );
		?>
		<div class="ssc-calculator ssc-calculator--product"
			data-calc-id="<?php echo $calc_id; ?>"
			data-config="<?php echo esc_attr( $calc_json ); ?>">

			<?php $this->render_calculator_core( $calc, $product ); ?>
		</div>
		<?php
	}

	/**
	 * Рендер основного блока калькулятора (размеры → canvas → итоги → форма КП).
	 * Используется как для страницы товара, так и для категории (без блока товаров).
	 *
	 * @param array            $calc
	 * @param WC_Product|null  $product
	 */
	private function render_calculator_core( $calc, $product ) {
		$calc_id      = esc_attr( $calc['id'] );
		$is_linoleum  = in_array( $calc['calculator_type'] ?? 'grass', array( 'linoleum', 'sceniclinoleum', 'simple' ), true );
		$has_sand     = ! $is_linoleum && ! empty( $calc['sand_enabled'] );
		$has_rubber   = ! $is_linoleum && ! empty( $calc['rubber_enabled'] );
		$has_markup   = ! empty( $calc['markup_enabled'] );
		$canvas_image = $this->make_absolute_url( $calc['canvas_image'] ?? '' );

		// Данные о ширине рулона из атрибутов товара (для страницы товара)
		$width_values  = array();
		$length_value  = 0;
		$product_price = 0;
		$product_name  = '';
		$product_attrs = array();

		if ( $product ) {
			$product_price = floatval( $product->get_price() );
			$product_name  = $product->get_name();

			// Ширина рулона
			$width_attr_key = 'pa_' . $calc['width_attr'];
			$widths_raw     = $product->get_attribute( $width_attr_key );
			if ( $widths_raw ) {
				$width_values = ssc_split_attr_values( $widths_raw );
			}

			// Длина рулона (поддерживает несколько значений через «, » или «|»)
			$length_attr_key  = 'pa_' . $calc['length_attr'];
			$lengths_raw      = $product->get_attribute( $length_attr_key );
			$length_values    = $lengths_raw ? ssc_split_attr_values( $lengths_raw ) : array();
			$length_value     = ! empty( $length_values ) ? ssc_attr_float( $length_values[0] ) : 0;

			// Все атрибуты для PDF
			foreach ( $product->get_attributes() as $attr ) {
				if ( $attr->is_taxonomy() ) {
					$tax_obj = get_taxonomy( $attr->get_name() );
					$label   = $tax_obj ? $tax_obj->labels->singular_name : $attr->get_name();
					$values  = wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
					if ( $values ) {
						$product_attrs[] = array(
							'label' => $label,
							'value' => implode( ', ', $values ),
						);
					}
				}
			}
		}
		?>
		<?php if ( $calc['calculator_type'] !== 'simple' || ! empty( $calc['simple_rolls_enabled'] ) ) : ?>
		<!-- Параметры рулона -->
		<div class="ssc-roll-params-wrap ssc-step ssc-step--roll <?php echo $product ? 'ssc-step--active' : ''; ?>">
			<h3 class="ssc-section-title"><?php esc_html_e( 'Параметры рулона', 'ssc' ); ?></h3>
			<div class="ssc-step__content">
				<div class="ssc-roll-params">
					<div class="ssc-roll-row">
						<span class="ssc-roll-row__label" id="ssc-roll-label-<?php echo $calc_id; ?>" style="<?php echo $product && $width_values ? '' : 'display:none'; ?>"><?php esc_html_e( 'Ширина рулона:', 'ssc' ); ?></span>
						<div class="ssc-width-list<?php echo $product && $width_values ? ' ssc-width-list--has-data' : ''; ?>" id="ssc-width-<?php echo $calc_id; ?>">
							<?php if ( $product && $width_values ) : ?>
								<?php foreach ( $width_values as $i => $w ) : ?>
									<label class="ssc-radio-btn <?php echo 0 === $i ? 'ssc-radio-btn--active' : ''; ?>">
										<input type="radio" name="ssc_width_<?php echo $calc_id; ?>"
											value="<?php echo esc_attr( ssc_attr_float( $w ) ); ?>"
											<?php checked( $i, 0 ); ?>>
										<?php echo esc_html( ssc_attr_display( $w ) ); ?> м
									</label>
								<?php endforeach; ?>
							<?php else : ?>
								<span class="ssc-placeholder"><?php esc_html_e( 'Сначала выберите покрытие', 'ssc' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div class="ssc-roll-row ssc-length-field" style="<?php echo $product && $length_value ? '' : 'display:none'; ?>">
						<span class="ssc-roll-row__label" id="ssc-length-label-<?php echo $calc_id; ?>" style="<?php echo $product && $length_value ? '' : 'display:none'; ?>"><?php esc_html_e( 'Длина рулона:', 'ssc' ); ?></span>
						<?php if ( $product && count( $length_values ) > 1 ) : ?>
							<div class="ssc-width-list" id="ssc-length-<?php echo $calc_id; ?>">
								<?php foreach ( $length_values as $i => $lv ) : ?>
									<label class="ssc-radio-btn <?php echo 0 === $i ? 'ssc-radio-btn--active' : ''; ?>">
										<input type="radio" name="ssc_length_<?php echo $calc_id; ?>"
											value="<?php echo esc_attr( ssc_attr_float( $lv ) ); ?>"
											<?php checked( $i, 0 ); ?>>
										<?php echo esc_html( ssc_attr_display( $lv ) ); ?> м
									</label>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<span class="ssc-length-val" id="ssc-length-<?php echo $calc_id; ?>">
								<?php echo $length_value ? esc_html( $length_value ) . ' м' : '—'; ?>
							</span>
						<?php endif; ?>
						<input type="hidden" id="ssc-length-input-<?php echo $calc_id; ?>"
							value="<?php echo esc_attr( $length_value ); ?>">
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Размеры площадки + canvas -->
		<div class="ssc-dimensions-wrap ssc-step ssc-step--dims <?php echo $product && $width_values ? 'ssc-step--active' : ''; ?>">
			<h3 class="ssc-section-title"><?php esc_html_e( 'Размеры площадки', 'ssc' ); ?></h3>
			<div class="ssc-step__content">
				<div class="ssc-dims-and-canvas">
					<div class="ssc-dims-fields">
						<div class="ssc-field-group">
							<label><?php esc_html_e( 'Длина (м)', 'ssc' ); ?></label>
							<div class="ssc-counter">
								<button type="button" class="ssc-counter__btn ssc-counter__minus">−</button>
								<input type="number" class="ssc-counter__val ssc-area-length"
									name="ssc_area_length_<?php echo $calc_id; ?>" value="0" min="0" step="1">
								<button type="button" class="ssc-counter__btn ssc-counter__plus">+</button>
							</div>
						</div>
						<div class="ssc-dims-x">×</div>
						<div class="ssc-field-group">
							<label><?php esc_html_e( 'Ширина (м)', 'ssc' ); ?></label>
							<div class="ssc-counter">
								<button type="button" class="ssc-counter__btn ssc-counter__minus">−</button>
								<input type="number" class="ssc-counter__val ssc-area-width"
									name="ssc_area_width_<?php echo $calc_id; ?>" value="0" min="0" step="1">
								<button type="button" class="ssc-counter__btn ssc-counter__plus">+</button>
							</div>
						</div>
						<div class="ssc-dims-eq">
							= <span class="ssc-area-result">0</span> м²
						</div>
					</div>

					<div class="ssc-canvas-wrap">
						<?php if ( $canvas_image ) : ?>
							<img class="ssc-canvas-bg" src="<?php echo $canvas_image; ?>" alt="">
						<?php endif; ?>
						<canvas class="ssc-canvas" width="730" height="365"></canvas>
					</div>
				</div>
			</div>
		</div>

		<?php if ( $has_markup ) : ?>
		<!-- Тип разметки -->
		<div class="ssc-markup-type-wrap ssc-step ssc-step--markup">
			<h3 class="ssc-section-title"><?php esc_html_e( 'Разметка поля', 'ssc' ); ?></h3>
			<div class="ssc-step__content">
				<?php $ssc_img = home_url( '/wp-content/calculator/' ); ?>
			<div class="ssc-markup-type-list">
					<label class="ssc-radio-btn ssc-radio-btn--active">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="none" checked>
						<span class="ssc-markup-icon ssc-markup-icon--none">✕</span>
						<?php esc_html_e( 'Без разметки', 'ssc' ); ?>
					</label>
					<?php if ( $is_linoleum ) : ?>
					<label class="ssc-radio-btn">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="volleyball">
						<img src="<?php echo esc_url( $ssc_img . 'volleyball.png' ); ?>" class="ssc-markup-icon" alt="">
						<?php esc_html_e( 'Волейбол', 'ssc' ); ?>
					</label>
					<label class="ssc-radio-btn">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="basketball">
						<img src="<?php echo esc_url( $ssc_img . 'basketball.png' ); ?>" class="ssc-markup-icon" alt="">
						<?php esc_html_e( 'Баскетбол', 'ssc' ); ?>
					</label>
					<label class="ssc-radio-btn">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="mini-football">
						<img src="<?php echo esc_url( $ssc_img . 'mini-football.png' ); ?>" class="ssc-markup-icon" alt="">
						<?php esc_html_e( 'Мини-футбол', 'ssc' ); ?>
					</label>
					<?php else : ?>
					<label class="ssc-radio-btn">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="football">
						<img src="<?php echo esc_url( $ssc_img . 'football.png' ); ?>" class="ssc-markup-icon" alt="">
						<?php esc_html_e( 'Футбол', 'ssc' ); ?>
					</label>
					<label class="ssc-radio-btn">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="mini-football">
						<img src="<?php echo esc_url( $ssc_img . 'mini-football.png' ); ?>" class="ssc-markup-icon" alt="">
						<?php esc_html_e( 'Мини-футбол', 'ssc' ); ?>
					</label>
					<label class="ssc-radio-btn">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="tennis">
						<img src="<?php echo esc_url( $ssc_img . 'tennis.png' ); ?>" class="ssc-markup-icon" alt="">
						<?php esc_html_e( 'Теннис', 'ssc' ); ?>
					</label>
					<label class="ssc-radio-btn">
						<input type="radio" name="ssc_markup_type_<?php echo $calc_id; ?>" value="hockey">
						<img src="<?php echo esc_url( $ssc_img . 'hockey.png' ); ?>" class="ssc-markup-icon" alt="">
						<?php esc_html_e( 'Хоккей', 'ssc' ); ?>
					</label>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Итоги расчёта -->
		<div class="ssc-results-wrap ssc-step ssc-step--results">
			<h3 class="ssc-section-title"><?php esc_html_e( 'Результаты расчёта', 'ssc' ); ?></h3>
			<div class="ssc-step__content">
				<div class="ssc-results ssc-results--table" id="ssc-results-<?php echo $calc_id; ?>">
					<?php echo $this->render_result_rows( $calc, $calc_id ); ?>
				</div>

				<div class="ssc-kp-btn-wrap">
					<button type="button" class="ssc-kp-btn"><?php esc_html_e( 'Оформить КП (PDF)', 'ssc' ); ?></button>
					<div class="ssc-kp-error" style="display:none"><?php esc_html_e( 'Введите данные для расчёта', 'ssc' ); ?></div>
				</div>
			</div>
		</div>

		<!-- Модальное окно КП -->
		<div class="ssc-modal" id="ssc-modal-<?php echo $calc_id; ?>" style="display:none">
			<div class="ssc-modal__overlay ssc-modal-close"></div>
			<div class="ssc-modal__box">
				<button type="button" class="ssc-modal__close ssc-modal-close">✕</button>
				<h3><?php esc_html_e( 'Получить коммерческое предложение', 'ssc' ); ?></h3>
				<p class="ssc-modal__desc"><?php esc_html_e( 'Заполните форму — мы отправим вам КП с подробным расчётом.', 'ssc' ); ?></p>

				<form class="ssc-kp-form">
					<input type="hidden" name="ssc_calc_id" value="<?php echo $calc_id; ?>">
					<input type="hidden" name="ssc_nonce" value="<?php echo wp_create_nonce( 'ssc_frontend_nonce' ); ?>">
					<!-- Скрытые поля с данными расчёта (заполняются через JS) -->
					<input type="hidden" class="ssc-form-product-name" name="product_name" value="<?php echo esc_attr( $product_name ); ?>">
					<input type="hidden" class="ssc-form-area" name="area" value="">
					<input type="hidden" class="ssc-form-rolls" name="rolls" value="">
					<input type="hidden" class="ssc-form-seams" name="seams" value="">
					<input type="hidden" class="ssc-form-glue" name="glue" value="">
					<input type="hidden" class="ssc-form-tape" name="tape" value="">
					<input type="hidden" class="ssc-form-total" name="total" value="">
					<!-- UTM -->
					<input type="hidden" name="utm_source" value="<?php echo isset( $_COOKIE['utm_source'] ) ? esc_attr( $_COOKIE['utm_source'] ) : ''; ?>">
					<input type="hidden" name="utm_medium" value="<?php echo isset( $_COOKIE['utm_medium'] ) ? esc_attr( $_COOKIE['utm_medium'] ) : ''; ?>">
					<input type="hidden" name="utm_campaign" value="<?php echo isset( $_COOKIE['utm_campaign'] ) ? esc_attr( $_COOKIE['utm_campaign'] ) : ''; ?>">

					<div class="ssc-form-field">
						<input type="text" name="client_name" placeholder="<?php esc_attr_e( 'Ваше имя', 'ssc' ); ?>" required class="ssc-input">
					</div>
					<div class="ssc-form-field">
						<input type="tel" name="client_phone" placeholder="<?php esc_attr_e( 'Телефон', 'ssc' ); ?>" required class="ssc-input">
					</div>
					<div class="ssc-form-field">
						<input type="email" name="client_email" placeholder="<?php esc_attr_e( 'Email', 'ssc' ); ?>" required class="ssc-input">
					</div>

					<button type="submit" class="ssc-submit-btn"><?php esc_html_e( 'Скачать КП (PDF)', 'ssc' ); ?></button>
					<div class="ssc-form-success" style="display:none"><?php esc_html_e( 'Заявка отправлена! КП загружается.', 'ssc' ); ?></div>
					<p class="ssc-form-privacy">
						<?php
						printf(
							esc_html__( 'Отправляя данные, вы соглашаетесь с %sполитикой конфиденциальности%s', 'ssc' ),
							'<a href="' . esc_url( home_url( '/politika/' ) ) . '" target="_blank">',
							'</a>'
						);
						?>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Рендер строк результатов расчёта (общий для category и product).
	 * Ветвится по calculator_type: grass (газон) | linoleum (спорт. линолеум) | sceniclinoleum (сцен. линолеум).
	 *
	 * @param array  $calc
	 * @param string $calc_id
	 * @return string HTML
	 */
	private function render_result_rows( $calc, $calc_id ) {
		$type        = $calc['calculator_type'] ?? 'grass';
		$is_linoleum = $type === 'linoleum';
		$is_scenic   = $type === 'sceniclinoleum';
		$is_simple   = $type === 'simple';
		$has_sand    = ! $is_linoleum && ! $is_scenic && ! $is_simple && ! empty( $calc['sand_enabled'] );
		$has_rubber  = ! $is_linoleum && ! $is_scenic && ! $is_simple && ! empty( $calc['rubber_enabled'] );
		$has_markup  = ! $is_linoleum && ! $is_scenic && ! $is_simple && ! empty( $calc['markup_enabled'] );
		ob_start();
		?>
		<?php if ( ! $is_simple || ! empty( $calc['simple_rolls_enabled'] ) ) : ?>
		<div class="ssc-result-row ssc-result-row--info">
			<span class="ssc-result-label"><?php esc_html_e( 'Рулонов в раскладке:', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-rolls">0 шт</span></span>
			<span class="ssc-result-price"></span>
		</div>
		<div class="ssc-result-row ssc-result-row--info">
			<span class="ssc-result-label"><?php esc_html_e( 'Длина швов:', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-seams">0 м.п.</span></span>
			<span class="ssc-result-price"></span>
		</div>
		<div class="ssc-result-row ssc-result-row--info ssc-res-row-leftover" style="display:none">
			<span class="ssc-result-label"><?php esc_html_e( 'Остаток рулона:', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-leftover"></span></span>
			<span class="ssc-result-price"></span>
		</div>
		<?php endif; ?>

		<!-- Площадь -->
		<div class="ssc-result-row ssc-result-row--cost">
			<span class="ssc-result-label"><?php esc_html_e( 'Площадь:', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-area">0 м²</span></span>
			<span class="ssc-result-price ssc-res-grass-cost">0 руб.</span>
		</div>

		<?php if ( $is_simple ) : ?>

		<?php if ( ! empty( $calc['simple_glue_enabled'] ) ) : ?>
		<!-- Клей (приклейка) -->
		<div class="ssc-result-row ssc-result-row--cost">
			<span class="ssc-result-label"><?php esc_html_e( 'Клей (приклейка):', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-glue-base-val"></span></span>
			<span class="ssc-result-price ssc-res-glue-base-cost"></span>
		</div>
		<?php endif; ?>

		<?php elseif ( $is_scenic ) : ?>

		<?php if ( ! empty( $calc['markup_enabled'] ) ) : ?>
		<!-- Краска для разметки (только если выбран тип разметки) -->
		<div class="ssc-result-row ssc-result-row--cost ssc-res-row-paint" style="display:none">
			<span class="ssc-result-label"><?php esc_html_e( 'Краска для разметки:', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-paint-val">0 банок</span></span>
			<span class="ssc-result-price ssc-res-paint-cost">0 руб.</span>
		</div>
		<?php endif; ?>

		<!-- Основание — радио-кнопки + строка результата -->
		<div class="ssc-scenic-group">
			<div class="ssc-result-row ssc-result-row--scenic-radios">
				<span class="ssc-result-label"><?php esc_html_e( 'Основание:', 'ssc' ); ?></span>
				<span class="ssc-result-sub ssc-scenic-sub">
					<div class="ssc-markup-type-list">
						<label class="ssc-radio-btn ssc-radio-btn--active">
							<input type="radio" name="ssc_base_<?php echo $calc_id; ?>" value="none" checked>
							<span><?php esc_html_e( 'Без основания', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_base_<?php echo $calc_id; ?>" value="glue">
							<span><?php esc_html_e( 'Клей', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_base_<?php echo $calc_id; ?>" value="tape">
							<span><?php esc_html_e( 'Скотч', 'ssc' ); ?></span>
						</label>
					</div>
				</span>
			</div>
			<div class="ssc-result-row ssc-result-row--scenic-result" style="display:none">
				<span class="ssc-result-label"></span>
				<span class="ssc-result-sub ssc-scenic-sub">
					<span class="ssc-result-val ssc-res-base-val"></span>
				</span>
				<span class="ssc-result-price ssc-res-base-cost"></span>
			</div>
		</div>

		<!-- Склейка швов — радио-кнопки + строка результата -->
		<div class="ssc-scenic-group">
			<div class="ssc-result-row ssc-result-row--scenic-radios">
				<span class="ssc-result-label"><?php esc_html_e( 'Склейка швов:', 'ssc' ); ?></span>
				<span class="ssc-result-sub ssc-scenic-sub">
					<div class="ssc-markup-type-list">
						<label class="ssc-radio-btn ssc-radio-btn--active">
							<input type="radio" name="ssc_seam_<?php echo $calc_id; ?>" value="none" checked>
							<span><?php esc_html_e( 'Без склейки', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_seam_<?php echo $calc_id; ?>" value="cord">
							<span><?php esc_html_e( 'Шнур', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_seam_<?php echo $calc_id; ?>" value="tape">
							<span><?php esc_html_e( 'Скотч', 'ssc' ); ?></span>
						</label>
						<label class="ssc-radio-btn">
							<input type="radio" name="ssc_seam_<?php echo $calc_id; ?>" value="cold_weld">
							<span><?php esc_html_e( 'Холодная сварка', 'ssc' ); ?></span>
						</label>
					</div>
				</span>
			</div>
			<div class="ssc-result-row ssc-result-row--scenic-result" style="display:none">
				<span class="ssc-result-label"></span>
				<span class="ssc-result-sub ssc-scenic-sub">
					<span class="ssc-result-val ssc-res-seam-val"></span>
				</span>
				<span class="ssc-result-price ssc-res-seam-cost"></span>
			</div>
		</div>

		<?php elseif ( $is_linoleum ) : ?>

		<?php if ( ! empty( $calc['markup_enabled'] ) ) : ?>
		<!-- Краска для разметки (только если выбран тип разметки) -->
		<div class="ssc-result-row ssc-result-row--cost ssc-res-row-paint" style="display:none">
			<span class="ssc-result-label"><?php esc_html_e( 'Краска для разметки:', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-paint-val">0 банок</span></span>
			<span class="ssc-result-price ssc-res-paint-cost">0 руб.</span>
		</div>
		<?php endif; ?>

		<!-- Клей (0.35 кг/м²) -->
		<div class="ssc-result-row ssc-result-row--extra ssc-result-row--cost ssc-res-glue-base">
			<span class="ssc-result-label">
				<label class="ssc-checkbox-label">
					<input type="checkbox" class="ssc-glue-check">
					<?php esc_html_e( 'Клей', 'ssc' ); ?>
				</label>
			</span>
			<span class="ssc-result-sub">
				<span class="ssc-result-val ssc-res-glue-base-val"></span>
			</span>
			<span class="ssc-result-price ssc-res-glue-base-cost"></span>
		</div>

		<!-- Сварочный шнур (швы × 1.10) -->
		<div class="ssc-result-row ssc-result-row--extra ssc-result-row--cost ssc-res-tape-base">
			<span class="ssc-result-label">
				<label class="ssc-checkbox-label">
					<input type="checkbox" class="ssc-tape-check">
					<?php esc_html_e( 'Сварочный шнур', 'ssc' ); ?>
				</label>
			</span>
			<span class="ssc-result-sub">
				<span class="ssc-result-val ssc-res-tape-base-val"></span>
			</span>
			<span class="ssc-result-price ssc-res-tape-base-cost"></span>
		</div>

		<?php else : ?>

		<!-- Разметка площадь (только если разметка выбрана) -->
		<div class="ssc-result-row ssc-result-row--cost ssc-res-row-markup-area" style="display:none">
			<span class="ssc-result-label"><?php esc_html_e( 'Разметка:', 'ssc' ); ?></span>
			<span class="ssc-result-sub"><span class="ssc-result-val ssc-res-markup-area-val">0 м²</span></span>
			<span class="ssc-result-price ssc-res-markup-cost">0 руб.</span>
		</div>

		<!-- Лента основные швы (с чекбоксом) -->
		<div class="ssc-result-row ssc-result-row--extra ssc-result-row--cost ssc-res-tape-base">
			<span class="ssc-result-label">
				<label class="ssc-checkbox-label">
					<input type="checkbox" class="ssc-tape-check">
					<?php esc_html_e( 'Шовная лента', 'ssc' ); ?>
				</label>
			</span>
			<span class="ssc-result-sub">
				<span class="ssc-result-label-sub ssc-tape-sub-label" style="display:none"><?php esc_html_e( 'Основные швы:', 'ssc' ); ?></span>
				<span class="ssc-result-val ssc-res-tape-base-val"></span>
			</span>
			<span class="ssc-result-price ssc-res-tape-base-cost"></span>
		</div>
		<!-- Лента разметка -->
		<div class="ssc-result-row ssc-result-row--cost ssc-res-tape-markup" style="display:none">
			<span class="ssc-result-label"></span>
			<span class="ssc-result-sub">
				<span class="ssc-result-label-sub"><?php esc_html_e( 'Разметка:', 'ssc' ); ?></span>
				<span class="ssc-result-val ssc-res-tape-markup-val">0 м.п. +5% подрез</span>
			</span>
			<span class="ssc-result-price ssc-res-tape-markup-cost">0 руб.</span>
		</div>

		<!-- Клей основные швы (с чекбоксом) -->
		<div class="ssc-result-row ssc-result-row--extra ssc-result-row--cost ssc-res-glue-base">
			<span class="ssc-result-label">
				<label class="ssc-checkbox-label">
					<input type="checkbox" class="ssc-glue-check">
					<?php esc_html_e( 'Клей', 'ssc' ); ?>
				</label>
			</span>
			<span class="ssc-result-sub">
				<span class="ssc-result-label-sub ssc-glue-sub-label" style="display:none"><?php esc_html_e( 'Основные швы:', 'ssc' ); ?></span>
				<span class="ssc-result-val ssc-res-glue-base-val"></span>
			</span>
			<span class="ssc-result-price ssc-res-glue-base-cost"></span>
		</div>
		<!-- Клей разметка -->
		<div class="ssc-result-row ssc-result-row--cost ssc-res-glue-markup" style="display:none">
			<span class="ssc-result-label"></span>
			<span class="ssc-result-sub">
				<span class="ssc-result-label-sub"><?php esc_html_e( 'Разметка:', 'ssc' ); ?></span>
				<span class="ssc-result-val ssc-res-glue-markup-val">0 банок (0 кг)</span>
			</span>
			<span class="ssc-result-price ssc-res-glue-markup-cost">0 руб.</span>
		</div>

		<?php if ( $has_sand ) : ?>
		<div class="ssc-result-row ssc-result-row--extra ssc-result-row--cost ssc-res-row-sand">
			<span class="ssc-result-label">
				<label class="ssc-checkbox-label">
					<input type="checkbox" class="ssc-sand-check">
					<?php esc_html_e( 'Кварцевый песок', 'ssc' ); ?>
				</label>
			</span>
			<span class="ssc-result-sub">
				<span class="ssc-result-val ssc-res-sand" style="display:none">0 кг</span>
			</span>
			<span class="ssc-result-price ssc-res-sand-cost" style="display:none">0 руб.</span>
		</div>
		<?php endif; ?>
		<?php if ( $has_rubber ) : ?>
		<div class="ssc-result-row ssc-result-row--extra ssc-result-row--cost ssc-res-row-rubber">
			<span class="ssc-result-label">
				<label class="ssc-checkbox-label">
					<input type="checkbox" class="ssc-rubber-check">
					<?php esc_html_e( 'Резиновая крошка', 'ssc' ); ?>
				</label>
			</span>
			<span class="ssc-result-sub">
				<span class="ssc-result-val ssc-res-rubber" style="display:none">0 кг</span>
			</span>
			<span class="ssc-result-price ssc-res-rubber-cost" style="display:none">0 руб.</span>
		</div>
		<?php endif; ?>

		<?php endif; // end linoleum/grass ?>

		<div class="ssc-total">
			<span><?php esc_html_e( 'Итого:', 'ssc' ); ?></span>
			<span class="ssc-res-total">0 руб.</span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Рендер блока фильтров атрибутов для страницы категории.
	 *
	 * @param array $calc
	 * @return string HTML
	 */
	private function render_filter_attrs( $calc ) {
		if ( empty( $calc['filter_attrs'] ) ) {
			return '';
		}

		$html = '';
		foreach ( $calc['filter_attrs'] as $attr_slug ) {
			$taxonomy = 'pa_' . $attr_slug;
			$terms    = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			$tax_obj = get_taxonomy( $taxonomy );
			$label   = $tax_obj ? $tax_obj->labels->singular_name : $attr_slug;

			$html .= '<div class="ssc-filter-attr" data-attr="' . esc_attr( $attr_slug ) . '">';
			$html .= '<span class="ssc-filter-attr__label">' . esc_html( $label ) . ':</span>';
			$html .= '<div class="ssc-filter-attr__values">';
			foreach ( $terms as $term ) {
				$html .= '<label class="ssc-filter-check">';
				$html .= '<input type="checkbox" name="ssc_filter_' . esc_attr( $attr_slug ) . '[]" value="' . esc_attr( $term->slug ) . '" class="ssc-filter-cb">';
				$html .= esc_html( $term->name );
				$html .= '</label>';
			}
			$html .= '</div></div>';
		}
		return $html;
	}

	/**
	 * Конфиг для JavaScript.
	 *
	 * @param array           $calc
	 * @param WC_Product|null $product
	 * @return array
	 */
	private function get_calc_js_config( $calc, $product ) {
		$config = array(
			'id'           => $calc['id'],
			'mode'         => $product ? 'product' : 'category',
			'calcType'     => $calc['calculator_type'] ?? 'grass',
			'categorySlug' => $calc['category_slug'],
			'subcategorySlugs' => $calc['subcategory_slugs'] ?? array(),
			'canvasImages' => $this->make_absolute_urls( $calc['canvas_images'] ?? array() ),
			'canvasImage'  => $this->make_absolute_url( $calc['canvas_image'] ?? '' ),
			'widthAttr'    => $calc['width_attr'],
			'lengthAttr'   => $calc['length_attr'],
			'filterAttrs'  => $calc['filter_attrs'],
			'gluePrice'           => intval( $calc['glue_price'] ),
			'glueVolume'          => max( 1, intval( $calc['glue_volume'] ?? 10 ) ),
			'tapePrice'           => intval( $calc['tape_price'] ),
			'tapeVolume'          => max( 1, intval( $calc['tape_volume'] ?? 50 ) ),
			'scenicBaseTapePrice' => intval( $calc['scenic_base_tape_price'] ?? 500 ),
			'scenicBaseTapeVol'   => max( 1, intval( $calc['scenic_base_tape_volume'] ?? 50 ) ),
			'scenicSeamCordPrice' => intval( $calc['scenic_seam_cord_price'] ?? 500 ),
			'scenicSeamCordVol'   => max( 1, intval( $calc['scenic_seam_cord_volume'] ?? 50 ) ),
			'scenicSeamTapePrice' => intval( $calc['scenic_seam_tape_price'] ?? 500 ),
			'scenicSeamTapeVol'   => max( 1, intval( $calc['scenic_seam_tape_volume'] ?? 50 ) ),
			'scenicSeamWeldPrice'  => intval( $calc['scenic_seam_weld_price'] ?? 500 ),
			'scenicSeamWeldVol'    => max( 1, intval( $calc['scenic_seam_weld_volume'] ?? 10 ) ),
			'simpleRollsEnabled'   => ! empty( $calc['simple_rolls_enabled'] ),
			'simpleGlueEnabled'    => ! empty( $calc['simple_glue_enabled'] ),
			'simpleGlueRate'       => max( 0.01, floatval( $calc['simple_glue_rate'] ?? 0.35 ) ),
			'sandEnabled'  => ! empty( $calc['sand_enabled'] ),
			'sandPrice'    => intval( $calc['sand_price'] ?? 3950 ),
			'rubberEnabled'=> ! empty( $calc['rubber_enabled'] ),
			'rubberPrice'  => intval( $calc['rubber_price'] ?? 24500 ),
			'markupEnabled'=> ! empty( $calc['markup_enabled'] ),
			'markupPercent'=> floatval( $calc['markup_percent'] ),
			'paintPrice'   => intval( $calc['paint_price'] ?? 0 ),
			'companyName'  => $calc['company_name'] ?? get_bloginfo( 'name' ),
			'siteDate'     => date( 'd.m.Y' ),
		);

		if ( $product ) {
			// Ширина рулона
			$widths_raw   = $product->get_attribute( 'pa_' . $calc['width_attr'] );
			$width_values  = $widths_raw ? array_map( 'ssc_attr_float', ssc_split_attr_values( $widths_raw ) ) : array();
			$lengths_raw   = $product->get_attribute( 'pa_' . $calc['length_attr'] );
			$length_values = $lengths_raw ? array_map( 'ssc_attr_float', ssc_split_attr_values( $lengths_raw ) ) : array();
			$length_value  = ! empty( $length_values ) ? $length_values[0] : 0;
			$height_value = ssc_attr_float( $product->get_attribute( 'pa_vysota-vorsa' ) ); // если есть

			$config['product'] = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'price'         => floatval( $product->get_price() ),
				'widths'        => $width_values,
				'lengths'       => $length_values,
				'length'        => $length_value,
				'height'        => $height_value,
				'defaultWidth'  => ! empty( $width_values ) ? $width_values[0] : 0,
				'defaultLength' => $length_value,
			);
		}

		return $config;
	}

	/**
	 * AJAX: загрузить список товаров подкатегории с фильтрацией по атрибутам.
	 */
	public function ajax_load_products() {


		$calc_id         = sanitize_key( $_POST['calc_id'] ?? '' );
		$subcategory_slug = sanitize_text_field( $_POST['subcategory_slug'] ?? '' );
		$filters         = isset( $_POST['filters'] ) ? (array) $_POST['filters'] : array();

		$calc = ssc_get_calculator( $calc_id );
		if ( ! $calc ) {
			wp_send_json_error( 'Calculator not found' );
		}

		// Определяем категорию: если передана подкатегория — используем её, иначе — категорию калькулятора
		$category_slug = ! empty( $subcategory_slug ) ? $subcategory_slug : $calc['category_slug'];
		$term          = get_term_by( 'slug', $category_slug, 'product_cat' );
		if ( ! $term ) {
			wp_send_json_error( 'Category not found' );
		}

		// Базовый фильтр по категории
		$base_term_filter = array(
			'taxonomy' => 'product_cat',
			'field'    => 'term_id',
			'terms'    => $term->term_id,
		);

		// Атрибутные фильтры отдельно (нужны для conjunctive facets)
		$attr_filters_tax = array();
		foreach ( $filters as $attr_slug => $values ) {
			$attr_slug = sanitize_key( $attr_slug );
			$values    = array_map( 'sanitize_title', (array) $values );
			if ( ! empty( $values ) ) {
				$attr_filters_tax[ $attr_slug ] = array(
					'taxonomy' => 'pa_' . $attr_slug,
					'field'    => 'slug',
					'terms'    => $values,
					'operator' => 'IN',
				);
			}
		}

		// Основной tax_query: все фильтры (для отображения товаров)
		$tax_query             = array_values( $attr_filters_tax );
		$tax_query[]           = $base_term_filter;
		$tax_query['relation'] = 'AND';

		// Генерируем стабильный ключ кеша (сортируем фильтры)
		$filters_for_cache = $filters;
		ksort( $filters_for_cache );
		foreach ( $filters_for_cache as $k => $v ) {
			sort( $v );
		}
		$cache_key = md5( $category_slug . wp_json_encode( $filters_for_cache ) );

		// Проверяем кеш
		$cache = $this->get_cache();
		if ( isset( $cache['products'][ $cache_key ] ) ) {
			$result           = $cache['products'][ $cache_key ];
			$result['source'] = 'cache';
			wp_send_json_success( $result );
		}

		$products = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => $tax_query,
			)
		);

		if ( empty( $products ) ) {
			wp_send_json_success( array(
				'html'             => '<p class="ssc-no-products">' . esc_html__( 'Товары не найдены', 'ssc' ) . '</p>',
				'available_filters' => array(),
			) );
		}

		$html = '';
		foreach ( $products as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}

			// Ширина рулона
			$widths_raw   = $product->get_attribute( 'pa_' . $calc['width_attr'] );
			$width_parts  = $widths_raw ? ssc_split_attr_values( $widths_raw ) : array();
			$width_floats = array_map( 'ssc_attr_float', $width_parts );
			// Длина рулона
			$lengths_raw   = $product->get_attribute( 'pa_' . $calc['length_attr'] );
			$length_parts  = $lengths_raw ? ssc_split_attr_values( $lengths_raw ) : array();
			$length_floats = array_map( 'ssc_attr_float', $length_parts );
			$length_val    = ! empty( $length_floats ) ? $length_floats[0] : 0;
			$price         = floatval( $product->get_price() );
			// Высота ворса: числовое значение (floatval обрезает «мм»)
			$height_raw = $product->get_attribute( 'pa_vysota-vorsa' );
			$height_val = ssc_attr_float( $height_raw );

			$product_data = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'price'         => $price,
				'widths'        => $width_floats,
				'lengths'       => $length_floats,
				'length'        => $length_val,
				'height'        => $height_val,
				'defaultWidth'  => ! empty( $width_floats ) ? $width_floats[0] : 2,
				'defaultLength' => $length_val,
			);

			$img      = get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: wc_placeholder_img_src();

			// Показываем только атрибуты, выбранные как фильтры в настройках калькулятора
			// Пропускаем высоту ворса, выводим только значения (без названий)
			$filter_attr_slugs = ! empty( $calc['filter_attrs'] ) ? $calc['filter_attrs'] : array();
			$attrs_preview = '';
			foreach ( $filter_attr_slugs as $attr_slug ) {
				if ( $attr_slug === 'vysota-vorsa' ) {
					continue;
				}
				$taxonomy = 'pa_' . $attr_slug;
				$tax_obj  = get_taxonomy( $taxonomy );
				if ( ! $tax_obj ) {
					continue;
				}
				$vals = wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'names' ) );
				if ( $vals ) {
					$attrs_preview .= '<span class="ssc-prod-attr">' . esc_html( implode( ', ', $vals ) ) . '</span>';
				}
			}

			$html .= '<label class="ssc-product-card">';
			$html .= '<input type="radio" name="ssc_product_' . esc_attr( $calc['id'] ) . '" value="' . esc_attr( $product->get_id() ) . '"';
			$html .= ' data-product="' . esc_attr( wp_json_encode( $product_data ) ) . '">';
			$html .= '<img src="' . esc_url( $img ) . '" alt="" class="ssc-prod-img">';
			$html .= '<div class="ssc-prod-info">';
			$html .= '<span class="ssc-prod-name">' . esc_html( $product->get_name() ) . '</span>';
			$html .= '<div class="ssc-prod-attrs">' . $attrs_preview . '</div>';
			$html .= '<span class="ssc-prod-price">' . wc_price( $price ) . ' / м²</span>';
			$html .= '</div></label>';
		}

		// Собираем доступные значения фильтров из отфильтрованных товаров (захардкоженный порядок)
		if ( in_array( $calc['calculator_type'] ?? 'grass', array( 'linoleum', 'sceniclinoleum' ), true ) ) {
			$attr_definitions = array(
				'tolshhina'                   => 'pa_tolshhina',
				'tolshhina-zashhitnogo-sloya' => 'pa_tolshhina-zashhitnogo-sloya',
				'linejka'                     => 'pa_linejka',
			);
		} else {
			$attr_definitions = array(
				'vysota-vorsa'         => 'pa_vysota-vorsa',
				'tolshhina-volokna'    => 'pa_tolshhina-volokna',
				'kolichestvo-stezhkov' => 'pa_kolichestvo-stezhkov',
			);
		}

		// Conjunctive facets: для каждого атрибута считаем доступные значения
		// из товаров, отфильтрованных по ВСЕМ атрибутам КРОМЕ текущего.
		$available_filters = array();
		foreach ( $attr_definitions as $attr_slug => $taxonomy ) {
			$tq = array( 'relation' => 'AND', $base_term_filter );
			foreach ( $attr_filters_tax as $slug => $filter ) {
				if ( $slug !== $attr_slug ) {
					$tq[] = $filter;
				}
			}
			$ids = get_posts( array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => $tq,
			) );
			$vals = array();
			foreach ( $ids as $pid ) {
				$terms = wc_get_product_terms( $pid, $taxonomy, array( 'fields' => 'slugs' ) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $v ) {
						$vals[] = $v;
					}
				}
			}
			$available_filters[ $attr_slug ] = array_values( array_unique( $vals ) );
		}

		$result = array(
			'html'              => $html,
			'available_filters' => $available_filters,
			'source'            => 'db',
		);

		// Сохраняем в кеш
		$cache['products'][ $cache_key ] = $result;
		$this->save_cache( $cache );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: загрузить атрибуты выбранной подкатегории.
	 * Возвращает HTML фильтров + config для JS.
	 * Порядок атрибутов захардкожен: высота ворса → кол-во стежков → толщина волокна.
	 */
	public function ajax_load_category_attrs() {
		$calc_id          = sanitize_key( $_POST['calc_id'] ?? '' );
		$subcategory_slug = sanitize_text_field( $_POST['subcategory_slug'] ?? '' );

		$calc = ssc_get_calculator( $calc_id );
		if ( ! $calc ) {
			wp_send_json_error( 'Calculator not found' );
		}

		if ( empty( $subcategory_slug ) ) {
			wp_send_json_error( 'Subcategory slug is empty' );
		}

		$term = get_term_by( 'slug', $subcategory_slug, 'product_cat' );
		if ( ! $term ) {
			wp_send_json_error( 'Category not found' );
		}

		// Проверяем кеш
		$cache = $this->get_cache();
		$cache_key = 'attrs_' . $subcategory_slug;
		if ( isset( $cache['attrs'][ $cache_key ] ) ) {
			$result           = $cache['attrs'][ $cache_key ];
			$result['source'] = 'cache';
			wp_send_json_success( $result );
		}

		// Захардкоженные атрибуты в нужном порядке (зависят от типа калькулятора)
		if ( in_array( $calc['calculator_type'] ?? 'grass', array( 'linoleum', 'sceniclinoleum' ), true ) ) {
			$attr_definitions = array(
				'tolshhina'                   => 'pa_tolshhina',
				'tolshhina-zashhitnogo-sloya' => 'pa_tolshhina-zashhitnogo-sloya',
				'linejka'                     => 'pa_linejka',
			);
		} else {
			$attr_definitions = array(
				'vysota-vorsa'         => 'pa_vysota-vorsa',
				'tolshhina-volokna'    => 'pa_tolshhina-volokna',
				'kolichestvo-stezhkov' => 'pa_kolichestvo-stezhkov',
			);
		}

		// Получаем товары подкатегории
		$products = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
			)
		);

		if ( empty( $products ) ) {
			wp_send_json_success( array(
				'filter_html'  => '<p class="ssc-no-products">' . esc_html__( 'Товары не найдены', 'ssc' ) . '</p>',
				'filter_attrs' => array(),
			) );
		}

		// Собираем какие значения атрибутов реально используются у товаров подкатегории
		$used_term_ids = array(); // $used_term_ids[ $taxonomy ] = array( $term_id => true )
		foreach ( $products as $post ) {
			$product = wc_get_product( $post->ID );
			if ( ! $product ) {
				continue;
			}
			foreach ( $attr_definitions as $attr_slug => $taxonomy ) {
				$vals = wc_get_product_terms( $product->get_id(), $taxonomy, array( 'fields' => 'ids' ) );
				if ( ! is_wp_error( $vals ) ) {
					foreach ( $vals as $tid ) {
						$used_term_ids[ $taxonomy ][ $tid ] = true;
					}
				}
			}
		}

		// Рендерим HTML фильтров в захардкоженном порядке, сортировка как в админке (get_terms по name)
		$filter_html = '';
		$filter_attrs_list = array();
		foreach ( $attr_definitions as $attr_slug => $taxonomy ) {
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'term_order',
				'order'      => 'ASC',
			) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			// Оставляем только те значения, которые есть у товаров подкатегории
			$active_terms = array();
			foreach ( $terms as $t ) {
				if ( isset( $used_term_ids[ $taxonomy ][ $t->term_id ] ) ) {
					$active_terms[] = $t;
				}
			}

			// Сортируем численно (как в WC-админке: 2 мм < 3 мм < 10 мм)
			usort( $active_terms, function ( $a, $b ) {
				$na = floatval( $a->name );
				$nb = floatval( $b->name );
				if ( $na !== $nb ) {
					return $na <=> $nb;
				}
				return strcmp( $a->name, $b->name );
			} );

			if ( empty( $active_terms ) ) {
				continue;
			}

			$filter_attrs_list[] = $attr_slug;
			$tax_obj = get_taxonomy( $taxonomy );
			$label = $tax_obj ? $tax_obj->labels->singular_name : $attr_slug;
			$filter_html .= '<div class="ssc-filter-attr" data-attr="' . esc_attr( $attr_slug ) . '">';
			$filter_html .= '<span class="ssc-filter-attr__label">' . esc_html( $label ) . ':</span>';
			$filter_html .= '<div class="ssc-filter-attr__values">';
			foreach ( $active_terms as $t ) {
				$filter_html .= '<label class="ssc-filter-check">';
				$filter_html .= '<input type="checkbox" name="ssc_filter_' . esc_attr( $attr_slug ) . '[]" value="' . esc_attr( $t->slug ) . '" class="ssc-filter-cb">';
				$filter_html .= esc_html( $t->name );
				$filter_html .= '</label>';
			}
			$filter_html .= '</div></div>';
		}

		$result = array(
			'filter_html'  => $filter_html,
			'filter_attrs' => $filter_attrs_list,
			'source'       => 'db',
		);

		// Сохраняем в кеш
		$cache['attrs'][ $cache_key ] = $result;
		$this->save_cache( $cache );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: отправить заявку на КП (email + данные формы).
	 */
	public function ajax_send_kp() {
		$nonce = isset( $_POST['ssc_nonce'] ) ? sanitize_text_field( $_POST['ssc_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ssc_frontend_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		$calc_id = sanitize_key( $_POST['ssc_calc_id'] ?? '' );
		$calc    = ssc_get_calculator( $calc_id );

		$name   = sanitize_text_field( $_POST['client_name'] ?? '' );
		$phone  = sanitize_text_field( $_POST['client_phone'] ?? '' );
		$email  = sanitize_email( $_POST['client_email'] ?? '' );
		$area   = sanitize_text_field( $_POST['area'] ?? '' );
		$rolls  = sanitize_text_field( $_POST['rolls'] ?? '' );
		$total  = sanitize_text_field( $_POST['total'] ?? '' );
		$product_name = sanitize_text_field( $_POST['product_name'] ?? '' );

		$to      = $calc ? sanitize_email( $calc['admin_email'] ?? '' ) : '';
		$to      = $to ?: get_option( 'admin_email' );
		$subject = sprintf( __( 'Заявка на КП с сайта %s', 'ssc' ), get_bloginfo( 'name' ) );
		$message = sprintf(
			"Имя: %s\nТелефон: %s\nEmail: %s\nПродукт: %s\nПлощадь: %s\nРулонов: %s\nИтого: %s руб.",
			$name,
			$phone,
			$email,
			$product_name,
			$area,
			$rolls,
			$total
		);

		wp_mail( $to, $subject, $message );

		wp_send_json_success();
	}

	/**
	 * Преобразует относительный путь в абсолютный URL.
	 *
	 * @param string $url
	 * @return string
	 */
	private function make_absolute_url( $url ) {
		$url = trim( $url );
		if ( ! $url ) {
			return '';
		}
		// Если уже полный URL — возвращаем как есть
		if ( preg_match( '#^https?://#i', $url ) ) {
			return esc_url( $url );
		}
		// Относительный путь — добавляем home_url()
		return esc_url( home_url( $url ) );
	}

	/**
	 * Преобразует массив canvas_images в абсолютные URL.
	 * Поддерживает вложенную структуру: { slug: { markup_type: url } }
	 *
	 * @param array $images
	 * @return array
	 */
	private function make_absolute_urls( $images ) {
		$result = array();
		foreach ( $images as $slug => $inner ) {
			if ( is_array( $inner ) ) {
				$result[ $slug ] = array();
				foreach ( $inner as $markup => $url ) {
					$result[ $slug ][ $markup ] = $this->make_absolute_url( $url );
				}
			} else {
				// Обратная совместимость: старая плоская структура
				$result[ $slug ] = $this->make_absolute_url( $inner );
			}
		}
		return $result;
	}

	/**
	 * Кеширование данных калькулятора.
	 */
	private function get_cache() {
		$cache = get_transient( 'ssc_calc_cache' );
		return is_array( $cache ) ? $cache : array( 'attrs' => array(), 'products' => array() );
	}

	private function save_cache( $data ) {
		set_transient( 'ssc_calc_cache', $data, DAY_IN_SECONDS );
	}

	private function __clone() {}
	public function __wakeup() { throw new \Exception( 'Cannot unserialize singleton' ); }
}
