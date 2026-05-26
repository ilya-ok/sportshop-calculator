/* ==========================================================================
   SSC Frontend Calculator JS
   canvas + расчёт + PDF
   ========================================================================== */
(function ($) {
    'use strict';

    var g = window.sscCalc || {};

    /* =========================================================================
       Экземпляр калькулятора
       ========================================================================= */
    function SSCInstance($wrap) {
        this.$wrap     = $wrap;
        this.config    = JSON.parse($wrap.attr('data-config') || '{}');
        this.calcId    = $wrap.attr('data-calc-id');
        this.product   = this.config.product || null; // null = режим категории
        this.rollWidth  = this.product ? (this.product.defaultWidth || 0) : 0;
        this.rollLength = this.product ? (this.product.defaultLength || 0) : 0;
        this.areaLen    = 0;
        this.areaWid    = 0;
        this.$canvas    = $wrap.find('.ssc-canvas');
        this.ctx        = this.$canvas.length ? this.$canvas[0].getContext('2d') : null;
        this.bgImage    = null;
        this._lastCalc  = null;

        this.init();
    }

    SSCInstance.prototype = {

        init: function () {
            var self = this;

            // Текущая выбранная подкатегория
            this.selectedSubcategory = null;
            // Текущие фильтры-атрибуты категории
            this.categoryFilterAttrs = [];

            this.bindEvents();

            // Простой расчёт без рулонов — шаг размеров активен сразу
            if (this.config.calcType === 'simple' && !this.config.simpleRollsEnabled) {
                this.$wrap.find('.ssc-step--dims').addClass('ssc-step--active');
            }

            // Режим категории — ждём выбора подкатегории, показываем дефолтное изображение
            if (this.config.mode === 'category') {
                // resetFilterStep не вызываем — блок уже виден с плейсхолдером из PHP
                this.updateCanvasBg();

                // Если нет блока выбора подкатегории (линолеум) — загружаем атрибуты автоматически
                var $subSelect = this.$wrap.find('.ssc-subcategory-select');
                if (!$subSelect.length) {
                    var slugs = this.config.subcategorySlugs;
                    this.selectedSubcategory = (slugs && slugs.length) ? slugs[0] : (this.config.categorySlug || '');
                    console.log('[SSC] Линолеум авто-загрузка: selectedSubcategory=', this.selectedSubcategory, 'subcategorySlugs=', slugs, 'categorySlug=', this.config.categorySlug);
                    if (this.selectedSubcategory) {
                        this.loadCategoryAttrs();
                    } else {
                        console.warn('[SSC] selectedSubcategory пуст — загрузка атрибутов не запущена. Проверьте настройки калькулятора (category_slug или subcategory_slugs).');
                    }
                }
            }

            // Режим товара — PHP уже отрисовал радио-кнопки ширины и длины; просто читаем значения
            if (this.config.mode === 'product' && this.product) {
                var $checkedWidth = this.$wrap.find('input[name="ssc_width_' + this.calcId + '"]:checked');
                this.rollWidth = $checkedWidth.length ? parseFloat($checkedWidth.val()) : (this.product.defaultWidth || 0);
                var $checkedLength = this.$wrap.find('input[name="ssc_length_' + this.calcId + '"]:checked');
                this.rollLength = $checkedLength.length ? parseFloat($checkedLength.val()) : (this.product.defaultLength || 0);
                this.$wrap.find('.ssc-form-product-name').val(this.product.name || '');
                this.calculate();
            }
        },

        /* -------------------------------------------------------------------
           Привязка событий
           ------------------------------------------------------------------- */
        bindEvents: function () {
            var self = this;

            // Выбор подкатегории (категория)
            this.$wrap.on('change', '.ssc-subcategory-select', function () {
                var subcatSlug = $(this).val();
                if (subcatSlug) {
                    self.selectedSubcategory = subcatSlug;
                    self.loadCategoryAttrs();
                } else {
                    self.selectedSubcategory = null;
                    self.resetFilterStep();
                }
            });

            // Переключение значения внутри одной группы атрибутов (radio-поведение)
            this.$wrap.on('click', '.ssc-filter-check', function (e) {
                var $label   = $(this);
                var $input   = $label.find('.ssc-filter-cb');
                if ($input.prop('checked')) return; // клик по уже выбранному — снять выбор, обычное поведение
                var $group   = $label.closest('.ssc-filter-attr');
                var $current = $group.find('.ssc-filter-cb:checked');
                if (!$current.length) return; // в группе ничего не выбрано — обычное поведение
                e.preventDefault();
                $current.prop('checked', false);
                $input.prop('disabled', false).prop('checked', true).trigger('change');
            });

            // Фильтры (категория) — динамически загруженные
            this.$wrap.on('change', '.ssc-filter-cb', function () {
                // Дизейблим все остальные группы атрибутов на время загрузки
                self.$wrap.find('.ssc-filter-attr').each(function () {
                    var $group = $(this);
                    var hasChecked = $group.find('.ssc-filter-cb:checked').length > 0;
                    // Если в этой группе есть выбранный чекбокс — не трогаем, остальные — дизейблим
                    if (!hasChecked) {
                        $group.addClass('ssc-filter-attr--loading');
                    }
                });
                // Дизейблим текущую группу тоже (кроме изменённого чекбокса)
                $(this).closest('.ssc-filter-attr').find('.ssc-filter-cb').not(this).prop('disabled', true);
                self.loadProducts();
            });

            // Кнопка выбора покрытия — открывает Magnific Popup
            this.$wrap.on('click', '.ssc-select-product-btn', function () {
                var $popup = self.$wrap.find('#ssc-products-' + self.calcId);
                if (!$popup.children().length || $popup.find('.ssc-products-loading').length) return;
                $popup.css('display', 'block');
                $.magnificPopup.open({
                    items: { src: $popup },
                    type: 'inline',
                    midClick: true,
                    closeBtnInside: true,
                    closeOnBgClick: true,
                    mainClass: 'ssc-product-popup',
                    removalDelay: 300,
                    callbacks: {
                        close: function () {
                            $popup.hide();
                        }
                    }
                });
            });

            // Выбор товара (категория) — клик по карточке, т.к. popup перемещает элементы
            $(document).on('click', '.ssc-product-card', function (e) {
                e.preventDefault();
                var $radio = $(this).find('input[name="ssc_product_' + self.calcId + '"]');
                if ($radio.length && !$radio.prop('checked')) {
                    $radio.prop('checked', true);
                }
                var productData = JSON.parse($radio.attr('data-product') || 'null');
                if (productData) {
                    // Закрываем popup
                    $.magnificPopup.close();
                    // Обновляем текст кнопки
                    var $btn = self.$wrap.find('#ssc-select-product-btn-' + self.calcId);
                    $btn.find('.ssc-select-product-btn__text').text(productData.name);
                    // Подгружаем параметры рулона
                    self.onProductSelected(productData);
                }
            });

            // Выбор ширины рулона
            this.$wrap.on('change', 'input[name="ssc_width_' + this.calcId + '"]', function () {
                self.rollWidth = parseFloat($(this).val()) || 0;
                $(this).closest('.ssc-width-list').find('.ssc-radio-btn').removeClass('ssc-radio-btn--active');
                $(this).closest('.ssc-radio-btn').addClass('ssc-radio-btn--active');
                self.calculate();
            });

            // Выбор длины рулона (если несколько значений)
            this.$wrap.on('change', 'input[name="ssc_length_' + this.calcId + '"]', function () {
                self.rollLength = parseFloat($(this).val()) || 0;
                $(this).closest('.ssc-width-list').find('.ssc-radio-btn').removeClass('ssc-radio-btn--active');
                $(this).closest('.ssc-radio-btn').addClass('ssc-radio-btn--active');
                self.calculate();
            });

            // Размеры площадки — поля ввода
            this.$wrap.on('input change', '.ssc-area-length', function () {
                self.areaLen = parseFloat($(this).val()) || 0;
                self.calculate();
            });
            this.$wrap.on('input change', '.ssc-area-width', function () {
                self.areaWid = parseFloat($(this).val()) || 0;
                self.calculate();
            });

            // Счётчики +/–
            this.$wrap.on('click', '.ssc-counter__plus', function () {
                var $input = $(this).siblings('.ssc-counter__val');
                var val = parseFloat($input.val()) || 0;
                $input.val((val + 1).toFixed(0)).trigger('change');
            });
            this.$wrap.on('click', '.ssc-counter__minus', function () {
                var $input = $(this).siblings('.ssc-counter__val');
                var val = parseFloat($input.val()) || 0;
                var next = Math.max(0, val - 1);
                $input.val(next.toFixed(0)).trigger('change');
            });

            // Выбор типа разметки
            this.$wrap.on('change', 'input[name="ssc_markup_type_' + this.calcId + '"]', function () {
                $(this).closest('.ssc-markup-type-list').find('.ssc-radio-btn').removeClass('ssc-radio-btn--active');
                $(this).closest('.ssc-radio-btn').addClass('ssc-radio-btn--active');
                // Меняем фоновое изображение canvas в зависимости от типа разметки
                self.updateCanvasBg();
                self.calculate();
            });

            // Сценический линолеум: изменение типа основания или шва
            this.$wrap.on('change', 'input[name="ssc_base_' + this.calcId + '"], input[name="ssc_seam_' + this.calcId + '"]', function () {
                var $list = $(this).closest('.ssc-markup-type-list');
                $list.find('.ssc-radio-btn').removeClass('ssc-radio-btn--active');
                $(this).closest('.ssc-radio-btn').addClass('ssc-radio-btn--active');
                var val    = $(this).val();
                var $group = $(this).closest('.ssc-scenic-group');
                $group.find('.ssc-result-row--scenic-result').toggle(val !== 'none');
                if (self.config.calcType === 'sceniclinoleum' && self._lastCalc) {
                    self.calculate();
                }
            });

            // Чекбоксы доп. материалов
            this.$wrap.on('change', '.ssc-glue-check, .ssc-tape-check, .ssc-sand-check, .ssc-rubber-check', function () {
                var $cb = $(this);
                if (self.config.calcType === 'linoleum') {
                    self._syncCheckboxRowsLinoleum(self._lastCalc);
                    self._updateTotalsLinoleum(self._lastCalc);
                    return;
                }
                // Показываем/скрываем подпись ": Основные швы:" при переключении чекбоксов
                if ($cb.hasClass('ssc-glue-check')) {
                    $cb.closest('.ssc-res-glue-base').find('.ssc-glue-sub-label').toggle(this.checked);
                } else if ($cb.hasClass('ssc-tape-check')) {
                    $cb.closest('.ssc-res-tape-base').find('.ssc-tape-sub-label').toggle(this.checked);
                }
                self.syncCheckboxRows(self._lastCalc);
                self.updateTotals(self._lastCalc);
            });

            // Кнопка КП
            this.$wrap.on('click', '.ssc-kp-btn', function () {
                self.openModal();
            });

            // Кнопка теста PDF — сразу генерация без модалки
            this.$wrap.on('click', '.ssc-kp-test-btn', function () {
                if (!self._lastCalc || !self._lastCalc.area) {
                    alert('Сначала выполните расчёт');
                    return;
                }
                self.generatePDF();
            });

            // Закрыть модал
            this.$wrap.on('click', '.ssc-modal-close', function () {
                self.closeModal();
            });

            // Отправить форму
            this.$wrap.on('submit', '.ssc-kp-form', function (e) {
                e.preventDefault();
                self.submitKP($(this));
            });
        },

        /* -------------------------------------------------------------------
           Сбросить шаг фильтров к начальному состоянию
           ------------------------------------------------------------------- */
        resetFilterStep: function () {
            var $filterWrap = this.$wrap.find('#ssc-filter-wrap-' + this.calcId);
            var $filterContent = this.$wrap.find('#ssc-filter-content-' + this.calcId);
            var $filterPlaceholder = this.$wrap.find('#ssc-filter-placeholder-' + this.calcId);
            var $selectBtn = this.$wrap.find('#ssc-select-product-btn-' + this.calcId);
            var $list = this.$wrap.find('#ssc-products-' + this.calcId);

            // Показать плейсхолдер, скрыть контент
            if ($filterPlaceholder.length) $filterPlaceholder.show();
            if ($filterContent.length) $filterContent.hide();
            if ($selectBtn.length) $selectBtn.hide();
            if ($filterWrap.length) $filterWrap.html('');
            if ($list.length) {
                $list.html('<p class="ssc-products-loading">' + 'Выберите категорию для загрузки товаров' + '</p>').hide();
            }
            this.categoryFilterAttrs = [];
            this.selectedSubcategory = null;
            // Сброс кнопки
            var $btn = this.$wrap.find('#ssc-select-product-btn-' + this.calcId);
            if ($btn.length) {
                $btn.find('.ssc-select-product-btn__text').text('Выберите покрытие');
                $btn.find('#ssc-product-count-' + this.calcId).text('0');
            }
            this.updateCanvasBg();
        },

        /* -------------------------------------------------------------------
           Обновить фоновое изображение canvas для выбранной подкатегории и типа разметки
           ------------------------------------------------------------------- */
        updateCanvasBg: function () {
            var imgUrl = '';
            var markupType = this.$wrap.find('input[name="ssc_markup_type_' + this.calcId + '"]:checked').val() || 'none';

            // Пробуем найти изображение для подкатегории + типа разметки
            if (this.selectedSubcategory && this.config.canvasImages && this.config.canvasImages[this.selectedSubcategory]) {
                var subcatImages = this.config.canvasImages[this.selectedSubcategory];
                if (typeof subcatImages === 'object' && !Array.isArray(subcatImages)) {
                    // Новая вложенная структура
                    imgUrl = subcatImages[markupType] || subcatImages['none'] || '';
                } else {
                    // Обратная совместимость: старая плоская структура
                    imgUrl = subcatImages;
                }
            }
            // Если для подкатегории нет изображения — используем изображение по умолчанию
            if (!imgUrl) {
                imgUrl = this.config.canvasImage || '';
            }
            var $wrap = this.$wrap.find('.ssc-canvas-wrap');
            var $bg = $wrap.find('.ssc-canvas-bg');
            if (imgUrl) {
                // Преобразуем относительный путь в полный URL
                var fullUrl = (imgUrl.indexOf('http') === 0) ? imgUrl : (g.siteUrl + imgUrl);
                if ($bg.length) {
                    $bg.attr('src', fullUrl);
                } else {
                    $wrap.prepend('<img class="ssc-canvas-bg" src="' + fullUrl + '" alt="">');
                }
            } else {
                $bg.remove();
            }
        },

        /* -------------------------------------------------------------------
           Загрузить атрибуты выбранной подкатегории
           ------------------------------------------------------------------- */
        loadCategoryAttrs: function () {
            var self = this;
            var $filterStep = this.$wrap.find('#ssc-filter-step-' + this.calcId);
            var $filterWrap = this.$wrap.find('#ssc-filter-wrap-' + this.calcId);
            var $filterContent = this.$wrap.find('#ssc-filter-content-' + this.calcId);
            var $filterPlaceholder = this.$wrap.find('#ssc-filter-placeholder-' + this.calcId);
            var $selectBtn = this.$wrap.find('#ssc-select-product-btn-' + this.calcId);
            var $list = this.$wrap.find('#ssc-products-' + this.calcId);

            // Показать контент, скрыть плейсхолдер
            if ($filterContent.length) $filterContent.show();
            if ($filterPlaceholder.length) $filterPlaceholder.hide();
            if ($selectBtn.length) $selectBtn.show();

            if ($list.length) {
                $list.html('<p class="ssc-products-loading">' + g.strings.loading + '</p>').hide();
            }

            // Меняем фон canvas на изображение выбранной подкатегории
            this.updateCanvasBg();
            // Сбрасываем счётчик товаров
            this.$wrap.find('#ssc-product-count-' + this.calcId).text('0');
            var $btn = this.$wrap.find('#ssc-select-product-btn-' + this.calcId);
            if ($btn.length) {
                $btn.find('.ssc-select-product-btn__text').text('Выберите покрытие');
            }

            $.post(g.ajaxUrl, {
                action: 'ssc_load_subcategory_attrs',
                nonce:   g.nonce,
                calc_id: this.calcId,
                subcategory_slug: this.selectedSubcategory
            }, function (res) {
                if (res.success) {
                    self.categoryFilterAttrs = res.data.filter_attrs || [];
                    if ($filterWrap.length) {
                        $filterWrap.html(res.data.filter_html);
                    }
                    // Заголовок фильтров всегда виден
                    self.$wrap.find('#ssc-filter-title-' + self.calcId).show();
                    
                    // Отладка кеша
                    if (res.data.source === 'cache') {
                        console.log('%c[SSC] Атрибуты загружены из кеша ⚡', 'color: blue; font-weight: bold');
                    } else {
                        console.log('[SSC] Атрибуты загружены из базы данных');
                    }
                    
                    $filterStep.show();
                    self.loadProducts();
                } else {
                    if ($list.length) {
                        $list.html('<p class="ssc-no-products">' + (res.data || 'Ошибка загрузки') + '</p>');
                    }
                }
            }).fail(function (xhr) {
                console.log('loadCategoryAttrs failed:', xhr.status, xhr.responseText.substring(0, 500));
                if ($list.length) {
                    $list.html('<p class="ssc-no-products">Ошибка: ' + xhr.status + '</p>');
                }
            });
        },

        /* -------------------------------------------------------------------
           Загрузить список товаров (режим категории)
           ------------------------------------------------------------------- */
        loadProducts: function () {
            var self = this;
            var $list = this.$wrap.find('#ssc-products-' + this.calcId);
            if (!$list.length) return;

            var filters = {};
            this.$wrap.find('.ssc-filter-cb:checked').each(function () {
                var $cb  = $(this);
                var attr = $cb.closest('.ssc-filter-attr').data('attr');
                if (!filters[attr]) filters[attr] = [];
                filters[attr].push($cb.val());
            });

            // Показать анимацию загрузки на кнопке
            var $btn = this.$wrap.find('#ssc-select-product-btn-' + this.calcId);
            $btn.addClass('ssc-btn--loading');
            $list.html('<p class="ssc-products-loading">' + g.strings.loading + '</p>');

            $.post(g.ajaxUrl, {
                action: 'ssc_load_products',
                nonce:   g.nonce,
                calc_id: this.calcId,
                subcategory_slug: this.selectedSubcategory || '',
                filters: filters
            }, function (res) {
                // Снимаем блокировку с групп атрибутов
                self.$wrap.find('.ssc-filter-attr--loading').removeClass('ssc-filter-attr--loading');
                self.$wrap.find('.ssc-filter-attr .ssc-filter-cb').prop('disabled', false);

                // Убрать анимацию загрузки с кнопки
                $btn.removeClass('ssc-btn--loading');
                if (res.success) {
                    $list.html(res.data.html).hide();
                    // Обновляем счётчик товаров на кнопке
                    var count = $list.find('.ssc-product-card').length;
                    self.$wrap.find('#ssc-product-count-' + self.calcId).text(count);
                    
                    // Отладка кеша
                    if (res.data.source === 'cache') {
                        console.log('%c[SSC] Товары загружены из кеша ⚡', 'color: green; font-weight: bold');
                        $btn.attr('data-source', 'cache');
                    } else {
                        console.log('[SSC] Товары загружены из базы данных');
                        $btn.removeAttr('data-source');
                    }
                    
                    // Закрываем popup если он открыт
                    $.magnificPopup.close();
                    // Обновляем доступные фильтры
                    if (res.data.available_filters) {
                        self.updateFilterAvailability(res.data.available_filters);
                    }
                }
            }).fail(function () {
                // При ошибке тоже снимаем блокировку
                self.$wrap.find('.ssc-filter-attr--loading').removeClass('ssc-filter-attr--loading');
                self.$wrap.find('.ssc-filter-attr .ssc-filter-cb').prop('disabled', false);
                $btn.removeClass('ssc-btn--loading');
            });
        },

        /* -------------------------------------------------------------------
           Дизейблить чекбоксы фильтров, значения которых нет у оставшихся товаров
           ------------------------------------------------------------------- */
        updateFilterAvailability: function (availableFilters) {
            var self = this;
            // Для каждого атрибута проходим по всем чекбоксам
            this.$wrap.find('.ssc-filter-attr').each(function () {
                var $attrBlock = $(this);
                var attr = $attrBlock.data('attr');
                var available = (availableFilters[attr] && Array.isArray(availableFilters[attr])) ? availableFilters[attr] : null;
                // Если available === null — атрибута нет в ответе, не трогаем
                // Если available === [] — атрибут не применим к этим товарам, активируем все чекбоксы обратно
                if (available === null) return;

                $attrBlock.find('.ssc-filter-check').each(function () {
                    var $cb = $(this);
                    var $input = $cb.find('input.ssc-filter-cb');
                    var val = $input.val();
                    if (available.length > 0 && available.indexOf(val) === -1) {
                        // Значение недоступно — дизейблим
                        $cb.addClass('ssc-filter-check--disabled');
                        $input.prop('disabled', true);
                    } else {
                        // Значение доступно (или массив пуст — нет ограничений) — активируем
                        $cb.removeClass('ssc-filter-check--disabled');
                        $input.prop('disabled', false);
                    }
                });
            });
        },

        /* -------------------------------------------------------------------
           Товар выбран → подставить ширину и длину рулона (режим категории)
           ------------------------------------------------------------------- */
        onProductSelected: function (product) {
            var self = this;
            this.product    = product;
            this.rollLength = product.defaultLength || product.length || 0;

            // Перерисовать список ширин из данных товара
            var $widthList = this.$wrap.find('#ssc-width-' + this.calcId);
            if ($widthList.length && product.widths && product.widths.length) {
                var html = '';
                product.widths.forEach(function (w, i) {
                    html += '<label class="ssc-radio-btn' + (i === 0 ? ' ssc-radio-btn--active' : '') + '">' +
                        '<input type="radio" name="ssc_width_' + self.calcId + '" value="' + w + '"' + (i === 0 ? ' checked' : '') + '>' +
                        w + ' м</label>';
                });
                $widthList.html(html).addClass('ssc-width-list--has-data');
                this.rollWidth = product.widths[0] || 0;
                // Показать label
                this.$wrap.find('#ssc-roll-label-' + this.calcId).show();
                // Скрыть placeholder
                $widthList.find('.ssc-placeholder').hide();
            }

            // Список длин
            var $lenWrap = this.$wrap.find('.ssc-length-field');
            if ($lenWrap.length) {
                $lenWrap.show();
                this.$wrap.find('#ssc-length-label-' + this.calcId).show();
                if (product.lengths && product.lengths.length > 1) {
                    var lhtml = '';
                    product.lengths.forEach(function (l, i) {
                        lhtml += '<label class="ssc-radio-btn' + (i === 0 ? ' ssc-radio-btn--active' : '') + '">' +
                            '<input type="radio" name="ssc_length_' + self.calcId + '" value="' + l + '"' + (i === 0 ? ' checked' : '') + '>' +
                            l + ' м</label>';
                    });
                    this.$wrap.find('#ssc-length-' + this.calcId).html(lhtml);
                } else {
                    var $lenEl = this.$wrap.find('#ssc-length-' + this.calcId);
                    if ($lenEl.is('span')) {
                        $lenEl.text(this.rollLength + ' м');
                    }
                }
                this.$wrap.find('#ssc-length-input-' + this.calcId).val(this.rollLength);
            }

            this.$wrap.find('.ssc-step--roll').addClass('ssc-step--active');
            this.$wrap.find('.ssc-step--dims').addClass('ssc-step--active');
            this.$wrap.find('.ssc-form-product-name').val(product.name || '');

            // Закрываем popup с товарами с небольшой задержкой
            var self2 = this;
            setTimeout(function () {
                $.magnificPopup.close();
            }, 100);

            this.calculate();
        },

        /* -------------------------------------------------------------------
           Расчёт (формулы из trava2 calculator-js.js)
           ------------------------------------------------------------------- */
        calculate: function () {
            var L  = this.areaLen;   // Длина площадки
            var W  = this.areaWid;   // Ширина площадки
            var rW = this.rollWidth;  // Ширина рулона
            var rL = this.rollLength; // Длина рулона

            this.$wrap.find('.ssc-area-result').text(L && W ? Math.round(L * W) : 0);

            var needRoll = !(this.config.calcType === 'simple' && !this.config.simpleRollsEnabled);
            if (!L || !W || (needRoll && (!rW || !rL))) {
                this.drawCanvas();
                return;
            }

            var area = L * W;

            if (this.config.calcType === 'linoleum') {
                this._calculateLinoleum(L, W, rW, rL, area);
                return;
            }

            if (this.config.calcType === 'sceniclinoleum') {
                this._calculateScenic(L, W, rW, rL, area);
                return;
            }

            if (this.config.calcType === 'simple') {
                this._calculateSimple(L, W, rW, rL, area);
                return;
            }

            /* --- Разметка поля (формулы из trava2) ---
               markupCount  — суммарная длина линий разметки (м.п.)
               markupArea   — площадь материала разметки с запасом 3% (м²), ceil */
            var markupType  = this.$wrap.find('input[name="ssc_markup_type_' + this.calcId + '"]:checked').val() || 'none';
            var markupCount = 0;
            var markupArea  = 0;
            if (this.config.markupEnabled && markupType !== 'none') {
                var rawMarkup = 0;
                if (markupType === 'football') {
                    markupCount = L * 2 + W * 3 + 314;
                    rawMarkup   = markupCount * 0.10;
                } else if (markupType === 'mini-football') {
                    markupCount = L * 2 + W * 3 + 65;
                    rawMarkup   = markupCount * 0.08;
                } else if (markupType === 'tennis') {
                    markupCount = L * 4 + W * 2 + W + 1.73 + (W - 2.74) * 2 + 12.85;
                    rawMarkup   = markupCount * 0.05;
                } else if (markupType === 'hockey') {
                    markupCount = L * 2 + W * 5 + 121;
                    rawMarkup   = markupCount * 0.075;
                }
                markupArea = Math.ceil(rawMarkup * 1.03); // 3% подрез, округление вверх
            }

            /* --- Количество рулонов (шахматный раскрой, включая материал разметки) ---
               Общий объём материала = площадь поля + площадь материала разметки */
            var totalMaterialArea = area + markupArea;
            var rollCount = Math.ceil(totalMaterialArea / (rW * rL));

            /* --- Горизонтальные стыки (между полосами по ширине) ---
               stripsCount = ceil(W / rW)
               Стыков на 1 меньше, чем полос */
            var stripsCount  = Math.ceil(W / rW);
            var hSeamsCount  = stripsCount - 1;
            var hSeamLen     = hSeamsCount * L;

            /* --- Вертикальные стыки (шахматный порядок) ---
               Нечётные полосы: первый шов на rL, затем через rL
               Чётные полосы: первый шов на rL/2, затем через rL */
            var styk_odd = 0, styk_even = 0, xi;
            if (rL < L) {
                xi = rL;
                while (xi < L) { styk_odd++;  xi += rL; }
                xi = rL / 2;
                while (xi < L) { styk_even++; xi += rL; }
            }

            var oddStripsCount  = Math.ceil(stripsCount / 2);
            var evenStripsCount = Math.floor(stripsCount / 2);
            var totalStyk  = oddStripsCount * styk_odd + evenStripsCount * styk_even;
            var vSeamLen   = totalStyk * rW;

            var totalSeamLen = hSeamLen + vSeamLen;

            /* --- Клей и шовная лента с учётом длины линий разметки ---
               Разбиваем на основные швы и разметку отдельно */
            var baseGlueKg    = totalSeamLen * 0.5 * 1.05;
            var markupGlueKg  = markupCount * 0.5 * 1.05;
            var totalGlueKg   = baseGlueKg + markupGlueKg;
            var glueVolume    = this.config.glueVolume || 10;
            var glueCount     = Math.ceil(totalGlueKg / glueVolume);
            var glueKgDisplay = glueCount * glueVolume;

            var baseTapeMeters  = totalSeamLen * 1.05;
            var markupTapeMeters = markupCount * 1.05;
            var tapeMeters       = baseTapeMeters + markupTapeMeters; // = effectiveSeamLen * 1.05

            // Для display: показываем "каноничные" банки для каждой части
            var baseGlueCansDisplay   = Math.ceil(baseGlueKg / glueVolume);
            var markupGlueCansDisplay = Math.ceil(markupGlueKg / glueVolume);

            /* --- Кварцевый песок и резиновая крошка (кг/м²) ---
               Таблица из trava2: зависит от высоты ворса товара */
            var h = this.product ? (this.product.height || 0) : 0;
            var sandKg   = Math.round(area * this._sandDensity(h));
            var rubberKg = Math.round(area * this._rubberDensity(h));

            /* --- Стоимости --- */
            var price      = this.product ? (this.product.price || 0) : 0;
            var gluePrice  = this.config.gluePrice || 4000;
            var tapePrice  = this.config.tapePrice || 65;

            // Площадь поля (без разметки)
            var grassBaseCost = area * price;
            // Материал разметки — тот же продукт
            var markupAreaCost = markupArea * price;
            var totalGrassArea = area + markupArea;
            var grassCost      = totalGrassArea * price;

            // Клей: общая стоимость = кол-во банок * цена
            var glueCost = glueCount * gluePrice;
            // Разбиваем стоимость пропорционально кг
            var baseGlueCost   = totalGlueKg > 0 ? (baseGlueKg / totalGlueKg) * glueCost : 0;
            var markupGlueCost = totalGlueKg > 0 ? (markupGlueKg / totalGlueKg) * glueCost : 0;

            // Лента: стоимость = кол-во рулонов * объём * цена
            var tapeVolume     = this.config.tapeVolume || 50;
            var tapeCount      = Math.ceil(tapeMeters / tapeVolume);
            var tapeMDisplay   = tapeCount * tapeVolume;
            var tapeCost       = tapeMDisplay * tapePrice;
            var baseTapeCount  = Math.ceil(baseTapeMeters / tapeVolume);
            var markupTapeCount = Math.ceil(markupTapeMeters / tapeVolume);
            var baseTapeCost   = tapeMeters > 0 ? (baseTapeMeters / tapeMeters) * tapeCost : 0;
            var markupTapeCost = tapeMeters > 0 ? (markupTapeMeters / tapeMeters) * tapeCost : 0;

            var sandCost   = (sandKg / 1000) * (this.config.sandPrice || 3950);
            var rubberCost = (rubberKg / 1000) * (this.config.rubberPrice || 24500);

            var result = {
                area:            area,
                markupArea:      markupArea,
                totalGrassArea:  totalGrassArea,
                grassBaseCost:   grassBaseCost,
                markupAreaCost:  markupAreaCost,
                rollCount:       rollCount,
                stripsCount:     stripsCount,
                hSeamsCount:     hSeamsCount,
                totalStyk:       totalStyk,
                totalSeamLen:    totalSeamLen,
                markupType:      markupType,
                markupCount:     markupCount,
                // Клей (общий)
                glueCount:       glueCount,
                glueKgDisplay:   glueKgDisplay,
                // Клей разбивка
                baseGlueKg:      baseGlueKg,
                markupGlueKg:    markupGlueKg,
                baseGlueCansDisplay:   baseGlueCansDisplay,
                markupGlueCansDisplay: markupGlueCansDisplay,
                // Лента (общая)
                tapeMeters:      tapeMeters,
                tapeCount:       tapeCount,
                tapeMDisplay:    tapeMDisplay,
                // Лента разбивка
                baseTapeMeters:  baseTapeMeters,
                markupTapeMeters: markupTapeMeters,
                baseTapeCount:   baseTapeCount,
                markupTapeCount: markupTapeCount,
                // Песок/крошка
                sandKg:          sandKg,
                rubberKg:        rubberKg,
                // Стоимости
                grassCost:       grassCost,
                grassBaseCost:   grassBaseCost,
                markupAreaCost:  markupAreaCost,
                glueCost:        glueCost,
                baseGlueCost:    baseGlueCost,
                markupGlueCost:  markupGlueCost,
                tapeCost:        tapeCost,
                baseTapeCost:    baseTapeCost,
                markupTapeCost:  markupTapeCost,
                sandCost:        sandCost,
                rubberCost:      rubberCost
            };

            this._lastCalc = result;
            this.updateResultsUI(result);
            this.updateTotals(result);
            this.drawCanvas();
        },

        /* -------------------------------------------------------------------
           Расчёт для линолеума
           Клей: 0.35 кг/м² × площадь; Шнур: длина швов × 1.10
           ------------------------------------------------------------------- */

        /* -------------------------------------------------------------------
           Простой расчёт площади + опциональная приклейка клеем
           ------------------------------------------------------------------- */
        _calculateSimple: function (L, W, rW, rL, area) {
            /* --- Рулоны и швы (только если включено) --- */
            var rollCount    = 0;
            var stripsCount  = 0;
            var hSeamsCount  = 0;
            var totalStyk    = 0;
            var totalSeamLen = 0;

            if (this.config.simpleRollsEnabled) {
                rollCount   = Math.ceil(area / (rW * rL));
                stripsCount = Math.ceil(W / rW);
                hSeamsCount = stripsCount - 1;
                var hSeamLen = hSeamsCount * L;
                var styk_odd = 0, styk_even = 0, xi;
                if (rL < L) {
                    xi = rL;     while (xi < L) { styk_odd++;  xi += rL; }
                    xi = rL / 2; while (xi < L) { styk_even++; xi += rL; }
                }
                var oddStripsCount  = Math.ceil(stripsCount / 2);
                var evenStripsCount = Math.floor(stripsCount / 2);
                totalStyk    = oddStripsCount * styk_odd + evenStripsCount * styk_even;
                var vSeamLen = totalStyk * rW;
                totalSeamLen = hSeamLen + vSeamLen;
            }

            /* --- Стоимость покрытия --- */
            var price         = this.product ? (this.product.price || 0) : 0;
            var grassBaseCost = area * price;

            /* --- Приклейка клеем (если включена в настройках) --- */
            var glueCount    = 0;
            var glueKgDisplay = 0;
            var glueCost     = 0;
            if (this.config.simpleGlueEnabled) {
                var glueRate   = this.config.simpleGlueRate || 0.35;
                var glueVolume = this.config.glueVolume || 10;
                var glueKg     = area * glueRate;
                glueCount      = Math.ceil(glueKg / glueVolume);
                glueKgDisplay  = glueCount * glueVolume;
                glueCost       = glueCount * (this.config.gluePrice || 0);
            }

            var result = {
                area:         area,
                grassCost:    grassBaseCost,
                grassBaseCost: grassBaseCost,
                rollCount:    rollCount,
                stripsCount:  stripsCount,
                hSeamsCount:  hSeamsCount,
                totalStyk:    totalStyk,
                totalSeamLen: totalSeamLen,
                glueCount:    glueCount,
                glueKgDisplay: glueKgDisplay,
                glueCost:     glueCost
            };

            this._lastCalc = result;
            this._updateResultsSimple(result);
            this._updateTotalsSimple(result);
            this.drawCanvas();
        },

        _updateResultsSimple: function (res) {
            var $r = this.$wrap.find('#ssc-results-' + this.calcId);

            if (this.config.simpleRollsEnabled) {
                $r.find('.ssc-res-rolls').text(res.rollCount + ' шт');
                $r.find('.ssc-res-seams').text(
                    res.totalSeamLen.toFixed(1) + ' м.п. (горизонтальные: ' + res.hSeamsCount + ' шт., вертикальные: ' + res.totalStyk + ' шт.)'
                );
            }
            $r.find('.ssc-res-area').text(Math.round(res.area) + ' м²');
            $r.find('.ssc-res-grass-cost').text(this._fmt(res.grassBaseCost) + ' руб.');

            if (this.config.simpleGlueEnabled) {
                $r.find('.ssc-res-glue-base-val').text(res.glueCount + ' банок (' + res.glueKgDisplay + ' кг)');
                $r.find('.ssc-res-glue-base-cost').text(this._fmt(res.glueCost) + ' руб.');
            }

            this.$wrap.find('.ssc-step--results').addClass('ssc-step--active');
        },

        _updateTotalsSimple: function (res) {
            if (!res) return;
            var total = res.grassBaseCost;
            if (this.config.simpleGlueEnabled) total += res.glueCost;
            this.$wrap.find('.ssc-res-total').text(this._fmt(total) + ' руб.');
            this.$wrap.find('.ssc-form-area').val(Math.round(res.area) + ' м²');
            this.$wrap.find('.ssc-form-rolls').val(res.rollCount + ' шт');
        },

        _calculateLinoleum: function (L, W, rW, rL, area) {
            /* --- Рулоны --- */
            var rollCount = Math.ceil(area / (rW * rL));

            /* --- Горизонтальные стыки --- */
            var stripsCount = Math.ceil(W / rW);
            var hSeamsCount = stripsCount - 1;
            var hSeamLen    = hSeamsCount * L;

            /* --- Вертикальные стыки (шахматный порядок) --- */
            var styk_odd = 0, styk_even = 0, xi;
            if (rL < L) {
                xi = rL;     while (xi < L) { styk_odd++;  xi += rL; }
                xi = rL / 2; while (xi < L) { styk_even++; xi += rL; }
            }
            var oddStripsCount  = Math.ceil(stripsCount / 2);
            var evenStripsCount = Math.floor(stripsCount / 2);
            var totalStyk    = oddStripsCount * styk_odd + evenStripsCount * styk_even;
            var vSeamLen     = totalStyk * rW;
            var totalSeamLen = hSeamLen + vSeamLen;

            /* --- Клей (напольный): 0.35 кг/м², округление до банок --- */
            var glueKg       = area * 0.35;
            var glueVolume   = this.config.glueVolume || 10;
            var glueCount    = Math.ceil(glueKg / glueVolume);
            var glueKgDisplay = glueCount * glueVolume;
            var glueCost     = glueCount * (this.config.gluePrice || 0);

            /* --- Сварочный шнур: +10% подрез --- */
            var cordMeters   = totalSeamLen * 1.10;
            var tapeVolume   = this.config.tapeVolume || 50;
            var cordCount    = Math.ceil(cordMeters / tapeVolume);
            var cordMDisplay = cordCount * tapeVolume;
            var cordCost     = cordMDisplay * (this.config.tapePrice || 0);

            /* --- Стоимость покрытия --- */
            var price         = this.product ? (this.product.price || 0) : 0;
            var grassBaseCost = area * price;

            /* --- Разметка: краска --- */
            var markupType = this.$wrap.find('input[name="ssc_markup_type_' + this.calcId + '"]:checked').val() || 'none';
            var paintCans  = 0;
            var paintCost  = 0;
            if (this.config.markupEnabled && markupType !== 'none') {
                paintCans = (markupType === 'volleyball') ? 2 : 3;
                paintCost = paintCans * (this.config.paintPrice || 0);
            }

            var result = {
                area:         area,
                grassCost:    grassBaseCost,
                grassBaseCost: grassBaseCost,
                rollCount:    rollCount,
                stripsCount:  stripsCount,
                hSeamsCount:  hSeamsCount,
                totalStyk:    totalStyk,
                totalSeamLen: totalSeamLen,
                // Клей
                glueKg:    glueKg,
                glueCost:  glueCost,
                // Шнур
                cordMeters:   cordMeters,
                cordCount:    cordCount,
                cordMDisplay: cordMDisplay,
                cordCost:     cordCost,
                // Краска разметки
                markupType:  markupType,
                paintCans:   paintCans,
                paintCost:   paintCost,
                // Заглушки для общей логики PDF/form
                glueCount:     glueCount,
                glueKgDisplay: glueKgDisplay,
                tapeMeters:    cordMeters,
                tapeCount:     cordCount,
                tapeMDisplay:  cordMDisplay,
                tapeCost:      cordCost,
                markupCount:   0,
                markupArea:    0,
            };

            this._lastCalc = result;
            this._updateResultsLinoleum(result);
            this._updateTotalsLinoleum(result);
            this.drawCanvas();
        },

        /* -------------------------------------------------------------------
           Расчёт для сценического линолеума
           Основание: клей (0.35 кг/м²) ИЛИ скотч двуст. по швам (×2 +10%) ИЛИ по периметру (+5%)
           Швы: шнур (+10%) ИЛИ скотч одностор. (+10%) ИЛИ холодная сварка (без цены)
           ------------------------------------------------------------------- */
        _calculateScenic: function (L, W, rW, rL, area) {
            /* --- Рулоны --- */
            var rollCount = Math.ceil(area / (rW * rL));

            /* --- Швы (те же формулы, что у линолеума) --- */
            var stripsCount = Math.ceil(W / rW);
            var hSeamsCount = stripsCount - 1;
            var hSeamLen    = hSeamsCount * L;

            var styk_odd = 0, styk_even = 0, xi;
            if (rL < L) {
                xi = rL;     while (xi < L) { styk_odd++;  xi += rL; }
                xi = rL / 2; while (xi < L) { styk_even++; xi += rL; }
            }
            var oddStripsCount  = Math.ceil(stripsCount / 2);
            var evenStripsCount = Math.floor(stripsCount / 2);
            var totalStyk    = oddStripsCount * styk_odd + evenStripsCount * styk_even;
            var vSeamLen     = totalStyk * rW;
            var totalSeamLen = hSeamLen + vSeamLen;

            var gluePrice = this.config.gluePrice || 0;
            var tapePrice = this.config.tapePrice || 0;

            /* --- Основание --- */
            var baseType    = this.$wrap.find('input[name="ssc_base_' + this.calcId + '"]:checked').val() || 'none';
            var baseKg      = 0;
            var baseMeters  = 0;
            var baseCost    = 0;
            var baseDisplay = '';

            if (baseType === 'glue') {
                baseKg            = area * 0.35;
                var glueVolume    = this.config.glueVolume || 10;
                var baseGlueCount = Math.ceil(baseKg / glueVolume);
                baseCost          = baseGlueCount * gluePrice;
                baseDisplay       = baseGlueCount + ' банок (' + Math.round(baseKg) + ' кг)';
            } else if (baseType === 'tape') {
                var seamsM          = totalSeamLen * 2 * 1.10;
                var perimM          = (2 * (L + W)) * 1.05;
                baseMeters          = seamsM + perimM;
                var baseTapePrice   = this.config.scenicBaseTapePrice || 0;
                var baseTapeVol     = this.config.scenicBaseTapeVol || 50;
                var baseTapeCount   = Math.ceil(baseMeters / baseTapeVol);
                var baseTapeDisplay = baseTapeCount * baseTapeVol;
                baseCost            = baseTapeCount * baseTapePrice;
                baseDisplay         = baseTapeCount + ' рул. (' + baseMeters.toFixed(1) + ' м.п.)';
            }

            /* --- Обработка швов --- */
            var seamType     = this.$wrap.find('input[name="ssc_seam_' + this.calcId + '"]:checked').val() || 'none';
            var seamMeters   = 0;
            var seamCost     = 0;
            var seamDisplay  = '';
            var seamCount    = 0;
            var seamMDisplay = 0;

            if (seamType === 'cord') {
                seamMeters   = totalSeamLen * 1.10;
                var cordVol  = this.config.scenicSeamCordVol || 50;
                seamCount    = Math.ceil(seamMeters / cordVol);
                seamMDisplay = seamCount * cordVol;
                seamCost     = seamCount * (this.config.scenicSeamCordPrice || 0);
                seamDisplay  = seamCount + ' бухт. (' + seamMeters.toFixed(1) + ' м.п. +10% подрез)';
            } else if (seamType === 'tape') {
                seamMeters   = totalSeamLen * 1.10;
                var stVol    = this.config.scenicSeamTapeVol || 50;
                seamCount    = Math.ceil(seamMeters / stVol);
                seamMDisplay = seamCount * stVol;
                seamCost     = seamCount * (this.config.scenicSeamTapePrice || 0);
                seamDisplay  = seamCount + ' рул. (' + seamMeters.toFixed(1) + ' м.п. +10% подрез)';
            } else if (seamType === 'cold_weld') {
                seamMeters   = totalSeamLen;
                var weldVol  = this.config.scenicSeamWeldVol || 10;
                seamCount    = Math.ceil(seamMeters / weldVol);
                seamMDisplay = seamCount * weldVol;
                seamCost     = seamCount * (this.config.scenicSeamWeldPrice || 0);
                seamDisplay  = seamCount + ' тюб. (' + seamMeters.toFixed(1) + ' м.п.)';
            }

            /* --- Стоимость покрытия --- */
            var price         = this.product ? (this.product.price || 0) : 0;
            var grassBaseCost = area * price;

            /* --- Разметка: краска (те же типы, что у линолеума) --- */
            var markupType = this.$wrap.find('input[name="ssc_markup_type_' + this.calcId + '"]:checked').val() || 'none';
            var paintCans  = 0;
            var paintCost  = 0;
            if (this.config.markupEnabled && markupType !== 'none') {
                paintCans = (markupType === 'volleyball') ? 2 : 3;
                paintCost = paintCans * (this.config.paintPrice || 0);
            }

            var result = {
                area:          area,
                grassCost:     grassBaseCost,
                grassBaseCost: grassBaseCost,
                rollCount:     rollCount,
                stripsCount:   stripsCount,
                hSeamsCount:   hSeamsCount,
                totalStyk:     totalStyk,
                totalSeamLen:  totalSeamLen,
                // Основание
                baseType:    baseType,
                baseKg:      baseKg,
                baseMeters:  baseMeters,
                baseCost:    baseCost,
                baseDisplay: baseDisplay,
                // Швы
                seamType:    seamType,
                seamMeters:  seamMeters,
                seamCount:   seamCount,
                seamMDisplay: seamMDisplay,
                seamCost:    seamCost,
                seamDisplay: seamDisplay,
                // Краска разметки
                markupType: markupType,
                paintCans:  paintCans,
                paintCost:  paintCost,
                // Заглушки для совместимости PDF/form
                glueKg:        baseType === 'glue' ? baseKg : 0,
                glueCost:      baseType === 'glue' ? baseCost : 0,
                cordMeters:    seamMeters,
                cordCount:     seamCount,
                cordMDisplay:  seamMDisplay,
                cordCost:      seamCost,
                glueCount:     baseType === 'glue' ? Math.ceil(baseKg / (this.config.glueVolume || 10)) : 0,
                glueKgDisplay: baseType === 'glue' ? Math.ceil(baseKg / (this.config.glueVolume || 10)) * (this.config.glueVolume || 10) : 0,
                tapeMeters:    seamMeters,
                tapeCount:     seamCount,
                tapeMDisplay:  seamMDisplay,
                tapeCost:      seamCost,
                markupCount:   0,
                markupArea:    0,
            };

            this._lastCalc = result;
            this._updateResultsScenic(result);
            this._updateTotalsScenic(result);
            this.drawCanvas();
        },

        _updateResultsScenic: function (res) {
            var $r = this.$wrap.find('#ssc-results-' + this.calcId);

            $r.find('.ssc-res-rolls').text(res.rollCount + ' шт');
            $r.find('.ssc-res-seams').text(
                res.totalSeamLen.toFixed(1) + ' м.п. (горизонтальные: ' + res.hSeamsCount + ' шт., вертикальные: ' + res.totalStyk + ' шт.)'
            );
            $r.find('.ssc-res-area').text(Math.round(res.area) + ' м²');
            $r.find('.ssc-res-grass-cost').text(this._fmt(res.grassBaseCost) + ' руб.');

            // Основание
            $r.find('.ssc-res-base-val').text(res.baseDisplay);
            $r.find('.ssc-res-base-cost').text(res.baseCost > 0 ? this._fmt(res.baseCost) + ' руб.' : '');
            $r.find('.ssc-res-base-val').closest('.ssc-result-row--scenic-result').toggle(res.baseType !== 'none');

            // Швы
            $r.find('.ssc-res-seam-val').text(res.seamDisplay);
            $r.find('.ssc-res-seam-cost').text(res.seamCost > 0 ? this._fmt(res.seamCost) + ' руб.' : '');
            $r.find('.ssc-res-seam-val').closest('.ssc-result-row--scenic-result').toggle(res.seamType !== 'none');

            // Краска разметки
            var hasPaint = res.paintCans > 0;
            $r.find('.ssc-res-row-paint').toggle(hasPaint);
            if (hasPaint) {
                $r.find('.ssc-res-paint-val').text(res.paintCans + ' банки');
                $r.find('.ssc-res-paint-cost').text(this._fmt(res.paintCost) + ' руб.');
            }

            this.$wrap.find('.ssc-step--results').addClass('ssc-step--active');
        },

        _updateTotalsScenic: function (res) {
            if (!res) return;
            var total = res.grassBaseCost + res.baseCost + res.seamCost;
            if (res.paintCans > 0) total += res.paintCost;

            this.$wrap.find('.ssc-res-total').text(this._fmt(total) + ' руб.');

            this.$wrap.find('.ssc-form-area').val(Math.round(res.area) + ' м²');
            this.$wrap.find('.ssc-form-rolls').val(res.rollCount + ' шт');
            this.$wrap.find('.ssc-form-seams').val(res.totalSeamLen.toFixed(1) + ' м.п.');
            this.$wrap.find('.ssc-form-glue').val(res.baseDisplay || '—');
            this.$wrap.find('.ssc-form-tape').val(res.seamDisplay || '—');
            this.$wrap.find('.ssc-form-total').val(this._fmt(total));
        },

        _updateResultsLinoleum: function (res) {
            var $r = this.$wrap.find('#ssc-results-' + this.calcId);

            $r.find('.ssc-res-rolls').text(res.rollCount + ' шт');
            $r.find('.ssc-res-seams').text(
                res.totalSeamLen.toFixed(1) + ' м.п. (горизонтальные: ' + res.hSeamsCount + ' шт., вертикальные: ' + res.totalStyk + ' шт.)'
            );

            $r.find('.ssc-res-area').text(Math.round(res.area) + ' м²');
            $r.find('.ssc-res-grass-cost').text(this._fmt(res.grassBaseCost) + ' руб.');

            $r.find('.ssc-res-glue-base-val').text(res.glueCount + ' банок (' + res.glueKgDisplay + ' кг)');
            $r.find('.ssc-res-glue-base-cost').text(this._fmt(res.glueCost) + ' руб.');

            $r.find('.ssc-res-tape-base-val').text(res.cordCount + ' рул. (' + res.cordMeters.toFixed(1) + ' м.п. +10% подрез)');
            $r.find('.ssc-res-tape-base-cost').text(this._fmt(res.cordCost) + ' руб.');

            // Краска разметки
            var hasPaint = res.paintCans > 0;
            $r.find('.ssc-res-row-paint').toggle(hasPaint);
            if (hasPaint) {
                $r.find('.ssc-res-paint-val').text(res.paintCans + ' банки');
                $r.find('.ssc-res-paint-cost').text(this._fmt(res.paintCost) + ' руб.');
            }

            this.$wrap.find('.ssc-step--results').addClass('ssc-step--active');
            this._syncCheckboxRowsLinoleum(res);
        },

        _syncCheckboxRowsLinoleum: function (res) {
            if (!res) return;
            var glueChecked = this.$wrap.find('.ssc-glue-check').prop('checked');
            if (glueChecked) {
                this.$wrap.find('.ssc-res-glue-base-val').text(res.glueCount + ' банок (' + res.glueKgDisplay + ' кг)');
                this.$wrap.find('.ssc-res-glue-base-cost').text(this._fmt(res.glueCost) + ' руб.');
            } else {
                this.$wrap.find('.ssc-res-glue-base-val').text('');
                this.$wrap.find('.ssc-res-glue-base-cost').text('');
            }
            var cordChecked = this.$wrap.find('.ssc-tape-check').prop('checked');
            if (cordChecked) {
                this.$wrap.find('.ssc-res-tape-base-val').text(res.cordCount + ' рул. (' + res.cordMeters.toFixed(1) + ' м.п. +10% подрез)');
                this.$wrap.find('.ssc-res-tape-base-cost').text(this._fmt(res.cordCost) + ' руб.');
            } else {
                this.$wrap.find('.ssc-res-tape-base-val').text('');
                this.$wrap.find('.ssc-res-tape-base-cost').text('');
            }
        },

        _updateTotalsLinoleum: function (res) {
            if (!res) return;
            var total = res.grassBaseCost;
            if (this.$wrap.find('.ssc-glue-check').is(':checked')) total += res.glueCost;
            if (this.$wrap.find('.ssc-tape-check').is(':checked')) total += res.cordCost;
            if (res.paintCans > 0) total += res.paintCost;

            this.$wrap.find('.ssc-res-total').text(this._fmt(total) + ' руб.');

            this.$wrap.find('.ssc-form-area').val(Math.round(res.area) + ' м²');
            this.$wrap.find('.ssc-form-rolls').val(res.rollCount + ' шт');
            this.$wrap.find('.ssc-form-seams').val(res.totalSeamLen.toFixed(1) + ' м.п.');
            this.$wrap.find('.ssc-form-glue').val(res.glueCount + ' банок (' + res.glueKgDisplay + ' кг)');
            this.$wrap.find('.ssc-form-tape').val(res.cordCount + ' рул. (' + res.cordMDisplay + ' м.п.)');
            this.$wrap.find('.ssc-form-total').val(this._fmt(total));
        },

        /* -------------------------------------------------------------------
           Плотность насыпки по высоте ворса (кг/м²)
           Точная таблица из trava2 calculator-js.js
           Результат в кг/м², для перевода в тонны делить на 1000.
           ------------------------------------------------------------------- */
        _sandDensity: function (h) {
            if (h >= 10 && h <= 12) return 7.8;
            if (h >= 14 && h <= 15) return 10;
            if (h == 20) return 4.8;
            if (h == 25) return 6;
            if (h == 30) return 7.5;
            if (h >= 32 && h <= 35) return 9;
            if (h == 40) return 12;
            if (h == 45) return 20;
            if (h == 50) return 22;
            if (h == 55) return 22;
            if (h == 60) return 22;
            return 0;
        },
        _rubberDensity: function (h) {
            if (h < 20) return 0;
            if (h == 20) return 3;
            if (h == 25) return 4;
            if (h == 30) return 5;
            if (h >= 32 && h <= 35) return 6;
            if (h == 40) return 7;
            if (h == 45) return 8;
            if (h == 50) return 9;
            if (h == 55) return 10.5;
            if (h == 60) return 12;
            return 0;
        },

        /* -------------------------------------------------------------------
           Обновить UI результатов
           ------------------------------------------------------------------- */
        updateResultsUI: function (res) {
            var $r = this.$wrap.find('#ssc-results-' + this.calcId);
            var hasMarkup = res.markupType && res.markupType !== 'none' && res.markupCount > 0;

            // Рулоны и швы (без цен)
            $r.find('.ssc-res-rolls').text(res.rollCount + ' шт');
            $r.find('.ssc-res-seams').text(res.totalSeamLen.toFixed(1) + ' м.п. (горизонтальные: ' + res.hSeamsCount + ' шт., вертикальные: ' + res.totalStyk + ' шт.)');

            // Площадь
            $r.find('.ssc-res-area').text(Math.round(res.area) + ' м²');
            $r.find('.ssc-res-grass-cost').text(this._fmt(res.grassBaseCost) + ' руб.');

            // Разметка площадь
            $r.find('.ssc-res-row-markup-area').toggle(hasMarkup);
            if (hasMarkup) {
                $r.find('.ssc-res-markup-area-val').text(res.markupArea + ' м²');
                $r.find('.ssc-res-markup-cost').text(this._fmt(res.markupAreaCost) + ' руб.');
            }

            // Клей
            $r.find('.ssc-res-glue-base-val').text(res.baseGlueCansDisplay + ' банок (' + Math.round(res.baseGlueKg) + ' кг)');
            $r.find('.ssc-res-glue-base-cost').text(this._fmt(res.baseGlueCost) + ' руб.');
            $r.find('.ssc-res-glue-markup').toggle(hasMarkup);
            if (hasMarkup) {
                $r.find('.ssc-res-glue-markup-val').text(res.markupGlueCansDisplay + ' банок (' + Math.round(res.markupGlueKg) + ' кг)');
                $r.find('.ssc-res-glue-markup-cost').text(this._fmt(res.markupGlueCost) + ' руб.');
            }

            // Лента
            $r.find('.ssc-res-tape-base-val').text(res.baseTapeCount + ' рул. (' + res.baseTapeMeters.toFixed(1) + ' м.п. +5% подрез)');
            $r.find('.ssc-res-tape-base-cost').text(this._fmt(res.baseTapeCost) + ' руб.');
            $r.find('.ssc-res-tape-markup').toggle(hasMarkup);
            if (hasMarkup) {
                $r.find('.ssc-res-tape-markup-val').text(res.markupTapeCount + ' рул. (' + res.markupTapeMeters.toFixed(1) + ' м.п. +5% подрез)');
                $r.find('.ssc-res-tape-markup-cost').text(this._fmt(res.markupTapeCost) + ' руб.');
            }

            // Песок/крошка
            $r.find('.ssc-res-sand').text(res.sandKg + ' кг');
            $r.find('.ssc-res-sand-cost').text(this._fmt(res.sandCost) + ' руб.');
            $r.find('.ssc-res-rubber').text(res.rubberKg + ' кг');
            $r.find('.ssc-res-rubber-cost').text(this._fmt(res.rubberCost) + ' руб.');

            this.$wrap.find('.ssc-step--results').addClass('ssc-step--active');
            this.syncCheckboxRows(res);
        },

        /* -------------------------------------------------------------------
           Показать/скрыть ssc-result-val в строках с чекбоксами
           ------------------------------------------------------------------- */
        syncCheckboxRows: function (res) {
            var hasMarkup = res && res.markupType && res.markupType !== 'none' && res.markupCount > 0;

            // Клей: строка-чекбокс всегда видна, значения только если ✓
            var glueChecked = this.$wrap.find('.ssc-glue-check').prop('checked');
            this.$wrap.find('.ssc-glue-sub-label').toggle(glueChecked);
            if (glueChecked) {
                this.$wrap.find('.ssc-res-glue-base-val').text(res.baseGlueCansDisplay + ' банок (' + Math.round(res.baseGlueKg) + ' кг)');
                this.$wrap.find('.ssc-res-glue-base-cost').text(this._fmt(res.baseGlueCost) + ' руб.');
            } else {
                this.$wrap.find('.ssc-res-glue-base-val').text('');
                this.$wrap.find('.ssc-res-glue-base-cost').text('');
            }
            this.$wrap.find('.ssc-res-glue-markup').toggle(glueChecked && hasMarkup);

            // Лента: строка-чекбокс всегда видна, значения только если ✓
            var tapeChecked = this.$wrap.find('.ssc-tape-check').prop('checked');
            this.$wrap.find('.ssc-tape-sub-label').toggle(tapeChecked);
            if (tapeChecked) {
                this.$wrap.find('.ssc-res-tape-base-val').text(res.baseTapeCount + ' рул. (' + res.baseTapeMeters.toFixed(1) + ' м.п. +5% подрез)');
                this.$wrap.find('.ssc-res-tape-base-cost').text(this._fmt(res.baseTapeCost) + ' руб.');
            } else {
                this.$wrap.find('.ssc-res-tape-base-val').text('');
                this.$wrap.find('.ssc-res-tape-base-cost').text('');
            }
            this.$wrap.find('.ssc-res-tape-markup').toggle(tapeChecked && hasMarkup);
            if (tapeChecked && hasMarkup) {
                this.$wrap.find('.ssc-res-tape-markup-val').text(res.markupTapeCount + ' рул. (' + res.markupTapeMeters.toFixed(1) + ' м.п. +5% подрез)');
                this.$wrap.find('.ssc-res-tape-markup-cost').text(this._fmt(res.markupTapeCost) + ' руб.');
            }

            // Песок/крошка
            var sandChecked = this.$wrap.find('.ssc-sand-check').prop('checked');
            this.$wrap.find('.ssc-res-sand').toggle(sandChecked);
            this.$wrap.find('.ssc-res-sand-cost').toggle(sandChecked);

            var rubberChecked = this.$wrap.find('.ssc-rubber-check').prop('checked');
            this.$wrap.find('.ssc-res-rubber').toggle(rubberChecked);
            this.$wrap.find('.ssc-res-rubber-cost').toggle(rubberChecked);
        },

        updateTotals: function (res) {
            if (!res) return;
            var total = res.grassCost;
            if (this.$wrap.find('.ssc-glue-check').is(':checked'))   total += res.glueCost;
            if (this.$wrap.find('.ssc-tape-check').is(':checked'))   total += res.tapeCost;
            if (this.$wrap.find('.ssc-sand-check').is(':checked'))   total += res.sandCost;
            if (this.$wrap.find('.ssc-rubber-check').is(':checked')) total += res.rubberCost;

            this.$wrap.find('.ssc-res-total').text(this._fmt(total) + ' руб.');

            // Скрытые поля формы
            this.$wrap.find('.ssc-form-area').val(Math.round(res.area) + ' м²');
            this.$wrap.find('.ssc-form-rolls').val(res.rollCount + ' шт');
            this.$wrap.find('.ssc-form-seams').val(res.totalSeamLen.toFixed(1) + ' м.п.');
            this.$wrap.find('.ssc-form-glue').val(res.glueCount + ' банок (' + res.glueKgDisplay + ' кг)');
            this.$wrap.find('.ssc-form-tape').val(res.tapeCount + ' рул. (' + res.tapeMDisplay + ' м.п.)');
            this.$wrap.find('.ssc-form-total').val(this._fmt(total));
        },

        _fmt: function (n) {
            return Math.round(n).toLocaleString('ru-RU');
        },

        /* -------------------------------------------------------------------
           Отрисовка canvas — шахматная раскладка рулонов
           ------------------------------------------------------------------- */
        drawCanvas: function () {
            if (!this.ctx) return;
            var canvas = this.$canvas[0];
            var CW     = canvas.width;
            var CH     = canvas.height;
            var ctx    = this.ctx;

            ctx.clearRect(0, 0, CW, CH);

            var L  = this.areaLen;
            var Aw = this.areaWid;
            var rW = this.rollWidth;
            var rL = this.rollLength;

            if (!L || !Aw || !rW || !rL) return;

            // Масштаб: ось X = длина площадки (L), ось Y = ширина площадки (Aw)
            var scaleX = CW / L;
            var scaleY = CH / Aw;

            var stripsCount = Math.ceil(Aw / rW);
            ctx.lineWidth   = 1.5;
            ctx.strokeStyle = '#ffff00';

            for (var s = 0; s < stripsCount; s++) {
                var y0 = Math.round(s * rW * scaleY);
                var y1 = Math.round(Math.min((s + 1) * rW, Aw) * scaleY);
                var isOdd = (s % 2 === 0); // 0-indexed: чётный индекс = нечётная полоса

                // Чередующийся фон полос
                ctx.fillStyle = isOdd ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
                ctx.fillRect(0, y0, CW, y1 - y0);

                // Горизонтальная линия — шов между полосами
                if (s > 0) {
                    ctx.strokeStyle = 'rgba(255,220,0,0.9)';
                    ctx.lineWidth   = 2;
                    ctx.setLineDash([6, 3]);
                    ctx.beginPath();
                    ctx.moveTo(0, y0);
                    ctx.lineTo(CW, y0);
                    ctx.stroke();
                }

                // Вертикальные швы — шахматный сдвиг (только если рулон короче площадки)
                if (rL < L) {
                    var xStart = isOdd ? rL * scaleX : (rL / 2) * scaleX;
                    var xi = xStart;
                    ctx.strokeStyle = 'rgba(255,220,0,0.7)';
                    ctx.lineWidth   = 1.5;
                    ctx.setLineDash([4, 4]);
                    while (xi < CW) {
                        ctx.beginPath();
                        ctx.moveTo(Math.round(xi), y0);
                        ctx.lineTo(Math.round(xi), y1);
                        ctx.stroke();
                        xi += rL * scaleX;
                    }
                }
            }

            ctx.setLineDash([]);

            // Контур площадки
            ctx.strokeStyle = 'rgba(255,255,255,0.8)';
            ctx.lineWidth   = 2;
            ctx.strokeRect(0, 0, CW, CH);

            // Подписи
            ctx.fillStyle   = '#fff';
            ctx.font        = 'bold 13px sans-serif';
            ctx.shadowColor = 'rgba(0,0,0,0.6)';
            ctx.shadowBlur  = 4;
            ctx.textAlign   = 'left';
            ctx.fillText('Длина: ' + L + ' м', 8, CH - 8);
            ctx.textAlign   = 'right';
            ctx.fillText('Ширина: ' + Aw + ' м', CW - 8, CH - 8);
            ctx.textAlign   = 'left';
            ctx.shadowBlur  = 0;
        },

        /* -------------------------------------------------------------------
           Модальное окно КП
           ------------------------------------------------------------------- */
        openModal: function () {
            var res = this._lastCalc;
            if (!res || !res.area) {
                this.$wrap.find('.ssc-kp-error').show();
                return;
            }
            this.$wrap.find('.ssc-kp-error').hide();
            this.$wrap.find('#ssc-modal-' + this.calcId).fadeIn(200);
            $('body').addClass('ssc-modal-open');
        },

        closeModal: function () {
            this.$wrap.find('#ssc-modal-' + this.calcId).fadeOut(200);
            $('body').removeClass('ssc-modal-open');
        },

        /* -------------------------------------------------------------------
           Отправка формы + генерация PDF
           ------------------------------------------------------------------- */
        submitKP: function ($form) {
            var self = this;
            var $btn = $form.find('.ssc-submit-btn');

            $btn.prop('disabled', true).text(g.strings.sending);

            var formData = $form.serialize() + '&action=ssc_send_kp';
            $.post(g.ajaxUrl, formData, function () {
                $form.find('.ssc-form-success').show();
                $btn.hide();
                self.generatePDF($form);
            }).fail(function () {
                $btn.prop('disabled', false).text('Скачать КП (PDF)');
            });
        },

        /* -------------------------------------------------------------------
           Генерация PDF через pdfMake
           ------------------------------------------------------------------- */
        generatePDF: function ($form) {
            var self   = this;
            var res    = this._lastCalc;
            var config = this.config;
            var date   = config.siteDate || '';
            var company = config.companyName || window.location.hostname;

            // Поддержка вызова без формы (тестовая кнопка)
            var clientName  = '', clientPhone = '', clientEmail = '', productName = '';
            var $calc = this.$wrap;

            if ($form && $form.length) {
                clientName  = $form.find('[name="client_name"]').val();
                clientPhone = $form.find('[name="client_phone"]').val();
                clientEmail = $form.find('[name="client_email"]').val();
                productName = $form.find('.ssc-form-product-name').val();
                $calc = $form.closest('.ssc-calculator');
            }

            var total = res.grassBaseCost || res.grassCost;
            if (config.calcType === 'sceniclinoleum') {
                total += res.baseCost + res.seamCost;
                if (res.paintCans > 0) total += res.paintCost;
            } else if (config.calcType === 'linoleum') {
                if ($calc.find('.ssc-glue-check').is(':checked')) total += res.glueCost;
                if ($calc.find('.ssc-tape-check').is(':checked')) total += res.cordCost;
                if (res.paintCans > 0) total += res.paintCost;
            } else {
                if ($calc.find('.ssc-glue-check').is(':checked'))   total += res.glueCost;
                if ($calc.find('.ssc-tape-check').is(':checked'))   total += res.tapeCost;
                if ($calc.find('.ssc-sand-check').is(':checked'))   total += res.sandCost;
                if ($calc.find('.ssc-rubber-check').is(':checked')) total += res.rubberCost;
                if ($calc.find('.ssc-markup-check').is(':checked')) total += res.markupCost;
            }

            var canvasDataUrl = self.$canvas.length ? self.$canvas[0].toDataURL('image/png') : null;

            var tableBody;
            if (config.calcType === 'sceniclinoleum') {
                var baseLabels = {
                    'none': 'Без основания',
                    'glue': 'Клей (0,35 кг/м²)',
                    'tape': 'Скотч'
                };
                var seamLabels = {
                    'none':      'Без склейки',
                    'cord':      'Шнур',
                    'tape':      'Скотч',
                    'cold_weld': 'Холодная сварка'
                };
                tableBody = [
                    [{ text: 'Параметр', bold: true, fillColor: '#f0f6ff' }, { text: 'Значение', bold: true, fillColor: '#f0f6ff' }],
                    ['Покрытие', productName || '—'],
                    ['Площадь', Math.round(res.area) + ' м²'],
                    ['Количество рулонов', res.rollCount + ' шт'],
                    ['Горизонтальных стыков', res.hSeamsCount + ' шт'],
                    ['Вертикальных стыков', res.totalStyk + ' шт'],
                    ['Длина швов (всего)', res.totalSeamLen.toFixed(1) + ' м.п.'],
                    ['Основание', baseLabels[res.baseType] || res.baseType],
                ];
                if (res.baseDisplay) {
                    tableBody.push(['Количество (основание)', res.baseDisplay + (res.baseCost > 0 ? '  ≈ ' + self._fmt(res.baseCost) + ' руб.' : '')]);
                }
                tableBody.push(['Обработка швов', seamLabels[res.seamType] || res.seamType]);
                if (res.seamDisplay) {
                    tableBody.push(['Количество (швы)', res.seamDisplay + (res.seamCost > 0 ? '  ≈ ' + self._fmt(res.seamCost) + ' руб.' : '')]);
                }
                if (res.paintCans > 0) {
                    var paintLabels = { volleyball: 'Волейбол', basketball: 'Баскетбол', 'mini-football': 'Мини-футбол' };
                    tableBody.push(['Краска для разметки (' + (paintLabels[res.markupType] || res.markupType) + ')', res.paintCans + ' банки  ≈ ' + self._fmt(res.paintCost) + ' руб.']);
                }
                tableBody.push([{ text: 'ИТОГО', bold: true }, { text: self._fmt(total) + ' руб.', bold: true, color: '#2271b1' }]);
            } else if (config.calcType === 'linoleum') {
                var glueInPdf  = $calc.find('.ssc-glue-check').is(':checked');
                var cordInPdf  = $calc.find('.ssc-tape-check').is(':checked');
                tableBody = [
                    [{ text: 'Параметр', bold: true, fillColor: '#f0f6ff' }, { text: 'Значение', bold: true, fillColor: '#f0f6ff' }],
                    ['Покрытие', productName || '—'],
                    ['Площадь', Math.round(res.area) + ' м²'],
                    ['Количество рулонов', res.rollCount + ' шт'],
                    ['Горизонтальных стыков', res.hSeamsCount + ' шт'],
                    ['Вертикальных стыков', res.totalStyk + ' шт'],
                    ['Длина швов (всего)', res.totalSeamLen.toFixed(1) + ' м.п.'],
                ];
                if (glueInPdf) {
                    tableBody.push(['Клей (приклеивание, 0.35 кг/м²)', res.glueCount + ' банок (' + res.glueKgDisplay + ' кг)  ≈ ' + self._fmt(res.glueCost) + ' руб.']);
                }
                if (cordInPdf) {
                    tableBody.push(['Сварочный шнур (+10% подрез)', res.cordMeters.toFixed(1) + ' м.п.  ≈ ' + self._fmt(res.cordCost) + ' руб.']);
                }
                if (res.paintCans > 0) {
                    var paintLabels = { volleyball: 'Волейбол', basketball: 'Баскетбол', 'mini-football': 'Мини-футбол' };
                    tableBody.push(['Краска для разметки (' + (paintLabels[res.markupType] || res.markupType) + ')', res.paintCans + ' банки  ≈ ' + self._fmt(res.paintCost) + ' руб.']);
                }
                tableBody.push([{ text: 'ИТОГО', bold: true }, { text: self._fmt(total) + ' руб.', bold: true, color: '#2271b1' }]);
            } else {
            tableBody = [
                [{ text: 'Параметр', bold: true, fillColor: '#f0f6ff' }, { text: 'Значение', bold: true, fillColor: '#f0f6ff' }],
                ['Покрытие', productName || '—'],
                ['Площадь', res.totalGrassArea > res.area ? Math.round(res.totalGrassArea) + ' м² (поле ' + Math.round(res.area) + ' + разметка ' + res.markupArea + ')' : Math.round(res.area) + ' м²'],
                ['Количество рулонов', res.rollCount + ' шт'],
                ['Горизонтальных стыков', res.hSeamsCount + ' шт'],
                ['Вертикальных стыков', res.totalStyk + ' шт'],
                ['Длина швов (всего)', res.totalSeamLen.toFixed(1) + ' м.п.'],
                ['Клей', res.glueCount + ' банок (' + res.glueKgDisplay + ' кг)  ≈ ' + self._fmt(res.glueCost) + ' руб.'],
                ['Шовная лента', res.tapeCount + ' рул. (' + res.tapeMDisplay + ' м.п.)  ≈ ' + self._fmt(res.tapeCost) + ' руб.'],
            ];

            if (config.sandEnabled && res.sandKg) {
                tableBody.push(['Кварцевый песок', res.sandKg + ' кг  ≈ ' + self._fmt(res.sandCost) + ' руб.']);
            }
            if (config.rubberEnabled && res.rubberKg) {
                tableBody.push(['Резиновая крошка', res.rubberKg + ' кг  ≈ ' + self._fmt(res.rubberCost) + ' руб.']);
            }
            if (config.markupEnabled && res.markupType && res.markupType !== 'none' && res.markupArea > 0) {
                var markupLabels = { football: 'Футбол', 'mini-football': 'Мини-футбол', tennis: 'Теннис', hockey: 'Хоккей' };
                tableBody.push(['Тип разметки', markupLabels[res.markupType] || res.markupType]);
                tableBody.push(['Длина линий разметки', res.markupCount.toFixed(1) + ' м.п.']);
                tableBody.push(['Материал разметки (+3% подрез)', res.markupArea + ' м²  ≈ ' + self._fmt(res.markupCost) + ' руб.']);
            }
            tableBody.push([{ text: 'ИТОГО', bold: true }, { text: self._fmt(total) + ' руб.', bold: true, color: '#2271b1' }]);
            } // end grass/linoleum branch

            var content = [
                {
                    columns: [
                        { text: company, style: 'companyName', width: '*' },
                        { text: date, style: 'dateText', alignment: 'right', width: 'auto' }
                    ],
                    marginBottom: 12
                },
                { text: 'КОММЕРЧЕСКОЕ ПРЕДЛОЖЕНИЕ', style: 'header' },
                { text: 'Расчёт стоимости покрытия', style: 'subheader' },
                { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineColor: '#2271b1', lineWidth: 2 }], margin: [0, 0, 0, 16] },
            ];

            if (canvasDataUrl) {
                content.push({
                    image: canvasDataUrl,
                    width: 515,
                    margin: [0, 0, 0, 16],
                    alignment: 'center'
                });
            }

            content.push({
                table: {
                    widths: ['*', '*'],
                    body: tableBody
                },
                layout: {
                    hLineWidth: function () { return 0.5; },
                    vLineWidth: function () { return 0.5; },
                    hLineColor: function () { return '#ddd'; },
                    vLineColor: function () { return '#ddd'; },
                    fillColor: function (rowIndex) { return rowIndex % 2 === 0 ? null : '#f9f9f9'; }
                },
                margin: [0, 0, 0, 20]
            });

            content.push(
                { canvas: [{ type: 'line', x1: 0, y1: 0, x2: 515, y2: 0, lineColor: '#ddd', lineWidth: 1 }], margin: [0, 0, 0, 10] },
                { text: 'Данные заявителя:', style: 'sectionTitle', margin: [0, 0, 0, 6] },
                { text: 'Имя: ' + clientName, fontSize: 11, margin: [0, 0, 0, 3] },
                { text: 'Телефон: ' + clientPhone, fontSize: 11, margin: [0, 0, 0, 3] },
                { text: 'Email: ' + clientEmail, fontSize: 11, margin: [0, 0, 0, 12] },
                {
                    text: 'Данное коммерческое предложение является предварительным. Окончательная стоимость уточняется у менеджера.',
                    fontSize: 9,
                    color: '#888',
                    italics: true
                }
            );

            var docDef = {
                pageSize: 'A4',
                pageMargins: [30, 40, 30, 40],
                content: content,
                styles: {
                    companyName: { fontSize: 14, bold: true, color: '#1d2327' },
                    dateText: { fontSize: 11, color: '#666' },
                    header: { fontSize: 20, bold: true, color: '#2271b1', margin: [0, 0, 0, 4] },
                    subheader: { fontSize: 13, color: '#444', margin: [0, 0, 0, 8] },
                    sectionTitle: { fontSize: 12, bold: true, color: '#1d2327' }
                },
                defaultStyle: {
                    font: 'Roboto',
                    fontSize: 11,
                    color: '#1d2327'
                }
            };

            if (window.pdfMake) {
                pdfMake.createPdf(docDef).download('kp-' + (productName || 'raschet') + '.pdf');
            }
        }
    };

    /* =========================================================================
       Инициализация всех калькуляторов на странице
       ========================================================================= */
    $(document).ready(function () {
        $('.ssc-calculator').each(function () {
            new SSCInstance($(this));
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('.ssc-modal:visible').fadeOut(200);
                $('body').removeClass('ssc-modal-open');
            }
        });
    });

    $('<style>.ssc-modal-open { overflow: hidden; }</style>').appendTo('head');

}(jQuery));
