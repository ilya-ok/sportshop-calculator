# QWEN.md — История изменений плагина Sportshop Calculator

> Файл содержит полную историю всех изменений, сделанных за сессию. Создан для того, чтобы любая ИИ могла быстро включиться в контекст и продолжить разработку.

---

## Общая информация о плагине

**Sportshop Calculator** — WordPress-плагин для расчёта материалов искусственного газона (спортивных покрытий).

### Структура файлов
```
sportshop-calculator/
├── sportshop-calculator.php       # Главный файл: константы, хелперы, CRUD функции, регистрация AJAX-хуков
├── claude.md                      # Контекстная документация
├── includes/
│   ├── class-ssc-admin.php        # Админ-панель: список калькуляторов, форма настройки, AJAX
│   └── class-ssc-frontend.php     # Фронтенд: шорткод, рендер, AJAX-обработчики
└── assets/
    ├── css/
    │   ├── calculator.css         # Стили калькулятора на фронтенде
    │   └── admin.css              # Стили админки
    ├── js/
    │   ├── calculator.js          # Логика расчёта, canvas, PDF на фронтенде
    │   └── admin.js               # JS для формы настройки в админке
    └── pdfmake/
        ├── pdfmake.min.js         # Генерация PDF
        └── vfs_fonts.js           # Шрифты для pdfMake
```

### Два режима работы
- **Страница товара** — калькулятор вставляется как таб через action-хук, данные о рулоне берутся из атрибутов текущего товара WooCommerce
- **Страница категории** — шорткод `[ssc_calculator id="calc_xxx"]`, пользователь выбирает подкатегорию → фильтры по атрибутам → товар → расчёт

---

## Все изменения за сессию (в хронологическом порядке)

### 20. Заголовки секций вынесены влево с двоеточием

**Решение:**
- `.ssc-step` → `display: flex; align-items: flex-start; gap: 16px;`
- `.ssc-section-title` → `text-align: right; white-space: nowrap; width: 200px; flex-shrink: 0`
- `.ssc-section-title::after` → добавляет двоеточие `:`
- **`.ssc-step__content`** → новая обёртка `flex: 1; max-width: 800px; min-width: 0`, содержит весь контент секции
- Мобильная версия: `flex-direction: column`, заголовок влево, `width: auto`
- Возвращены заголовки "Параметры покрытия" (показывается JS при загрузке чекбоксов) и "Параметры рулона"
- **Все** блоки шаг: подкатегория, фильтры, параметры рулона, разметка, размеры, результаты

**Файлы:** `class-ssc-frontend.php` (оба рендера), `calculator.css`, `calculator.js`

### 21. Ограничение ширины калькулятора 1100px, центрирование

**Решение:** `.ssc-calculator` получил `max-width: 1100px; margin: 0 auto`. `.ssc-step__content` → `max-width: 800px`.

**Файлы:** `calculator.css`

### 22. Окно выбора товаров в Magnific Popup

**Решение:**
- Кнопка `.ssc-select-product-btn` открывает `.ssc-products-list` в Magnific Popup
- Подключён Magnific Popup из CDN (fallback из темы)
- Popup стилизован: `background: #fff; padding: 10px 10px 15px; border-radius: 5px`
- При выборе товара: popup закрывается, страница прокручивается к параметрам рулона
- Кнопка показывает название выбранного товара и счётчик доступных товаров
- **Структура кнопки:** `.ssc-select-product-btn__label` (текст + счётчик) + `.ssc-select-product-btn__arrow` (стрелка вниз через CSS border + rotate)
- **Стили кнопки:** `border: 2px solid #8bc73f`, `background: #eef5e0`, hover `#e4f0d4`
- **Анимация загрузки (`.ssc-btn--loading`):**
  - Фон — `::before` псевдоэлемент с `repeating-linear-gradient` (32 чёткие полосы, `blur(2px)`)
  - Анимация `ssc-btn-shimmer` — плавное движение градиента за 3s (настраивается)
  - `pointer-events: none` — кнопка не кликабельна во время загрузки
  - Текст, счётчик и стрелка остаются чёткими (`z-index: 1`)

**Файлы:** `class-ssc-frontend.php`, `calculator.js`, `calculator.css`

### 23. Относительные URL изображений canvas

**Проблема:** Относительный путь `/wp-content/...` на странице товара резолвился в `http://localhost/wp-content/...` вместо `http://localhost/sportshop/wp-content/...`.

