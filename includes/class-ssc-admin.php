<?php
/**
 * Административная страница плагина Sportshop Calculator.
 *
 * Список калькуляторов, создание, редактирование, удаление.
 * Страница: /wp-admin/admin.php?page=ssc-calculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Forbidden' );
}

class SSC_Admin {

	/** @var SSC_Admin|null */
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ssc_save_calculator', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_ssc_delete_calculator', array( $this, 'ajax_delete' ) );
		add_action( 'wp_ajax_ssc_get_calculator', array( $this, 'ajax_get' ) );
		add_action( 'wp_ajax_ssc_load_category_attrs', array( $this, 'ajax_load_category_attrs' ) );
		add_action( 'wp_ajax_ssc_clear_cache', array( $this, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_ssc_sync_calculator', array( $this, 'ajax_sync_calculator' ) );
		add_action( 'wp_ajax_ssc_sync_all_calculators', array( $this, 'ajax_sync_all_calculators' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'Калькуляторы', 'ssc' ),
			__( 'Калькуляторы', 'ssc' ),
			'manage_options',
			'ssc-calculators',
			array( $this, 'render_page' ),
			'dashicons-calculator',
			56
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_ssc-calculators' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'ssc-admin', SSC_PLUGIN_URL . 'assets/css/admin.css', array(), SSC_VERSION );
		wp_enqueue_script( 'ssc-admin', SSC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SSC_VERSION, true );
		wp_localize_script(
			'ssc-admin',
			'sscAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ssc_admin_nonce' ),
				'siteUrl' => home_url(),
				'strings' => array(
					'confirmDelete' => __( 'Удалить этот калькулятор?', 'ssc' ),
					'saved'         => __( 'Сохранено', 'ssc' ),
					'saving'        => __( 'Сохранение...', 'ssc' ),
					'error'         => __( 'Ошибка', 'ssc' ),
					'selectImage'   => __( 'Выбрать изображение', 'ssc' ),
					'useImage'      => __( 'Использовать', 'ssc' ),
				),
			)
		);
	}

	/**
	 * Рендер страницы администратора.
	 */
	public function render_page() {
		$calculators = ssc_get_calculators();
		?>
		<div class="wrap ssc-admin-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Калькуляторы материалов', 'ssc' ); ?></h1>
			<button type="button" class="page-title-action" id="ssc-add-new"><?php esc_html_e( '+ Добавить', 'ssc' ); ?></button>
			<?php if ( is_multisite() ) : ?>
			<button type="button" class="page-title-action" id="ssc-sync-all-btn"><?php esc_html_e( 'Синхронизировать все', 'ssc' ); ?></button>
			<?php endif; ?>
			<hr class="wp-header-end">

			<div id="ssc-notice" class="notice" style="display:none"><p></p></div>

			<!-- Список калькуляторов -->
			<table class="wp-list-table widefat fixed striped ssc-list-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Название', 'ssc' ); ?></th>
						<th><?php esc_html_e( 'Категория', 'ssc' ); ?></th>
						<th><?php esc_html_e( 'Хук (для таба)', 'ssc' ); ?></th>
						<th><?php esc_html_e( 'Шорткод', 'ssc' ); ?></th>
						<th><?php esc_html_e( 'Действия', 'ssc' ); ?></th>
					</tr>
				</thead>
				<tbody id="ssc-list-body">
				<?php if ( empty( $calculators ) ) : ?>
					<tr id="ssc-empty-row">
						<td colspan="5"><?php esc_html_e( 'Нет ни одного калькулятора. Создайте первый.', 'ssc' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $calculators as $calc ) : ?>
						<?php echo $this->render_table_row( $calc ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<!-- Форма редактирования / создания (скрытая панель) -->
			<div id="ssc-edit-panel" class="ssc-edit-panel" style="display:none">
				<div class="ssc-edit-panel__header">
					<h2 id="ssc-panel-title"><?php esc_html_e( 'Новый калькулятор', 'ssc' ); ?></h2>
					<button type="button" class="button ssc-panel-close">✕</button>
				</div>
				<div class="ssc-edit-panel__body">
					<input type="hidden" id="ssc-calc-id" value="">

					<table class="form-table">
						<tr>
							<th><label for="ssc-calc-type"><?php esc_html_e( 'Тип калькулятора', 'ssc' ); ?></label></th>
							<td>
								<select id="ssc-calc-type" class="regular-text">
									<option value="grass"><?php esc_html_e( 'Искусственный газон', 'ssc' ); ?></option>
									<option value="linoleum"><?php esc_html_e( 'Спортивный линолеум', 'ssc' ); ?></option>
									<option value="sceniclinoleum"><?php esc_html_e( 'Сценический линолеум', 'ssc' ); ?></option>
									<option value="simple"><?php esc_html_e( 'Простой расчёт', 'ssc' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="ssc-name"><?php esc_html_e( 'Название', 'ssc' ); ?></label></th>
							<td><input type="text" id="ssc-name" class="regular-text" placeholder="Калькулятор Искусственного газона"></td>
						</tr>
						<tr>
							<th><label for="ssc-category"><?php esc_html_e( 'Категория товаров', 'ssc' ); ?></label></th>
							<td>
								<?php
								$product_cats = get_terms(
									array(
										'taxonomy'   => 'product_cat',
										'hide_empty' => false,
									)
								);
								?>
								<select id="ssc-category" class="regular-text">
									<option value=""><?php esc_html_e( '— выберите категорию —', 'ssc' ); ?></option>
									<?php if ( ! is_wp_error( $product_cats ) ) : ?>
										<?php foreach ( $product_cats as $cat ) : ?>
											<option value="<?php echo esc_attr( $cat->slug ); ?>" data-id="<?php echo esc_attr( $cat->term_id ); ?>">
												<?php echo esc_html( $cat->name ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
									<option value="simple"><?php esc_html_e( 'Простой расчёт', 'ssc' ); ?></option>
								</select>
								<p class="description">После выбора категории загрузятся её атрибуты.</p>
							</td>
						</tr>
						<tr>
							<th><label for="ssc-subcategory-slugs"><?php esc_html_e( 'Подкатегории (slug)', 'ssc' ); ?></label></th>
							<td>
								<textarea id="ssc-subcategory-slugs" class="large-text" rows="6" placeholder="dlya-futbola&#10;dlya-mini-futbola&#10;dlya-tennisa&#10;dlya-hokkeya-s-myachom&#10;dlya-golfa&#10;landshaftnaya"></textarea>
								<p class="description">Введите slug подкатегорий, каждая с новой строки. На фронтенде пользователь сначала выберет одну из этих подкатегорий, затем увидит соответствующие атрибуты и товары.</p>
							</td>
						</tr>
						<tr>
							<th><label for="ssc-width-attr"><?php esc_html_e( 'Атрибут: ширина рулона', 'ssc' ); ?></label></th>
							<td>
								<select id="ssc-width-attr" class="regular-text">
									<option value=""><?php esc_html_e( '— атрибуты загрузятся после выбора категории —', 'ssc' ); ?></option>
									<option value="simple"><?php esc_html_e( 'Простой расчёт', 'ssc' ); ?></option>
								</select>
								<p class="description">Значения этого атрибута будут предложены пользователю для выбора ширины рулона.</p>
							</td>
						</tr>
						<tr>
							<th><label for="ssc-length-attr"><?php esc_html_e( 'Атрибут: длина рулона', 'ssc' ); ?></label></th>
							<td>
								<select id="ssc-length-attr" class="regular-text">
									<option value=""><?php esc_html_e( '— выберите ширину сначала —', 'ssc' ); ?></option>
									<option value="simple"><?php esc_html_e( 'Простой расчёт', 'ssc' ); ?></option>
								</select>
								<p class="description">Значение берётся из товара автоматически (числовое, в метрах).</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Атрибуты фильтрации', 'ssc' ); ?></th>
							<td>
								<div id="ssc-filter-attrs-wrap" class="ssc-filter-attrs-wrap">
									<p class="description"><?php esc_html_e( 'Сначала выберите категорию', 'ssc' ); ?></p>
								</div>
								<p class="description">Выбранные атрибуты будут показаны как фильтр на странице категории для сужения списка товаров.</p>
							</td>
						</tr>
						<tr>
							<th><label for="ssc-canvas-image"><?php esc_html_e( 'Фоновое изображение canvas', 'ssc' ); ?></label></th>
							<td>
								<div class="ssc-canvas-default-field">
									<span><?php esc_html_e( 'Изображение по умолчанию:', 'ssc' ); ?></span>
									<div class="ssc-image-field">
										<input type="text" id="ssc-canvas-image" class="regular-text" placeholder="/wp-content/uploads/.../field.jpg">
										<button type="button" class="button" id="ssc-canvas-default-pick"><?php esc_html_e( 'Выбрать', 'ssc' ); ?></button>
									</div>
									<div id="ssc-canvas-preview" class="ssc-canvas-preview"></div>
								</div>
								<div class="ssc-canvas-images-wrap">
									<p class="description" id="ssc-canvas-images-placeholder">Заполните подкатегории — здесь появятся поля для изображений</p>
								</div>
								<p class="description">Изображение по умолчанию показывается при загрузке страницы. Для каждой подкатегории можно задать своё изображение.</p>
							</td>
						</tr>
					</table>

					<h3><?php esc_html_e( 'Дополнительные материалы', 'ssc' ); ?></h3>
					<table class="form-table">
						<tr>
							<th>
								<label for="ssc-glue-price" class="ssc-grass-label"><?php esc_html_e( 'Клей: цена за банку (руб)', 'ssc' ); ?></label>
								<label for="ssc-glue-price" class="ssc-linoleum-label" style="display:none"><?php esc_html_e( 'Клей (напольный): цена за банку (руб)', 'ssc' ); ?></label>
								<label for="ssc-glue-price" class="ssc-simple-label" style="display:none"><?php esc_html_e( 'Клей (приклейка): цена за банку (руб)', 'ssc' ); ?></label>
							</th>
							<td><input type="number" id="ssc-glue-price" value="4000" min="0" step="1" class="small-text"></td>
						</tr>
						<tr>
							<th>
								<label for="ssc-glue-volume" class="ssc-grass-label"><?php esc_html_e( 'Клей: объём тары (кг)', 'ssc' ); ?></label>
								<label for="ssc-glue-volume" class="ssc-linoleum-label" style="display:none"><?php esc_html_e( 'Клей (напольный): объём тары (кг)', 'ssc' ); ?></label>
								<label for="ssc-glue-volume" class="ssc-simple-label" style="display:none"><?php esc_html_e( 'Клей (приклейка): объём тары (кг)', 'ssc' ); ?></label>
							</th>
							<td><input type="number" id="ssc-glue-volume" value="10" min="1" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-not-simple">
							<th>
								<label for="ssc-tape-price" class="ssc-grass-label"><?php esc_html_e( 'Шовная лента: цена за м.п. (руб)', 'ssc' ); ?></label>
								<label for="ssc-tape-price" class="ssc-linoleum-label" style="display:none"><?php esc_html_e( 'Сварочный шнур: цена за м.п. (руб)', 'ssc' ); ?></label>
							</th>
							<td><input type="number" id="ssc-tape-price" value="55" min="0" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-not-simple">
							<th>
								<label for="ssc-tape-volume" class="ssc-grass-label"><?php esc_html_e( 'Шовная лента: длина рулона (м)', 'ssc' ); ?></label>
								<label for="ssc-tape-volume" class="ssc-linoleum-label" style="display:none"><?php esc_html_e( 'Сварочный шнур: длина рулона (м)', 'ssc' ); ?></label>
							</th>
							<td><input type="number" id="ssc-tape-volume" value="50" min="1" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-grass-only">
							<th><?php esc_html_e( 'Кварцевый песок', 'ssc' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="ssc-sand-enabled">
									<?php esc_html_e( 'Включить расчёт (кол-во кг зависит от высоты ворса)', 'ssc' ); ?>
								</label>
								<br>
								<label for="ssc-sand-price"><?php esc_html_e( 'Цена за тонну (руб):', 'ssc' ); ?></label>
								<input type="number" id="ssc-sand-price" value="3950" min="0" step="1" class="small-text">
							</td>
						</tr>
						<tr class="ssc-grass-only">
							<th><?php esc_html_e( 'Резиновая крошка', 'ssc' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="ssc-rubber-enabled">
									<?php esc_html_e( 'Включить расчёт (кол-во кг зависит от высоты ворса)', 'ssc' ); ?>
								</label>
								<br>
								<label for="ssc-rubber-price"><?php esc_html_e( 'Цена за тонну (руб):', 'ssc' ); ?></label>
								<input type="number" id="ssc-rubber-price" value="24500" min="0" step="1" class="small-text">
							</td>
						</tr>
						<tr class="ssc-not-simple">
							<th><?php esc_html_e( 'Разметка поля', 'ssc' ); ?></th>
							<td>
								<label>
									<input type="checkbox" id="ssc-markup-enabled">
									<span class="ssc-grass-label"><?php esc_html_e( 'Включить выбор типа разметки (Футбол / Мини-футбол / Теннис / Хоккей)', 'ssc' ); ?></span>
									<span class="ssc-linoleum-label" style="display:none"><?php esc_html_e( 'Включить выбор типа разметки (Волейбол / Баскетбол / Мини-футбол)', 'ssc' ); ?></span>
								</label>
							</td>
						</tr>
						<tr class="ssc-linoleum-only" style="display:none">
							<th><label for="ssc-paint-price"><?php esc_html_e( 'Краска для разметки: цена за банку (руб)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-paint-price" value="0" min="0" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-base-tape-price"><?php esc_html_e( 'Основание — Скотч: цена за рулон (руб)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-base-tape-price" value="500" min="0" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-base-tape-volume"><?php esc_html_e( 'Основание — Скотч: длина рулона (м)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-base-tape-volume" value="50" min="1" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-seam-cord-price"><?php esc_html_e( 'Швы — Шнур: цена за бухту (руб)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-seam-cord-price" value="500" min="0" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-seam-cord-volume"><?php esc_html_e( 'Швы — Шнур: длина бухты (м)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-seam-cord-volume" value="50" min="1" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-seam-tape-price"><?php esc_html_e( 'Швы — Скотч: цена за рулон (руб)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-seam-tape-price" value="500" min="0" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-seam-tape-volume"><?php esc_html_e( 'Швы — Скотч: длина рулона (м)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-seam-tape-volume" value="50" min="1" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-seam-weld-price"><?php esc_html_e( 'Швы — Холодная сварка: цена за тюбик (руб)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-seam-weld-price" value="500" min="0" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-scenic-only" style="display:none">
							<th><label for="ssc-scenic-seam-weld-volume"><?php esc_html_e( 'Швы — Холодная сварка: расход на тюбик (м шва)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-scenic-seam-weld-volume" value="10" min="1" step="1" class="small-text"></td>
						</tr>
						<tr class="ssc-simple-only" style="display:none">
							<th><?php esc_html_e( 'Расчёт рулонами', 'ssc' ); ?></th>
							<td><label><input type="checkbox" id="ssc-simple-rolls-enabled"> <?php esc_html_e( 'Включить расчёт количества рулонов и швов', 'ssc' ); ?></label></td>
						</tr>
						<tr class="ssc-simple-only" style="display:none">
							<th><?php esc_html_e( 'Приклейка клеем', 'ssc' ); ?></th>
							<td><label><input type="checkbox" id="ssc-simple-glue-enabled"> <?php esc_html_e( 'Включить расчёт приклейки клеем', 'ssc' ); ?></label></td>
						</tr>
						<tr class="ssc-simple-only" style="display:none">
							<th><label for="ssc-simple-glue-rate"><?php esc_html_e( 'Расход клея (кг/м²)', 'ssc' ); ?></label></th>
							<td><input type="number" id="ssc-simple-glue-rate" value="0.35" min="0.01" step="0.01" class="small-text"></td>
						</tr>
					</table>

					<h3><?php esc_html_e( 'Настройки КП (PDF)', 'ssc' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><label for="ssc-company-name"><?php esc_html_e( 'Название компании', 'ssc' ); ?></label></th>
							<td><input type="text" id="ssc-company-name" class="regular-text" placeholder="ООО «Компания»"></td>
						</tr>
						<tr>
							<th><label for="ssc-admin-email"><?php esc_html_e( 'Email для уведомлений', 'ssc' ); ?></label></th>
							<td><input type="email" id="ssc-admin-email" class="regular-text"></td>
						</tr>
					</table>

					<div class="ssc-edit-panel__footer">
						<button type="button" class="button button-primary" id="ssc-save-btn"><?php esc_html_e( 'Сохранить', 'ssc' ); ?></button>
						<button type="button" class="button ssc-panel-close"><?php esc_html_e( 'Отмена', 'ssc' ); ?></button>
						<span class="ssc-save-status"></span>
						<button type="button" class="button ssc-clear-cache-btn" style="margin-left:auto;"><?php esc_html_e( 'Очистить кеш', 'ssc' ); ?></button>
						<?php if ( is_multisite() ) : ?>
						<button type="button" class="button ssc-sync-btn" id="ssc-sync-btn"><?php esc_html_e( 'Синхронизировать со всеми городами', 'ssc' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Рендер строки таблицы.
	 *
	 * @param array $calc
	 * @return string
	 */
	private function render_table_row( $calc ) {
		$id        = esc_attr( $calc['id'] );
		$name      = esc_html( $calc['name'] );
		$cat       = esc_html( $calc['category_slug'] );
		$hook      = esc_html( $calc['hook_name'] );
		$shortcode = '[ssc_calculator id="' . esc_attr( $calc['id'] ) . '"]';
		$type      = $calc['calculator_type'] ?? '—';
		if ( $type === 'linoleum' ) {
			$type_label = '<span style="color:#0073aa">Спорт. линолеум</span>';
		} elseif ( $type === 'sceniclinoleum' ) {
			$type_label = '<span style="color:#7c3aed">Сцен. линолеум</span>';
		} elseif ( $type === 'grass' ) {
			$type_label = 'Газон';
		} else {
			$type_label = esc_html( $type );
		}

		$sync_btn = '';
		if ( is_multisite() ) {
			$sync_btn = ' <button type="button" class="button button-small ssc-sync-row-btn" data-id="' . $id . '">' . __( 'Синхр.', 'ssc' ) . '</button>';
		}

		return '<tr data-id="' . $id . '">
			<td><strong>' . $name . '</strong><br><small>' . $type_label . '</small></td>
			<td>' . $cat . '</td>
			<td><code>' . $hook . '</code></td>
			<td><code>' . esc_html( $shortcode ) . '</code> <button type="button" class="button-link ssc-copy-shortcode" data-shortcode="' . esc_attr( $shortcode ) . '">📋</button></td>
			<td>
				<button type="button" class="button button-small ssc-edit-btn" data-id="' . $id . '">' . __( 'Изменить', 'ssc' ) . '</button>
				<button type="button" class="button button-small ssc-delete-btn" data-id="' . $id . '">' . __( 'Удалить', 'ssc' ) . '</button>
				<button type="button" class="button button-small ssc-clear-cache-btn" data-id="' . $id . '">' . __( 'Сброс кеша', 'ssc' ) . '</button>
				' . $sync_btn . '
			</td>
		</tr>';
	}

	/**
	 * AJAX: сохранить калькулятор.
	 */
	public function ajax_save() {
		check_ajax_referer( 'ssc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}

		$filter_attrs = isset( $_POST['filter_attrs'] ) ? (array) $_POST['filter_attrs'] : array();

		// Разбиваем slug подкатегорий по строкам
		$subcategory_slugs_raw = isset( $_POST['subcategory_slugs'] ) ? sanitize_textarea_field( $_POST['subcategory_slugs'] ) : '';
		$subcategory_slugs = array_filter( array_map( 'sanitize_title', preg_split( '/\r?\n/', $subcategory_slugs_raw ) ) );

		// Canvas images для подкатегорий (вложенная структура: slug → markup_type → url)
		$canvas_images_raw = isset( $_POST['canvas_images_json'] ) ? sanitize_text_field( wp_unslash( $_POST['canvas_images_json'] ) ) : '';
		// Debug
		error_log( 'SSC canvas_images_raw: ' . $canvas_images_raw );

		$canvas_images_clean = array();
		if ( $canvas_images_raw ) {
			$canvas_images = json_decode( $canvas_images_raw, true );
			error_log( 'SSC canvas_images decoded: ' . print_r( $canvas_images, true ) );
			if ( is_array( $canvas_images ) ) {
				foreach ( $canvas_images as $slug => $markup_urls ) {
					$clean_slug = sanitize_key( $slug );
					if ( ! is_array( $markup_urls ) ) {
						continue;
					}
					foreach ( $markup_urls as $markup_type => $url ) {
						$clean_markup = sanitize_key( $markup_type );
						$clean_url = sanitize_text_field( $url );
						if ( $clean_url ) {
							if ( ! isset( $canvas_images_clean[ $clean_slug ] ) ) {
								$canvas_images_clean[ $clean_slug ] = array();
							}
							$canvas_images_clean[ $clean_slug ][ $clean_markup ] = $clean_url;
						}
					}
				}
			}
		}

		$data = array(
			'id'                 => sanitize_key( $_POST['id'] ?? '' ),
			'name'               => sanitize_text_field( $_POST['name'] ?? '' ),
			'calculator_type'    => sanitize_key( $_POST['calculator_type'] ?? 'grass' ),
			'category_slug'      => sanitize_title( $_POST['category_slug'] ?? '' ),
			'subcategory_slugs'  => array_values( $subcategory_slugs ),
			'canvas_images'      => $canvas_images_clean,
			'width_attr'         => sanitize_key( $_POST['width_attr'] ?? '' ),
			'length_attr'        => sanitize_key( $_POST['length_attr'] ?? '' ),
			'filter_attrs'       => $filter_attrs,
			'canvas_image'       => esc_url_raw( $_POST['canvas_image'] ?? '' ),
			'glue_price'              => absint( $_POST['glue_price'] ?? 4000 ),
			'glue_volume'             => max( 1, absint( $_POST['glue_volume'] ?? 10 ) ),
			'tape_price'              => absint( $_POST['tape_price'] ?? 65 ),
			'tape_volume'             => max( 1, absint( $_POST['tape_volume'] ?? 50 ) ),
			'scenic_base_tape_price'  => absint( $_POST['scenic_base_tape_price'] ?? 500 ),
			'scenic_base_tape_volume' => max( 1, absint( $_POST['scenic_base_tape_volume'] ?? 50 ) ),
			'scenic_seam_cord_price'  => absint( $_POST['scenic_seam_cord_price'] ?? 500 ),
			'scenic_seam_cord_volume' => max( 1, absint( $_POST['scenic_seam_cord_volume'] ?? 50 ) ),
			'scenic_seam_tape_price'  => absint( $_POST['scenic_seam_tape_price'] ?? 500 ),
			'scenic_seam_tape_volume' => max( 1, absint( $_POST['scenic_seam_tape_volume'] ?? 50 ) ),
			'scenic_seam_weld_price'  => absint( $_POST['scenic_seam_weld_price'] ?? 500 ),
			'scenic_seam_weld_volume' => max( 1, absint( $_POST['scenic_seam_weld_volume'] ?? 10 ) ),
			'simple_rolls_enabled'    => ! empty( $_POST['simple_rolls_enabled'] ),
			'simple_glue_enabled'     => ! empty( $_POST['simple_glue_enabled'] ),
			'simple_glue_rate'        => max( 0.01, floatval( $_POST['simple_glue_rate'] ?? 0.35 ) ),
			'sand_enabled'       => ! empty( $_POST['sand_enabled'] ),
			'sand_price'         => absint( $_POST['sand_price'] ?? 3950 ),
			'rubber_enabled'     => ! empty( $_POST['rubber_enabled'] ),
			'rubber_price'       => absint( $_POST['rubber_price'] ?? 24500 ),
			'markup_enabled'     => ! empty( $_POST['markup_enabled'] ),
			'markup_percent'     => floatval( $_POST['markup_percent'] ?? 0 ),
			'paint_price'        => absint( $_POST['paint_price'] ?? 0 ),
			'admin_email'        => sanitize_email( $_POST['admin_email'] ?? '' ),
			'company_name'       => sanitize_text_field( $_POST['company_name'] ?? '' ),
		);

		$id   = ssc_save_calculator( $data );
		$calc = ssc_get_calculator( $id );

		wp_send_json_success(
			array(
				'id'  => $id,
				'row' => $this->render_table_row( $calc ),
			)
		);
	}

	/**
	 * AJAX: удалить калькулятор.
	 */
	public function ajax_delete() {
		check_ajax_referer( 'ssc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}
		$id = sanitize_key( $_POST['id'] ?? '' );
		ssc_delete_calculator( $id );
		wp_send_json_success();
	}

	/**
	 * AJAX: получить данные калькулятора для редактирования.
	 */
	public function ajax_get() {
		check_ajax_referer( 'ssc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}
		$id   = sanitize_key( $_GET['id'] ?? '' );
		$calc = ssc_get_calculator( $id );
		if ( ! $calc ) {
			wp_send_json_error( 'Not found' );
		}
		wp_send_json_success( $calc );
	}

	/**
	 * AJAX: загрузить атрибуты категории.
	 * Ищет атрибуты ТОЛЬКО в указанной родительской категории (и её дочерних).
	 */
	public function ajax_load_category_attrs() {
		check_ajax_referer( 'ssc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}

		$category_slug = sanitize_title( $_GET['category_slug'] ?? '' );
		if ( ! $category_slug ) {
			wp_send_json_error( 'No category' );
		}

		// Получаем parent + все дочерние категории
		$term_ids = array();
		$parent_term = get_term_by( 'slug', $category_slug, 'product_cat' );
		if ( ! $parent_term ) {
			wp_send_json_error( 'Category not found' );
		}

		$term_ids[] = $parent_term->term_id;
		// Добавляем все дочерние
		$children = get_terms( array(
			'taxonomy'   => 'product_cat',
			'parent'     => $parent_term->term_id,
			'hide_empty' => false,
			'fields'     => 'ids',
		) );
		if ( ! is_wp_error( $children ) && ! empty( $children ) ) {
			$term_ids = array_merge( $term_ids, $children );
		}

		// Получаем товары из этих категорий
		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => $term_ids,
						'include_children' => false,
					),
				),
			)
		);

		if ( empty( $product_ids ) ) {
			wp_send_json_error( 'Товары не найдены в категории «' . $category_slug . '»' );
		}

		// Собираем атрибуты (и taxonomy, и custom)
		$attrs = array();
		foreach ( $product_ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( ! $product ) {
				continue;
			}
			foreach ( $product->get_attributes() as $attr ) {
				if ( $attr->is_taxonomy() ) {
					$slug = str_replace( 'pa_', '', $attr->get_name() );
					if ( ! isset( $attrs[ $slug ] ) ) {
						$tax                = get_taxonomy( $attr->get_name() );
						$attrs[ $slug ]     = array(
							'slug'  => $slug,
							'label' => $tax ? $tax->labels->singular_name : $slug,
						);
					}
				} else {
					// Custom text attribute
					$slug = $attr->get_name();
					if ( ! isset( $attrs[ $slug ] ) ) {
						$label = ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
						$attrs[ $slug ] = array(
							'slug'  => $slug,
							'label' => $label,
						);
					}
				}
			}
		}

		if ( empty( $attrs ) ) {
			wp_send_json_error( 'Атрибуты не найдены у товаров категории' );
		}

		wp_send_json_success( array_values( $attrs ) );
	}

	/**
	 * AJAX: очистить кеш калькулятора.
	 */
	public function ajax_clear_cache() {
		check_ajax_referer( 'ssc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}
		delete_transient( 'ssc_calc_cache' );
		wp_send_json_success( array( 'message' => __( 'Кеш очищен', 'ssc' ) ) );
	}

	/**
	 * AJAX: синхронизировать настройки калькулятора со всеми сайтами мультисайта.
	 */
	public function ajax_sync_calculator() {
		check_ajax_referer( 'ssc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}
		if ( ! is_multisite() ) {
			wp_send_json_error( 'Not multisite' );
		}

		$calc_id = sanitize_key( $_POST['calc_id'] ?? '' );
		if ( ! $calc_id ) {
			wp_send_json_error( 'No calc ID' );
		}

		// Получаем текущий калькулятор
		$current_calc = ssc_get_calculator( $calc_id );
		if ( ! $current_calc ) {
			wp_send_json_error( 'Calculator not found' );
		}

		// Получаем все сайты сети
		$sites = get_sites( array(
			'number' => 100,
			'archived' => 0,
			'deleted'  => 0,
		) );

		$current_blog_id = get_current_blog_id();
		$synced_count = 0;
		$errors = array();

		foreach ( $sites as $site ) {
			$blog_id = (int) $site->blog_id;
			if ( $blog_id === $current_blog_id ) {
				continue; // Пропускаем текущий сайт
			}

			switch_to_blog( $blog_id );

			try {
				// Получаем калькуляторы на целевом сайте
				$calculators = get_option( SSC_OPTION_KEY, array() );

				// Если калькулятор с таким ID уже есть — обновляем, иначе — создаем
				$calculators[ $calc_id ] = $current_calc;

				// Сохраняем
				update_option( SSC_OPTION_KEY, $calculators );
				$synced_count++;
			} catch ( \Exception $e ) {
				$errors[] = sprintf( 'Site %d: %s', $blog_id, $e->getMessage() );
			}

			restore_current_blog();
		}

		if ( $synced_count > 0 ) {
			wp_send_json_success( array(
				'message' => sprintf( __( 'Синхронизировано с %d городами', 'ssc' ), $synced_count ),
				'synced'  => $synced_count,
				'errors'  => $errors,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Нет городов для синхронизации', 'ssc' ) ) );
		}
	}

	/**
	 * AJAX: синхронизировать ВСЕ калькуляторы со всеми сайтами мультисайта.
	 */
	public function ajax_sync_all_calculators() {
		check_ajax_referer( 'ssc_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}
		if ( ! is_multisite() ) {
			wp_send_json_error( 'Not multisite' );
		}

		$all_calcs = ssc_get_calculators();
		if ( empty( $all_calcs ) ) {
			wp_send_json_error( array( 'message' => __( 'Нет калькуляторов для синхронизации', 'ssc' ) ) );
		}

		$sites = get_sites( array(
			'number'   => 100,
			'archived' => 0,
			'deleted'  => 0,
		) );

		$current_blog_id = get_current_blog_id();
		$synced_sites    = 0;
		$errors          = array();

		foreach ( $sites as $site ) {
			$blog_id = (int) $site->blog_id;
			if ( $blog_id === $current_blog_id ) {
				continue;
			}

			switch_to_blog( $blog_id );

			try {
				$target_calcs = get_option( SSC_OPTION_KEY, array() );
				foreach ( $all_calcs as $calc_id => $calc_data ) {
					$target_calcs[ $calc_id ] = $calc_data;
				}
				update_option( SSC_OPTION_KEY, $target_calcs );
				$synced_sites++;
			} catch ( \Exception $e ) {
				$errors[] = sprintf( 'Site %d: %s', $blog_id, $e->getMessage() );
			}

			restore_current_blog();
		}

		if ( $synced_sites > 0 ) {
			wp_send_json_success( array(
				'message' => sprintf(
					_n(
						'Все калькуляторы (%d шт.) синхронизированы с %d городом',
						'Все калькуляторы (%d шт.) синхронизированы с %d городами',
						$synced_sites,
						'ssc'
					),
					count( $all_calcs ),
					$synced_sites
				),
				'synced'  => $synced_sites,
				'calcs'   => count( $all_calcs ),
				'errors'  => $errors,
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Нет городов для синхронизации', 'ssc' ) ) );
		}
	}

	private function __clone() {}
	public function __wakeup() { throw new \Exception( 'Cannot unserialize singleton' ); }
}
