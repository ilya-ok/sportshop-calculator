# Структура данных и PHP-хелперы

## Структура данных калькулятора (опция `ssc_calculators` в wp_options)

```php
[
    'id'                => 'calc_xxx',
    'name'              => 'Название',
    'calculator_type'   => 'grass',                                  // 'grass' | 'linoleum'
    'category_slug'     => 'slug-kategorii',
    'subcategory_slugs' => ['dlya-futbola', 'dlya-mini-futbola'],  // slug подкатегорий для выбора на фронтенде
    'canvas_images'     => [                                        // вложенная структура: подкатегория → тип разметки → URL (относит. пути)
        'dlya-futbola' => [
            'none'        => '/wp-content/uploads/field-none.jpg',
            'football'    => '/wp-content/uploads/field-football.jpg',
            'mini-football' => '/wp-content/uploads/field-mini.jpg',
            'tennis'      => '/wp-content/uploads/field-tennis.jpg',
            'hockey'      => '/wp-content/uploads/field-hockey.jpg',
        ]
    ],
    'canvas_image'      => '/wp-content/uploads/field-default.jpg', // изображение по умолчанию (относит. путь)
    'hook_name'         => 'ssc_calc_slug_kategorii',               // для action-хука на странице товара
    'width_attr'        => 'shirina-rulona',                        // slug атрибута (без pa_)
    'length_attr'       => 'dlina-rulona',
    'filter_attrs'      => ['vysota-vorsa', 'cvet'],                // атрибуты для карточек товаров (превью)
    'glue_price'        => 4000,                                     // газон: руб за банку 10 кг / линолеум: руб за кг
    'tape_price'        => 65,                                       // газон: руб за м.п. шовной ленты / линолеум: руб за м.п. шнура
    'sand_enabled'      => true,                                     // только для газона
    'sand_price'        => 3950,                                     // руб за тонну
    'rubber_enabled'    => true,                                     // только для газона
    'rubber_price'      => 24500,                                    // руб за тонну
    'markup_enabled'    => true,                                     // показывать выбор типа разметки (газон: Футбол/Теннис/…; линолеум: Волейбол/Баскетбол/Мини-футбол)
    'markup_percent'    => 0,                                        // устаревшее поле, не используется
    'paint_price'       => 0,                                        // линолеум: руб за банку краски для разметки
    'admin_email'       => 'email@example.com',
    'company_name'      => 'Название компании',
]
```

**Важно про `calculator_type`:** Определяет режим расчёта и набор UI-элементов. `'grass'` — газон (по умолчанию, если поле отсутствует). `'linoleum'` — спортивный линолеум. Влияет на: набор фильтров-атрибутов, шаг выбора подкатегории, типы разметки, формулы расчёта, строки результата.

**Важно про `canvas_images`:** Структура вложенная: `{ slug_подкатегории: { тип_разметки: url } }`. Типы разметки: `none`, `football`, `mini-football`, `tennis`, `hockey`. Пути хранятся относительными. Санитизация через `ssc_sanitize_canvas_images()` (в `sportshop-calculator.php`).

**Важно про `filter_attrs`:** используется ТОЛЬКО для превью атрибутов в карточках товаров (`ajax_load_products`). Сами фильтры-чекбоксы берутся из захардкоженного `$attr_definitions`, а не из `filter_attrs`.

## Ключевые хелперы (sportshop-calculator.php)

```php
ssc_split_attr_values($raw)
// Разбивает строку атрибута WooCommerce на массив значений.
// Разделители: «|» или «, » (запятая+пробел).
// НЕ разбивает одиночную запятую в «1,8» (десятичный разделитель).
// Пример: "2 м, 4 м" → ["2 м", "4 м"];  "1,8 м" → ["1,8 м"]

ssc_attr_float($val)
// Возвращает float из строки атрибута. Убирает единицы, заменяет «,» на «.»
// Пример: "40 мм" → 40.0;  "1,8 м" → 1.8

ssc_attr_display($val)
// Убирает суффикс «м»/«мм» для отображения в кнопке.
// Пример: "1,8 м" → "1,8";  "40 мм" → "40"
```

> **Важно:** WooCommerce возвращает атрибуты через `$product->get_attribute('pa_slug')` как строку «значение1, значение2». Десятичные числа пишутся через запятую («1,8 м»). Нельзя использовать `explode(',', ...)` или `/[\s,|]+/` — сломает парсинг.
