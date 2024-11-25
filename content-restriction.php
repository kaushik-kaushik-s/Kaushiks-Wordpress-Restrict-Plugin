<?php
/**
 * Plugin Name: Content Restriction
 * Description: Restrict access to blog content for non-logged-in users or users without specified roles.
 * Version: 1.1
 * Author: Kaushik Sannidhi
 */

// Add meta box for role selection
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

// Add settings page for custom restricted message
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

// Settings page callback
function cr_settings_page() {
    if (isset($_POST['cr_restricted_message'])) {
        update_option('cr_restricted_message', wp_kses_post($_POST['cr_restricted_message']));
        echo '<div class="updated"><p>Restricted message updated.</p></div>';
    }
    $restricted_message = get_option('cr_restricted_message', cr_get_default_restricted_message());
    ?>
    <div class="wrap">
        <h1>Content Restriction Settings</h1>
        <form method="POST">
            <h2>Custom Restricted Message</h2>
            <p>Enter the HTML you want to display to unauthorized users:</p>
            <textarea name="cr_restricted_message" rows="10" style="width: 100%;"><?php echo esc_textarea($restricted_message); ?></textarea>
            <p><input type="submit" class="button-primary" value="Save Changes"></p>
        </form>
    </div>
    <?php
}

// Default restricted message
function cr_get_default_restricted_message() {
    return '
    <div style="background-color: rgb(22, 122, 135, 0.1); padding: 2rem; border-radius: 12px; text-align: center; font-family: Arial, sans-serif; max-width: 600px; margin: 2rem auto; box-shadow: 0 4px 6px rgba(22, 122, 135, 0.1);">
        <h2 style="color: #167a87; margin-bottom: 1rem; font-size: 24px;">🔒 Members-Only Content</h2>
        <p style="color: #444; font-size: 16px; line-height: 1.6; margin-bottom: 1.5rem;">This exclusive content is available to members only. Please login or join to access this content.</p>
    </div>';
}

// Restrict content based on roles
function cr_restrict_content($content) {
    if (is_single() && in_the_loop() && is_main_query()) {
        $post_id = get_the_ID();
        $allowed_roles = get_post_meta($post_id, '_cr_allowed_roles', true);

        if (!is_user_logged_in()) {
            return get_option('cr_restricted_message', cr_get_default_restricted_message());
        }

        $user = wp_get_current_user();
        $user_roles = $user->roles;

        if (!array_intersect($allowed_roles, $user_roles)) {
            return get_option('cr_restricted_message', cr_get_default_restricted_message());
        }
    }
    return $content;
}
add_filter('the_content', 'cr_restrict_content');
