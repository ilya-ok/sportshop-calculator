<?php
/**
 * Страница настроек «Карточки категорий» для плагина Sportshop Calculator.
 * Позволяет задать для каждой категории товаров заголовок, изображение и ссылку
 * на калькулятор. Дочерние категории наследуют настройки родителя.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Forbidden' );
}

class SSC_Calc_Cards_Admin {

	const OPTION_KEY   = 'ssc_calc_card_rules';
	const MENU_SLUG    = 'ssc-calc-cards';
	const NONCE_SAVE   = 'ssc_calc_cards_save';
	const NONCE_SYNC   = 'ssc_calc_cards_nonce';
	const AJAX_SYNC    = 'ssc_sync_calc_cards';

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_init',            [ $this, 'handle_save' ] );
		add_action( 'wp_ajax_' . self::AJAX_SYNC, [ $this, 'ajax_sync' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'ssc-calculators',
			'Карточки категорий',
			'Карточки категорий',
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Обработка POST-сохранения.
	 */
	public function handle_save(): void {
		if ( ! isset( $_POST['ssc_calc_cards_action'] ) ) {
			return;
		}
		check_admin_referer( self::NONCE_SAVE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}

		$raw   = $_POST['rules'] ?? [];
		$rules = [];

		if ( is_array( $raw ) ) {
			foreach ( $raw as $slug => $data ) {
				$link = sanitize_text_field( $data['link_url'] ?? '' );
				if ( ! $link ) {
					continue;
				}
				$rules[ sanitize_key( $slug ) ] = [
					'title'        => sanitize_text_field( $data['title']        ?? '' ),
					'subtitle'     => sanitize_text_field( $data['subtitle']     ?? '' ),
					'tag'          => sanitize_text_field( $data['tag']          ?? '' ),
					'bg_image_url' => sanitize_text_field( $data['bg_image_url'] ?? '' ),
					'link_url'     => $link,
					'text_color'   => sanitize_hex_color( $data['text_color']   ?? '' ) ?: '',
					'title_size'   => absint( $data['title_size'] ?? 0 ) ?: '',
				];
			}
		}

		update_option( self::OPTION_KEY, $rules );

		wp_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'saved' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * AJAX: синхронизировать настройки на все сайты мультисайта.
	 */
	public function ajax_sync(): void {
		check_ajax_referer( self::NONCE_SYNC );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden' );
		}
		if ( ! is_multisite() ) {
			wp_send_json_error( 'Not multisite' );
		}

		$rules   = get_option( self::OPTION_KEY, [] );
		$current = get_current_blog_id();
		$count   = 0;
		$errors  = [];

		foreach ( get_sites( [ 'number' => 200 ] ) as $site ) {
			if ( (int) $site->blog_id === $current ) {
				continue;
			}
			switch_to_blog( $site->blog_id );
			$ok = update_option( self::OPTION_KEY, $rules );
			restore_current_blog();
			if ( false !== $ok ) {
				$count++;
			} else {
				$errors[] = $site->blog_id;
			}
		}

		wp_send_json_success( [ 'synced' => $count, 'errors' => $errors ] );
	}

	/**
	 * Рендер страницы настроек.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$rules = get_option( self::OPTION_KEY, [] );
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'parent',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) ) {
			$terms = [];
		}

		// Строим иерархию: сначала корневые, потом дочерние
		$terms_by_parent = [];
		foreach ( $terms as $term ) {
			$terms_by_parent[ $term->parent ][] = $term;
		}

		$saved = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
		?>
		<div class="wrap">
			<h1>Карточки категорий</h1>
			<p>Настройте карточку калькулятора для каждой категории товаров. Дочерние категории без своей настройки наследуют настройку родителя. Если ссылка пустая — карточка для этой категории не отображается.</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( self::NONCE_SAVE ); ?>
				<input type="hidden" name="ssc_calc_cards_action" value="save">

				<table class="wp-list-table widefat fixed striped" style="margin-top:16px">
					<thead>
						<tr>
							<th style="width:16%">Категория</th>
							<th>Заголовок</th>
							<th>Подзаголовок</th>
							<th style="width:7%">Тег</th>
							<th>Фоновое изображение<br><small style="font-weight:normal">относительный путь</small></th>
							<th>Ссылка<br><small style="font-weight:normal">относительный путь</small></th>
							<th style="width:80px">Цвет текста</th>
							<th style="width:80px">Размер загол., px</th>
						</tr>
					</thead>
					<tbody>
						<?php $this->render_terms_rows( $terms_by_parent, 0, $rules, 0 ); ?>
					</tbody>
				</table>

				<p class="submit" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
					<button type="submit" class="button button-primary">Сохранить настройки</button>
					<?php if ( is_multisite() ) : ?>
						<button type="button" id="ssc-cards-sync-btn" class="button">
							Синхронизировать на все города
						</button>
						<span id="ssc-cards-sync-status" style="color:#555"></span>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<?php if ( is_multisite() ) : ?>
		<script>
		jQuery(document).ready(function($) {
			$('#ssc-cards-sync-btn').on('click', function() {
				var $btn    = $(this);
				var $status = $('#ssc-cards-sync-status');
				$btn.prop('disabled', true);
				$status.text('Синхронизация...');
				$.post(ajaxurl, {
					action: '<?php echo esc_js( self::AJAX_SYNC ); ?>',
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( self::NONCE_SYNC ) ); ?>'
				}, function(resp) {
					$btn.prop('disabled', false);
					if (resp.success) {
						$status.text('✓ Синхронизировано на ' + resp.data.synced + ' гор.');
					} else {
						$status.text('Ошибка: ' + (resp.data || 'неизвестная'));
					}
				});
			});
		});
		</script>
		<?php endif;
	}

	/**
	 * Рекурсивный рендер строк таблицы категорий.
	 */
	private function render_terms_rows( array $by_parent, int $parent_id, array $rules, int $depth ): void {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return;
		}
		foreach ( $by_parent[ $parent_id ] as $term ) {
			$slug = $term->slug;
			$rule = $rules[ $slug ] ?? [];
			$pad  = $depth > 0 ? str_repeat( '&nbsp;&nbsp;&nbsp;', $depth ) . '— ' : '';
			?>
			<tr>
				<td>
					<?php echo $pad; ?>
					<strong><?php echo esc_html( $term->name ); ?></strong><br>
					<small style="color:#888"><?php echo esc_html( $slug ); ?></small>
				</td>
				<td>
					<input type="text" class="widefat"
					       name="rules[<?php echo esc_attr( $slug ); ?>][title]"
					       value="<?php echo esc_attr( $rule['title'] ?? '' ); ?>"
					       placeholder="Заголовок">
				</td>
				<td>
					<input type="text" class="widefat"
					       name="rules[<?php echo esc_attr( $slug ); ?>][subtitle]"
					       value="<?php echo esc_attr( $rule['subtitle'] ?? '' ); ?>"
					       placeholder="Подзаголовок">
				</td>
				<td>
					<input type="text" class="widefat"
					       name="rules[<?php echo esc_attr( $slug ); ?>][tag]"
					       value="<?php echo esc_attr( $rule['tag'] ?? '' ); ?>"
					       placeholder="Тег">
				</td>
				<td>
					<input type="text" class="widefat"
					       name="rules[<?php echo esc_attr( $slug ); ?>][bg_image_url]"
					       value="<?php echo esc_attr( $rule['bg_image_url'] ?? '' ); ?>"
					       placeholder="/wp-content/uploads/...">
				</td>
				<td>
					<input type="text" class="widefat"
					       name="rules[<?php echo esc_attr( $slug ); ?>][link_url]"
					       value="<?php echo esc_attr( $rule['link_url'] ?? '' ); ?>"
					       placeholder="/kalkulyator/">
				</td>
				<td>
					<div style="display:flex;align-items:center;gap:4px">
						<input type="color"
						       value="<?php echo esc_attr( $rule['text_color'] ?? '#ffffff' ); ?>"
						       style="width:36px;height:28px;padding:1px;cursor:pointer;flex-shrink:0"
						       oninput="this.nextElementSibling.value=this.value">
						<input type="text"
						       name="rules[<?php echo esc_attr( $slug ); ?>][text_color]"
						       value="<?php echo esc_attr( $rule['text_color'] ?? '#ffffff' ); ?>"
						       placeholder="#ffffff"
						       style="width:72px;font-family:monospace"
						       oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))this.previousElementSibling.value=this.value">
					</div>
				</td>
				<td>
					<input type="number" min="10" max="72"
					       name="rules[<?php echo esc_attr( $slug ); ?>][title_size]"
					       value="<?php echo esc_attr( $rule['title_size'] ?? '' ); ?>"
					       placeholder="24"
					       style="width:60px">
				</td>
			</tr>
			<?php
			$this->render_terms_rows( $by_parent, $term->term_id, $rules, $depth + 1 );
		}
	}
}
