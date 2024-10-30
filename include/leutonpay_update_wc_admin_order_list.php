<?php
/**
 * Try to prevent direct access data leaks
 */
if (!defined('ABSPATH')) exit;

/**
 * Adds new columns in woocommerce admin order list
 */
function leutonpay_add_custom_columns_to_order_list(array $columns): array
{
    $new_columns = [];

    foreach ($columns as $column_key => $column_name) {
        $new_columns[$column_key] = $column_name;

        if ($column_key === 'order_status')
            $new_columns['order_key'] = __('Transaction reference', 'leuton');
    }

    return $new_columns;
}

add_filter('manage_edit-shop_order_columns', 'leutonpay_add_custom_columns_to_order_list', 20);


/**
 * Adds content in previously added columns
 */
function leutonpay_add_content_to_custom_columns(string $column)
{
    global $post;

    if ($column === 'order_key')
        echo esc_attr(wc_get_order($post->ID)->get_order_key());
}

add_action('manage_shop_order_posts_custom_column', 'leutonpay_add_content_to_custom_columns');


/**
 * Make custom columns searchable in the admin order list by adding them to woocommerce search fields
 */
function leutonpay_add_custom_columns_to_meta_keys($meta_keys)
{
    $meta_keys[] = '_order_key';
    return $meta_keys;
}

add_filter('woocommerce_shop_order_search_fields', 'leutonpay_add_custom_columns_to_meta_keys', 10, 1);
