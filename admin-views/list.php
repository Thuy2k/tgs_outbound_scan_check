<?php
if (!defined('ABSPATH')) {
    exit;
}

$scan_url = admin_url('admin.php?page=tgs-shop-management&view=outbound-scan-check');
?>

<div class="tgs-osc-page tgs-osc-list" data-osc-list>
    <div class="tgs-osc-head">
        <div>
            <div class="tgs-osc-breadcrumb">Kho vận / Kiểm hàng máy quét</div>
            <h1>Kiểm hàng bằng máy quét</h1>
        </div>
        <button type="button" class="btn btn-primary" data-osc-open-create>
            <i class="bx bx-plus"></i> Tạo phiếu kiểm
        </button>
    </div>

    <div class="tgs-osc-toolbar">
        <div class="input-group tgs-osc-search">
            <span class="input-group-text"><i class="bx bx-search"></i></span>
            <input type="text" class="form-control" data-osc-list-search placeholder="Tìm mã phiếu kiểm, mã phiếu cha...">
        </div>
        <select class="form-select" data-osc-filter-status>
            <option value="">Tất cả trạng thái</option>
            <option value="draft">Nháp</option>
            <option value="checking">Đang kiểm</option>
            <option value="done">Đã xong</option>
        </select>
        <select class="form-select" data-osc-filter-result>
            <option value="">Tất cả kết quả</option>
            <option value="unchecked">Chưa kiểm</option>
            <option value="short">Thiếu</option>
            <option value="enough">Đủ</option>
            <option value="over">Dư/Thừa</option>
            <option value="mixed">Hỗn hợp</option>
        </select>
        <button type="button" class="btn btn-outline-primary" data-osc-refresh>
            <i class="bx bx-refresh"></i>
        </button>
    </div>

    <div class="tgs-osc-table-wrap">
        <table class="table tgs-osc-table">
            <thead>
                <tr>
                    <th>Mã kiểm</th>
                    <th>Phiếu cha</th>
                    <th>Trạng thái</th>
                    <th>Kết quả</th>
                    <th>Dòng</th>
                    <th>Đã kiểm</th>
                    <th>Cập nhật</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody data-osc-list-body>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">Đang tải...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="oscCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tạo phiếu kiểm hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="oscParentCodeInput">Mã phiếu xuất / trả nội bộ</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="oscParentCodeInput" data-osc-parent-code placeholder="VD: PX-..., MNB-...">
                    <button type="button" class="btn btn-outline-primary" data-osc-lookup-parent>
                        <i class="bx bx-search"></i>
                    </button>
                </div>
                <div class="tgs-osc-parent-preview" data-osc-parent-preview></div>
                <label class="form-label mt-3" for="oscCreateNote">Ghi chú</label>
                <textarea class="form-control" id="oscCreateNote" data-osc-create-note rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <a class="btn btn-outline-secondary" href="<?php echo esc_url($scan_url); ?>">
                    Mở trang quét
                </a>
                <button type="button" class="btn btn-primary" data-osc-create-check>
                    <i class="bx bx-barcode-reader"></i> Tạo và quét
                </button>
            </div>
        </div>
    </div>
</div>
