<?php
// Add the return/exchange form to the WooCommerce order details page
add_action('woocommerce_order_details_after_order_table', 'add_return_exchange_form', 10, 1);

function add_return_exchange_form($order) {
    if ($order->get_status() != 'completed') {
        return;
    }

    $items = $order->get_items();
    
    // Capture the selected action type and reason if the form has been submitted
    $selected_action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'return';
    $selected_reason = isset($_POST['exchange_reason']) ? sanitize_text_field($_POST['exchange_reason']) : '';

    ?>
    <h2><?php _e('Return or Exchange Products', 'woocommerce'); ?></h2>

    <?php if (isset($_GET['return_action_success'])) : ?>
        <div class="woocommerce-message">
            <?php _e('Your return/exchange request has been submitted successfully.', 'woocommerce'); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(add_query_arg('key', $order->get_order_key(), $order->get_view_order_url())); ?>">
        <table class="shop_table">
            <thead>
                <tr>
                    <th><?php _e('Product', 'woocommerce'); ?></th>
                    <th><?php _e('Quantity', 'woocommerce'); ?></th>
                    <th><?php _e('Select for Return/Exchange', 'woocommerce'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item_id => $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item->get_name()); ?></td>
                        <td><?php echo esc_html($item->get_quantity()); ?></td>
                        <td>
                            <input type="checkbox" name="return_items[]" value="<?php echo esc_attr($item_id); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <label for="action_type"><?php _e('Choose Action:', 'woocommerce'); ?></label>
            <select name="action_type" id="action_type" onchange="this.form.submit()">
                <option value="return" <?php selected($selected_action, 'return'); ?>><?php _e('Return', 'woocommerce'); ?></option>
                <option value="exchange" <?php selected($selected_action, 'exchange'); ?>><?php _e('Exchange', 'woocommerce'); ?></option>
            </select>
        </p>

        <?php if ($selected_action === 'exchange') : ?>
        <p>
            <label for="exchange_reason"><?php _e('Reason for Exchange:', 'woocommerce'); ?></label>
            <select name="exchange_reason" id="exchange_reason" onchange="this.form.submit()">
                <option value="size_exchange" <?php selected($selected_reason, 'size_exchange'); ?>><?php _e('Size Exchange', 'woocommerce'); ?></option>
                <option value="defective_product" <?php selected($selected_reason, 'defective_product'); ?>><?php _e('Defective Product', 'woocommerce'); ?></option>
                <option value="other" <?php selected($selected_reason, 'other'); ?>><?php _e('Other', 'woocommerce'); ?></option>
            </select>
        </p>

        <?php if ($selected_reason === 'size_exchange') : ?>
        <p id="size_section">
            <label for="size_list"><?php _e('Select Size:', 'woocommerce'); ?></label>
            <select name="size_list" id="size_list">
                <option value="small"><?php _e('Small', 'woocommerce'); ?></option>
                <option value="medium"><?php _e('Medium', 'woocommerce'); ?></option>
                <option value="large"><?php _e('Large', 'woocommerce'); ?></option>
                <option value="extra_large"><?php _e('Extra Large', 'woocommerce'); ?></option>
            </select>
        </p>
        <?php elseif ($selected_reason === 'other') : ?>
        <p id="other_reason_section">
            <label for="other_reason"><?php _e('Please Specify:', 'woocommerce'); ?></label>
            <textarea name="other_reason" id="other_reason" placeholder="<?php _e('Please specify your reason...', 'woocommerce'); ?>"></textarea>
        </p>
        <?php endif; ?>
        <?php endif; ?>

        <button type="submit" name="submit_action"><?php _e('Submit', 'woocommerce'); ?></button>
        <?php wp_nonce_field('return_exchange_request', 'return_exchange_nonce'); ?>
    </form>
    <?php
}

// Handle the return/exchange form submission
add_action('template_redirect', 'handle_return_exchange_form_submission');

function handle_return_exchange_form_submission() {
    if (isset($_POST['submit_action']) && isset($_POST['return_items']) && isset($_POST['return_exchange_nonce'])) {
        if (!wp_verify_nonce($_POST['return_exchange_nonce'], 'return_exchange_request')) {
            wc_add_notice(__('Security check failed.', 'woocommerce'), 'error');
            return;
        }

        if (!isset($_GET['key'])) {
            wc_add_notice(__('Invalid order key.', 'woocommerce'), 'error');
            return;
        }

        $order_key = sanitize_text_field($_GET['key']);
        $order_id = wc_get_order_id_by_order_key($order_key);

        if (!$order_id) {
            wc_add_notice(__('Invalid order ID.', 'woocommerce'), 'error');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Invalid order.', 'woocommerce'), 'error');
            return;
        }

        if ($order->get_status() != 'completed') {
            wc_add_notice(__('Order is not completed.', 'woocommerce'), 'error');
            return;
        }

        $return_items = isset($_POST['return_items']) ? $_POST['return_items'] : [];
        if (empty($return_items)) {
            wc_add_notice(__('No items selected.', 'woocommerce'), 'error');
            return;
        }

        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        if (!in_array($action_type, ['return', 'exchange'], true)) {
            wc_add_notice(__('Invalid action type.', 'woocommerce'), 'error');
            return;
        }

        $exchange_reason = '';
        if ($action_type === 'exchange') {
            $exchange_reason = isset($_POST['exchange_reason']) ? sanitize_text_field($_POST['exchange_reason']) : '';
            if ($exchange_reason === 'size_exchange') {
                $size_selected = isset($_POST['size_list']) ? sanitize_text_field($_POST['size_list']) : '';
                $exchange_reason = 'Size Exchange - ' . $size_selected;
            } elseif ($exchange_reason === 'other') {
                $other_reason = isset($_POST['other_reason']) ? sanitize_text_field($_POST['other_reason']) : '';
                $exchange_reason = 'Other - ' . $other_reason;
            }
        }

        $email_content = '';
        foreach ($return_items as $item_id) {
            $item = $order->get_item($item_id);
            if (!$item) {
                wc_add_notice(__('Invalid item selected.', 'woocommerce'), 'error');
                continue;
            }
            $product = $item->get_product();

            // Update order item meta with the latest action
            wc_update_order_item_meta($item_id, '_return_exchange', json_encode([
                'product_name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'product_image' => wp_get_attachment_url($product->get_image_id()),
                'action' => $action_type,
                'reason' => $exchange_reason
            ]));

            // Prepare email content
            $email_content .= sprintf(
                __('Product: %s<br>Quantity: %d<br>Action: %s<br>Reason: %s<br><br>', 'woocommerce'),
                esc_html($item->get_name()),
                esc_html($item->get_quantity()),
                esc_html(ucfirst($action_type)),
                esc_html($action_type === 'exchange' ? $exchange_reason : __('N/A', 'woocommerce'))
            );
        }

        // Email setup
        $admin_email = get_option('admin_email'); // Admin email address
        $customer_email = $order->get_billing_email(); // Customer email address
        $subject = __('Return/Exchange Request Received', 'woocommerce');
        $message = sprintf(
            __('A return/exchange request has been submitted for Order #%d.<br><br>%s', 'woocommerce'),
            $order_id,
            $email_content
        );

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send email to admin
        wp_mail($admin_email, $subject, $message, $headers);

        // Send email to customer
        wp_mail($customer_email, $subject, $message, $headers);

        // Redirect to the order view with a success message
        wp_redirect(add_query_arg('return_action_success', '1', $order->get_view_order_url()));
        exit;
    }
}

>