/* ==========================================================================
   SSC Admin JS
   ========================================================================== */
(function ($) {
    'use strict';

    var cfg = window.sscAdmin || {};
    var $panel = $('#ssc-edit-panel');
    var $panelTitle = $('#ssc-panel-title');
    var currentAttrs = []; // атрибуты текущей категории

    // --------------------------------------------------------------------------
    // Открыть/закрыть панель
    // --------------------------------------------------------------------------
    function openPanel(title) {
        $panelTitle.text(title);
        $panel.slideDown(200);
        $('html, body').animate({ scrollTop: $panel.offset().top - 40 }, 300);
    }

    function closePanel() {
        $panel.slideUp(200);
        resetForm();
    }

    function resetForm() {
        $('#ssc-calc-id').val('');
        $('#ssc-name').val('');
        $('#ssc-category').val('');
        $('#ssc-subcategory-slugs').val('');
        $('#ssc-width-attr').html('<option value="">' + cfg.strings.loading + '</option>');
        $('#ssc-length-attr').html('<option value="">' + cfg.strings.loading + '</option>');
        $('#ssc-filter-attrs-wrap').html('<p class="description">Сначала выберите категорию</p>');
        $('#ssc-canvas-image').val('');
        $('#ssc-canvas-preview').empty();
        $('.ssc-canvas-images-wrap').html('<p class="description" id="ssc-canvas-images-placeholder">Заполните подкатегории — здесь появятся поля для изображений</p>');
        $('#ssc-glue-price').val(4000);
        $('#ssc-tape-price').val(65);
        $('#ssc-sand-enabled').prop('checked', false);
        $('#ssc-sand-price').val(3950);
        $('#ssc-rubber-enabled').prop('checked', false);
        $('#ssc-rubber-price').val(24500);
        $('#ssc-markup-enabled').prop('checked', false);
        $('#ssc-paint-price').val(0);
        $('#ssc-company-name').val('');
        $('#ssc-admin-email').val('');
        $('.ssc-save-status').text('');
        $('#ssc-calc-type').val('grass').trigger('change');
    }

    // --------------------------------------------------------------------------
    // Рендер полей canvas для подкатегорий (по 4 типа разметки + без разметки)
    // --------------------------------------------------------------------------
    var mediaFrames = {};
    var markupTypes = [
        { key: 'none',        label: 'Без разметки' },
        { key: 'football',    label: 'Футбол' },
        { key: 'mini-football', label: 'Мини-футбол' },
        { key: 'tennis',      label: 'Теннис' },
        { key: 'hockey',      label: 'Хоккей' },
    ];

    function renderCanvasFields(canvasImages) {
        var slugs = ($('#ssc-subcategory-slugs').val() || '').trim().split(/\r?\n/).filter(Boolean);
        var $wrap = $('.ssc-canvas-images-wrap');
        if (!slugs.length) {
            $wrap.html('<p class="description" id="ssc-canvas-images-placeholder">Заполните подкатегории — здесь появятся поля для изображений</p>');
            return;
        }

        var calcType = $('#ssc-calc-type').val() || 'grass';
        var typesToRender = calcType === 'linoleum'
            ? [{ key: 'none', label: 'Изображение площадки' }]
            : markupTypes;

        var html = '';
        slugs.forEach(function (slug) {
            html += '<div class="ssc-canvas-subcategory" data-slug="' + slug + '">';
            html += '<h4 class="ssc-canvas-subcat-title">' + slug + '</h4>';
            typesToRender.forEach(function (mt) {
                var val = (canvasImages && canvasImages[slug] && canvasImages[slug][mt.key]) || '';
                var previewSrc = val;
                if (val && val.indexOf('http') !== 0) {
                    var siteUrl = cfg.siteUrl || '';
                    if (siteUrl) previewSrc = siteUrl + val;
                }
                html += '<div class="ssc-canvas-row" data-slug="' + slug + '" data-markup="' + mt.key + '">';
                html += '<span class="ssc-canvas-label">' + mt.label + ':</span>';
                html += '<div class="ssc-image-field">';
                html += '<input type="text" class="ssc-canvas-url regular-text" data-slug="' + slug + '" data-markup="' + mt.key + '" value="' + val + '" placeholder="https://.../field.jpg">';
                html += '<button type="button" class="button ssc-canvas-pick" data-slug="' + slug + '" data-markup="' + mt.key + '">' + cfg.strings.useImage + '</button>';
                html += '</div>';
                if (val) {
                    html += '<div class="ssc-canvas-preview"><img src="' + previewSrc + '" alt="" style="max-width:200px;margin-top:4px;border:1px solid #ddd;border-radius:3px;"></div>';
                }
                html += '</div>';
            });
            html += '</div>';
        });
        $wrap.html(html);
    }

    // Выбор изображения для canvas
    $(document).on('click', '.ssc-canvas-pick', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var slug = $btn.data('slug');
        var markup = $btn.data('markup');
        var key = slug + '|' + markup;
        var frame = mediaFrames[key];
        if (!frame) {
            frame = mediaFrames[key] = wp.media({
                title: cfg.strings.selectImage,
                button: { text: cfg.strings.useImage },
                multiple: false
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.url;
                // Конвертируем абсолютный URL в относительный путь для сохранения
                var siteUrl = cfg.siteUrl || '';
                var relUrl = url;
                if (siteUrl && url.indexOf(siteUrl) === 0) {
                    relUrl = url.replace(siteUrl, '');
                }
                var $input = $('.ssc-canvas-url[data-slug="' + slug + '"][data-markup="' + markup + '"]');
                $input.val(relUrl);
                var $preview = $input.closest('.ssc-canvas-row').find('.ssc-canvas-preview');
                $preview.remove();
                $input.closest('.ssc-image-field').after('<div class="ssc-canvas-preview"><img src="' + url + '" alt="" style="max-width:200px;margin-top:4px;border:1px solid #ddd;border-radius:3px;"></div>');
            });
        }
        frame.open();
    });

    // Переключение типа калькулятора
    $('#ssc-calc-type').on('change', function () {
        var type = $(this).val();
        $('.ssc-simple-label').hide();
        $('.ssc-scenic-only').hide();
        $('.ssc-simple-only').hide();
        if (type === 'simple') {
            $('.ssc-grass-only').hide();
            $('.ssc-linoleum-only').hide();
            $('.ssc-not-simple').hide();
            $('.ssc-grass-label').hide();
            $('.ssc-linoleum-label').hide();
            $('.ssc-simple-label').show();
            $('.ssc-simple-only').show();
        } else if (type === 'linoleum' || type === 'sceniclinoleum') {
            $('.ssc-grass-only').hide();
            $('.ssc-linoleum-only').show();
            $('.ssc-not-simple').show();
            $('.ssc-grass-label').hide();
            $('.ssc-linoleum-label').show();
            if (type === 'sceniclinoleum') {
                $('.ssc-scenic-only').show();
            }
        } else {
            $('.ssc-grass-only').show();
            $('.ssc-linoleum-only').hide();
            $('.ssc-not-simple').show();
            $('.ssc-grass-label').show();
            $('.ssc-linoleum-label').hide();
        }
        renderCanvasFields();
    });

    // Рендер полей при изменении подкатегорий
    $('#ssc-subcategory-slugs').on('input', function () {
        renderCanvasFields();
    });

    // --------------------------------------------------------------------------
    // Media Library — выбор изображения по умолчанию для canvas
    // --------------------------------------------------------------------------
    var defaultMediaFrame;
    $('#ssc-canvas-default-pick').on('click', function (e) {
        e.preventDefault();
        if (!defaultMediaFrame) {
            defaultMediaFrame = wp.media({
                title: cfg.strings.selectImage,
                button: { text: cfg.strings.useImage },
                multiple: false
            });
            defaultMediaFrame.on('select', function () {
                var attachment = defaultMediaFrame.state().get('selection').first().toJSON();
                var url = attachment.url;
                // Конвертируем абсолютный URL в относительный путь для сохранения
                var siteUrl = cfg.siteUrl || '';
                var relUrl = url;
                if (siteUrl && url.indexOf(siteUrl) === 0) {
                    relUrl = url.replace(siteUrl, '');
                }
                $('#ssc-canvas-image').val(relUrl);
                $('#ssc-canvas-preview').html('<img src="' + url + '" alt="" style="max-width:200px;margin-top:4px;border:1px solid #ddd;border-radius:3px;">');
            });
        }
        defaultMediaFrame.open();
    });

    // --------------------------------------------------------------------------
    // Кнопка «+ Добавить»
    // --------------------------------------------------------------------------
    $('#ssc-add-new').on('click', function () {
        resetForm();
        openPanel('Новый калькулятор');
    });

    // --------------------------------------------------------------------------
    // Закрыть панель
    // --------------------------------------------------------------------------
    $(document).on('click', '.ssc-panel-close', closePanel);

    // --------------------------------------------------------------------------
    // Кнопка «Изменить»
    // --------------------------------------------------------------------------
    $(document).on('click', '.ssc-edit-btn', function () {
        var id = $(this).data('id');
        $.get(cfg.ajaxUrl, { action: 'ssc_get_calculator', id: id, nonce: cfg.nonce }, function (res) {
            if (!res.success) {
                alert(cfg.strings.error);
                return;
            }
            var c = res.data;
            $('#ssc-calc-id').val(c.id);
            $('#ssc-calc-type').val(c.calculator_type || 'grass').trigger('change');
            $('#ssc-name').val(c.name);
            $('#ssc-company-name').val(c.company_name || '');
            $('#ssc-admin-email').val(c.admin_email || '');
            $('#ssc-glue-price').val(c.glue_price || 4000);
            $('#ssc-glue-volume').val(c.glue_volume || 10);
            $('#ssc-tape-price').val(c.tape_price || 55);
            $('#ssc-tape-volume').val(c.tape_volume || 50);
            $('#ssc-scenic-base-tape-price').val(c.scenic_base_tape_price || 500);
            $('#ssc-scenic-base-tape-volume').val(c.scenic_base_tape_volume || 50);
            $('#ssc-scenic-seam-cord-price').val(c.scenic_seam_cord_price || 500);
            $('#ssc-scenic-seam-cord-volume').val(c.scenic_seam_cord_volume || 50);
            $('#ssc-scenic-seam-tape-price').val(c.scenic_seam_tape_price || 500);
            $('#ssc-scenic-seam-tape-volume').val(c.scenic_seam_tape_volume || 50);
            $('#ssc-scenic-seam-weld-price').val(c.scenic_seam_weld_price || 500);
            $('#ssc-scenic-seam-weld-volume').val(c.scenic_seam_weld_volume || 10);
            $('#ssc-simple-rolls-enabled').prop('checked', !!c.simple_rolls_enabled);
            $('#ssc-simple-glue-enabled').prop('checked', !!c.simple_glue_enabled);
            $('#ssc-simple-glue-rate').val(c.simple_glue_rate || 0.35);
            $('#ssc-sand-enabled').prop('checked', !!c.sand_enabled);
            $('#ssc-sand-price').val(c.sand_price || 3950);
            $('#ssc-rubber-enabled').prop('checked', !!c.rubber_enabled);
            $('#ssc-rubber-price').val(c.rubber_price || 24500);
            $('#ssc-markup-enabled').prop('checked', !!c.markup_enabled);
            $('#ssc-paint-price').val(c.paint_price || 0);

            // Подкатегории — массив в строку
            if (c.subcategory_slugs && c.subcategory_slugs.length) {
                $('#ssc-subcategory-slugs').val(c.subcategory_slugs.join('\n'));
            }

            // Canvas изображение по умолчанию
            if (c.canvas_image) {
                $('#ssc-canvas-image').val(c.canvas_image);
                var previewSrc = (c.canvas_image.indexOf('http') === 0) ? c.canvas_image : ((cfg.siteUrl || '') + c.canvas_image);
                $('#ssc-canvas-preview').html('<img src="' + previewSrc + '" alt="" style="max-width:200px;margin-top:4px;border:1px solid #ddd;border-radius:3px;">');
            }

            // Canvas изображения для подкатегорий
            renderCanvasFields(c.canvas_images || {});

            // Выбрать категорию и загрузить атрибуты
            $('#ssc-category').val(c.category_slug);
            loadCategoryAttrs(c.category_slug, function () {
                // После загрузки атрибутов — выбрать нужные
                $('#ssc-width-attr').val(c.width_attr);
                $('#ssc-length-attr').val(c.length_attr);
                // Отметить фильтр-атрибуты
                if (c.filter_attrs && c.filter_attrs.length) {
                    c.filter_attrs.forEach(function (slug) {
                        $('#ssc-filter-attrs-wrap input[value="' + slug + '"]').prop('checked', true);
                    });
                }
            });

            openPanel('Редактировать калькулятор: ' + c.name);
        });
    });

    // --------------------------------------------------------------------------
    // Кнопка «Удалить»
    // --------------------------------------------------------------------------
    $(document).on('click', '.ssc-delete-btn', function () {
        if (!confirm(cfg.strings.confirmDelete)) return;
        var id = $(this).data('id');
        var $row = $(this).closest('tr');
        $.post(cfg.ajaxUrl, { action: 'ssc_delete_calculator', id: id, nonce: cfg.nonce }, function (res) {
            if (res.success) {
                $row.fadeOut(200, function () {
                    $(this).remove();
                    if ($('#ssc-list-body tr').length === 0) {
                        $('#ssc-list-body').html('<tr id="ssc-empty-row"><td colspan="5">Нет ни одного калькулятора. Создайте первый.</td></tr>');
                    }
                });
            }
        });
    });

    // --------------------------------------------------------------------------
    // Кнопка «Сохранить»
    // --------------------------------------------------------------------------
    $('#ssc-save-btn').on('click', function () {
        var $btn = $(this);
        var $status = $('.ssc-save-status');

        // Собираем filter_attrs
        var filterAttrs = [];
        $('#ssc-filter-attrs-wrap input:checked').each(function () {
            filterAttrs.push($(this).val());
        });

        // Собираем canvas_images: slug → { markup_type: url }
        var canvasImages = {};
        $('.ssc-canvas-url').each(function () {
            var slug = $(this).data('slug');
            var markup = $(this).data('markup');
            var val = $(this).val().trim();
            if (!canvasImages[slug]) canvasImages[slug] = {};
            if (val) canvasImages[slug][markup] = val;
        });

        var data = {
            action: 'ssc_save_calculator',
            nonce: cfg.nonce,
            id: $('#ssc-calc-id').val(),
            calculator_type: $('#ssc-calc-type').val(),
            name: $('#ssc-name').val(),
            category_slug: $('#ssc-category').val(),
            subcategory_slugs: $('#ssc-subcategory-slugs').val(),
            canvas_images_json: JSON.stringify(canvasImages),
            width_attr: $('#ssc-width-attr').val(),
            length_attr: $('#ssc-length-attr').val(),
            filter_attrs: filterAttrs,
            canvas_image: $('#ssc-canvas-image').val(),
            glue_price: $('#ssc-glue-price').val(),
            glue_volume: $('#ssc-glue-volume').val(),
            tape_price: $('#ssc-tape-price').val(),
            tape_volume: $('#ssc-tape-volume').val(),
            scenic_base_tape_price: $('#ssc-scenic-base-tape-price').val(),
            scenic_base_tape_volume: $('#ssc-scenic-base-tape-volume').val(),
            scenic_seam_cord_price: $('#ssc-scenic-seam-cord-price').val(),
            scenic_seam_cord_volume: $('#ssc-scenic-seam-cord-volume').val(),
            scenic_seam_tape_price: $('#ssc-scenic-seam-tape-price').val(),
            scenic_seam_tape_volume: $('#ssc-scenic-seam-tape-volume').val(),
            scenic_seam_weld_price: $('#ssc-scenic-seam-weld-price').val(),
            scenic_seam_weld_volume: $('#ssc-scenic-seam-weld-volume').val(),
            simple_rolls_enabled: $('#ssc-simple-rolls-enabled').is(':checked') ? 1 : '',
            simple_glue_enabled: $('#ssc-simple-glue-enabled').is(':checked') ? 1 : '',
            simple_glue_rate: $('#ssc-simple-glue-rate').val(),
            sand_enabled: $('#ssc-sand-enabled').is(':checked') ? 1 : '',
            sand_price: $('#ssc-sand-price').val(),
            rubber_enabled: $('#ssc-rubber-enabled').is(':checked') ? 1 : '',
            rubber_price: $('#ssc-rubber-price').val(),
            markup_enabled: $('#ssc-markup-enabled').is(':checked') ? 1 : '',
            paint_price: $('#ssc-paint-price').val(),
            company_name: $('#ssc-company-name').val(),
            admin_email: $('#ssc-admin-email').val()
        };

        if (!data.name || !data.category_slug) {
            $status.addClass('error').text('Заполните название и категорию');
            return;
        }

        if (!data.subcategory_slugs.trim()) {
            $status.addClass('error').text('Укажите хотя бы один slug подкатегории');
            return;
        }

        $btn.prop('disabled', true);
        $status.removeClass('error').text(cfg.strings.saving);

        console.log('[SSC Admin] Saving canvas_images:', canvasImages);

        $.post(cfg.ajaxUrl, data, function (res) {
            $btn.prop('disabled', false);
            console.log('[SSC Admin] Save response:', res);
            if (!res.success) {
                $status.addClass('error').text(cfg.strings.error);
                return;
            }
            $status.text(cfg.strings.saved);

            // Обновить строку в таблице или добавить новую
            var $existing = $('#ssc-list-body tr[data-id="' + res.data.id + '"]');
            var $newRow = $(res.data.row);
            if ($existing.length) {
                $existing.replaceWith($newRow);
            } else {
                $('#ssc-empty-row').remove();
                $('#ssc-list-body').append($newRow);
            }

            setTimeout(closePanel, 800);
        }).fail(function () {
            $btn.prop('disabled', false);
            $status.addClass('error').text(cfg.strings.error);
        });
    });

    // --------------------------------------------------------------------------
    // Выбор категории → загрузить атрибуты
    // --------------------------------------------------------------------------
    $('#ssc-category').on('change', function () {
        var slug = $(this).val();
        if (!slug) {
            $('#ssc-filter-attrs-wrap').html('<p class="description">Сначала выберите категорию</p>');
            return;
        }
        loadCategoryAttrs(slug);
    });

    function loadCategoryAttrs(slug, callback) {
        var $attrWrap = $('#ssc-filter-attrs-wrap');
        var $widthSel = $('#ssc-width-attr');
        var $lengthSel = $('#ssc-length-attr');

        $attrWrap.html('<p class="description">Загрузка...</p>');
        $widthSel.html('<option value="">Загрузка...</option>');
        $lengthSel.html('<option value="">Загрузка...</option>');

        $.get(cfg.ajaxUrl, {
            action: 'ssc_load_category_attrs',
            category_slug: slug,
            nonce: cfg.nonce
        }, function (res) {
            if (!res.success || !res.data.length) {
                var errMsg = 'Атрибуты не найдены';
                if (res.data) errMsg += ': ' + res.data;
                $attrWrap.html('<p class="description">' + errMsg + '</p>');
                $widthSel.html('<option value="">Нет атрибутов</option>');
                $lengthSel.html('<option value="">Нет атрибутов</option>');
                return;
            }
            currentAttrs = res.data;

            // Заполнить select для ширины и длины
            var attrOptions = '<option value="">— выберите —</option>';
            res.data.forEach(function (a) {
                attrOptions += '<option value="' + a.slug + '">' + a.label + ' (' + a.slug + ')</option>';
            });
            $widthSel.html(attrOptions);
            $lengthSel.html(attrOptions);

            // Чекбоксы для фильтрации
            var checksHtml = '';
            res.data.forEach(function (a) {
                checksHtml += '<label class="ssc-attr-check">' +
                    '<input type="checkbox" value="' + a.slug + '"> ' + a.label + '</label>';
            });
            $attrWrap.html(checksHtml);

            if (typeof callback === 'function') callback();
        }).fail(function (xhr) {
            var errMsg = 'Ошибка загрузки';
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.data) errMsg += ': ' + resp.data;
            } catch(e) {}
            $attrWrap.html('<p class="description" style="color:#d63638">' + errMsg + '</p>');
            $widthSel.html('<option value="">Ошибка</option>');
            $lengthSel.html('<option value="">Ошибка</option>');
        });
    }

    // --------------------------------------------------------------------------
    // Копировать шорткод
    // --------------------------------------------------------------------------
    $(document).on('click', '.ssc-copy-shortcode', function () {
        var text = $(this).data('shortcode');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            var el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        $(this).text('✅');
        var $btn = $(this);
        setTimeout(function () { $btn.text('📋'); }, 1500);
    });

    // --------------------------------------------------------------------------
    // Очистить кеш
    // --------------------------------------------------------------------------
    $(document).on('click', '.ssc-clear-cache-btn', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Очистка...');
        $.post(cfg.ajaxUrl, {
            action: 'ssc_clear_cache',
            nonce: cfg.nonce
        }, function (res) {
            if (res.success) {
                $btn.text('✓ Очищено');
                setTimeout(function () {
                    $btn.prop('disabled', false).text('Сброс кеша');
                }, 1500);
            } else {
                $btn.prop('disabled', false).text('Ошибка');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Ошибка');
        });
    });

    // --------------------------------------------------------------------------
    // Синхронизировать конкретный калькулятор из строки таблицы
    // --------------------------------------------------------------------------
    $(document).on('click', '.ssc-sync-row-btn', function () {
        var $btn = $(this);
        var calcId = $btn.data('id');

        $btn.prop('disabled', true).text('Синхр...');

        $.post(cfg.ajaxUrl, {
            action: 'ssc_sync_calculator',
            nonce: cfg.nonce,
            calc_id: calcId
        }, function (res) {
            if (res.success) {
                $btn.text('✓ ' + res.data.synced + ' гор.');
                setTimeout(function () {
                    $btn.prop('disabled', false).text('Синхр.');
                }, 2000);
            } else {
                $btn.prop('disabled', false).text('Ошибка');
                alert(res.data && res.data.message ? res.data.message : 'Ошибка синхронизации');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Синхр.');
            alert('Ошибка соединения');
        });
    });

    // --------------------------------------------------------------------------
    // Синхронизировать все калькуляторы
    // --------------------------------------------------------------------------
    $('#ssc-sync-all-btn').on('click', function () {
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Синхронизация...');

        $.post(cfg.ajaxUrl, {
            action: 'ssc_sync_all_calculators',
            nonce: cfg.nonce
        }, function (res) {
            if (res.success) {
                $btn.text('✓ ' + res.data.message);
                setTimeout(function () {
                    $btn.prop('disabled', false).text(originalText);
                }, 3000);
            } else {
                $btn.prop('disabled', false).text(originalText);
                alert(res.data && res.data.message ? res.data.message : 'Ошибка синхронизации');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(originalText);
            alert('Ошибка соединения');
        });
    });

    // --------------------------------------------------------------------------
    // Синхронизировать со всеми городами (из формы редактирования)
    // --------------------------------------------------------------------------
    $('#ssc-sync-btn').on('click', function () {
        var calcId = $('#ssc-calc-id').val();
        if (!calcId) {
            alert('Сначала сохраните калькулятор');
            return;
        }

        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Синхронизация...');

        $.post(cfg.ajaxUrl, {
            action: 'ssc_sync_calculator',
            nonce: cfg.nonce,
            calc_id: calcId
        }, function (res) {
            if (res.success) {
                $btn.text('✓ ' + res.data.message);
                setTimeout(function () {
                    $btn.prop('disabled', false).text(originalText);
                }, 2000);
            } else {
                $btn.prop('disabled', false).text('Ошибка');
                alert(res.data.message || 'Ошибка синхронизации');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(originalText);
            alert('Ошибка соединения');
        });
    });

}(jQuery));