**Решение:**
- Добавлен метод `make_absolute_url()` — преобразует `/wp-content/...` → `home_url('/wp-content/...')`
- Добавлен метод `make_absolute_urls()` — для массива `canvas_images`
- Применяется к `$canvas_image` в обоих рендерах и к `canvasImages`/`canvasImage` в JS config

**Файлы:** `class-ssc-frontend.php`

### 24. Площадь округляется до целых

**Решение:** Все `.toFixed(2)` для площади заменены на `Math.round()`:
- Счётчик площади на странице
- Результаты расчёта (`.ssc-res-area`)
- Скрытые поля формы
- PDF-таблица

**Файлы:** `calculator.js`

### 25. Canvas ограничен 600px, кнопка 600px

**Решение:**
- `.ssc-canvas-wrap` → `max-width: 600px`
- `.ssc-select-product-btn` → `max-width: 600px`
- `.ssc-counter__btn` → `display: flex; justify-content: center; align-items: center`
- `.ssc-counter`, `.ssc-radio-btn` → `border: 1px solid #c3c4c7`
- `.ssc-dims-eq` → `font-size: 20px`
- `.ssc-area-result` → `font-size: 20px`
- `.ssc-prod-img` → `30×30px`

**Файлы:** `calculator.css`

---

### 1. Выбор подкатегории на фронтенде (режим category)

**Проблема:** При загрузке страницы калькулятора в режиме категории выводились все товары сразу.

**Решение:**
- В `render_category_calculator()` (class-ssc-frontend.php) добавлен `<select>` с подкатегориями из `subcategory_slugs` настроек калькулятора
- Шаг фильтров/товаров скрыт по умолчанию (`style="display:none"`)
- JS (`calculator.js`) не загружает товары при инициализации — ждёт выбора категории
- `subcategory_slugs` — поле в админке (textarea, каждый slug с новой строки), на фронтенде рендерится `<select>` только из существующих категорий

**Файлы:** `class-ssc-frontend.php`, `calculator.js`, `admin.js`

---

### 2. Динамическая загрузка атрибутов при выборе подкатегории

**Проблема:** Атрибуты фильтрации брались из настроек калькулятора статично, не учитывая выбранную подкатегорию.

**Решение:**
- Новый AJAX-обработчик `ssc_load_subcategory_attrs` (фронтенд) — при выборе подкатегории:
  1. Берёт `filter_attrs` из настроек калькулятора
  2. Получает товары выбранной подкатегории
  3. Для каждого атрибута из `filter_attrs` собирает ТОЛЬКО те значения, которые реально есть у товаров этой подкатегории
  4. Возвращает HTML чекбоксов
- JS заменяет содержимое блока фильтров и загружает товары с учётом новых фильтров

**Важно:** AJAX-хук `ssc_load_subcategory_attrs` зарегистрирован в `sportshop-calculator.php` напрямую, чтобы избежать конфликта с админским `ssc_load_category_attrs` (разные nonce).

**Файлы:** `class-ssc-frontend.php`, `calculator.js`, `sportshop-calculator.php`

---

### 3. Админка — атрибуты грузятся из родительской категории

**Решение:**
- `ajax_load_category_attrs` (class-ssc-admin.php) ищет атрибуты в родительской категории (и всех её дочерних)
- **Захардкоженные атрибуты** в строгом порядке: высота ворса → толщина волокна → количество стежков
- Не зависит от `subcategory_slugs` — ищет только по `category_slug`
- Поддержка и taxonomy, и custom атрибутов
- `filter_attrs` из настроек калькулятора **больше не используется** для фронтенд-фильтров
- Оба AJAX-обработчика (`ssc_load_products` и `ssc_load_subcategory_attrs`) используют одинаковый захардкоженный список
- `array_values()` для каждого массива — гарантирует JSON-массивы `[]` вместо объектов `{}`

**Файлы:** `class-ssc-admin.php`, `class-ssc-frontend.php`, `admin.js`

---

### 4. Удалена строка "в т.ч. материал разметки (+3%)" из результатов

**Решение:** Удалён HTML-блок `.ssc-res-row-markup` из PHP и JS-обработка его toggle. Расчёт разметки полностью сохранён — разметка добавляется к площади, стоимости травы, длине швов и расходу клея/ленты.

**Файлы:** `class-ssc-frontend.php`, `calculator.js`

