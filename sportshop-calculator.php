<?php
/**
 * Plugin Name: Sportshop Calculator
 * Plugin URI:
 * Description: Калькулятор материалов для категорий товаров. Поддерживает страницу товара (таб) и страницу категории (шорткод).
 * Version: 1.0.0
 * Author: SportShop
 * Text Domain: ssc
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Forbidden' );
}

define( 'SSC_VERSION', '1.0.0' );
define( 'SSC_PLUGIN_FILE', __FILE__ );
define( 'SSC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SSC_OPTION_KEY', 'ssc_calculators' );

require_once SSC_PLUGIN_DIR . 'includes/class-ssc-admin.php';
require_once SSC_PLUGIN_DIR . 'includes/class-ssc-frontend.php';

/**
 * Инициализация плагина.
 */
function ssc_init() {
	if ( is_admin() ) {
		SSC_Admin::get_instance();
	}
	SSC_Frontend::get_instance();
}
add_action( 'plugins_loaded', 'ssc_init' );

// AJAX-хуки регистрируем здесь, чтобы они работали даже если singleton уже создан
// Используем другое имя, чтобы не конфликтовать с админским ssc_load_category_attrs
add_action( 'wp_ajax_ssc_load_subcategory_attrs', 'ssc_ajax_load_subcategory_attrs' );
add_action( 'wp_ajax_nopriv_ssc_load_subcategory_attrs', 'ssc_ajax_load_subcategory_attrs' );
function ssc_ajax_load_subcategory_attrs() {
	SSC_Frontend::get_instance()->ajax_load_category_attrs();
}

add_action( 'wp_ajax_ssc_clear_cache', 'ssc_ajax_clear_cache' );
add_action( 'wp_ajax_nopriv_ssc_clear_cache', 'ssc_ajax_clear_cache' );
function ssc_ajax_clear_cache() {
	delete_transient( 'ssc_calc_cache' );
	wp_send_json_success();
}

// Автоматическая очистка кеша при изменениях
function ssc_clear_cache_hook() {
	delete_transient( 'ssc_calc_cache' );
}
add_action( 'save_post_product', 'ssc_clear_cache_hook' );
add_action( 'edited_product_cat', 'ssc_clear_cache_hook' );
add_action( 'created_product_cat', 'ssc_clear_cache_hook' );
add_action( 'delete_product_cat', 'ssc_clear_cache_hook' );

// Очистка при изменении атрибутов
function ssc_register_attr_cache_hooks() {
	$attr_taxonomies = array( 'pa_vysota-vorsa', 'pa_tolshhina-volokna', 'pa_kolichestvo-stezhkov' );
	foreach ( $attr_taxonomies as $tax ) {
		add_action( 'created_' . $tax, 'ssc_clear_cache_hook' );
		add_action( 'edited_' . $tax, 'ssc_clear_cache_hook' );
		add_action( 'delete_' . $tax, 'ssc_clear_cache_hook' );
	}
}
add_action( 'init', 'ssc_register_attr_cache_hooks' );

/**
 * Получить все калькуляторы.
 *
 * @return array
 */
function ssc_get_calculators() {
	return get_option( SSC_OPTION_KEY, array() );
}

/**
 * Получить калькулятор по ID.
 *
 * @param string $id
 * @return array|null
 */
function ssc_get_calculator( $id ) {
	$calculators = ssc_get_calculators();
	return isset( $calculators[ $id ] ) ? $calculators[ $id ] : null;
}

/**
 * Получить калькулятор по slug категории.
 *
 * @param string $category_slug
 * @return array|null
 */
function ssc_get_calculator_by_category( $category_slug ) {
	foreach ( ssc_get_calculators() as $calc ) {
		if ( isset( $calc['category_slug'] ) && $calc['category_slug'] === $category_slug ) {
			return $calc;
		}
	}
	return null;
}

/**
 * Сохранить калькулятор.
 *
 * @param array $data
 * @return string ID калькулятора.
 */
