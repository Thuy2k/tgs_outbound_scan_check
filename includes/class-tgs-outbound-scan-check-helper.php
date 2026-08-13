<?php
/**
 * Data helper for outbound scan checks.
 */

if (!defined('ABSPATH')) {
    exit;
}

class TGS_Outbound_Scan_Check_Helper
{
    const STATUS_DRAFT = 'draft';
    const STATUS_CHECKING = 'checking';
    const STATUS_DONE = 'done';

    const RESULT_UNCHECKED = 'unchecked';
    const RESULT_MISSING = 'missing';
    const RESULT_SHORT = 'short';
    const RESULT_ENOUGH = 'enough';
    const RESULT_OVER = 'over';
    const RESULT_EXTRA = 'extra';
    const RESULT_MIXED = 'mixed';

    public static function check_table()
    {
        global $wpdb;
        return defined('TGS_TABLE_LOCAL_OUTBOUND_SCAN_CHECK')
            ? TGS_TABLE_LOCAL_OUTBOUND_SCAN_CHECK
            : $wpdb->prefix . 'local_outbound_scan_check';
    }

    public static function item_table()
    {
        global $wpdb;
        return defined('TGS_TABLE_LOCAL_OUTBOUND_SCAN_CHECK_ITEM')
            ? TGS_TABLE_LOCAL_OUTBOUND_SCAN_CHECK_ITEM
            : $wpdb->prefix . 'local_outbound_scan_check_item';
    }

    public static function ledger_table()
    {
        return defined('TGS_TABLE_LOCAL_LEDGER') ? TGS_TABLE_LOCAL_LEDGER : $GLOBALS['wpdb']->prefix . 'local_ledger';
    }

    public static function ledger_item_table()
    {
        return defined('TGS_TABLE_LOCAL_LEDGER_ITEM') ? TGS_TABLE_LOCAL_LEDGER_ITEM : $GLOBALS['wpdb']->prefix . 'local_ledger_item';
    }

    public static function user_can_manage()
    {
        return is_user_logged_in() && current_user_can('read');
    }

    public static function table_exists($table)
    {
        global $wpdb;
        return $table && $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function column_exists($table, $column)
    {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }

    public static function tables_ready()
    {
        return self::table_exists(self::check_table()) && self::table_exists(self::item_table());
    }

    public static function normalize_number($value)
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0.0;
        }

        $value = str_replace([' ', ','], ['', '.'], $value);
        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function get_parent_ledger($identifier)
    {
        global $wpdb;

        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return null;
        }