---

### 5. Добавлен текст "+ 5% на подрез" в строку шовной ленты

**Решение:** В строке `.ssc-res-tape` добавлен текст `- Длина швов + 5% на подрез`. Сохранена стоимость в скобках.

**Файлы:** `calculator.js`

---

### 6. Цена в строках песка и резиновой крошки

**Решение:** Добавлена стоимость в скобках для песка и резиновой крошки: `X кг (~X руб.)`

**Файлы:** `calculator.js`

---

### 7. Объединение строк клея и шовной ленты с чекбоксами

**Проблема:** Были отдельные строки с расчётом клея/ленты и отдельные строки с чекбоксами «Добавить клей»/«Добавить шовную ленту».

**Решение:** Объединены — теперь в строке клея и ленты есть чекбокс в начале (как у песка/крошки).

**Файлы:** `class-ssc-frontend.php`

---

### 8. Чекбоксы клея и шовной ленты выключены по умолчанию

**Решение:** Убран атрибут `checked` у `.ssc-glue-check` и `.ssc-tape-check`.

**Файлы:** `class-ssc-frontend.php`

---

### 9. Двухколоночная раскладка калькулятора

**Структура:**
- **Левая колонка:** подкатегория, фильтры + товары, параметры рулона
- **Правая колонка:** разметка поля, размеры площадки + canvas, результаты расчёта

**Решение:**
- PHP: обёртка `<div class="ssc-cols">` с двумя `<div class="ssc-col ssc-col--left">` и `<div class="ssc-col ssc-col--right">`
- CSS: `display: grid; grid-template-columns: 1fr 1fr; gap: 16px`
- Адаптивность: на ≤768px — одна колонка

**Файлы:** `class-ssc-frontend.php`, `calculator.css`

---

### 10. Убран max-width у .ssc-calculator

**Решение:** Удалено `max-width: 860px`. Калькулятор занимает всю ширину контейнера.

**Файлы:** `calculator.css`

---

### 11. Шаг счётчиков размеров площадки = 1

**Решение:** Кнопки +/− меняют значение на 1 (было 0.5). `step="1"` в input, JS `val + 1`.

**Файлы:** `class-ssc-frontend.php`, `calculator.js`

---

### 12. Компактные стили калькулятора

**Изменения:**
- Шрифт калькулятора: 15px → 13px
- Заголовки: 15px → 12px, цвет #888
- Отступы секций: 24px/20px → 12px/10px
- Gap колонок: 24px → 16px
- Радио-кнопки: padding 5px 16px → 3px 10px, border 2px → 1px
- Счётчики: 34×38px → 26×30px
- Результаты: padding 7px → 4px, шрифт 14px → 12px
- Итого: 22px → 18px
- Кнопка КП: padding 11px 28px → 7px 20px
- Модалка: padding 28px 32px → 20px 24px
- **Все шрифты не менее 12px**

**Файлы:** `calculator.css`

---

### 13. Карточка товара (ssc-product-card) в одну строку

**Изменения:**
- `width: 100%` (было 50%)
- Список товаров — вертикальный, `max-height: 600px`, `overflow-y: auto`
- Порядок в строке: картинка (30×30px) → название → атрибуты → цена
- Всё в одну строку с `flex-wrap: nowrap`
- Название: `text-overflow: ellipsis` если не помещается
- Цена: `flex-shrink: 0` (всегда справа)
- Атрибуты: без высоты ворса, только значения (без названий)

**Файлы:** `class-ssc-frontend.php`, `calculator.css`

---

### 14. Акцентный цвет заменён с #2271b1 на #8bc73f

**Изменения:** Все синие акцентные цвета заменены на зелёный `#8bc73f`:
- Активные радио-кнопки и чекбоксы фильтров
- Карточки товаров (активное состояние)
- Цена товара
- Площадь в результатах
- Кнопки КП и отправки формы
- Чекбоксы (`accent-color`)
- Граница итого
- Hover кнопок: `#6fa32e`
- Фон активных элементов: `#eef5e0` / `#f0f5e6`

**Файлы:** `calculator.css`

---

### 15. Поле subcategory_slugs в структуре данных

**Решение:**
- Новое поле в `ssc_save_calculator()`: `subcategory_slugs` (массив)
- В админке: textarea, каждый slug с новой строки
- В admin.js: сохранение, загрузка при редактировании, валидация
- На фронтенде: `<select>` рендерится только из существующих категорий

