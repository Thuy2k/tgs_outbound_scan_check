# ĐƠN VỊ TÍNH KHI QUÉT KIỂM HÀNG

> ĐVT quy đổi lấy theo **bảng giá của website**
> Phiên bản: 1.0 | 2026-08-13
> Mô hình bảng giá đầy đủ: `tgs_pos/docs/GIA_VA_DON_VI_TINH.md`
> API dùng chung: `tgs_shop_management/docs/gia-va-don-vi-tinh.md`

---

## 1. BỐI CẢNH

Hệ thống có **nhiều bảng giá** (Bảng giá công ty TGS, Phú Thọ, Nguyễn Tất
Thành…), mỗi website áp **đúng 1 bảng**. Cấu hình ĐVT quy đổi nằm chung bảng
`wp_global_htsoft_stock_convert`, phân biệt bằng cột `price_list_id`:

```
wp_global_htsoft_price_list        ← danh sách bảng giá
wp_global_htsoft_price_list_blog   ← website ↔ bảng giá (UNIQUE blog_id)
wp_global_htsoft_stock_convert     ← ĐVT + tỷ lệ + khối lượng của từng bảng giá
```

Plugin này chỉ dùng **ĐVT + tỷ lệ + khối lượng**, không dùng giá bán.

---

## 2. LỖI ĐÃ SỬA

`includes/class-tgs-outbound-scan-check-helper.php` → `unit_options_for_sku()`

Câu cũ:

```sql
SELECT convert_unit, convert_to_htsoft, unit_weight_kg
FROM wp_global_htsoft_stock_convert
WHERE BINARY global_product_sku = %s
  AND (is_deleted = 0 OR is_deleted IS NULL)
ORDER BY convert_unit ASC
```

Ba vấn đề:

1. **Không lọc `price_list_id`** → trả về ĐVT của **mọi bảng giá**. Shop chỉ áp
   1 bảng nhưng nhân viên vẫn thấy ĐVT của bảng khác trong ô chọn.
2. **Trùng tên ĐVT giữa 2 bảng giá** → hàm dedup theo tên (`$seen`) giữ dòng
   ĐẦU TIÊN, mà thứ tự lại do MySQL quyết định ⇒ lấy nhầm **tỷ lệ quy đổi** và
   **khối lượng** của bảng giá khác. Đây là lỗi nặng nhất: đối soát hàng xuất
   báo lệch, người thật phải đi kiểm lại kiện hàng.
3. `BINARY global_product_sku` **chặn index** → quét full bảng quy đổi
   (20k+ dòng) cho mỗi sản phẩm.

Nay gọi qua `TGS_Price_List` (plugin `tgs_shop_management`):

```php
$configs = TGS_Price_List::unit_configs_by_skus([$sku]);
```

Hàm này lọc sẵn `price_list_id`, dùng `IN (...)` trên cột `utf8mb4_bin` nên vẫn
so khớp phân biệt hoa/thường **y hệt `BINARY`** nhưng dùng được index.

---

## 3. CẤU TRÚC `units` TRẢ VỀ

```php
[
  ['unit_name' => 'Lon',   'ratio' => 1.0, 'unit_weight_kg' => null,
   'is_base' => true,  'is_default_unit' => 0],
  ['unit_name' => 'Lốc_4', 'ratio' => 4.0, 'unit_weight_kg' => 2.3,
   'is_base' => false, 'is_default_unit' => 1],
]
```

| Key | Ý nghĩa |
|---|---|
| `unit_name` | Tên ĐVT hiển thị trong ô chọn |
| `ratio` | 1 ĐVT này = N đơn vị nhỏ nhất → JS nhân ra `smallestQty` |
| `unit_weight_kg` | Khối lượng 1 ĐVT (dùng đối soát kiện hàng) |
| `is_base` | (CŨ) true = đơn vị nhỏ nhất, luôn là phần tử đầu tiên |
| `is_default_unit` | (MỚI) 1 = ĐVT bán chính của bảng giá — **chỉ để tham khảo** |

---

## 4. VÌ SAO KHÔNG CHỌN SẴN "ĐVT BÁN CHÍNH"

POS ưu tiên ĐVT bán chính khi thêm vào giỏ. **Màn kiểm hàng thì không.**

Giao diện dựng `<select>` theo thứ tự mảng và chọn phần tử đầu tiên; nhân viên
quét xong thường bấm xác nhận ngay. Nếu đổi mặc định sang ĐVT lớn, thao tác
"thêm 1" đang là 1 lon sẽ thành 1 lốc = 4 lon — **sai số lượng kiểm hàng mà
không ai để ý**.

Nên: `is_base` (đơn vị nhỏ nhất) vẫn đứng đầu và được chọn sẵn như cũ. Cờ
`is_default_unit` chỉ được trả ra để giao diện muốn đánh dấu ★ thì có sẵn dữ
liệu — muốn đổi hành vi này phải là quyết định nghiệp vụ, không phải sửa ngầm.

> Giao diện đọc `data-ratio` của option đang chọn, **không** dùng chỉ số mảng để
> tra ngược, nên thêm/bớt phần tử không làm lệch lựa chọn.

---

## 5. TỐI ƯU KÈM THEO

`search_products_for_scan()` nạp trước cấu hình ĐVT cho **cả lô SKU** trước khi
vào vòng lặp:

```php
TGS_Price_List::unit_configs_by_skus($skus);   // 1 query cho tối đa 30 SKU
```

`TGS_Price_List` cache theo request nên các lời gọi `unit_options_for_sku()`
trong vòng lặp lấy từ cache, không còn N query.

---

## 6. QUY TẮC KHI SỬA TIẾP

1. Cần ĐVT / tỷ lệ / khối lượng → gọi `TGS_Price_List::unit_configs_by_skus()`.
   **Không viết SQL mới** vào `wp_global_htsoft_stock_convert`.
2. Buộc viết SQL thì thêm `TGS_Price_List::where_clause()`.
3. Không dùng `BINARY` trên `global_product_sku` — cột đã là `utf8mb4_bin`, so
   sánh thường đã phân biệt hoa/thường và dùng được index.
4. Không tự đổi ĐVT mặc định của ô chọn (xem mục 4).

Plugin đã khai `Requires Plugins: tgs_shop_management` nên `TGS_Price_List` luôn
có mặt; code vẫn bọc `class_exists()` như các chỗ khác trong plugin.

---

## 7. KIỂM TRA NHANH

```sql
-- ĐVT của 1 mã hàng ở từng bảng giá
SELECT c.price_list_id, l.price_list_name, c.convert_unit,
       c.convert_to_htsoft AS ty_le, c.unit_weight_kg, c.is_default_unit
FROM wp_global_htsoft_stock_convert c
JOIN wp_global_htsoft_price_list l
  ON l.global_htsoft_price_list_id = c.price_list_id
WHERE c.global_product_sku = '100861007'
  AND (c.is_deleted = 0 OR c.is_deleted IS NULL)
ORDER BY c.price_list_id, c.convert_to_htsoft;
```

| Hiện tượng | Nguyên nhân |
|---|---|
| Ô chọn chỉ có đơn vị gốc | Bảng giá đang áp chưa khai ĐVT nào cho SKU đó |
| Hai shop thấy danh sách ĐVT khác nhau | Đúng — 2 shop áp 2 bảng giá khác nhau |
| Khối lượng trống | Bảng giá chưa khai `unit_weight_kg` cho ĐVT đó |
