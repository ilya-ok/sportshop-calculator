# Фронтенд: методы, JS-конфиг, режимы, CSS

## Методы SSC_Frontend (class-ssc-frontend.php)

### Private helpers

```php
make_absolute_url($url): string
// Если $url начинается с http — возвращает как есть.
// Если относительный путь (/wp-content/...) — добавляет home_url().
// Пример: "/wp-content/uploads/a.jpg" → "http://site.ru/wp-content/uploads/a.jpg"

make_absolute_urls($images): array
// Преобразует относительные пути в абсолютные для вложенной структуры canvas_images.
// Формат: ['slug_подкатегории' => ['тип_разметки' => '/path/...']]
// Поддерживает обратную совместимость с плоским массивом.
```

### Рендер

- `render_shortcode($atts)` — точка входа шорткода, вызывает `render_category_calculator()`
- `render_category_calculator($calc)` — полный рендер режима категории (select подкатегорий + filter step + параметры рулона + разметка + размеры + canvas + результаты + форма КП + кнопка тест-PDF)
- `render_product_calculator($calc, $product_id)` — оболочка для страницы товара, вызывает `render_calculator_core()`
- `render_calculator_core($calc, $product)` — параметры рулона + разметка + размеры + canvas + результаты + форма КП (без кнопки тест-PDF)

## JS-конфиг (data-config на .ssc-calculator)

```json
{
    "id": "calc_xxx",
    "mode": "product | category",
    "calcType": "grass | linoleum | sceniclinoleum",
    "categorySlug": "iskusstvennyj-gazon",
    "subcategorySlugs": ["dlya-futbola", "dlya-tennisa"],
    "canvasImages": {
        "dlya-futbola": {
            "none": "https://site.ru/wp-content/uploads/field.jpg",
            "football": "https://site.ru/wp-content/uploads/football.jpg"
        }
    },
    "canvasImage": "https://site.ru/wp-content/uploads/default.jpg",
    "widthAttr": "shirina-rulona",
    "lengthAttr": "dlina-rulona",
    "filterAttrs": ["vysota-vorsa", "cvet"],
    "gluePrice": 4000,
    "tapePrice": 65,
    "sandEnabled": true,
    "sandPrice": 3950,
    "rubberEnabled": true,
    "rubberPrice": 24500,
    "markupEnabled": true,
    "paintPrice": 0,
    "companyName": "...",
    "siteDate": "01.01.2026",
    "product": {
        "id": 123,
        "name": "Название товара",
        "price": 500,
        "widths": [2, 4],
        "lengths": [20, 40],
        "length": 20,
        "defaultWidth": 2,
        "defaultLength": 20,
        "height": 40
    }
}
```

`paintPrice` — цена за банку краски для разметки (только линолеум). `calcType` — тип калькулятора.

`product` присутствует только в режиме `product`. В режиме `category` = `null`.
`canvasImages` и `canvasImage` — уже абсолютные URL (конвертированы PHP-методами).

## Режим category (страница категории)

- **Шаг 0 `.ssc-step--subcategory`** — PHP рендерит `<select class="ssc-subcategory-select">` с подкатегориями из `subcategory_slugs`. Только существующие в базе. **Скрыт для `linoleum` и `sceniclinoleum`** — блок не рендерится вовсе (`$is_linoleum = in_array(type, ['linoleum', 'sceniclinoleum'])`).
- **Шаг 1 `.ssc-step--filter`** — для газона скрыт по умолчанию, для линолеума виден сразу. JS показывает после выбора подкатегории. Внутри: `.ssc-filter-wrap` + `.ssc-select-product-btn` + скрытый `.ssc-products-list`.
- **Кнопка выбора** `.ssc-select-product-btn` открывает Magnific Popup с `.ssc-products-list`.
- **Шаг 2** — список товаров загружается AJAX `ssc_load_products` с фильтрами. Ответ: HTML карточек + available_filters.
- `onProductSelected()` вызывается после клика по карточке; перерисовывает ширины/длины, запускает `calculate()`.

### Авто-загрузка атрибутов для линолеума и сценического линолеума

В `init()`, если `.ssc-subcategory-select` не найден в DOM (т.е. линолеум), JS автоматически запускает `loadCategoryAttrs()`:

```javascript
var $subSelect = this.$wrap.find('.ssc-subcategory-select');
if (!$subSelect.length) {
    var slugs = this.config.subcategorySlugs;
    this.selectedSubcategory = (slugs && slugs.length) ? slugs[0] : (this.config.categorySlug || '');
    if (this.selectedSubcategory) {
        this.loadCategoryAttrs();
    }
}
```

Используется `subcategorySlugs[0]` как приоритет, fallback — `categorySlug`.

### Структура кнопки выбора покрытия

```html
<button class="ssc-select-product-btn" id="ssc-select-product-btn-{id}">
    <span class="ssc-select-product-btn__label">
        <span class="ssc-select-product-btn__text">Выберите покрытие</span>
        <span class="ssc-select-product-btn__count" id="ssc-product-count-{id}">0</span>
    </span>
    <span class="ssc-select-product-btn__arrow"></span>
</button>
```

`.ssc-btn--loading` — класс во время загрузки товаров: repeating-linear-gradient shimmer через `::before`, `pointer-events: none`.

## Режим product (страница товара)

- PHP рендерит радио-кнопки ширины/длины сразу в HTML (без AJAX)
- JS в `init()` читает уже отрендеренные `input[name="ssc_width_{calcId}"]`
- `onProductSelected()` не вызывается
- Нет кнопки «Скачать PDF (тест)»