**Файлы:** `sportshop-calculator.php`, `class-ssc-admin.php`, `admin.js`, `class-ssc-frontend.php`

---

### 16. Поле canvas_images — изображения для каждой подкатегории

**Решение:**
- Новое поле `canvas_images` — ассоциативный массив `{ slug: url }`
- В админке: динамические поля появляются при вводе slug'ов в `subcategory_slugs`
- Для каждой подкатегории: label с именем, input URL, кнопка выбора из медиа-библиотеки, превью
- **Изображение по умолчанию** (`canvas_image`) — показывается при загрузке страницы и если для подкатегории не задано своё изображение
- Все пути сохраняются **относительными** (`/wp-content/uploads/...`)
- На фронтенде: при выборе подкатегории canvas-bg меняется автоматически
- Canvas полностью прозрачный (`ctx.clearRect`) — изображение видно под линиями раскладки

**Файлы:** `sportshop-calculator.php`, `class-ssc-admin.php`, `admin.js`, `class-ssc-frontend.php`, `calculator.js`, `calculator.css`, `admin.css`

### 17. Динамическое дизейбливание недоступных значений фильтров

**Проблема:** После выбора одного атрибута (например, 40 мм высоты ворса) остальные фильтры позволяли выбрать комбинации, которым не соответствует ни один товар.

**Решение:**
- Сервер (`ajax_load_products`) возвращает `available_filters` — для каждого атрибута массив slug'ов значений, которые есть у оставшихся товаров
- JS `updateFilterAvailability()` дизейблит чекбоксы, значения которых нет в `available_filters`
- `.ssc-filter-check--disabled` — `opacity: 0.4`, `pointer-events: none`
- При снятии фильтра — все чекбоксы активируются обратно
- Пустой массив `[]` = атрибут не применим к этим товарам → активируем все чекбоксы

**Файлы:** `class-ssc-frontend.php`, `calculator.js`

### 18. Захардкоженные атрибуты: высота ворса → толщина волокна → количество стежков

**Решение:**
- `$attr_definitions` в обоих AJAX-обработчиках:
  ```php
  'vysota-vorsa'       => 'pa_vysota-vorsa',
  'tolshhina-volokna'  => 'pa_tolshhina-volokna',
  'kolichestvo-stezhkov' => 'pa_kolichestvo-stezhkov',
  ```
- Порядок строго зафиксирован, `filter_attrs` из настроек игнорируется

**Файлы:** `class-ssc-frontend.php`

### 19. Кнопка выбора покрытия с Magnific Popup

(См. пункт 0 выше — добавлено позже)

### 20. Убраны заголовки "Параметры покрытия" и "Параметры рулона"

**Решение:** Удалены `<h3 class="ssc-section-title">` для этих секций из `render_category_calculator()` и `render_calculator_core()`, а также из AJAX-обработчика `ajax_load_category_attrs()`.

**Файлы:** `class-ssc-frontend.php`

### 21. Ограничение ширины и центрирование калькулятора

**Решение:** `.ssc-calculator` получил `max-width: 800px; margin: 0 auto;`.

**Файлы:** `calculator.css`

---

## Структура данных калькулятора (актуальная)

```php
[
    'id'              => 'calc_xxx',
    'name'            => 'Название',
    'category_slug'   => 'iskusstvennyj-gazon',
    'subcategory_slugs' => ['dlya-futbola', 'dlya-tennisa', ...],  // slug подкатегорий
    'canvas_images'   => [                                         // изображения для подкатегорий (относит. пути)
        'dlya-futbola' => '/wp-content/uploads/field-football.jpg',
        'dlya-tennisa' => '/wp-content/uploads/field-tennis.jpg',
    ],
    'canvas_image'    => '/wp-content/uploads/field-default.jpg',   // изображение по умолчанию
    'hook_name'       => 'ssc_calc_slug_kategorii',
    'width_attr'      => 'shirina-rulona',
    'length_attr'     => 'dlina-rulona',
    'filter_attrs'    => ['vysota-vorsa', 'cvet'],
    'glue_price'      => 4000,
    'tape_price'      => 65,
    'sand_enabled'    => true,
    'sand_price'      => 3950,
    'rubber_enabled'  => true,
    'rubber_price'    => 24500,
    'markup_enabled'  => true,
    'markup_percent'  => 0,  // устаревшее, не используется
    'admin_email'     => 'email@example.com',
    'company_name'    => 'Название компании',
]
```

