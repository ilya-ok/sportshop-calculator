# Известные особенности и ловушки

1. **Захардкоженный slug высоты ворса** — `pa_vysota-vorsa` в `get_calc_js_config()` и в `$attr_definitions`. Если slug отличается — `height: 0`, песок/крошка не считаются, фильтр не появится.

2. **`ssc_load_subcategory_attrs` vs `ssc_load_category_attrs`** — фронтенд AJAX-экшн называется `ssc_load_subcategory_attrs`. Метод класса называется `ajax_load_category_attrs()`. Это одна функция с разными именами. Не пересекается с admin-экшном `ssc_load_category_attrs`.

3. **Canvas** — рисует шахматную раскладку рулонов. Ось X = длина (L), ось Y = ширина (W). При `rL >= L` вертикальные линии не рисуются. Canvas прозрачный, фоновое изображение — HTML `<img>` под ним.

4. **pdfMake** — подключается из `assets/pdfmake/`. Fallback: тема — `/pdfmake/pdfmake.min.js`. Magnific Popup: тема — `/assets/js/jquery.magnific-popup.min.js`, fallback — CDN.

5. **`markup_percent`** — устаревшее поле в БД, сохраняется, но не используется. Стоимость разметки = `markupArea × grassPrice` (тот же продукт).

6. **`filter_attrs`** — хранится в настройках, но для фильтров-чекбоксов НЕ используется. Только для превью атрибутов в карточках товаров (без `vysota-vorsa`, только значения без меток).

7. **`canvas_images`** — пути хранятся относительными. PHP конвертирует при передаче в JS-конфиг через `make_absolute_url()`. JS дополнительно страхуется: `imgUrl.indexOf('http') === 0 ? imgUrl : g.siteUrl + imgUrl`.

8. **Кнопка «Скачать PDF (тест)»** — есть только в `render_category_calculator()`. В `render_calculator_core()` (страница товара) её нет.

9. **`array_values()` для PHP-массивов атрибутов** — при формировании `available_filters` каждый массив значений прогоняется через `array_values()`, чтобы JSON-энкодер отдал `[]` вместо объекта `{}`. Без этого JS получит `{"0":"val"}` вместо `["val"]` и `indexOf()` сломается.

10. **Админка — динамические поля `canvas_images`** — `admin.js` слушает изменения в textarea `subcategory_slugs`. При каждом изменении парсит slug'и по строкам и динамически рендерит поля. Поля появляются в блоке `.ssc-canvas-images-wrap`.

11. **Результаты — строки:**
   - **Площадь**: `Math.round()` (не toFixed).
   - **Швы**: одна строка `.ssc-res-seams` с текстом `"горизонтальные: X шт., вертикальные: Y шт."`. Отдельная строка для швов разметки удалена.
   - **Шовная лента**: текст `"X м.п. +5% подрез"`.
   - **Клей**: текст `"X банок (Y кг)"`.
   - **Песок/крошка**: текст `"X кг"`.

12. **Чекбоксы доп. материалов** — `.ssc-result-val` скрыт по умолчанию (`display:none`). Управляется `syncCheckboxRows()`, который вызывается при каждом `updateResultsUI` и при смене чекбокса.

13. **Иконки в кнопках разметки** — изображения из `/wp-content/calculator/`: `football.png`, `mini-football.png`, `tennis.png`, `hockey.png`. URL формируется через `home_url('/wp-content/calculator/')` в PHP.

14. **Синхронизация настроек** — при сохранении калькулятора на одном сайте сети, можно применить эти настройки ко всем остальным сайтам через кнопку в админке. Удобно для управления одинаковыми калькуляторами в разных городах.

15. **Изображения для разметки** — фон canvas меняется не только при выборе подкатегории, но и при смене типа разметки (Футбол, Теннис и т.д.), если для них заданы разные пути в `canvas_images`.

16. **`calculator_type` отсутствует у старых калькуляторов** — поле появилось позже. Везде читается через `$calc['calculator_type'] ?? 'grass'`. Если калькулятор создан до добавления поля — он работает как газон по умолчанию. Для переключения на линолеум нужно открыть калькулятор в админке, сменить тип и **пересохранить**.

17. **Кеш при смене слага атрибута** — кеш `ssc_calc_cache` хранит результаты по ключу `attrs_{subcategory_slug}`. Если слаг атрибута WooCommerce менялся (напр., `lineika` → `linejka`), старый кеш вернёт пустые атрибуты. Нужно сбросить кеш через кнопку "Сброс кеша" в таблице калькуляторов (или phpMyAdmin: удалить `_transient_ssc_calc_cache`).

