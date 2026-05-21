<?php
/**
 * Виджет «Карточка калькулятора».
 * Автоматически определяет текущую категорию товаров и отображает карточку
 * согласно настройкам из «Калькуляторы → Карточки категорий».
 * Дочерние категории наследуют настройку родителя.
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Forbidden' );
}

class SSC_Calc_Card_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'ssc_calc_card',
			'Карточка калькулятора',
			[ 'description' => 'Отображает карточку-ссылку на калькулятор для текущей категории товаров. Настраивается в «Калькуляторы → Карточки категорий».' ]
		);
	}

	/**
	 * Фронтенд — вывод виджета.
	 */
	public function widget( $args, $instance ) {
		if ( ! is_product_category() ) {
			return;
		}

		$term = get_queried_object();
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$rule = ssc_find_card_rule( $term );
		if ( ! $rule ) {
			return;
		}

		$title        = $rule['title']        ?? '';
		$subtitle     = $rule['subtitle']     ?? '';
		$tag          = $rule['tag']          ?? '';
		$bg_image_url = trim( $rule['bg_image_url'] ?? '' );
		$link_url     = trim( $rule['link_url']     ?? '' );
		$text_color   = $rule['text_color']   ?? '';
		$title_size   = $rule['title_size']   ?? '';

		// Относительный путь (/slug/) → абсолютный через home_url()
		if ( $link_url && ! preg_match( '#^https?://#', $link_url ) ) {
			$link_url = home_url( $link_url );
		}

		$bg_image_url = $bg_image_url ? home_url( $bg_image_url ) : '';

		$bg_style     = $bg_image_url
			? ' style="background-image:url(' . esc_attr( $bg_image_url ) . ')"'
			: '';
		$no_image_cls = $bg_image_url ? '' : ' ssc-calc-card--no-image';

		echo $args['before_widget'];
		?>
		<a class="ssc-calc-card<?php echo esc_attr( $no_image_cls ); ?>"
		   href="<?php echo esc_attr( $link_url ); ?>">

			<div class="ssc-calc-card__bg"<?php echo $bg_style; ?>></div>

			<div class="ssc-calc-card__overlay"></div>

			<div class="ssc-calc-card__body">

				<div class="ssc-calc-card__text"<?php
					$text_style = $text_color ? ' style="color:' . esc_attr( $text_color ) . '"' : '';
					echo $text_style;
				?>>
					<?php if ( $title ) :
						$title_style = $title_size ? ' style="font-size:' . absint( $title_size ) . 'px"' : '';
					?>
						<h3 class="ssc-calc-card__title"<?php echo $title_style; ?>><?php echo esc_html( $title ); ?></h3>
					<?php endif; ?>

					<?php if ( $subtitle ) : ?>
						<p class="ssc-calc-card__subtitle"><?php echo esc_html( $subtitle ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( $tag ) : ?>
					<div class="ssc-calc-card__footer">
						<span class="ssc-calc-card__tag">
							<svg class="ssc-calc-card__tag-icon" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
								<rect x="3" y="2" width="18" height="5" rx="1"/>
								<rect x="3" y="10" width="5" height="4" rx="1"/>
								<rect x="9.5" y="10" width="5" height="4" rx="1"/>
								<rect x="16" y="10" width="5" height="4" rx="1"/>
								<rect x="3" y="17" width="5" height="4" rx="1"/>
								<rect x="9.5" y="17" width="5" height="4" rx="1"/>
								<rect x="16" y="17" width="5" height="4" rx="1"/>
							</svg>
							<?php echo esc_html( $tag ); ?>
						</span>
					</div>
				<?php endif; ?>

			</div>

			<div class="ssc-calc-card__deco" aria-hidden="true"></div>

		</a>
		<?php
		echo $args['after_widget'];
	}

	/**
	 * Форма настройки виджета в админке — виджет без ручных настроек.
	 */
	public function form( $instance ) {
		?>
		<p style="color:#555">
			Карточка определяется автоматически по текущей категории товаров.<br>
			Настройте карточки в <a href="<?php echo esc_url( admin_url( 'admin.php?page=ssc-calc-cards' ) ); ?>">Калькуляторы → Карточки категорий</a>.
		</p>
		<?php
	}

	/**
	 * Нет настроек для сохранения.
	 */
	public function update( $new_instance, $old_instance ) {
		return [];
	}
}
