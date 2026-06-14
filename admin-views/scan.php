<?php
if (!defined('ABSPATH')) {
    exit;
}

$list_url = admin_url('admin.php?page=tgs-shop-management&view=outbound-scan-checks');
?>

<div class="tgs-osc-page tgs-osc-scan" data-osc-scan>
    <div class="tgs-osc-head">
        <div>
            <div class="tgs-osc-breadcrumb">Kho vận / Kiểm hàng máy quét</div>
            <h1 data-osc-check-title>Kiểm hàng</h1>
        </div>
        <div class="tgs-osc-actions">
            <a class="btn btn-outline-secondary" href="<?php echo esc_url($list_url); ?>">
                <i class="bx bx-arrow-back"></i> Danh sách
            </a>
            <button type="button" class="btn btn-outline-primary" data-osc-analyze>
                <i class="bx bx-pie-chart-alt-2"></i> Phân tích
            </button>
            <button type="button" class="btn btn-outline-danger" data-osc-reset>
                <i class="bx bx-reset"></i> Reset
            </button>
            <button type="button" class="btn btn-success" data-osc-save>
                <i class="bx bx-save"></i> Lưu nháp
            </button>
            <button type="button" class="btn btn-primary" data-osc-complete>
                <i class="bx bx-check-circle"></i> Hoàn tất
            </button>
        </div>
    </div>

    <div class="tgs-osc-feedback" data-osc-feedback></div>

    <div class="tgs-osc-summary-row">
        <div class="tgs-osc-parent-box">
            <div class="tgs-osc-mini-label">Phiếu cha</div>
            <div class="tgs-osc-parent-code" data-osc-parent-code>--</div>
            <div class="tgs-osc-parent-note" data-osc-parent-note>Đang tải dữ liệu...</div>
        </div>
        <div class="tgs-osc-stat"><span data-osc-stat-total>0</span><small>Tổng dòng</small></div>
        <div class="tgs-osc-stat is-short"><span data-osc-stat-short>0</span><small>Thiếu</small></div>
        <div class="tgs-osc-stat is-enough"><span data-osc-stat-enough>0</span><small>Đủ</small></div>
        <div class="tgs-osc-stat is-over"><span data-osc-stat-over>0</span><small>Thừa</small></div>
        <div class="tgs-osc-stat is-extra"><span data-osc-stat-extra>0</span><small>Ngoài phiếu</small></div>
    </div>

    <div class="tgs-osc-scan-panel">
        <label class="form-label" for="oscProductSearch">Quét hoặc tìm sản phẩm</label>
        <div class="input-group tgs-osc-product-search">
            <span class="input-group-text"><i class="bx bx-barcode-reader"></i></span>
            <input type="text" class="form-control" id="oscProductSearch" data-osc-product-search
                   placeholder="Quét barcode, nhập SKU hoặc tên sản phẩm..." autocomplete="off">
        </div>
        <div class="tgs-osc-product-results" data-osc-product-results></div>
    </div>

    <div class="tgs-osc-table-wrap">
        <table class="table tgs-osc-table tgs-osc-item-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>SKU</th>
                    <th>Tên sản phẩm</th>
                    <th class="text-end">Cần kiểm</th>
                    <th class="text-end">Đã kiểm</th>
                    <th>Trạng thái</th>
                    <th>Ghi chú phiếu</th>
                    <th>Ghi chú kiểm</th>
                </tr>
            </thead>
            <tbody data-osc-items-body>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Đang tải...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="tgs-osc-note-log">
        <div>
            <label class="form-label" for="oscCheckNote">Ghi chú phiếu kiểm</label>
            <textarea class="form-control" id="oscCheckNote" data-osc-check-note rows="4"></textarea>
        </div>
        <div>
            <div class="tgs-osc-log-head">
                <span>Nhật ký thao tác</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-osc-clear-log>
                    <i class="bx bx-trash"></i>
                </button>
            </div>
            <div class="tgs-osc-log" data-osc-log></div>
        </div>
    </div>
</div>

<div class="modal fade" id="oscQtyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" data-osc-modal-name>Sản phẩm</h5>
                    <div class="text-muted small" data-osc-modal-sku>SKU: --</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="oscQtyInput">Số lượng</label>
                        <input type="number" min="0" step="0.001" class="form-control" id="oscQtyInput" data-osc-modal-qty value="1">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="oscUnitSelect">Đơn vị</label>
                        <select class="form-select" id="oscUnitSelect" data-osc-modal-unit></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="oscSmallQty">Số lượng nhỏ nhất</label>
                        <input type="text" class="form-control" id="oscSmallQty" data-osc-modal-small-qty disabled>
                    </div>
                </div>
                <div class="tgs-osc-warning" data-osc-modal-warning></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" data-osc-confirm-product>
                    <i class="bx bx-check"></i> Thêm vào phiếu kiểm
                </button>
            </div>
        </div>
    </div>
</div>
