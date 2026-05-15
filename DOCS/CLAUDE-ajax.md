# AJAX-обработчики

## Фронтенд (class-ssc-frontend.php + sportshop-calculator.php)

| action | nonce | где зарегистрирован | описание |
|--------|-------|---------------------|----------|
| `ssc_load_subcategory_attrs` | `ssc_frontend_nonce` | `sportshop-calculator.php` (глобально) | Загрузить атрибуты выбранной подкатегории (HTML фильтров + filter_attrs для JS) |
| `ssc_load_products` | `ssc_frontend_nonce` | `SSC_Frontend::__construct()` | Загрузить товары подкатегории с фильтрацией; возвращает HTML + available_filters |
| `ssc_send_kp` | `ssc_frontend_nonce` | `SSC_Frontend::__construct()` | Отправить заявку КП на email |

> **Важно:** `ssc_load_subcategory_attrs` зарегистрирован в `sportshop-calculator.php` (не в конструкторе SSC_Frontend), чтобы избежать конфликта singleton с одноимённым методом в классе (вызывает `SSC_Frontend::get_instance()->ajax_load_category_attrs()`).

## Админка (class-ssc-admin.php)

| action | nonce | описание |
|--------|-------|----------|
| `ssc_load_category_attrs` | `ssc_admin_nonce` | Загрузить атрибуты родительской категории (для select'ов в форме) |
| `ssc_save_calculator` | `ssc_admin_nonce` | Сохранить/создать калькулятор |
| `ssc_delete_calculator` | `ssc_admin_nonce` | Удалить калькулятор |
| `ssc_get_calculator` | `ssc_admin_nonce` | Получить данные калькулятора для редактирования |
| `ssc_sync_calculator` | `ssc_admin_nonce` | Синхронизировать калькулятор со всеми сайтами мультисайта |

## Захардкоженные атрибуты фильтрации

В `ajax_load_category_attrs()` (фронтенд) и `ajax_load_products()` `$attr_definitions` зависит от `calculator_type`:

**Газон (`grass`, по умолчанию):**
```php
$attr_definitions = [
    'vysota-vorsa'         => 'pa_vysota-vorsa',
    'tolshhina-volokna'    => 'pa_tolshhina-volokna',
    'kolichestvo-stezhkov' => 'pa_kolichestvo-stezhkov',
];
```

**Линолеум (`linoleum`) и сценический линолеум (`sceniclinoleum`):**
```php
$attr_definitions = [
    'tolshhina'                   => 'pa_tolshhina',
    'tolshhina-zashhitnogo-sloya' => 'pa_tolshhina-zashhitnogo-sloya',
    'linejka'                     => 'pa_linejka',
];
```

Условие: `in_array($calc['calculator_type'] ?? 'grass', ['linoleum', 'sceniclinoleum'], true)`. Одиночная проверка `=== 'linoleum'` **сломает загрузку для scenic**.

Порядок строго фиксирован. `filter_attrs` из настроек калькулятора игнорируется для фильтров-чекбоксов.

## Динамическое дизейбливание фильтров

`ajax_load_products` возвращает `available_filters` — для каждого атрибута массив slug'ов доступных значений по текущим товарам. JS `updateFilterAvailability()` добавляет `.ssc-filter-check--disabled` (opacity 0.4, pointer-events none) на недоступные чекбоксы.

> **Ловушка:** при формировании `available_filters` каждый массив значений прогоняется через `array_values()`, чтобы JSON-энкодер отдал `[]` вместо объекта `{}`. Без этого JS получит `{"0":"val"}` вместо `["val"]` и `indexOf()` сломается.
