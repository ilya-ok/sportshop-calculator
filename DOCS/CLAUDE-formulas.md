# Расчётные формулы (calculator.js)

## Количество рулонов (шахматный раскрой)
```
rollCount = ceil((area + markupArea) / (rW × rL))
```

## Горизонтальные стыки
```
stripsCount = ceil(W / rW)
hSeamsCount = stripsCount - 1
hSeamLen    = hSeamsCount × L
```

## Вертикальные стыки (шахматный сдвиг)
- Нечётные полосы: первый шов на `rL`, затем через `rL`
- Чётные полосы: первый шов на `rL/2`, затем через `rL`
- **Если `rL >= L` — вертикальных швов нет** (canvas их тоже не рисует)

```js
if (rL < L) {
    xi = rL;      while (xi < L) { styk_odd++;  xi += rL; }
    xi = rL / 2;  while (xi < L) { styk_even++; xi += rL; }
}
```

## Клей и шовная лента (с учётом линий разметки)
```
effectiveSeamLen = totalSeamLen + markupCount   // markupCount = 0 если разметка не выбрана
glueKg           = effectiveSeamLen × 0.5 × 1.05
glueCount        = ceil(glueKg / 10)            // банок по 10 кг
tapeMeters       = effectiveSeamLen × 1.05      // + 5% на подрез
```

### Разбивка на основные швы и разметку
```
baseGlueKg    = totalSeamLen × 0.5 × 1.05
markupGlueKg  = markupCount × 0.5 × 1.05
totalGlueKg   = baseGlueKg + markupGlueKg
glueCount     = ceil(totalGlueKg / 10)

baseTapeMeters   = totalSeamLen × 1.05
markupTapeMeters = markupCount × 1.05
tapeMeters       = baseTapeMeters + markupTapeMeters
```

Стоимость клея/ленты разбивается пропорционально:
```
glueCost       = glueCount × gluePrice
baseGlueCost   = (baseGlueKg / totalGlueKg) × glueCost
markupGlueCost = (markupGlueKg / totalGlueKg) × glueCost

tapeCost       = tapeMeters × tapePrice
baseTapeCost   = baseTapeMeters × tapePrice
markupTapeCost = markupTapeMeters × tapePrice
```

## Кварцевый песок и резиновая крошка

Плотность зависит от высоты ворса (мм). `h` берётся из `product.height` (атрибут `pa_vysota-vorsa`).

| Высота (h) | Песок (кг/м²) | Крошка (кг/м²) |
|-----------|--------------|----------------|
| 10–12     | 7.8          | 0              |
| 14–15     | 10           | 0              |
| 20        | 4.8          | 3              |
| 25        | 6            | 4              |
| 30        | 7.5          | 5              |
| 32–35     | 9            | 6              |
| 40        | 12           | 7              |
| 45        | 20           | 8              |
| 50        | 22           | 9              |
| 55        | 22           | 10.5           |
| 60        | 22           | 12             |

```
sandKg   = Math.round(area × sandDensity(h))
rubberKg = Math.round(area × rubberDensity(h))
sandCost   = (sandKg / 1000) × sandPrice
rubberCost = (rubberKg / 1000) × rubberPrice
```

## Разметка поля — газон (4 типа)

`L` = длина площадки, `W` = ширина площадки.

| Тип            | markupCount (м.п.)                                    | ширина полосы |
|----------------|-------------------------------------------------------|---------------|
| Футбол         | `L×2 + W×3 + 314`                                    | 0.10 м        |
| Мини-футбол    | `L×2 + W×3 + 65`                                     | 0.08 м        |
| Теннис         | `L×4 + W×2 + W + 1.73 + (W-2.74)×2 + 12.85`         | 0.05 м        |
| Хоккей         | `L×2 + W×5 + 121`                                    | 0.075 м       |

```
rawMarkupArea = markupCount × ширина_полосы
markupArea    = Math.ceil(rawMarkupArea × 1.03)   // +3% на подрез
totalGrassArea = area + markupArea
grassCost      = totalGrassArea × price
```

Линии разметки добавляются к шву для клея и ленты (`effectiveSeamLen`).

## Сценический линолеум (sceniclinoleum)

Формулы рулонов и швов — **идентичны линолеуму**. Отличие только в сопутствующих материалах.

### Основание (radio `ssc_base_{calcId}`)

