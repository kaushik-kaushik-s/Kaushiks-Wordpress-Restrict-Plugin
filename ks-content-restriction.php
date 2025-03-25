<?php
/**
 * Plugin Name: Kaushik Sannidhi's Content Restriction Plugin
 * Description: Restrict access to blog content for non-logged-in users or users without specified roles. Displays an optional excerpt before the restriction message.
 * Version: 1.2
 * Author: Kaushik Sannidhi
 */

function cr_add_meta_box() {
    add_meta_box(
        'cr_meta_box',
        'Content Restriction Settings',
        'cr_meta_box_callback',
        'post',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'cr_add_meta_box');

function cr_meta_box_callback($post) {
    $roles = get_editable_roles();
    $selected_roles = get_post_meta($post->ID, '_cr_allowed_roles', true);
    if (!$selected_roles) {
        $selected_roles = [];
    }

    foreach ($roles as $role => $details) {
        $checked = in_array($role, $selected_roles) ? 'checked' : '';
        echo '<label><input type="checkbox" name="cr_allowed_roles[]" value="' . esc_attr($role) . '" ' . $checked . '> ' . esc_html($details['name']) . '</label><br>';
    }
}

function cr_save_meta_box($post_id) {
    if (isset($_POST['cr_allowed_roles'])) {
        update_post_meta($post_id, '_cr_allowed_roles', $_POST['cr_allowed_roles']);
    } else {
        delete_post_meta($post_id, '_cr_allowed_roles');
    }
}
add_action('save_post', 'cr_save_meta_box');

function cr_add_admin_menu() {
    add_menu_page(
        'Content Restriction',
        'Restrict',
        'manage_options',
        'cr_settings',
        'cr_settings_page',
        'dashicons-lock',
        100
    );
}
add_action('admin_menu', 'cr_add_admin_menu');

function cr_settings_page() {
    if (isset($_POST['cr_restricted_message']) || isset($_POST['cr_show_excerpt'])) {
        update_option('cr_restricted_message', wp_kses_post($_POST['cr_restricted_message']));
        update_option('cr_show_excerpt', isset($_POST['cr_show_excerpt']) ? 1 : 0);
        echo '<div class="updated"><p>Settings updated.</p></div>';
    }

    $restricted_message = get_option('cr_restricted_message', cr_get_default_restricted_message());
    $show_excerpt = get_option('cr_show_excerpt', 0);
    ?>
    <div class="wrap">
        <h1>Content Restriction Settings</h1>
        <form method="POST">
            <h2>Custom Restricted Message</h2>
            <p>Enter the HTML you want to display to unauthorized users:</p>
            <textarea name="cr_restricted_message" rows="10" style="width: 100%;"><?php echo esc_textarea($restricted_message); ?></textarea>

            <h2>Display Excerpt</h2>
            <p>
                <label>
                    <input type="checkbox" name="cr_show_excerpt" value="1" <?php checked($show_excerpt, 1); ?>> Show a small excerpt before the restricted message
                </label>
            </p>
            <p><input type="submit" class="button-primary" value="Save Changes"></p>
        </form>
    </div>
    <?php
}

function cr_get_default_restricted_message() {
    return '
    <div style="background-color: rgb(22, 122, 135, 0.1); padding: 2rem; border-radius: 12px; text-align: center; font-family: Arial, sans-serif; max-width: 600px; margin: 2rem auto; box-shadow: 0 4px 6px rgba(22, 122, 135, 0.1);">
        <h2 style="color: #167a87; margin-bottom: 1rem; font-size: 24px;">🔒 Members-Only Content</h2>
        <p style="color: #444; font-size: 16px; line-height: 1.6; margin-bottom: 1.5rem;">This exclusive content is available to members only. Please login or join to access this content.</p>
    </div>';
}

function cr_restrict_content($content) {
    if (is_single() && in_the_loop() && is_main_query()) {
        $post_id = get_the_ID();
        $allowed_roles = get_post_meta($post_id, '_cr_allowed_roles', true);

        if (!is_user_logged_in()) {
            return cr_get_restricted_content($content);
        }

        $user = wp_get_current_user();
        $user_roles = $user->roles;

        if (!array_intersect($allowed_roles, $user_roles)) {
            return cr_get_restricted_content($content);
        }
    }
    return $content;
}
add_filter('the_content', 'cr_restrict_content');

function cr_get_restricted_content($content) {
    $show_excerpt = get_option('cr_show_excerpt', 0);
    $excerpt = $show_excerpt ? '<p>' . wp_trim_words(strip_shortcodes($content), 30, '...') . '</p>' : '';
    $restricted_message = get_option('cr_restricted_message', cr_get_default_restricted_message());

    return $excerpt . $restricted_message;
}