## CSS-классы (ключевые)

| Класс | Элемент |
|-------|---------|
| `.ssc-calculator` | корневой контейнер, `max-width: 1100px; margin: 0 auto` |
| `.ssc-step` | секция-шаг: `display: flex; align-items: flex-start; gap: 16px` |
| `.ssc-section-title` | заголовок шага: `width: 200px; text-align: right; white-space: nowrap; flex-shrink: 0` + `::after {content:':'}` |
| `.ssc-step__content` | обёртка контента: `flex: 1; max-width: 900px; min-width: 0` |
| `.ssc-step--subcategory` | выбор подкатегории |
| `.ssc-step--filter` | фильтры + кнопка выбора товара (скрыт по умолчанию) |
| `.ssc-subcategory-select` | select подкатегории |
| `.ssc-select-product-btn` | кнопка выбора покрытия (`border: 2px solid #8bc73f; max-width: 600px`) |
| `.ssc-btn--loading` | shimmer-анимация на кнопке во время загрузки |
| `.ssc-products-list` | список карточек в Magnific Popup (`max-height: 600px; overflow-y: auto`) |
| `.ssc-product-card` | карточка товара в 1 строку: img 30×30px → название → атрибуты → цена |
| `.ssc-filter-check--disabled` | дизейблед чекбокс фильтра (`opacity: 0.4; pointer-events: none`) |
| `.ssc-radio-btn` | кнопка выбора ширины/длины/разметки |
| `.ssc-radio-btn--active` | активная кнопка |
| `.ssc-counter` | счётчик +/− (шаг = 1) |
| `.ssc-canvas-wrap` | обёртка canvas `max-width: 600px`, содержит `.ssc-canvas-bg` + `.ssc-canvas` |
| `.ssc-canvas-bg` | фоновое изображение (HTML img, z-index под canvas) |
| `.ssc-canvas` | canvas раскладки 730×365, прозрачный |
| `.ssc-step--active` | добавляется JS к `.ssc-step--roll`, `.ssc-step--dims`, `.ssc-step--results` после выбора товара |
| `.ssc-step--markup` | выбор типа разметки — расположен **после** `.ssc-step--dims` (не до) |
| `.ssc-markup-type-list` | flex-контейнер кнопок разметки; также используется для scenic-радио |
| `.ssc-scenic-group` | обёртка для пары строк (radio + результат); `border-bottom` на группе |
| `.ssc-result-row--scenic-radios` | строка 1: label + radio-кнопки (sub через `grid-column: 2 / -1` занимает все оставшиеся колонки); `border-bottom: none` |
| `.ssc-result-row--scenic-result` | строка 2: пустой label + hint+value + price; скрыта по умолчанию, показывается JS при выборе не-none |
| `.ssc-scenic-sub` | sub-ячейка scenic: `flex-direction: column; gap: 6px` — в строке 1 содержит только `.ssc-markup-type-list`, в строке 2 — hint + value |
| `.ssc-scenic-hint` | подсказка: `font-size: 12px; color: #999; italic`; текст из `data-hint` радио-input'а, записывается JS в строке 2 |
| `.ssc-markup-icon` | иконка внутри кнопки разметки: `img` 17×17px или `span` с ✕ для «Без разметки» |
| `.ssc-markup-icon--none` | крестик ✕ для «Без разметки»; зелёный при активном состоянии |
| `.ssc-result-row` | строка результата |
| `.ssc-res-seams` | длина швов (текст: "горизонтальные: X шт., вертикальные: Y шт."), всегда видна |
| `.ssc-res-total` | итоговая сумма |
| `.ssc-glue-check` | чекбокс «добавить клей» (выключен по умолчанию) |
| `.ssc-tape-check` | чекбокс «добавить шовную ленту» (выключен по умолчанию) |
| `.ssc-sand-check` | чекбокс «добавить песок» |
| `.ssc-rubber-check` | чекбокс «добавить крошку» |

На мобильных (≤768px): `.ssc-step` переключается в `flex-direction: column`, заголовок выравнивается влево.

**Акцентный цвет `#8bc73f`** — используется глобально: активные радио-кнопки и чекбоксы фильтров, активные карточки товаров, цена товара, площадь в результатах, кнопки КП и отправки, `accent-color` чекбоксов, граница итого. Hover: `#6fa32e`. Фон активных элементов: `#eef5e0` / `#f0f5e6`.

**Иконки разметки** — из `/wp-content/calculator/`. Газон: `football.png`, `mini-football.png`, `tennis.png`, `hockey.png`. Линолеум: `volleyball.png`, `basketball.png`, `mini-football.png`. URL формируется через `home_url('/wp-content/calculator/')` в PHP. Для «Без разметки» — `<span class="ssc-markup-icon ssc-markup-icon--none">✕</span>`. Набор иконок определяется `$is_linoleum` в PHP при рендере.

**Canvas** — рисует шахматную раскладку рулонов. Ось X = длина (L), ось Y = ширина (W). При `rL >= L` вертикальные линии не рисуются. Canvas прозрачный (`ctx.clearRect`), фоновое изображение — HTML `<img>` под ним.

**pdfMake** — подключается из `assets/pdfmake/`. Fallback: тема — `/pdfmake/pdfmake.min.js`. Magnific Popup: тема — `/assets/js/jquery.magnific-popup.min.js`, fallback — CDN. Кнопка «Скачать PDF (тест)» есть только в `render_category_calculator()`, в `render_calculator_core()` её нет.