## AJAX-обработчики

### Фронтенд (class-ssc-frontend.php + sportshop-calculator.php)
| action | nonce | описание |
|--------|-------|----------|
| `ssc_load_subcategory_attrs` | `ssc_frontend_nonce` | Загрузить атрибуты выбранной подкатегории (HTML фильтров) |
| `ssc_load_products` | `ssc_frontend_nonce` | Загрузить товары подкатегории с фильтрацией |
| `ssc_send_kp` | `ssc_frontend_nonce` | Отправить заявку КП на email |

**Новые методы `SSC_Frontend`:**
- `make_absolute_url($url)` — преобразует `/wp-content/...` → `home_url('/wp-content/...')`
- `make_absolute_urls($images)` — для массива `canvas_images`

### Админка (class-ssc-admin.php)
| action | nonce | описание |
|--------|-------|----------|
| `ssc_load_category_attrs` | `ssc_admin_nonce` | Загрузить атрибуты родительской категории |
| `ssc_save_calculator` | `ssc_admin_nonce` | Сохранить/создать калькулятор |
| `ssc_delete_calculator` | `ssc_admin_nonce` | Удалить калькулятор |
| `ssc_get_calculator` | `ssc_admin_nonce` | Получить данные калькулятора |

## Поток пользователя в режиме category

1. **Выбор подкатегории** — `<select>` с категориями из `subcategory_slugs`
2. **Загрузка атрибутов** — AJAX `ssc_load_subcategory_attrs` → чекбоксы `filter_attrs` с реальными значениями товаров
3. **Фильтрация и загрузка товаров** — AJAX `ssc_load_products` с `subcategory_slug` + фильтры
4. **Выбор товара** — клик карточки → подстановка ширины/длины рулона
5. **Расчёт** — ввод размеров → canvas + результаты
6. **КП** — форма → email + PDF

## CSS-классы (ключевые)

| Класс | Элемент |
|-------|---------|
| `.ssc-step` | Секция-шаг (flex: заголовок слева + контент справа) |
| `.ssc-step__content` | Обёртка контента шага (max-width: 800px) |
| `.ssc-step--subcategory` | Выбор подкатегории |
| `.ssc-step--filter` | Фильтры + кнопка выбора товара |
| `.ssc-subcategory-select` | Select подкатегории |
| `.ssc-select-product-btn` | Кнопка выбора покрытия (открывает popup) |
| `.ssc-select-product-btn__count` | Badge-счётчик товаров на кнопке |
| `.ssc-radio-btn` | Кнопка выбора |
| `.ssc-radio-btn--active` | Активная кнопка |
| `.ssc-counter` | Счётчик +/− |
| `.ssc-product-card` | Карточка товара |
| `.ssc-prod-info` | Контейнер инфо товара (1 строка: имя → атрибуты → цена) |
| `.ssc-canvas-bg` | Фоновое изображение (под canvas) |
| `.ssc-canvas` | Canvas раскладки (поверх bg) |
| `.ssc-result-row` | Строка результата |
| `.ssc-total` | Итоговая сумма |

## Известные особенности

1. `pa_vysota-vorsa` захардкожен для высоты ворса. Если slug отличается — песок/крошка не считаются.
2. Canvas прозрачный (`ctx.clearRect`), фоновое изображение — HTML `<img>` под ним (`z-index: 1`). При `rL >= L` вертикальные швы не рисуются.
3. pdfMake подключается из плагина, fallback из темы.
4. `markup_percent` устарел. Стоимость разметки = `markupArea × grassPrice`.
5. Атрибуты в карточках товаров: только из `filter_attrs`, без высоты ворса, только значения.
6. Пути canvas_images — относительные. На фронтенде конвертируются в абсолютные через `make_absolute_url()`.
7. Хук `ssc_load_subcategory_attrs` зарегистрирован в `sportshop-calculator.php`, а не в конструкторе — чтобы избежать конфликтов singleton.
8. Заголовки секций (`.ssc-section-title`) имеют фиксированную ширину 200px и выровнены вправо с двоеточием через `::after`. Контент обёрнут в `.ssc-step__content` (max-width: 800px).
9. На мобильных (≤768px) секции переключаются в `flex-direction: column`, заголовок выравнивается влево.
