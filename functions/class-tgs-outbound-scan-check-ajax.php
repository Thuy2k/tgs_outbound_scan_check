<?php
/**
 * AJAX endpoints for outbound scan checks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TGS_Outbound_Scan_Check_Ajax
{
    public static function init()
    {
        add_action('wp_ajax_tgs_osc_list_checks', [__CLASS__, 'list_checks']);
        add_action('wp_ajax_tgs_osc_lookup_parent', [__CLASS__, 'lookup_parent']);
        add_action('wp_ajax_tgs_osc_get_check', [__CLASS__, 'get_check']);
        add_action('wp_ajax_tgs_osc_create_check', [__CLASS__, 'create_check']);
        add_action('wp_ajax_tgs_osc_save_check', [__CLASS__, 'save_check']);
        add_action('wp_ajax_tgs_osc_analyze_check', [__CLASS__, 'analyze_check']);
        add_action('wp_ajax_tgs_osc_search_products', [__CLASS__, 'search_products']);
        add_action('wp_ajax_tgs_osc_reset_check', [__CLASS__, 'reset_check']);
        add_action('wp_ajax_tgs_osc_delete_check', [__CLASS__, 'delete_check']);
    }

    private static function guard()
    {
        if (!TGS_Outbound_Scan_Check_Helper::user_can_manage()) {
            wp_send_json_error(['message' => 'Bạn không có quyền thao tác.'], 403);
        }

        check_ajax_referer('tgs_osc_nonce', 'nonce');
    }

    private static function send_result($result)
    {
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    public static function list_checks()
    {
        self::guard();

        $items = TGS_Outbound_Scan_Check_Helper::list_checks([
            'search' => sanitize_text_field(wp_unslash($_POST['search'] ?? '')),
            'check_status' => sanitize_key(wp_unslash($_POST['check_status'] ?? '')),
            'result_status' => sanitize_key(wp_unslash($_POST['result_status'] ?? '')),
            'parent_id' => (int) ($_POST['parent_id'] ?? 0),
            'limit' => (int) ($_POST['limit'] ?? 50),
            'offset' => (int) ($_POST['offset'] ?? 0),
        ]);

        $items = array_map(static function ($row) {
            $formatted = TGS_Outbound_Scan_Check_Helper::format_check($row);
            $formatted['scan_url'] = admin_url('admin.php?page=tgs-shop-management&view=outbound-scan-check&check_id=' . (int) $formatted['outbound_scan_check_id']);
            return $formatted;
        }, $items);

        wp_send_json_success(['items' => $items]);
    }

    public static function lookup_parent()
    {
        self::guard();

        $identifier = sanitize_text_field(wp_unslash($_POST['identifier'] ?? ''));
        $parent = TGS_Outbound_Scan_Check_Helper::get_parent_ledger($identifier);
        if (!$parent) {
            wp_send_json_error(['message' => 'Không tìm thấy phiếu cha.']);
        }

        $item_ids = TGS_Outbound_Scan_Check_Helper::decode_item_ids($parent['local_ledger_item_id'] ?? null);
        wp_send_json_success([
            'parent' => TGS_Outbound_Scan_Check_Helper::format_parent($parent),
            'item_count' => count($item_ids),
            'existing_checks' => TGS_Outbound_Scan_Check_Helper::count_checks_for_parent((int) $parent['local_ledger_id']),
        ]);
    }

    public static function get_check()
    {
        self::guard();

        $check_id = (int) ($_POST['check_id'] ?? 0);
        $parent_id = sanitize_text_field(wp_unslash($_POST['parent_id'] ?? ''));
        $force_new = !empty($_POST['force_new']);
        $result = TGS_Outbound_Scan_Check_Helper::get_or_create_check($check_id, $parent_id, $force_new);

        self::send_result($result);
    }

    public static function create_check()
    {
        self::guard();

        $parent = sanitize_text_field(wp_unslash($_POST['parent'] ?? ''));
        $note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
        $result = TGS_Outbound_Scan_Check_Helper::create_check($parent, $note);

        self::send_result($result);
    }

    public static function save_check()
    {
        self::guard();

        $check_id = (int) ($_POST['check_id'] ?? 0);
        $items_json = wp_unslash($_POST['items'] ?? '[]');
        $items = json_decode($items_json, true);
        if (!is_array($items)) {
            wp_send_json_error(['message' => 'Danh sách dòng kiểm hàng không hợp lệ.']);
        }

        $note = sanitize_textarea_field(wp_unslash($_POST['note'] ?? ''));
        $status = sanitize_key(wp_unslash($_POST['status'] ?? TGS_Outbound_Scan_Check_Helper::STATUS_CHECKING));
        $result = TGS_Outbound_Scan_Check_Helper::save_items($check_id, $items, $note, $status);

        self::send_result($result);
    }

    public static function analyze_check()
    {
        self::guard();

        $items_json = wp_unslash($_POST['items'] ?? '[]');
        $items = json_decode($items_json, true);
        if (!is_array($items)) {
            $items = [];
        }

        wp_send_json_success([
            'summary' => TGS_Outbound_Scan_Check_Helper::analyze_items($items),
        ]);
    }

    public static function search_products()
    {
        self::guard();

        $term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
        $items = TGS_Outbound_Scan_Check_Helper::search_products($term, (int) ($_POST['limit'] ?? 12));

        wp_send_json_success(['items' => $items]);
    }

    public static function reset_check()
    {
        self::guard();

        $check_id = (int) ($_POST['check_id'] ?? 0);
        $result = TGS_Outbound_Scan_Check_Helper::reset_items($check_id);

        self::send_result($result);
    }

    public static function delete_check()
    {
        self::guard();

        $check_id = (int) ($_POST['check_id'] ?? 0);
        $ok = TGS_Outbound_Scan_Check_Helper::delete_check($check_id);

        if (!$ok) {
            wp_send_json_error(['message' => 'Không xóa được phiếu kiểm hàng.']);
        }

        wp_send_json_success(['deleted' => true]);
    }
}
