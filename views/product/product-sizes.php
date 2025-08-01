<div class="size-selection">
    <h3 class="text-lg font-semibold text-secondary mb-3">Beden Seçimi:</h3>
    <div class="flex flex-wrap max-w-full sm:max-w-sm">
        <?php
        $product_size_ids = [];
        foreach ($product['variants'] as $variant) {
            if (!in_array($variant['size_id'], $product_size_ids)) {
                $product_size_ids[] = $variant['size_id'];
            }
        }

        $product_sizes = [];
        if (!empty($product_size_ids)) {
            if (!$db) {
                // Database bağlantısı yoksa demo size data
                $demo_sizes = [
                    ['id' => 1, 'size_value' => '36'],
                    ['id' => 2, 'size_value' => '37'],
                    ['id' => 3, 'size_value' => '38'],
                    ['id' => 4, 'size_value' => '39'],
                    ['id' => 5, 'size_value' => '40'],
                    ['id' => 6, 'size_value' => '41'],
                    ['id' => 7, 'size_value' => '42'],
                    ['id' => 8, 'size_value' => '43'],
                    ['id' => 9, 'size_value' => '44'],
                    ['id' => 10, 'size_value' => '45']
                ];
                $product_sizes = array_filter($demo_sizes, function($size) use ($product_size_ids) {
                    return in_array($size['id'], $product_size_ids);
                });
            } else {
                $product_sizes = $db->select('sizes', ['id' => ['in', $product_size_ids]]);
            }
            usort($product_sizes, fn($a, $b) => strnatcmp($a['size_value'], $b['size_value']));
        }

        foreach ($product_sizes as $size):
            $is_available = false;
            if ($selected_color_id) {
                foreach ($product['variants'] as $variant) {
                    if ($variant['color_id'] === $selected_color_id && $variant['size_id'] === $size['id'] && $variant['stock_quantity'] > 0) {
                        $is_available = true;
                        break;
                    }
                }
            }

            $class = "size-option w-12 h-12 flex items-center justify-center border border-gray-300 rounded-lg hover:border-brand hover:bg-brand hover:text-secondary transition-all font-medium m-1";
            if (!$is_available) {
                $class .= " line-through opacity-50 unavailable";
            }
            ?>
            <button class="<?php echo $class; ?>" data-size="<?php echo $size['id']; ?>"
                data-size-value="<?php echo $size['size_value']; ?>" <?php echo !$is_available ? 'disabled' : ''; ?>>
                <?php echo $size['size_value']; ?>
            </button>
        <?php endforeach; ?>
    </div>
    <p class="text-sm text-gray-600 mt-2">Seçili beden: <span id="selected-size">-</span></p>
</div>