18. **`paint_price` нужно добавлять в `ssc_save_calculator()`** — функция в `sportshop-calculator.php` формирует жёсткий массив полей. Любое новое поле, которое передаётся через `ajax_save()`, должно быть также добавлено в `ssc_save_calculator()`, иначе оно молча отбросится при сохранении.

19. **Разметка линолеума не влияет на материал** — `paintCans` и `paintCost` не добавляются к рулонам, швам или площади. В итог входят автоматически (без чекбокса), в отличие от клея и шнура.

20. **`sceniclinoleum` везде проверять через `in_array`** — любая проверка `calculator_type === 'linoleum'` должна быть `in_array(type, ['linoleum', 'sceniclinoleum'])`. Это касается: `$is_linoleum` в PHP-рендере, `$attr_definitions` в ajax-обработчиках, `calcType` в JS. Одиночная проверка `=== 'linoleum'` для scenic сломает загрузку фильтров, отображение шагов и расчёт.

21. **scenic radio-кнопки: нет `data-hint`** — хинты (`ssc-scenic-hint`) и атрибуты `data-hint` полностью удалены. Строка 2 показывается только через `toggle(val !== 'none')` при смене radio. Метки кнопок: «Скотч» (без уточнения тип).

22. **scenic: двухстрочная структура результата** — каждая секция (Основание / Склейка швов) рендерится двумя строками внутри `.ssc-scenic-group`. Строка 1 (`.ssc-result-row--scenic-radios`): label + radio-кнопки. Строка 2 (`.ssc-result-row--scenic-result`): пустой label + value + price; скрыта (`display:none`) пока выбрано «Без основания» / «Без склейки». JS показывает через `toggle(val !== 'none')` в `bindEvents` и `_updateResultsScenic`.

23. **scenic: 5 отдельных ценовых настроек** — у каждого материала своя пара полей цена+объём. Только `glue_price`/`glue_volume` общие с газоном/линолеумом. Остальные 4 пары — scenic-only: `scenic_base_tape_price/volume`, `scenic_seam_cord_price/volume`, `scenic_seam_tape_price/volume`, `scenic_seam_weld_price/volume`. В админке скрыты через класс `ssc-scenic-only` (показываются только при `calculator_type === 'sceniclinoleum'` через admin.js).

24. **`ssc-scenic-only` / `ssc-simple-only` / `ssc-not-simple` в admin.js** — обработчик `#ssc-calc-type change` управляет тремя новыми классами видимости. `ssc-scenic-only` — только для `sceniclinoleum`. `ssc-simple-only` — только для `simple`. `ssc-not-simple` — скрыто для `simple` (tape_price, tape_volume, markup_enabled). Логика: для `simple` скрывается всё что не нужно (grass-only, linoleum-only, not-simple), показывается simple-only + simple-label.

25. **`simple` тип: `$is_linoleum` включает `simple`** — в `render_category_calculator` и `render_calculator_core` переменная `$is_linoleum` проверяет `in_array(type, ['linoleum', 'sceniclinoleum', 'simple'])`. Это даёт simple те же шаги что linoleum (нет выпадающего списка подкатегорий, фильтр активен сразу).

26. **`simple` без rolls: блок «Параметры рулона» скрыт в PHP** — в обоих методах рендера (`render_category_calculator` и `render_calculator_core`) блок `ssc-roll-params-wrap` обёрнут в `<?php if ( $calc['calculator_type'] !== 'simple' || ! empty( $calc['simple_rolls_enabled'] ) ) : ?>`.

27. **`simple` без rolls: `calculate()` не ждёт rW/rL** — переменная `needRoll = !(calcType === 'simple' && !simpleRollsEnabled)`. Проверка `if (!L || !W || (needRoll && (!rW || !rL))) return;` пропускает требование рулона. Шаг `.ssc-step--dims` активируется сразу в `init()`.

