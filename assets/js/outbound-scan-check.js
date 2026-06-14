(function ($) {
    'use strict';

    var cfg = window.TGS_OSC || {};

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function num(v) {
        var n = parseFloat(String(v == null ? '' : v).replace(',', '.'));
        return isNaN(n) ? 0 : n;
    }

    function fmt(n) {
        n = num(n);
        return n.toLocaleString('vi-VN', { maximumFractionDigits: 3 });
    }

    function post(action, data) {
        data = data || {};
        data.action = action;
        data.nonce = cfg.nonce;
        return $.post(cfg.ajaxUrl, data);
    }

    function statusLabel(status) {
        return {
            draft: 'Nháp',
            checking: 'Đang kiểm',
            done: 'Đã xong',
            unchecked: 'Chưa kiểm',
            missing: 'Chưa nhặt',
            short: 'Thiếu',
            enough: 'Đủ',
            over: 'Thừa',
            extra: 'Ngoài phiếu',
            mixed: 'Hỗn hợp'
        }[status] || status || '--';
    }

    function badge(status) {
        return '<span class="tgs-osc-status is-' + esc(status || 'unchecked') + '">' + esc(statusLabel(status)) + '</span>';
    }

    function initList() {
        var $root = $('[data-osc-list]');
        if (!$root.length) return;

        var createModal = null;
        var parentPreview = null;
        if (window.bootstrap && document.getElementById('oscCreateModal')) {
            createModal = new bootstrap.Modal(document.getElementById('oscCreateModal'));
        }

        function loadList() {
            var $body = $root.find('[data-osc-list-body]');
            $body.html('<tr><td colspan="8" class="text-center text-muted py-4">Đang tải...</td></tr>');
            post('tgs_osc_list_checks', {
                search: $root.find('[data-osc-list-search]').val() || '',
                check_status: $root.find('[data-osc-filter-status]').val() || '',
                result_status: $root.find('[data-osc-filter-result]').val() || '',
                parent_id: cfg.listParentId || 0,
                limit: 100
            }).done(function (res) {
                if (!res || !res.success) {
                    $body.html('<tr><td colspan="8" class="text-danger text-center py-4">' + esc(res && res.data && res.data.message || 'Lỗi tải dữ liệu') + '</td></tr>');
                    return;
                }

                var items = res.data.items || [];
                if (!items.length) {
                    $body.html('<tr><td colspan="8" class="text-center text-muted py-4">Chưa có phiếu kiểm hàng</td></tr>');
                    return;
                }

                $body.html(items.map(function (r) {
                    return '<tr data-check-id="' + r.outbound_scan_check_id + '">'
                        + '<td><a class="fw-semibold" target="_blank" href="' + esc(r.scan_url) + '">' + esc(r.outbound_scan_check_code) + '</a></td>'
                        + '<td>' + esc(r.parent_ledger_code || ('#' + r.local_ledger_parent_id)) + '</td>'
                        + '<td>' + badge(r.check_status) + '</td>'
                        + '<td>' + badge(r.result_status) + '</td>'
                        + '<td>' + esc(r.total_lines) + '</td>'
                        + '<td>' + fmt(r.total_checked_qty) + ' / ' + fmt(r.total_expected_qty) + '</td>'
                        + '<td>' + esc(r.updated_at || '') + '</td>'
                        + '<td class="text-end">'
                        + '<a class="btn btn-sm btn-outline-primary me-1" target="_blank" href="' + esc(r.scan_url) + '"><i class="bx bx-barcode-reader"></i></a>'
                        + '<button type="button" class="btn btn-sm btn-outline-danger" data-osc-delete="' + r.outbound_scan_check_id + '"><i class="bx bx-trash"></i></button>'
                        + '</td>'
                        + '</tr>';
                }).join(''));
            });
        }

        var debounceTimer = null;
        function debounceLoad() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadList, 250);
        }

        $root.on('input', '[data-osc-list-search]', debounceLoad);
        $root.on('change', '[data-osc-filter-status],[data-osc-filter-result]', loadList);
        $root.on('click', '[data-osc-refresh]', loadList);
        $root.on('click', '[data-osc-open-create]', function () {
            parentPreview = null;
            $('[data-osc-parent-code]').val('');
            $('[data-osc-create-note]').val('');
            $('[data-osc-parent-preview]').html('');
            if (createModal) createModal.show();
        });

        $('[data-osc-lookup-parent]').on('click', function () {
            var code = $('[data-osc-parent-code]').val();
            var $preview = $('[data-osc-parent-preview]');
            $preview.html('<span class="text-muted">Đang tìm...</span>');
            post('tgs_osc_lookup_parent', { identifier: code }).done(function (res) {
                if (!res || !res.success) {
                    parentPreview = null;
                    $preview.html('<span class="text-danger">' + esc(res && res.data && res.data.message || 'Không tìm thấy') + '</span>');
                    return;
                }

                parentPreview = res.data.parent;
                $preview.html(
                    '<div class="alert alert-info py-2 mb-0">'
                    + '<strong>' + esc(parentPreview.local_ledger_code) + '</strong>'
                    + ' - ' + esc(res.data.item_count) + ' dòng hàng'
                    + ' - đã có ' + esc(res.data.existing_checks) + ' phiếu kiểm'
                    + '</div>'
                );
            });
        });

        $('[data-osc-create-check]').on('click', function () {
            var code = $('[data-osc-parent-code]').val();
            post('tgs_osc_create_check', {
                parent: code,
                note: $('[data-osc-create-note]').val() || ''
            }).done(function (res) {
                if (!res || !res.success) {
                    alert(res && res.data && res.data.message || 'Không tạo được phiếu kiểm.');
                    return;
                }

                var id = res.data.check.outbound_scan_check_id;
                window.open(cfg.adminUrl + '&view=outbound-scan-check&check_id=' + id, '_blank');
                if (createModal) createModal.hide();
                loadList();
            });
        });

        $root.on('click', '[data-osc-delete]', function () {
            var id = parseInt($(this).data('osc-delete'), 10) || 0;
            if (!id || !confirm('Xóa phiếu kiểm hàng này?')) return;
            post('tgs_osc_delete_check', { check_id: id }).done(loadList);
        });

        loadList();
    }

    function initScan() {
        var $root = $('[data-osc-scan]');
        if (!$root.length) return;

        var state = {
            check: null,
            parent: null,
            items: [],
            selectedProduct: null
        };
        var qtyModal = null;
        if (window.bootstrap && document.getElementById('oscQtyModal')) {
            qtyModal = new bootstrap.Modal(document.getElementById('oscQtyModal'));
        }

        function log(text) {
            var time = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            var $log = $('[data-osc-log]');
            $log.prepend('<div class="tgs-osc-log-line"><strong>' + esc(time) + '</strong> - ' + esc(text) + '</div>');
        }

        function flash(type, title, detail) {
            var $box = $('[data-osc-feedback]');
            if (!$box.length) return;
            type = type || 'info';
            $box
                .removeClass('is-success is-warning is-danger is-info')
                .addClass('is-visible is-' + type)
                .html(
                    '<button type="button" class="tgs-osc-feedback-close" data-osc-feedback-close aria-label="Đóng kết quả">&times;</button>'
                    + '<strong>' + esc(title || '') + '</strong>'
                    + (detail ? '<div>' + detail + '</div>' : '')
                );
        }

        $('[data-osc-feedback]').on('click', '[data-osc-feedback-close]', function () {
            $('[data-osc-feedback]').removeClass('is-visible is-success is-warning is-danger is-info').html('');
        });

        function summaryText(summary) {
            summary = summary || analyzeLocal();
            return 'Tổng ' + fmt(summary.total_lines || 0)
                + ' dòng, thiếu/chưa nhặt ' + fmt((summary.missing || 0) + (summary.short || 0))
                + ', đủ ' + fmt(summary.enough || 0)
                + ', thừa ' + fmt(summary.over || 0)
                + ', ngoài phiếu ' + fmt(summary.extra || 0) + '.';
        }

        function buildAnalysisDetail(summary) {
            summary = summary || analyzeLocal();
            var lines = state.items
                .map(function (item, idx) {
                    var result = classify(item);
                    if (result === 'enough') return '';
                    var expected = num(item.expected_quantity);
                    var checked = num(item.checked_quantity);
                    return '<li><strong>' + esc(item.local_product_sku || ('Dòng ' + (idx + 1))) + '</strong>'
                        + ' - ' + esc(item.product_name_cache || '')
                        + ': ' + esc(statusLabel(result))
                        + ' (cần ' + fmt(expected) + ', đã kiểm ' + fmt(checked) + ')</li>';
                })
                .filter(Boolean);

            if (!lines.length) {
                return '<div>' + esc(summaryText(summary)) + '</div><div>Tất cả dòng hàng đã khớp.</div>';
            }

            return '<div>' + esc(summaryText(summary)) + '</div>'
                + '<ul class="tgs-osc-analysis-list">' + lines.slice(0, 12).join('') + '</ul>'
                + (lines.length > 12 ? '<div>Còn ' + esc(lines.length - 12) + ' dòng cần xem thêm trong bảng bên dưới.</div>' : '');
        }

        function collectFromInputs() {
            $root.find('[data-item-index]').each(function () {
                var idx = parseInt($(this).data('item-index'), 10);
                if (!state.items[idx]) return;
                state.items[idx].checked_quantity = num($(this).find('[data-check-qty]').val());
                state.items[idx].check_note = $(this).find('[data-check-note]').val() || '';
                state.items[idx].check_result = classify(state.items[idx]);
            });
        }

        function classify(item) {
            var expected = num(item.expected_quantity);
            var checked = num(item.checked_quantity);
            if (item.is_extra || expected <= 0) return checked > 0 ? 'extra' : 'unchecked';
            if (checked <= 0) return 'missing';
            if (checked < expected) return 'short';
            if (checked > expected) return 'over';
            return 'enough';
        }

        function analyzeLocal() {
            var s = {
                total_lines: state.items.length,
                expected_qty: 0,
                checked_qty: 0,
                missing: 0,
                short: 0,
                enough: 0,
                over: 0,
                extra: 0
            };
            state.items.forEach(function (item) {
                var result = classify(item);
                item.check_result = result;
                s.expected_qty += num(item.expected_quantity);
                s.checked_qty += num(item.checked_quantity);
                if (s[result] !== undefined) s[result]++;
            });
            return s;
        }

        function renderSummary(summary) {
            summary = summary || analyzeLocal();
            $('[data-osc-stat-total]').text(summary.total_lines || 0);
            $('[data-osc-stat-short]').text((summary.missing || 0) + (summary.short || 0));
            $('[data-osc-stat-enough]').text(summary.enough || 0);
            $('[data-osc-stat-over]').text(summary.over || 0);
            $('[data-osc-stat-extra]').text(summary.extra || 0);
        }

        function renderItems() {
            var $body = $('[data-osc-items-body]');
            if (!state.items.length) {
                $body.html('<tr><td colspan="8" class="text-center text-muted py-4">Phiếu chưa có dòng hàng</td></tr>');
                renderSummary();
                return;
            }

            $body.html(state.items.map(function (item, idx) {
                item.check_result = classify(item);
                return '<tr class="tgs-osc-item-row is-' + esc(item.check_result) + '" data-item-index="' + idx + '">'
                    + '<td>' + (idx + 1) + '</td>'
                    + '<td><strong>' + esc(item.local_product_sku) + '</strong>' + (item.is_extra ? '<div class="text-danger small">Đang nhặt dư hàng</div>' : '') + '</td>'
                    + '<td>' + esc(item.product_name_cache || '') + '</td>'
                    + '<td class="text-end">' + fmt(item.expected_quantity) + '</td>'
                    + '<td><input type="number" step="0.001" min="0" class="form-control form-control-sm tgs-osc-check-qty" data-check-qty value="' + esc(num(item.checked_quantity)) + '"></td>'
                    + '<td data-status-cell>' + badge(item.check_result) + '</td>'
                    + '<td class="small text-muted">' + esc(item.source_item_note || '') + '</td>'
                    + '<td><input type="text" class="form-control form-control-sm tgs-osc-note-input" data-check-note value="' + esc(item.check_note || '') + '"></td>'
                    + '</tr>';
            }).join(''));
            renderSummary();
        }

        function renderHeader(payload) {
            state.check = payload.check;
            state.parent = payload.parent;
            state.items = payload.items || [];
            $('[data-osc-check-title]').text((state.check.outbound_scan_check_code || 'Kiểm hàng') + ' - ' + statusLabel(state.check.result_status));
            $('[data-osc-parent-code]').text(state.parent ? state.parent.local_ledger_code : '--');
            var parentNote = state.parent ? (state.parent.local_ledger_note || state.parent.local_ledger_title || '') : '';
            if (state.parent && state.parent.partner_shop_name) {
                parentNote = 'Shop liên quan: ' + state.parent.partner_shop_name + (parentNote ? ' - ' + parentNote : '');
            }
            $('[data-osc-parent-note]').text(parentNote);
            $('[data-osc-check-note]').val(state.check.outbound_scan_check_note || '');
            renderItems();
            renderSummary(payload.summary);
        }

        function loadCheck() {
            post('tgs_osc_get_check', {
                check_id: cfg.checkId || 0,
                parent_id: cfg.parentId || '',
                force_new: cfg.forceNew || 0
            }).done(function (res) {
                if (!res || !res.success) {
                    var errorMessage = res && res.data && res.data.message || 'Không tải được phiếu kiểm';
                    $('[data-osc-items-body]').html('<tr><td colspan="8" class="text-danger text-center py-4">' + esc(errorMessage) + '</td></tr>');
                    flash('danger', 'Không tải được phiếu kiểm', esc(errorMessage));
                    return;
                }
                cfg.checkId = res.data.check.outbound_scan_check_id;
                renderHeader(res.data);
                flash('info', 'Đã tải phiếu kiểm', esc(res.data.check.outbound_scan_check_code));
                log('Đã tải phiếu kiểm ' + res.data.check.outbound_scan_check_code);
            });
        }

        var searchTimer = null;
        var searchSeq = 0;

        function resetProductSearch(focusInput) {
            clearTimeout(searchTimer);
            searchSeq += 1;
            $('[data-osc-product-results]').hide().html('').removeData('products');
            $('[data-osc-product-search]').val('');
            if (focusInput) {
                $('[data-osc-product-search]').focus();
            }
        }

        $('[data-osc-product-search]').on('input', function () {
            var term = $(this).val();
            var currentSeq = ++searchSeq;
            clearTimeout(searchTimer);
            if (!term || term.trim().length < 2) {
                $('[data-osc-product-results]').hide().html('');
                return;
            }
            searchTimer = setTimeout(function () {
                post('tgs_osc_search_products', { term: term, limit: 12 }).done(function (res) {
                    var $box = $('[data-osc-product-results]');
                    if (currentSeq !== searchSeq || term !== $('[data-osc-product-search]').val()) {
                        return;
                    }
                    if (!res || !res.success || !res.data.items.length) {
                        $box.html('<div class="p-3 text-muted">Không tìm thấy sản phẩm</div>').show();
                        return;
                    }
                    $box.html(res.data.items.map(function (p, idx) {
                        return '<div class="tgs-osc-product-result" data-product-index="' + idx + '">'
                            + '<div><strong>' + esc(p.global_product_sku) + '</strong><div>' + esc(p.global_product_name) + '</div><small>Barcode: ' + esc(p.global_product_barcode_main || '--') + '</small></div>'
                            + '<div class="tgs-osc-product-stock">Tồn: ' + fmt(p.actual_stock) + '<small class="d-block">Dự kiến: ' + fmt(p.projected_stock) + '</small></div>'
                            + '</div>';
                    }).join('')).show();
                    $box.data('products', res.data.items);
                });
            }, 220);
        });

        $('[data-osc-product-results]').on('click', '[data-product-index]', function () {
            var products = $('[data-osc-product-results]').data('products') || [];
            var product = products[parseInt($(this).data('product-index'), 10)];
            if (!product) return;
            openQtyModal(product);
        });

        function openQtyModal(product) {
            state.selectedProduct = product;
            $('[data-osc-modal-name]').text(product.global_product_name || '');
            $('[data-osc-modal-sku]').text('SKU: ' + (product.global_product_sku || '--'));
            $('[data-osc-modal-qty]').val(1);
            var $unit = $('[data-osc-modal-unit]');
            $unit.html((product.units || [{ unit_name: product.global_product_unit || 'DVT', ratio: 1 }]).map(function (u, idx) {
                return '<option value="' + idx + '" data-ratio="' + esc(u.ratio || 1) + '">' + esc(u.unit_name || 'DVT') + ' - x' + fmt(u.ratio || 1) + '</option>';
            }).join(''));
            updateSmallQty();

            var hasMatch = state.items.some(function (item) {
                return String(item.local_product_sku || '') === String(product.global_product_sku || '') && !item.is_extra;
            });
            $('[data-osc-modal-warning]').text(hasMatch ? '' : 'SKU này không có trong phiếu cha. Hệ thống sẽ thêm dòng ngoài phiếu.');
            resetProductSearch(false);
            if (qtyModal) qtyModal.show();
        }

        function updateSmallQty() {
            var qty = num($('[data-osc-modal-qty]').val());
            var ratio = num($('[data-osc-modal-unit]').find('option:selected').data('ratio') || 1);
            $('[data-osc-modal-small-qty]').val(fmt(qty * ratio));
        }

        $('[data-osc-modal-qty], [data-osc-modal-unit]').on('input change', updateSmallQty);

        $('[data-osc-confirm-product]').on('click', function () {
            var product = state.selectedProduct;
            if (!product) return;
            var qty = num($('[data-osc-modal-qty]').val());
            var ratio = num($('[data-osc-modal-unit]').find('option:selected').data('ratio') || 1);
            var smallestQty = qty * ratio;
            if (smallestQty <= 0) return;

            collectFromInputs();
            allocateProduct(product, smallestQty);
            renderItems();
            flash('success', 'Đã thêm vào phiếu kiểm', esc(product.global_product_sku) + ' +' + esc(fmt(smallestQty)));
            log('Quét ' + product.global_product_sku + ' +' + fmt(smallestQty));
            resetProductSearch(true);
            if (qtyModal) qtyModal.hide();
        });

        function allocateProduct(product, qty) {
            var sku = String(product.global_product_sku || '');
            var matches = state.items.filter(function (item) {
                return String(item.local_product_sku || '') === sku && !item.is_extra;
            });
            var remaining = qty;

            matches.forEach(function (item) {
                if (remaining <= 0) return;
                var need = Math.max(0, num(item.expected_quantity) - num(item.checked_quantity));
                if (need <= 0) return;
                var add = Math.min(need, remaining);
                item.checked_quantity = num(item.checked_quantity) + add;
                remaining -= add;
            });

            if (remaining > 0 && matches.length) {
                matches[matches.length - 1].checked_quantity = num(matches[matches.length - 1].checked_quantity) + remaining;
                remaining = 0;
            }

            if (remaining > 0) {
                state.items.push({
                    outbound_scan_check_item_id: 0,
                    outbound_scan_check_id: cfg.checkId || 0,
                    local_ledger_parent_id: state.parent ? state.parent.local_ledger_id : 0,
                    local_ledger_item_id: 0,
                    local_product_sku: sku,
                    global_product_name_id: product.global_product_name_id || 0,
                    product_name_cache: product.global_product_name || '',
                    expected_quantity: 0,
                    checked_quantity: remaining,
                    difference_quantity: remaining,
                    check_result: 'extra',
                    check_note: 'Đang nhặt dư hàng',
                    source_item_note: '',
                    is_extra: 1,
                    is_gift: 0,
                    meta: { source: 'scan_extra' }
                });
            }
        }

        $root.on('input change', '[data-check-qty],[data-check-note]', function () {
            collectFromInputs();
            var $row = $(this).closest('[data-item-index]');
            var idx = parseInt($row.data('item-index'), 10);
            var result = state.items[idx] ? classify(state.items[idx]) : 'unchecked';
            $row.removeClass('is-missing is-short is-enough is-over is-extra is-unchecked').addClass('is-' + result);
            $row.find('[data-status-cell]').html(badge(result));
            renderSummary();
        });

        $('[data-osc-analyze]').on('click', function () {
            collectFromInputs();
            flash('info', 'Đang phân tích phiếu kiểm...', 'Hệ thống đang rà lại số lượng cần kiểm, đã kiểm và các dòng ngoài phiếu.');
            post('tgs_osc_analyze_check', { items: JSON.stringify(state.items) }).done(function (res) {
                if (res && res.success) {
                    renderSummary(res.data.summary);
                    renderItems();
                    var summary = res.data.summary || analyzeLocal();
                    var type = ((summary.missing || 0) + (summary.short || 0) + (summary.over || 0) + (summary.extra || 0)) > 0 ? 'warning' : 'success';
                    flash(type, 'Kết quả phân tích', buildAnalysisDetail(summary));
                    log('Đã phân tích kết quả kiểm hàng');
                    return;
                }
                flash('danger', 'Không phân tích được', esc(res && res.data && res.data.message || 'Có lỗi khi phân tích phiếu kiểm.'));
            });
        });

        function saveCheck(status) {
            collectFromInputs();
            var isDone = status === 'done';
            flash('info', isDone ? 'Đang hoàn tất phiếu kiểm...' : 'Đang lưu nháp...', 'Vui lòng đợi trong giây lát.');
            post('tgs_osc_save_check', {
                check_id: cfg.checkId || 0,
                items: JSON.stringify(state.items),
                note: $('[data-osc-check-note]').val() || '',
                status: status || 'checking'
            }).done(function (res) {
                if (!res || !res.success) {
                    flash('danger', isDone ? 'Không hoàn tất được' : 'Không lưu được', esc(res && res.data && res.data.message || 'Không lưu được phiếu kiểm.'));
                    return;
                }
                renderHeader(res.data);
                flash('success', isDone ? 'Đã hoàn tất phiếu kiểm' : 'Đã lưu nháp', esc(summaryText(res.data.summary)));
                log(isDone ? 'Đã hoàn tất phiếu kiểm' : 'Đã lưu nháp');
            });
        }

        $('[data-osc-save]').on('click', function () {
            saveCheck('checking');
        });

        $('[data-osc-complete]').on('click', function () {
            saveCheck('done');
        });

        $('[data-osc-reset]').on('click', function () {
            if (!confirm('Reset số lượng đã kiểm về 0?')) return;
            flash('info', 'Đang reset số lượng...', 'Các dòng đã kiểm sẽ được đưa về 0.');
            post('tgs_osc_reset_check', { check_id: cfg.checkId || 0 }).done(function (res) {
                if (res && res.success) {
                    renderHeader(res.data);
                    flash('success', 'Đã reset số lượng kiểm', esc(summaryText(res.data.summary)));
                    log('Đã reset số lượng kiểm');
                    return;
                }
                flash('danger', 'Không reset được', esc(res && res.data && res.data.message || 'Có lỗi khi reset phiếu kiểm.'));
            });
        });

        $('[data-osc-clear-log]').on('click', function () {
            $('[data-osc-log]').html('');
        });

        loadCheck();
    }

    $(function () {
        initList();
        initScan();
    });
})(jQuery);
