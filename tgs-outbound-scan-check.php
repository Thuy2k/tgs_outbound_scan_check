<?php
/**
 * Plugin Name: TGS Outbound Scan Check
 * Plugin URI: https://bizgpt.vn/
 * Description: Kiểm hàng bằng máy quét cho các phiếu xuất/trả nội bộ trước khi duyệt.
 * Version: 1.0.0
 * Author: BIZGPT_AI
 * Author URI: https://bizgpt.vn/
 * License: GPL v2 or later
 * Text Domain: tgs-outbound-scan-check
 * Requires Plugins: tgs_shop_management
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TGS_OSC_VERSION', '1.0.0');
define('TGS_OSC_PLUGIN_FILE', __FILE__);
define('TGS_OSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TGS_OSC_PLUGIN_URL', plugin_dir_url(__FILE__));

class TGS_Outbound_Scan_Check
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        require_once TGS_OSC_PLUGIN_DIR . 'includes/class-tgs-outbound-scan-check-helper.php';
        require_once TGS_OSC_PLUGIN_DIR . 'functions/class-tgs-outbound-scan-check-ajax.php';
    }

    private function init_hooks()
    {
        add_filter('tgs_shop_dashboard_routes', [$this, 'add_dashboard_routes']);
        add_filter('tgs_shop_workflow_nav', [$this, 'add_workflow_nav'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('tgs_ticket_detail_actions', [$this, 'render_ticket_detail_actions'], 10, 2);

        TGS_Outbound_Scan_Check_Ajax::init();
    }

    public function add_dashboard_routes($routes)
    {
        $routes['outbound-scan-checks'] = [
            'Kiểm hàng máy quét',
            TGS_OSC_PLUGIN_DIR . 'admin-views/list.php',
        ];
        $routes['outbound-scan-check'] = [
            'Quét kiểm hàng',
            TGS_OSC_PLUGIN_DIR . 'admin-views/scan.php',
        ];

        return $routes;
    }

    public function add_workflow_nav($workflow_nav, $current_view)
    {
        if (empty($workflow_nav['warehouse']['sections']) || !is_array($workflow_nav['warehouse']['sections'])) {
            return $workflow_nav;
        }

        $item = [
            'view' => 'outbound-scan-checks',
            'label' => 'Kiểm hàng máy quét',
            'icon' => 'bx bx-barcode-reader',
            'active_views' => ['outbound-scan-checks', 'outbound-scan-check'],
        ];

        foreach ($workflow_nav['warehouse']['sections'] as &$section) {
            if (!empty($section['heading']) && stripos((string) $section['heading'], 'Th') !== false) {
                $section['items'][] = $item;
                return $workflow_nav;
            }
        }

        $workflow_nav['warehouse']['sections'][0]['items'][] = $item;

        return $workflow_nav;
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'tgs-shop-') === false) {
            return;
        }

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : '';
        $is_plugin_view = in_array($view, ['outbound-scan-checks', 'outbound-scan-check'], true);
        $is_supported_detail = in_array($view, [
            'ticket-transfer-export-detail',
            'ticket-internal-return-detail',
            'ticket-export-v2-detail',
        ], true);

        if (!$is_plugin_view && !$is_supported_detail) {
            return;
        }

        wp_enqueue_style(
            'tgs-outbound-scan-check',
            TGS_OSC_PLUGIN_URL . 'assets/css/outbound-scan-check.css',
            [],
            TGS_OSC_VERSION
        );

        wp_enqueue_script(
            'tgs-outbound-scan-check',
            TGS_OSC_PLUGIN_URL . 'assets/js/outbound-scan-check.js',
            ['jquery'],
            TGS_OSC_VERSION,
            true
        );

        wp_localize_script('tgs-outbound-scan-check', 'TGS_OSC', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tgs_osc_nonce'),
            'adminUrl' => admin_url('admin.php?page=tgs-shop-management'),
            'view' => $view,
            'checkId' => isset($_GET['check_id']) ? (int) $_GET['check_id'] : 0,
            'parentId' => isset($_GET['parent_id']) ? sanitize_text_field(wp_unslash($_GET['parent_id'])) : '',
            'forceNew' => !empty($_GET['new']) ? 1 : 0,
            'listParentId' => isset($_GET['parent_id']) ? (int) $_GET['parent_id'] : 0,
        ]);
    }

    private function status_label($status)
    {
        $labels = [
            'draft' => 'Nháp',
            'checking' => 'Đang kiểm',
            'done' => 'Đã xong',
            'unchecked' => 'Chưa kiểm',
            'missing' => 'Chưa nhặt',
            'short' => 'Thiếu',
            'enough' => 'Đủ',
            'over' => 'Thừa',
            'extra' => 'Ngoài phiếu',
            'mixed' => 'Hỗn hợp',
        ];

        return $labels[$status] ?? ($status ?: '--');
    }

    public function render_ticket_detail_actions($ticket_id, $ticket_config)
    {
        $type_key = isset($ticket_config['type_key']) ? (string) $ticket_config['type_key'] : '';
        $supported = ['transfer_export', 'internal_return', 'export_v2'];
        if (!in_array($type_key, $supported, true)) {
            return;
        }

        $ticket_id = (int) $ticket_id;
        if ($ticket_id <= 0) {
            return;
        }

        $create_url = admin_url('admin.php?page=tgs-shop-management&view=outbound-scan-check&parent_id=' . $ticket_id . '&new=1');
        $list_url = admin_url('admin.php?page=tgs-shop-management&view=outbound-scan-checks&parent_id=' . $ticket_id);
        $tables_ready = class_exists('TGS_Outbound_Scan_Check_Helper') && TGS_Outbound_Scan_Check_Helper::tables_ready();
        $checks = $tables_ready
            ? TGS_Outbound_Scan_Check_Helper::list_checks(['parent_id' => $ticket_id, 'limit' => 20])
            : [];
        $count = count($checks);

        echo '<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="' . esc_url($create_url) . '" title="Tạo phiếu kiểm hàng bằng máy quét">';
        echo '<i class="bx bx-barcode-reader"></i> Tạo phiếu kiểm hàng';
        echo '</a>';

        if ($count > 0) {
            echo '<div class="btn-group" role="group">';
            echo '<button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Xem các phiếu soát liên quan">';
            echo '<i class="bx bx-list-check"></i> Phiếu soát (' . esc_html((string) $count) . ')';
            echo '</button>';
            echo '<div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 360px;">';
            echo '<div class="px-3 py-2 fw-semibold border-bottom">Phiếu soát của phiếu này</div>';

            foreach ($checks as $check) {
                $check = TGS_Outbound_Scan_Check_Helper::format_check($check);
                $url = admin_url('admin.php?page=tgs-shop-management&view=outbound-scan-check&check_id=' . (int) $check['outbound_scan_check_id']);
                $updated = !empty($check['updated_at']) ? mysql2date('d/m/Y H:i', $check['updated_at']) : '--';
                $qty = number_format_i18n((float) $check['total_checked_qty'], 3) . ' / ' . number_format_i18n((float) $check['total_expected_qty'], 3);

                echo '<a class="dropdown-item py-2" target="_blank" rel="noopener" href="' . esc_url($url) . '">';
                echo '<div class="fw-semibold text-primary">' . esc_html($check['outbound_scan_check_code']) . '</div>';
                echo '<div class="small text-muted">' . esc_html($this->status_label($check['check_status'])) . ' - ' . esc_html($this->status_label($check['result_status'])) . ' - ' . esc_html($qty) . '</div>';
                echo '<div class="small text-muted">Cập nhật: ' . esc_html($updated) . '</div>';
                echo '</a>';
            }

            echo '<div class="border-top"></div>';
            echo '<a class="dropdown-item py-2" target="_blank" rel="noopener" href="' . esc_url($list_url) . '">';
            echo '<i class="bx bx-list-ul"></i> Xem toàn bộ phiếu soát';
            echo '</a>';
            echo '</div>';
            echo '</div>';
        }
    }
}

add_action('plugins_loaded', ['TGS_Outbound_Scan_Check', 'instance']);