        $table = self::ledger_table();
        if (ctype_digit($identifier)) {
            $ledger = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE local_ledger_id = %d
                   AND (is_deleted = 0 OR is_deleted IS NULL)
                 LIMIT 1",
                (int) $identifier
            ), ARRAY_A);

            if ($ledger) {
                return $ledger;
            }
        }

        if (!self::table_exists(self::check_table())) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE local_ledger_code = %s
               AND (is_deleted = 0 OR is_deleted IS NULL)
             LIMIT 1",
            $identifier
        ), ARRAY_A);
    }

    public static function get_check($check_id)
    {
        global $wpdb;

        $check_id = (int) $check_id;
        if ($check_id <= 0) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::check_table() . "
             WHERE outbound_scan_check_id = %d
               AND is_deleted = 0
             LIMIT 1",
            $check_id
        ), ARRAY_A);
    }

    public static function list_checks(array $args = [])
    {
        global $wpdb;

        $args = wp_parse_args($args, [
            'search' => '',
            'check_status' => '',
            'result_status' => '',
            'parent_id' => 0,
            'limit' => 50,
            'offset' => 0,
        ]);

        if (!self::table_exists(self::check_table())) {
            return [];
        }

        $where = ['c.is_deleted = 0'];
        $values = [];

        if ((int) $args['parent_id'] > 0) {
            $where[] = 'c.local_ledger_parent_id = %d';
            $values[] = (int) $args['parent_id'];
        }

        if ($args['check_status'] !== '') {
            $where[] = 'c.check_status = %s';
            $values[] = sanitize_key((string) $args['check_status']);
        }

        if ($args['result_status'] !== '') {
            $where[] = 'c.result_status = %s';
            $values[] = sanitize_key((string) $args['result_status']);
        }

        if (trim((string) $args['search']) !== '') {
            $like = '%' . $wpdb->esc_like(trim((string) $args['search'])) . '%';
            $where[] = '(c.outbound_scan_check_code LIKE %s OR c.parent_ledger_code LIKE %s OR c.outbound_scan_check_title LIKE %s)';
            array_push($values, $like, $like, $like);
        }

        $where_sql = implode(' AND ', $where);
        $limit = max(1, min(200, (int) $args['limit']));
        $offset = max(0, (int) $args['offset']);

        $sql = "SELECT c.*
                FROM " . self::check_table() . " c
                WHERE {$where_sql}
                ORDER BY c.updated_at DESC, c.outbound_scan_check_id DESC
                LIMIT %d OFFSET %d";
        $values[] = $limit;
        $values[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A) ?: [];
    }

    public static function count_checks_for_parent($parent_id)
    {
        global $wpdb;

        if (!self::table_exists(self::check_table())) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM " . self::check_table() . "
             WHERE local_ledger_parent_id = %d
               AND is_deleted = 0",
            (int) $parent_id
        ));
    }

    public static function latest_check_for_parent($parent_id)
    {
        global $wpdb;

        if (!self::table_exists(self::check_table())) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT *
             FROM " . self::check_table() . "
             WHERE local_ledger_parent_id = %d
               AND is_deleted = 0
             ORDER BY updated_at DESC, outbound_scan_check_id DESC
             LIMIT 1",
            (int) $parent_id
        ), ARRAY_A);
    }

    public static function generate_code()
    {
        return sprintf(
            'KHH-%d-%s-%04d',
            get_current_blog_id(),
            current_time('ymdHis'),
            wp_rand(1, 9999)
        );
    }

    public static function create_check($parent_identifier, $note = '')
    {
        global $wpdb;

        $parent = self::get_parent_ledger($parent_identifier);
        if (!$parent) {
            return new WP_Error('parent_not_found', 'Không tìm thấy phiếu cha.');
        }

        $now = current_time('mysql');
        $code = self::generate_code();
        $title = 'Kiểm hàng - ' . (string) ($parent['local_ledger_code'] ?? $parent['local_ledger_id']);

        $inserted = $wpdb->insert(self::check_table(), [
            'outbound_scan_check_code' => $code,
            'outbound_scan_check_title' => $title,
            'outbound_scan_check_note' => sanitize_textarea_field($note),
            'local_ledger_parent_id' => (int) $parent['local_ledger_id'],
            'parent_ledger_code' => (string) ($parent['local_ledger_code'] ?? ''),
            'parent_ledger_type' => isset($parent['local_ledger_type']) ? (int) $parent['local_ledger_type'] : null,
            'check_status' => self::STATUS_DRAFT,
            'result_status' => self::RESULT_UNCHECKED,
            'outbound_scan_check_meta' => wp_json_encode(['blog_id' => get_current_blog_id()], JSON_UNESCAPED_UNICODE),
            'user_id' => get_current_user_id(),
            'is_deleted' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s',
        ]);

        if (!$inserted) {
            return new WP_Error('insert_failed', 'Không tạo được phiếu kiểm hàng.');
        }

        $check_id = (int) $wpdb->insert_id;
        self::snapshot_parent_items($check_id, $parent);
        self::recalculate_and_update_header($check_id);

        return self::get_check_payload($check_id);
    }

    public static function get_or_create_check($check_id, $parent_identifier = 0, $force_new = false)
    {
        $check_id = (int) $check_id;
        if ($check_id > 0) {
            $payload = self::get_check_payload($check_id);
            if ($payload) {
                return $payload;
            }
        }

        if (!$force_new && $parent_identifier) {
            $parent = self::get_parent_ledger($parent_identifier);
            if ($parent) {
                $latest = self::latest_check_for_parent((int) $parent['local_ledger_id']);
                if ($latest) {
                    return self::get_check_payload((int) $latest['outbound_scan_check_id']);
                }
            }
        }

        if ($parent_identifier) {
            return self::create_check($parent_identifier);
        }

        return new WP_Error('missing_check', 'Thiếu phiếu kiểm hàng.');
    }

    public static function snapshot_parent_items($check_id, array $parent)
    {
        global $wpdb;

        $check_id = (int) $check_id;
        if ($check_id <= 0) {
            return [];
        }

        $item_ids = self::decode_item_ids($parent['local_ledger_item_id'] ?? null);
        if (empty($item_ids)) {
            return [];
        }

        $existing_source_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT local_ledger_item_id
             FROM " . self::item_table() . "
             WHERE outbound_scan_check_id = %d
               AND local_ledger_item_id IS NOT NULL
               AND is_deleted = 0",
            $check_id
        )) ?: [];
        $existing_source_ids = array_map('intval', $existing_source_ids);

        $new_ids = array_values(array_diff($item_ids, $existing_source_ids));
        if (empty($new_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($new_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM " . self::ledger_item_table() . "
             WHERE local_ledger_item_id IN ({$placeholders})
               AND (is_deleted = 0 OR is_deleted IS NULL)",
            ...$new_ids
        ), ARRAY_A) ?: [];
        $row_map = [];
        foreach ($rows as $row) {
            $row_map[(int) ($row['local_ledger_item_id'] ?? 0)] = $row;
        }
        $rows = [];
        foreach ($new_ids as $source_id) {
            if (isset($row_map[(int) $source_id])) {
                $rows[] = $row_map[(int) $source_id];
            }
        }

        $product_names = self::product_name_map_for_items($rows);
        $now = current_time('mysql');
        $created = [];

        foreach ($rows as $index => $row) {
            $item_id = (int) ($row['local_ledger_item_id'] ?? 0);
            $sku = (string) ($row['local_product_sku'] ?? '');
            $product_id = (int) ($row['global_product_name_id'] ?: ($row['local_product_name_id'] ?? 0));
            $name = $product_names[$product_id] ?? '';
            $expected = self::normalize_number($row['quantity'] ?? 0);

            $wpdb->insert(self::item_table(), [
                'outbound_scan_check_id' => $check_id,
                'local_ledger_parent_id' => (int) $parent['local_ledger_id'],
                'local_ledger_item_id' => $item_id,
                'local_product_sku' => $sku,
                'global_product_name_id' => $product_id ?: null,
                'product_name_cache' => $name,
                'expected_quantity' => $expected,
                'checked_quantity' => 0,
                'difference_quantity' => -$expected,
                'check_result' => $expected > 0 ? self::RESULT_MISSING : self::RESULT_ENOUGH,
                'check_note' => '',
                'source_item_note' => (string) ($row['local_ledger_item_note'] ?? ''),
                'is_extra' => 0,
                'is_gift' => (int) ($row['local_ledger_item_gift_type'] ?? 0),
                'item_sort' => $index + 1,
                'outbound_scan_check_item_meta' => wp_json_encode([
                    'source' => 'parent_snapshot',
                    'unit_name' => $row['local_ledger_item_unit_name'] ?? '',
                    'unit_ratio' => $row['local_ledger_item_unit_ratio'] ?? '',
                    'unit_quantity' => $row['local_ledger_item_unit_quantity'] ?? '',
                ], JSON_UNESCAPED_UNICODE),
                'user_id' => get_current_user_id(),
                'is_deleted' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($wpdb->insert_id) {
                $created[] = (int) $wpdb->insert_id;
            }
        }

        return $created;
    }

    public static function decode_item_ids($raw)
    {
        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded))));
    }

    public static function product_name_map_for_items(array $rows)
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) (($row['global_product_name_id'] ?? 0) ?: ($row['local_product_name_id'] ?? 0));
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if (empty($ids) || !class_exists('TGS_Global_Product_Source')) {
            return [];
        }

        $products = TGS_Global_Product_Source::query_all_products([
            'ids' => array_values($ids),
            'parent_only' => false,
            'per_page' => count($ids),
        ]);

        $map = [];
        foreach ($products['items'] ?? [] as $product) {
            $map[(int) ($product['global_product_name_id'] ?? 0)] = (string) ($product['global_product_name'] ?? '');
        }

        return $map;
    }

    public static function get_check_items($check_id)
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM " . self::item_table() . "
             WHERE outbound_scan_check_id = %d
               AND is_deleted = 0
             ORDER BY item_sort ASC, outbound_scan_check_item_id ASC",
            (int) $check_id
        ), ARRAY_A) ?: [];

        return array_map([__CLASS__, 'format_item'], $rows);
    }

    public static function format_item(array $row)
    {
        $expected = self::normalize_number($row['expected_quantity'] ?? 0);
        $checked = self::normalize_number($row['checked_quantity'] ?? 0);

        return [
            'outbound_scan_check_item_id' => (int) ($row['outbound_scan_check_item_id'] ?? 0),
            'outbound_scan_check_id' => (int) ($row['outbound_scan_check_id'] ?? 0),
            'local_ledger_parent_id' => (int) ($row['local_ledger_parent_id'] ?? 0),
            'local_ledger_item_id' => (int) ($row['local_ledger_item_id'] ?? 0),
            'local_product_sku' => (string) ($row['local_product_sku'] ?? ''),
            'global_product_name_id' => (int) ($row['global_product_name_id'] ?? 0),
            'product_name_cache' => (string) ($row['product_name_cache'] ?? ''),
            'expected_quantity' => $expected,
            'checked_quantity' => $checked,
            'difference_quantity' => round($checked - $expected, 3),
            'check_result' => self::classify_item($expected, $checked, !empty($row['is_extra'])),
            'check_note' => (string) ($row['check_note'] ?? ''),
            'source_item_note' => (string) ($row['source_item_note'] ?? ''),
            'is_extra' => (int) ($row['is_extra'] ?? 0),
            'is_gift' => (int) ($row['is_gift'] ?? 0),
            'item_sort' => (int) ($row['item_sort'] ?? 0),
            'meta' => self::decode_json($row['outbound_scan_check_item_meta'] ?? null),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    public static function get_check_payload($check_id)
    {
        $check = self::get_check($check_id);
        if (!$check) {
            return null;
        }

        $parent = self::get_parent_ledger((string) $check['local_ledger_parent_id']);
        $items = self::get_check_items((int) $check['outbound_scan_check_id']);

        return [
            'check' => self::format_check($check),
            'parent' => self::format_parent($parent),
            'items' => $items,
            'summary' => self::analyze_items($items),
        ];
    }

    public static function format_check(array $check)
    {
        return [
            'outbound_scan_check_id' => (int) ($check['outbound_scan_check_id'] ?? 0),
            'outbound_scan_check_code' => (string) ($check['outbound_scan_check_code'] ?? ''),
            'outbound_scan_check_title' => (string) ($check['outbound_scan_check_title'] ?? ''),
            'outbound_scan_check_note' => (string) ($check['outbound_scan_check_note'] ?? ''),
            'local_ledger_parent_id' => (int) ($check['local_ledger_parent_id'] ?? 0),
            'parent_ledger_code' => (string) ($check['parent_ledger_code'] ?? ''),
            'parent_ledger_type' => (int) ($check['parent_ledger_type'] ?? 0),
            'check_status' => (string) ($check['check_status'] ?? self::STATUS_DRAFT),
            'result_status' => (string) ($check['result_status'] ?? self::RESULT_UNCHECKED),
            'total_expected_qty' => self::normalize_number($check['total_expected_qty'] ?? 0),
            'total_checked_qty' => self::normalize_number($check['total_checked_qty'] ?? 0),
            'total_lines' => (int) ($check['total_lines'] ?? 0),
            'total_missing_lines' => (int) ($check['total_missing_lines'] ?? 0),
            'total_short_lines' => (int) ($check['total_short_lines'] ?? 0),
            'total_enough_lines' => (int) ($check['total_enough_lines'] ?? 0),
            'total_over_lines' => (int) ($check['total_over_lines'] ?? 0),
            'total_extra_lines' => (int) ($check['total_extra_lines'] ?? 0),
            'created_at' => $check['created_at'] ?? null,
            'updated_at' => $check['updated_at'] ?? null,
        ];
    }

    public static function format_parent($parent)
    {
        if (!$parent) {
            return null;
        }

        $transfer = self::get_transfer_info((int) ($parent['local_ledger_id'] ?? 0));

        return [
            'local_ledger_id' => (int) ($parent['local_ledger_id'] ?? 0),
            'local_ledger_code' => (string) ($parent['local_ledger_code'] ?? ''),
            'local_ledger_code_source' => (string) ($parent['local_ledger_code_source'] ?? ''),
            'local_ledger_type' => (int) ($parent['local_ledger_type'] ?? 0),
            'local_ledger_title' => (string) ($parent['local_ledger_title'] ?? ''),
            'local_ledger_note' => (string) ($parent['local_ledger_note'] ?? ''),
            'local_ledger_total_amount' => self::normalize_number($parent['local_ledger_total_amount'] ?? 0),
            'local_ledger_person_id' => (int) ($parent['local_ledger_person_id'] ?? 0),
            'supplier_id' => (int) ($parent['supplier_id'] ?? 0),
            'transfer' => $transfer,
            'partner_blog_id' => (int) ($transfer['partner_blog_id'] ?? 0),
            'partner_shop_name' => (string) ($transfer['partner_shop_name'] ?? ''),
            'created_at' => $parent['created_at'] ?? null,
            'updated_at' => $parent['updated_at'] ?? null,
        ];
    }

    public static function get_transfer_info($ledger_id)
    {
        global $wpdb;

        $ledger_id = (int) $ledger_id;
        if ($ledger_id <= 0) {
            return null;
        }

        $table = $wpdb->prefix . 'transfer_ledger';
        if (!self::table_exists($table)) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT transfer_ledger_id, source_blog_id, source_ledger_id, destination_blog_id, destination_ledger_id, transfer_status
             FROM {$table}
             WHERE source_ledger_id = %d OR destination_ledger_id = %d
             ORDER BY transfer_ledger_id DESC
             LIMIT 1",
            $ledger_id,
            $ledger_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $current_blog_id = (int) get_current_blog_id();
        $source_blog_id = (int) ($row['source_blog_id'] ?? 0);
        $destination_blog_id = (int) ($row['destination_blog_id'] ?? 0);
        $partner_blog_id = $source_blog_id === $current_blog_id ? $destination_blog_id : $source_blog_id;
        $partner_name = $partner_blog_id > 0 ? get_blog_option($partner_blog_id, 'blogname') : '';

        return [
            'transfer_ledger_id' => (int) ($row['transfer_ledger_id'] ?? 0),
            'source_blog_id' => $source_blog_id,
            'source_ledger_id' => (int) ($row['source_ledger_id'] ?? 0),
            'destination_blog_id' => $destination_blog_id,
            'destination_ledger_id' => (int) ($row['destination_ledger_id'] ?? 0),
            'transfer_status' => (int) ($row['transfer_status'] ?? 0),
            'partner_blog_id' => $partner_blog_id,
            'partner_shop_name' => $partner_name ?: ($partner_blog_id ? 'Shop #' . $partner_blog_id : ''),
        ];
    }

    public static function analyze_items(array $items)
    {
        $summary = [
            'total_lines' => count($items),
            'expected_qty' => 0.0,
            'checked_qty' => 0.0,
            'missing' => 0,
            'short' => 0,
            'enough' => 0,
            'over' => 0,
            'extra' => 0,
            'result_status' => self::RESULT_UNCHECKED,
        ];

        foreach ($items as $item) {
            $expected = self::normalize_number($item['expected_quantity'] ?? 0);
            $checked = self::normalize_number($item['checked_quantity'] ?? 0);
            $is_extra = !empty($item['is_extra']);
            $result = self::classify_item($expected, $checked, $is_extra);

            $summary['expected_qty'] += $expected;
            $summary['checked_qty'] += $checked;

            if (isset($summary[$result])) {
                $summary[$result]++;
            }
        }

        if ($summary['total_lines'] === 0 || $summary['checked_qty'] <= 0) {
            $summary['result_status'] = self::RESULT_UNCHECKED;
        } elseif ($summary['extra'] > 0 || $summary['over'] > 0) {
            $summary['result_status'] = self::RESULT_OVER;
        } elseif ($summary['missing'] > 0 || $summary['short'] > 0) {
            $summary['result_status'] = self::RESULT_SHORT;
        } elseif ($summary['enough'] === $summary['total_lines']) {
            $summary['result_status'] = self::RESULT_ENOUGH;
        } else {
            $summary['result_status'] = self::RESULT_MIXED;
        }

        $summary['expected_qty'] = round($summary['expected_qty'], 3);
        $summary['checked_qty'] = round($summary['checked_qty'], 3);

        return $summary;
    }

    public static function classify_item($expected, $checked, $is_extra = false)
    {
        $expected = self::normalize_number($expected);
        $checked = self::normalize_number($checked);

        if ($is_extra || $expected <= 0) {
            return $checked > 0 ? self::RESULT_EXTRA : self::RESULT_UNCHECKED;
        }

        if ($checked <= 0) {
            return self::RESULT_MISSING;
        }

        if ($checked < $expected) {
            return self::RESULT_SHORT;
        }

        if ($checked > $expected) {
            return self::RESULT_OVER;
        }

        return self::RESULT_ENOUGH;
    }

    public static function save_items($check_id, array $items, $note = '', $status = self::STATUS_CHECKING)
    {
        global $wpdb;

        $check = self::get_check($check_id);
        if (!$check) {
            return new WP_Error('check_not_found', 'Không tìm thấy phiếu kiểm hàng.');
        }

        $now = current_time('mysql');
        $seen_ids = [];
        $sort = 1;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_id = (int) ($item['outbound_scan_check_item_id'] ?? 0);
            $expected = self::normalize_number($item['expected_quantity'] ?? 0);
            $checked = self::normalize_number($item['checked_quantity'] ?? 0);
            $is_extra = !empty($item['is_extra']) ? 1 : 0;
            $sku = sanitize_text_field((string) ($item['local_product_sku'] ?? ''));
            if ($sku === '' && !$item_id) {
                continue;
            }

            $result = self::classify_item($expected, $checked, $is_extra);
            $data = [
                'outbound_scan_check_id' => (int) $check_id,
                'local_ledger_parent_id' => (int) $check['local_ledger_parent_id'],
                'local_ledger_item_id' => !empty($item['local_ledger_item_id']) ? (int) $item['local_ledger_item_id'] : null,
                'local_product_sku' => $sku,
                'global_product_name_id' => !empty($item['global_product_name_id']) ? (int) $item['global_product_name_id'] : null,
                'product_name_cache' => sanitize_text_field((string) ($item['product_name_cache'] ?? '')),
                'expected_quantity' => $expected,
                'checked_quantity' => $checked,
                'difference_quantity' => round($checked - $expected, 3),
                'check_result' => $result,
                'check_note' => sanitize_textarea_field((string) ($item['check_note'] ?? '')),
                'source_item_note' => sanitize_textarea_field((string) ($item['source_item_note'] ?? '')),
                'is_extra' => $is_extra,
                'is_gift' => !empty($item['is_gift']) ? 1 : 0,
                'item_sort' => $sort++,
                'outbound_scan_check_item_meta' => wp_json_encode((array) ($item['meta'] ?? []), JSON_UNESCAPED_UNICODE),
                'user_id' => get_current_user_id(),
                'updated_at' => $now,
            ];

            if ($item_id > 0) {
                $wpdb->update(self::item_table(), $data, [
                    'outbound_scan_check_item_id' => $item_id,
                    'outbound_scan_check_id' => (int) $check_id,
                ]);
                $seen_ids[] = $item_id;
            } else {
                $data['is_deleted'] = 0;
                $data['created_at'] = $now;
                $wpdb->insert(self::item_table(), $data);
                if ($wpdb->insert_id) {
                    $seen_ids[] = (int) $wpdb->insert_id;
                }
            }
        }

        if (!empty($seen_ids)) {
            $placeholders = implode(',', array_fill(0, count($seen_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "UPDATE " . self::item_table() . "
                 SET is_deleted = 1, deleted_at = %s, updated_at = %s
                 WHERE outbound_scan_check_id = %d
                   AND outbound_scan_check_item_id NOT IN ({$placeholders})",
                $now,
                $now,
                (int) $check_id,
                ...$seen_ids
            ));
        }

        $status = in_array($status, [self::STATUS_DRAFT, self::STATUS_CHECKING, self::STATUS_DONE], true)
            ? $status
            : self::STATUS_CHECKING;

        self::recalculate_and_update_header($check_id, $note, $status);

        return self::get_check_payload($check_id);
    }

    public static function recalculate_and_update_header($check_id, $note = null, $status = null)
    {
        global $wpdb;

        $items = self::get_check_items($check_id);
        $summary = self::analyze_items($items);
        $data = [
            'result_status' => $summary['result_status'],
            'total_expected_qty' => $summary['expected_qty'],
            'total_checked_qty' => $summary['checked_qty'],
            'total_lines' => $summary['total_lines'],
            'total_missing_lines' => $summary['missing'],
            'total_short_lines' => $summary['short'],
            'total_enough_lines' => $summary['enough'],
            'total_over_lines' => $summary['over'],
            'total_extra_lines' => $summary['extra'],
            'updated_at' => current_time('mysql'),
        ];

        if ($note !== null) {
            $data['outbound_scan_check_note'] = sanitize_textarea_field($note);
        }

        if ($status !== null) {
            $data['check_status'] = sanitize_key($status);
        }

        $wpdb->update(self::check_table(), $data, ['outbound_scan_check_id' => (int) $check_id]);

        return $summary;
    }

    public static function reset_items($check_id)
    {
        global $wpdb;

        $now = current_time('mysql');
        $wpdb->query($wpdb->prepare(
            "UPDATE " . self::item_table() . "
             SET checked_quantity = 0,
                 difference_quantity = 0,
                 check_result = %s,
                 updated_at = %s
             WHERE outbound_scan_check_id = %d
               AND (is_deleted = 0 OR is_deleted IS NULL)",
            self::RESULT_MISSING,
            $now,
            (int) $check_id
        ));

        self::recalculate_and_update_header($check_id, null, self::STATUS_DRAFT);

        return self::get_check_payload($check_id);
    }

    public static function delete_check($check_id)
    {
        global $wpdb;

        $check_id = (int) $check_id;
        if ($check_id <= 0) {
            return false;
        }

        $wpdb->delete(self::item_table(), ['outbound_scan_check_id' => $check_id], ['%d']);

        return false !== $wpdb->delete(self::check_table(), ['outbound_scan_check_id' => $check_id], ['%d']);
    }

    public static function search_products($term, $limit = 12)
    {
        global $wpdb;

        $term = trim((string) $term);
        if ($term === '' || !class_exists('TGS_Global_Product_Source')) {
            return [];
        }

        $limit = max(1, min(30, (int) $limit));
        $table = defined('TGS_TABLE_GLOBAL_PRODUCT_NAME')
            ? TGS_TABLE_GLOBAL_PRODUCT_NAME
            : $wpdb->base_prefix . 'global_product_name';
        if (!TGS_Global_Product_Source::table_exists($table)) {
            return [];
        }

        $tokens = preg_split('/\s+/', $term);
        $tokens = array_values(array_filter(array_map('trim', (array) $tokens)));
        $where = [
            '(is_deleted = 0 OR is_deleted IS NULL)',
            "global_product_sku IS NOT NULL AND TRIM(global_product_sku) <> ''",
        ];
        $values = [];
        $has_special_barcode = self::column_exists($table, 'global_product_special_barcode');

        foreach ($tokens as $token) {
            $like = '%' . $wpdb->esc_like($token) . '%';
            if ($has_special_barcode) {
                $where[] = '(global_product_name LIKE %s OR global_product_sku LIKE %s OR global_product_barcode_main LIKE %s OR global_product_special_barcode LIKE %s)';
                array_push($values, $like, $like, $like, $like);
            } else {
                $where[] = '(global_product_name LIKE %s OR global_product_sku LIKE %s OR global_product_barcode_main LIKE %s)';
                array_push($values, $like, $like, $like);
            }
        }

        $where_sql = implode(' AND ', $where);
        $special_order = $has_special_barcode ? "WHEN global_product_special_barcode = %s THEN 2" : '';
        $sql = "SELECT *
                FROM {$table}
                WHERE {$where_sql}
                ORDER BY
                    CASE
                        WHEN global_product_sku = %s THEN 1
                        WHEN global_product_barcode_main = %s THEN 2
                        {$special_order}
                        WHEN global_product_name LIKE %s THEN 3
                        ELSE 4
                    END,
                    global_product_sku ASC
                LIMIT %d";
        if ($has_special_barcode) {
            array_push($values, $term, $term, $term, '%' . $wpdb->esc_like($term) . '%', $limit);
        } else {
            array_push($values, $term, $term, '%' . $wpdb->esc_like($term) . '%', $limit);
        }

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$values), ARRAY_A) ?: [];
        $skus = array_values(array_filter(array_map(static function ($row) {
            return (string) ($row['global_product_sku'] ?? '');
        }, $rows)));
        $stock_map = TGS_Global_Product_Source::get_stock_for_skus($skus, get_current_blog_id());

        // Nạp trước cấu hình ĐVT cho cả lô (TGS_Price_List cache theo request)
        // → tránh mỗi sản phẩm 1 query trong vòng lặp bên dưới.
        if (class_exists('TGS_Price_List')) {
            TGS_Price_List::unit_configs_by_skus($skus);
        }

        $items = [];
        foreach ($rows as $row) {
            $product = TGS_Global_Product_Source::normalize_product(
                $row,
                $stock_map[(string) ($row['global_product_sku'] ?? '')] ?? null,
                false
            );
            $sku = (string) ($product['global_product_sku'] ?? '');
            if ($sku === '') {
                continue;
            }

            $items[] = [
                'global_product_name_id' => (int) ($product['global_product_name_id'] ?? 0),
                'global_product_name' => (string) ($product['global_product_name'] ?? ''),
                'global_product_sku' => $sku,
                'global_product_barcode_main' => (string) ($product['global_product_barcode_main'] ?? ''),
                'global_product_unit' => (string) ($product['global_product_unit'] ?? ''),
                'actual_stock' => self::normalize_number($product['actual_stock'] ?? 0),
                'projected_stock' => self::normalize_number($product['projected_stock'] ?? 0),
                'units' => self::unit_options_for_sku($sku, (string) ($product['global_product_unit'] ?? '')),
            ];
        }

        return $items;
    }

    /**
     * Danh sách ĐVT quy đổi của 1 SKU để nhân viên chọn khi quét kiểm hàng.
     *
     * Nguồn: BẢNG GIÁ mà website đang áp (wp_global_htsoft_price_list_blog).
     * Trước đây query thẳng bảng quy đổi, không lọc price_list_id, nên khi hệ
     * thống có nhiều bảng giá sẽ:
     *   - Hiện thừa ĐVT của bảng giá khác (shop không dùng ĐVT đó);
     *   - Trùng tên ĐVT giữa 2 bảng giá thì dòng nào lấy được là do thứ tự
     *     MySQL trả về → sai TỶ LỆ và sai KHỐI LƯỢNG khi đối soát hàng xuất.
     * Ngoài ra `WHERE BINARY global_product_sku` chặn index (quét full bảng).
     *
     * Phần tử đầu tiên LUÔN là đơn vị nhỏ nhất (is_base = true) — giao diện
     * chọn mặc định phần tử này, giữ nguyên như cũ để nhân viên không quét
     * nhầm số lượng.
     */
    public static function unit_options_for_sku($sku, $base_unit = '')
    {
        $sku = trim((string) $sku);
        $base_unit = trim((string) $base_unit);
        $units = [[
            'unit_name' => $base_unit !== '' ? $base_unit : 'DVT',
            'ratio' => 1.0,
            'unit_weight_kg' => null,
            'is_base' => true,
            'is_default_unit' => 0,
        ]];

        if ($sku === '' || !class_exists('TGS_Price_List')) {
            return $units;
        }

        $configs = TGS_Price_List::unit_configs_by_skus([$sku]);
        $rows = $configs[$sku] ?? [];

        $seen = [strtolower($units[0]['unit_name']) => true];
        foreach ($rows as $row) {
            $unit_name = trim((string) ($row['unit'] ?? ''));
            if ($unit_name === '') {
                continue;
            }

            $key = strtolower($unit_name);
            if (isset($seen[$key])) {
                continue;
            }

            $ratio = self::normalize_number($row['ratio'] ?? 1);
            if ($ratio <= 0) {
                $ratio = 1.0;
            }

            $units[] = [
                'unit_name' => $unit_name,
                'ratio' => $ratio,
                'unit_weight_kg' => $row['unit_weight_kg'] !== null
                    ? self::normalize_number($row['unit_weight_kg'])
                    : null,
                'is_base' => false,
                // 1 = ĐVT bán chính của bảng giá (POS ưu tiên). Ở màn kiểm hàng
                // chỉ mang tính thông tin, KHÔNG dùng để chọn sẵn.
                'is_default_unit' => (int) ($row['is_default_unit'] ?? 0),
            ];
            $seen[$key] = true;
        }

        return $units;
    }

    public static function decode_json($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
