# Sportshop Calculator — контекст для Claude

## Что это

WordPress-плагин для расчёта материалов искусственного газона (спортивных покрытий).
Работает в двух режимах:
- **Страница товара** — калькулятор вставляется как таб через action-хук, данные о рулоне берутся из атрибутов текущего товара WooCommerce
- **Страница категории** — калькулятор вставляется шорткодом `[ssc_calculator id="calc_xxx"]`, пользователь выбирает подкатегорию, фильтрует по атрибутам и выбирает товар через Magnific Popup

### Поток пользователя в режиме категории

1. **Выбор подкатегории** — пользователь выбирает подкатегорию из выпадающего списка
2. **Загрузка атрибутов** — AJAX `ssc_load_subcategory_attrs` — загружаются захардкоженные атрибуты (высота ворса → толщина волокна → количество стежков), рендерятся как чекбоксы
3. **Фильтрация и загрузка товаров** — AJAX `ssc_load_products` с `subcategory_slug` + выбранные фильтры; сервер возвращает `available_filters` для динамического дизейбла чекбоксов
4. **Выбор товара** — кнопка `.ssc-select-product-btn` открывает Magnific Popup со списком карточек; клик по карточке закрывает popup и подставляет параметры рулона
5. **Расчёт** — пользователь вводит размеры площадки и получает расчёт
6. **КП** — форма → email + PDF через pdfMake

## Структура файлов

```
sportshop-calculator/
├── sportshop-calculator.php              # Главный файл: константы, хелперы, CRUD функции, регистрация AJAX-хуков
├── includes/
│   ├── class-ssc-admin.php               # Админ-панель: список калькуляторов, форма настройки, AJAX
│   ├── class-ssc-frontend.php            # Фронтенд: шорткод, рендер, AJAX-обработчики
│   ├── class-ssc-calc-card-widget.php    # Виджет карточки калькулятора (автоматический, по категории)
│   └── class-ssc-calc-cards-admin.php    # Страница настроек карточек по категориям + мультисайт-sync
└── assets/
    ├── css/
    │   ├── calculator.css         # Стили калькулятора + стили карточки .ssc-calc-card
    │   └── admin.css              # Стили админки
    ├── js/
    │   ├── calculator.js          # Логика расчёта, canvas, PDF на фронтенде
    │   └── admin.js               # JS для формы настройки в админке
    └── pdfmake/
        ├── pdfmake.min.js         # Генерация PDF
        └── vfs_fonts.js           # Шрифты для pdfMake (Roboto)
```

## Виджет карточки калькулятора

`SSC_Calc_Card_Widget` — WordPress-виджет без ручных настроек. Определяет текущую категорию товаров (`get_queried_object()`), ищет правило через `ssc_find_card_rule()` с наследованием от родительской категории, рендерит карточку с CSS-классами `.ssc-calc-card`.

Правила настраиваются в **Калькуляторы → Карточки категорий** (`/wp-admin/admin.php?page=ssc-calc-cards`).

Хелпер `ssc_find_card_rule(WP_Term $term): ?array` — рекурсивно поднимается по иерархии `product_cat` до корня. Определён в `sportshop-calculator.php`.

Данные: `wp_options` ключ `ssc_calc_card_rules` — массив `[category_slug => [title, subtitle, tag, bg_image_url, link_url]]`. Категории без записи (и без записи у родителя) карточку не показывают.

**Важно — пути изображений:** `bg_image_url` хранится как относительный путь (`/wp-content/...`). При рендере применяется `home_url($bg_image_url)` для получения абсолютного URL, корректного в том числе при WordPress в подпапке.

CSS (`calculator.css`) подключается на страницах категорий через `is_product_category()` в `enqueue_assets()` — только стили, без скриптов (pdfmake, magnific-popup, calculator.js не нужны на категориях без шорткода).

## Подробная документация

Читай только нужный файл, а не всё сразу:

- **[DOCS/CLAUDE-data.md](DOCS/CLAUDE-data.md)** — структура данных `ssc_calculators` в wp_options, PHP-хелперы (`ssc_split_attr_values`, `ssc_attr_float`, `ssc_attr_display`)
- **[DOCS/CLAUDE-ajax.md](DOCS/CLAUDE-ajax.md)** — все AJAX-обработчики (фронтенд + админка), захардкоженные атрибуты фильтрации, динамический дизейбл чекбоксов
- **[DOCS/CLAUDE-formulas.md](DOCS/CLAUDE-formulas.md)** — расчётные формулы: рулоны, швы, клей, лента, песок, крошка, разметка; UI строк результата
- **[DOCS/CLAUDE-frontend.md](DOCS/CLAUDE-frontend.md)** — методы SSC_Frontend, JS-конфиг `data-config`, режимы category/product, CSS-классы, canvas, pdfMake
- **[DOCS/CLAUDE-admin.md](DOCS/CLAUDE-admin.md)** — изображения canvas в админке, мультисайт/синхронизация, карточки категорий
- **[DOCS/CLAUDE-cache.md](DOCS/CLAUDE-cache.md)** — кеширование через Transient: что кешируется, очистка, debug-логи, методы
- **[DOCS/CLAUDE-gotchas.md](DOCS/CLAUDE-gotchas.md)** — все известные особенности и ловушки (пп. 1–15)
