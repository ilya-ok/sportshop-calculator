# Кеширование

Все данные фронтенд-запросов кешируются через WordPress Transient (`ssc_calc_cache`, время жизни `DAY_IN_SECONDS` = 24 часа).

## Что кешируется

1. **Атрибуты подкатегории** — ключ `attrs_{subcategory_slug}`. Хранит HTML фильтров + `filter_attrs`.
2. **Список товаров** — ключ `md5(category_slug + JSON(отсортированных фильтров))`. Хранит HTML карточек + `available_filters`.

## Автоматическая очистка

Transient удаляется при хуках:
- `save_post_product`, `edited_product_cat`, `created_product_cat`, `delete_product_cat`
- `created_/edited_/deleted_` для `pa_vysota-vorsa`, `pa_tolshhina-volokna`, `pa_kolichestvo-stezhkov`
- Кнопка «Очистить кеш» в админке и «Очистить кеш калькулятора» на фронтенде

## Отслеживание кеша (debug)

В каждый ответ AJAX добавляется поле `"source": "cache" | "db"`.
JS (`calculator.js`) выводит в консоль:
- 🟢 `[SSC] Товары загружены из кеша ⚡` или `[SSC] Товары загружены из базы данных`
- 🔵 `[SSC] Атрибуты загружены из кеша ⚡` или `[SSC] Атрибуты загружены из базы данных`

## Приватные методы кеширования (SSC_Frontend)

```php
get_cache(): array          // Возвращает массив из transient, либо {attrs:[], products:[]}
save_cache($data): void     // set_transient(..., DAY_IN_SECONDS)
```