| Значение | Формула | Единица | Цена |
|----------|---------|---------|------|
| `none` | — | — | 0 |
| `glue` | `baseKg = area × 0.35`; `count = ceil(baseKg / glue_volume)` | банок | `count × glue_price` |
| `tape` | `seamsM = totalSeamLen × 2 × 1.10`; `perimM = (2×(L+W)) × 1.05`; `baseM = seamsM + perimM`; `count = ceil(baseM / scenic_base_tape_volume)` | рулонов | `count × scenic_base_tape_price` |

- `glue_price` — цена за банку (`gluePrice` в JS-конфиге), `glue_volume` — кг/банка (`glueVolume`).
- `scenic_base_tape_price` — цена за рулон (`scenicBaseTapePrice`), `scenic_base_tape_volume` — м/рулон (`scenicBaseTapeVol`).

### Склейка швов (radio `ssc_seam_{calcId}`)

| Значение | Формула | Единица | Цена |
|----------|---------|---------|------|
| `none` | — | — | 0 |
| `cord` | `seamM = totalSeamLen × 1.10`; `count = ceil(seamM / scenic_seam_cord_volume)` | бухт | `count × scenic_seam_cord_price` |
| `tape` | `seamM = totalSeamLen × 1.10`; `count = ceil(seamM / scenic_seam_tape_volume)` | рулонов | `count × scenic_seam_tape_price` |
| `cold_weld` | `count = ceil(totalSeamLen / scenic_seam_weld_volume)` | тюбиков | `count × scenic_seam_weld_price` |

- JS-конфиг: `scenicSeamCordPrice/Vol`, `scenicSeamTapePrice/Vol`, `scenicSeamWeldPrice/Vol`.
- Все типы округляются вверх до целых единиц (`Math.ceil`). Оплачивается количество × объём единицы × цена единицы.

### Краска разметки

Те же типы и количество банок что у линолеума (Волейбол — 2, остальные — 3). Опция включается через `markup_enabled`.

### Итого
```
total = grassBaseCost + baseCost + seamCost + paintCost
```

---

---

## Простой расчёт (simple)

Тип `simple` — минимальный калькулятор: площадь × цена + опциональная приклейка клеем.

### Настройки (admin)

| Поле | Описание | Умолчание |
|------|----------|-----------|
| `simple_rolls_enabled` | Включить расчёт рулонов и швов | false |
| `simple_glue_enabled` | Включить расчёт приклейки клеем | false |
| `simple_glue_rate` | Расход клея (кг/м²) | 0.35 |
| `glue_price` | Цена за банку (руб) — общее с линолеумом | — |
| `glue_volume` | Объём тары (кг) — общее с линолеумом | 10 |

### Расчёт (`_calculateSimple`)

**Если `simpleRollsEnabled = true`** — рулоны и швы считаются по тем же формулам что у линолеума.  
**Если `simpleRollsEnabled = false`** — `rollCount = 0`, `totalSeamLen = 0`, строки «Рулонов» и «Длина швов» не рендерятся в PHP и не заполняются в JS.

```
area          = L × W
grassBaseCost = area × price
```

**Приклейка клеем (если `simpleGlueEnabled`):**
```
glueKg        = area × simpleGlueRate
glueCount     = ceil(glueKg / glueVolume)
glueKgDisplay = glueCount × glueVolume
glueCost      = glueCount × gluePrice
```

**Итого:**
```
total = grassBaseCost + (simpleGlueEnabled ? glueCost : 0)
```

### UI

- Блок «Параметры рулона» скрыт в PHP когда `simple_rolls_enabled = false` (в обоих рендерах: category и product).
- Шаг `.ssc-step--dims` активируется сразу при инициализации JS когда `simpleRollsEnabled = false` (не ждёт выбора продукта).
- Проверка `!rW || !rL` в `calculate()` обходится для `simple` без rolls: `needRoll = !(calcType === 'simple' && !simpleRollsEnabled)`.

### Результаты (simple)

| Строка | Условие |
|--------|---------|
| Рулонов в раскладке: X шт | Только если `simple_rolls_enabled` |
| Длина швов: X м.п. | Только если `simple_rolls_enabled` |
| Площадь: X м² \| X руб. | Всегда |
| Клей (приклейка): X банок (Y кг) \| X руб. | Только если `simple_glue_enabled` |