function ssc_save_calculator( $data ) {
	$calculators = ssc_get_calculators();
	$id          = ! empty( $data['id'] ) ? sanitize_key( $data['id'] ) : 'calc_' . uniqid();
	$hook_name   = 'ssc_calc_' . preg_replace( '/[^a-z0-9]+/', '_', sanitize_title( $data['category_slug'] ?? '' ) );

	$calculators[ $id ] = array(
		'id'              => $id,
		'name'            => sanitize_text_field( $data['name'] ?? '' ),
		'calculator_type' => sanitize_key( $data['calculator_type'] ?? 'grass' ),
		'category_slug'   => sanitize_title( $data['category_slug'] ?? '' ),
		'subcategory_slugs' => isset( $data['subcategory_slugs'] ) ? array_map( 'sanitize_key', (array) $data['subcategory_slugs'] ) : array(),
		'canvas_images'     => ssc_sanitize_canvas_images( $data['canvas_images'] ?? array() ),
		'hook_name'       => $hook_name,
		'width_attr'      => sanitize_key( $data['width_attr'] ?? '' ),
		'length_attr'     => sanitize_key( $data['length_attr'] ?? '' ),
		'filter_attrs'    => array_map( 'sanitize_key', (array) ( $data['filter_attrs'] ?? array() ) ),
		'canvas_image'    => esc_url_raw( $data['canvas_image'] ?? '' ),
		'glue_price'              => absint( $data['glue_price'] ?? 4000 ),
		'glue_volume'             => max( 1, absint( $data['glue_volume'] ?? 10 ) ),
		'tape_price'              => absint( $data['tape_price'] ?? 65 ),
		'tape_volume'             => max( 1, absint( $data['tape_volume'] ?? 50 ) ),
		'scenic_base_tape_price'  => absint( $data['scenic_base_tape_price'] ?? 500 ),
		'scenic_base_tape_volume' => max( 1, absint( $data['scenic_base_tape_volume'] ?? 50 ) ),
		'scenic_seam_cord_price'  => absint( $data['scenic_seam_cord_price'] ?? 500 ),
		'scenic_seam_cord_volume' => max( 1, absint( $data['scenic_seam_cord_volume'] ?? 50 ) ),
		'scenic_seam_tape_price'  => absint( $data['scenic_seam_tape_price'] ?? 500 ),
		'scenic_seam_tape_volume' => max( 1, absint( $data['scenic_seam_tape_volume'] ?? 50 ) ),
		'scenic_seam_weld_price'  => absint( $data['scenic_seam_weld_price'] ?? 500 ),
		'scenic_seam_weld_volume' => max( 1, absint( $data['scenic_seam_weld_volume'] ?? 10 ) ),
		'simple_rolls_enabled'    => ! empty( $data['simple_rolls_enabled'] ),
		'simple_glue_enabled'     => ! empty( $data['simple_glue_enabled'] ),
		'simple_glue_rate'        => max( 0.01, floatval( $data['simple_glue_rate'] ?? 0.35 ) ),
		'sand_enabled'    => ! empty( $data['sand_enabled'] ),
		'sand_price'      => absint( $data['sand_price'] ?? 3950 ),
		'rubber_enabled'  => ! empty( $data['rubber_enabled'] ),
		'rubber_price'    => absint( $data['rubber_price'] ?? 24500 ),
		'markup_enabled'  => ! empty( $data['markup_enabled'] ),
		'markup_percent'  => floatval( $data['markup_percent'] ?? 0 ),
		'paint_price'     => absint( $data['paint_price'] ?? 0 ),
		'admin_email'     => sanitize_email( $data['admin_email'] ?? '' ),
		'company_name'    => sanitize_text_field( $data['company_name'] ?? '' ),
	);

	update_option( SSC_OPTION_KEY, $calculators );
	return $id;
}

/**
 * Санитизация вложенной структуры canvas_images.
 * Ожидает: { slug: { markup_type: url } }
 *
 * @param array $images
 * @return array
 */
function ssc_sanitize_canvas_images( $images ) {
	$clean = array();
	if ( ! is_array( $images ) ) {
		return $clean;
	}
	foreach ( $images as $slug => $markup_urls ) {
		$clean_slug = sanitize_key( $slug );
		if ( ! is_array( $markup_urls ) ) {
			continue;
		}
		foreach ( $markup_urls as $markup => $url ) {
			$clean_markup = sanitize_key( $markup );
			$clean_url = sanitize_text_field( $url );
			if ( $clean_url ) {
				if ( ! isset( $clean[ $clean_slug ] ) ) {
					$clean[ $clean_slug ] = array();
				}
				$clean[ $clean_slug ][ $clean_markup ] = $clean_url;
			}
		}
	}
	return $clean;
}

/**
 * Разбить строку значений атрибута на массив.
 * WooCommerce возвращает термины через «, » (запятая-пробел).
 * Не должна разбивать десятичные запятые вида «1,8».
 *
 * @param string $raw
 * @return string[]
 */
function ssc_split_attr_values( $raw ) {
	$raw = trim( $raw );
	if ( ! $raw ) {
		return array();
	}
	// Разделители: « | » (pipe с пробелами) или «, » (запятая с пробелом).
	// НЕ разбиваем одиночную запятую (десятичный разделитель) или пробел (часть имени).
	return preg_split( '/\s*\|\s*|,\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
}

/**
 * Получить числовое (float) значение из названия термина атрибута.
 * Обрабатывает десятичную запятую («1,8») и единицы измерения («40 м», «40 мм»).
 *
 * @param string $val
 * @return float
 */
function ssc_attr_float( $val ) {
	// Убираем единицы измерения, оставляем цифры, точку и запятую
	$num = preg_replace( '/[^\d,.]/', '', trim( $val ) );
	// Заменяем десятичную запятую на точку
	return floatval( str_replace( ',', '.', $num ) );
}

/**
 * Получить строку для отображения: убрать единицу «м»/«мм» с конца.
 *
 * @param string $val
 * @return string  Только числовая часть («2», «1,8», «40»)
 */
function ssc_attr_display( $val ) {
	return trim( preg_replace( '/\s*мм?\s*$/u', '', trim( $val ) ) );
}

/**
 * Удалить калькулятор.
 *
 * @param string $id
 */
function ssc_delete_calculator( $id ) {
	$calculators = ssc_get_calculators();
	unset( $calculators[ $id ] );
	update_option( SSC_OPTION_KEY, $calculators );
}