28. **`simple_rolls_enabled` и `simple_glue_enabled` в `ssc_save_calculator()`** — оба поля нужно добавлять в жёсткий массив в `sportshop-calculator.php` (Gotcha #18). Уже добавлены.

29. **Условие «Параметры рулона» в `render_category_calculator` было инвертировано** — строка с `if ( empty( $calc['simple_rolls_enabled'] ) || $calc['calculator_type'] !== 'simple' )` показывала блок именно когда галочка выключена. Правильное условие: `if ( $calc['calculator_type'] !== 'simple' || ! empty( $calc['simple_rolls_enabled'] ) )` — одинаковое с `render_calculator_core`.

30. **Nonce-проверки сломают публичные AJAX-эндпоинты при серверном кеше** — `ssc_load_subcategory_attrs` и `ssc_load_products` — read-only публичные эндпоинты. Если хостинг кеширует страницы (Beget nginx), неавторизованный пользователь получает HTML с nonce авторизованного пользователя, и `wp_verify_nonce` падает. Решение: убрать nonce-проверку из `ajax_load_category_attrs()` и `ajax_load_products()`. Nonce оставить только в `ajax_send_kp()` (отправка email).

31. **PowerShell `WriteAllText` пишет BOM** — `[System.IO.File]::WriteAllText(file, content, [System.Text.Encoding]::UTF8)` добавляет UTF-8 BOM (EF BB BF). PHP выводит эти 3 байта до старта скрипта → WordPress: «Cookies заблокированы из-за неожиданного вывода». Всегда использовать `New-Object System.Text.UTF8Encoding $false` или `WriteAllBytes`.

32. **Сортировка атрибутов фильтра — числовая через `usort`** — `get_terms` с `orderby => 'name'` даёт лексикографический порядок MySQL («10» < «2»), `orderby => 'term_order'` — порядок вставки. Ни то, ни другое не совпадает с WC-админкой. Правильно: после фильтрации `$active_terms` сортировать через `usort` с `floatval($a->name) <=> floatval($b->name)`. При равном числовом значении — `strcmp`.

33. **`available_filters` — conjunctive facets** — изначально `available_filters` строился из товаров отфильтрованных по ВСЕМ выбранным атрибутам. Из-за этого после выбора, например, толщины=6мм, сервер возвращал `available_filters['tolshhina'] = ['6мм']` — и все остальные значения толщины дизейблились. Правильная логика: для каждого атрибута `X` считать доступные значения из товаров, отфильтрованных по ВСЕМ атрибутам КРОМЕ `X`. Реализовано через N отдельных `get_posts` запросов в цикле по `$attr_definitions`, каждый с `$tax_query` без фильтра текущего атрибута. После изменения этой логики нужно сбросить кеш — старые записи содержат неправильные `available_filters`.

34. **Radio-переключение внутри группы атрибутов** — при клике на значение атрибута, когда в той же группе уже выбрано другое значение, нужно автоматически снять предыдущий выбор. Реализовано через `click`-обработчик на `.ssc-filter-check` в `bindEvents()`: если `$input.prop('checked')` — выходим (клик по выбранному снимет его стандартно); если в группе есть выбранный (`$group.find('.ssc-filter-cb:checked')`) — `e.preventDefault()`, снимаем текущий, включаем и чекаем новый, триггерим `change`. `.ssc-filter-check--disabled` должен сохранять `pointer-events: none` — недоступные значения (несовместимые комбинации) не должны кликаться.

35. **`ssc-filter-attr` — вертикальная раскладка** — блок атрибута переведён на `flex-direction: column; align-items: flex-start; gap: 4px; margin-bottom: 12px`. Лейбл сверху, значения-чекбоксы под ним. `min-width: 130px` у `.ssc-filter-attr__label` удалён. `.ssc-filter-wrap` получил `padding-top: 6px`.

36. **Вертикальные швы — алгоритм остатков** — старый алгоритм (нечётные полосы на rL, чётные на rL/2) заменён на алгоритм остатков: `leftover` от последнего рулона переходит в следующую полосу. Это точно моделирует реальную укладку без лишних отходов. Переменная `lo` хранит остаток между итерациями цикла `for (si=0..stripsCount)`. После цикла `lo` = остаток от последнего рулона (`rollLeftover`). Применяется во всех 4 типах калькулятора и в `drawCanvas()` (переменная `drawLo`).

37. **`ssc-res-row-leftover` — скрыта через CSS** — строка «Остаток рулона» добавлена в PHP-шаблон (`class-ssc-frontend.php`) и рассчитывается в JS (`rollLeftover = lo` после цикла швов). Временно скрыта глобально через `.ssc-res-row-leftover { display: none !important; }` в `calculator.css`. Чтобы показать — удалить этот CSS-блок.