---

## Разметка поля — линолеум (3 типа)

Не влияет на площадь, рулоны и швы. Только краска (банки).

| Тип         | paintCans |
|-------------|-----------|
| Волейбол    | 2         |
| Баскетбол   | 3         |
| Мини-футбол | 3         |

```
paintCans = markupType === 'volleyball' ? 2 : 3
paintCost = paintCans × paintPrice
```

`paintPrice` — цена за банку краски, задаётся в настройках калькулятора (`paint_price`). Стоимость всегда входит в итог (нет чекбокса).

## Площадь
Площадь всегда округляется через `Math.round()` (не toFixed).

## Результаты расчёта (UI)

Строки рендерятся в режиме `grid-template-columns: 1fr auto auto` (название | значение | цена).
Первые две строки (Рулоны, Швы) имеют доп. класс `.ssc-result-row--info`.

### Газон (grass)

| Строка | Условие |
|--------|---------|
| Рулонов в раскладке: X шт | Всегда, без цены |
| Длина швов: X м.п. (горизонтальные: X шт., вертикальные: X шт.) | Всегда, без цены |
| Площадь: X м² \| X руб. | Всегда (площадь поля без разметки) |
| Разметка: X м² \| X руб. | Только если выбран тип разметки |
| **☑ Шовная лента** | Чекбокс всегда виден; val/cost заполняются если ✓ |
| └ Основные швы: X м.п. +5% подрез \| X руб. | Если ✓ |
| └ Разметка: X м.п. +5% подрез \| X руб. | Если ✓ **и** разметка выбрана |
| **☑ Клей** | Чекбокс всегда виден; val/cost заполняются если ✓ |
| └ Основные швы: X банок (X кг) \| X руб. | Если ✓ |
| └ Разметка: X банок (X кг) \| X руб. | Если ✓ **и** разметка выбрана |
| **☑ Кварцевый песок**: X кг \| X руб. | Только если ✓ |
| **☑ Резиновая крошка**: X кг \| X руб. | Только если ✓ |

### Линолеум (linoleum)

| Строка | Условие |
|--------|---------|
| Рулонов в раскладке: X шт | Всегда, без цены |
| Длина швов: X м.п. (горизонтальные: X шт., вертикальные: X шт.) | Всегда, без цены |
| Площадь: X м² \| X руб. | Всегда |
| Краска для разметки: X банки \| X руб. | Только если `markup_enabled` **и** выбран тип разметки; CSS `.ssc-res-row-paint` |
| **☑ Клей**: X кг \| X руб. | Чекбокс; значения показываются если ✓ |
| **☑ Сварочный шнур**: X м.п. (+10% подрез) \| X руб. | Чекбокс |

### Сценический линолеум (sceniclinoleum)

| Строка | Условие |
|--------|---------|
| Рулонов в раскладке: X шт | Всегда, без цены |
| Длина швов: X м.п. (горизонтальные: X шт., вертикальные: X шт.) | Всегда, без цены |
| Площадь: X м² \| X руб. | Всегда |
| Краска для разметки: X банки \| X руб. | Только если `markup_enabled` **и** выбран тип; CSS `.ssc-res-row-paint` |
| **Основание** — строка 1 (`.ssc-result-row--scenic-radios`): radio-кнопки в стиле `.ssc-radio-btn` | Всегда видна |
| **Основание** — строка 2 (`.ssc-result-row--scenic-result`): hint \| `ssc-res-base-val` \| `ssc-res-base-cost` | Скрыта при «Без основания»; показывается JS при выборе Клей / Скотч |
| **Склейка швов** — строка 1: radio-кнопки | Всегда видна |
| **Склейка швов** — строка 2: hint \| `ssc-res-seam-val` \| `ssc-res-seam-cost` | Скрыта при «Без склейки»; показывается при Шнур / Скотч / Холодная сварка |

> Стоимость краски входит в итог **автоматически** (нет чекбокса) при выборе типа разметки.

### Детали строк результата (общие)
- **Площадь**: `Math.round()` (не toFixed).
- **Швы**: одна строка `.ssc-res-seams` с текстом `"горизонтальные: X шт., вертикальные: Y шт."`.
- Цены — в отдельном столбце через `.ssc-result-price`.
- Строки "Рулонов" и "Длина швов" — класс `.ssc-result-row--info`.
