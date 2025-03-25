<?php
/**
 * Plugin Name: Kaushik Sannidhi's Content Restriction Plugin
 * Description: Restrict access to blog content for selected user roles. Includes Quick Edit functionality for faster role editing.
 * Version: 1.4
 * Author: Kaushik Sannidhi
 */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'cr_meta_box',
        'Restrict Types',
        function ($post) {
            $roles = wp_roles()->roles;
            $restricted_roles = get_post_meta($post->ID, '_cr_restricted_roles', true) ?: [];
            foreach ($roles as $role => $details) {
                echo '<label><input type="checkbox" name="cr_restricted_roles[]" value="' . esc_attr($role) . '" ' . checked(in_array($role, $restricted_roles), true, false) . '> ' . esc_html($details['name']) . '</label><br>';
            }
        },
        'post',
        'side'
    );
});

add_action('save_post', function ($post_id) {
    if (isset($_POST['cr_restricted_roles'])) {
        update_post_meta($post_id, '_cr_restricted_roles', array_map('sanitize_text_field', $_POST['cr_restricted_roles']));
    } else {
        delete_post_meta($post_id, '_cr_restricted_roles');
    }
});

add_action('admin_menu', function () {
    add_menu_page(
        'Content Restriction',
        'Restrict',
        'manage_options',
        'cr_settings',
        function () {
            if ($_POST) {
                update_option('cr_restricted_message', wp_kses_post($_POST['cr_restricted_message']));
                update_option('cr_show_excerpt', !empty($_POST['cr_show_excerpt']));
                echo '<div class="updated"><p>Settings updated.</p></div>';
            }
            $restricted_message = get_option('cr_restricted_message', cr_get_default_restricted_message());
            $show_excerpt = get_option('cr_show_excerpt', 0);
            ?>
            <div class="wrap">
                <h1>Content Restriction Settings</h1>
                <form method="POST">
                    <h2>Custom Restricted Message</h2>
                    <textarea name="cr_restricted_message" rows="10" style="width: 100%;"><?php echo esc_textarea($restricted_message); ?></textarea>
                    <h2>Display Excerpt</h2>
                    <label><input type="checkbox" name="cr_show_excerpt" value="1" <?php checked($show_excerpt, 1); ?>> Show excerpt</label>
                    <p><input type="submit" class="button-primary" value="Save Changes"></p>
                </form>
            </div>
            <?php
        },
        'dashicons-lock'
    );
});

add_filter('the_content', function ($content) {
    if (get_post_type() === 'event' || !is_single() || !in_the_loop() || !is_main_query()) return $content;

    $post_id = get_the_ID();
    $restricted_roles = get_post_meta($post_id, '_cr_restricted_roles', true);
    if (!is_user_logged_in() || array_intersect(wp_get_current_user()->roles, (array) $restricted_roles)) {
        $excerpt = get_option('cr_show_excerpt', 0) ? '<p>' . wp_trim_words(strip_shortcodes($content), 30, '...') . '</p>' : '';
        return $excerpt . get_option('cr_restricted_message', cr_get_default_restricted_message());
    }
    return $content;
});

function cr_get_default_restricted_message() {
    return '<div style="background: #167a871a; padding: 2rem; border-radius: 12px; text-align: center; font-family: Arial; max-width: 600px; margin: 2rem auto;">
                <h2 style="color: #167a87; margin-bottom: 1rem;">🔒 Restricted Content</h2>
                <p style="color: #444;">This content is restricted for your user type. Please contact the administrator for access.</p>
            </div>';
}

add_filter('manage_post_posts_columns', function ($columns) {
    $columns['cr_restricted_roles'] = __('Restricted Roles');
    return $columns;
});

add_action('manage_post_posts_custom_column', function ($column, $post_id) {
    if ($column === 'cr_restricted_roles') {
        $roles = get_post_meta($post_id, '_cr_restricted_roles', true);
        echo $roles ? esc_html(implode(', ', $roles)) : __('None');
    }
}, 10, 2);

add_action('quick_edit_custom_box', function ($column, $post_type) {
    if ($column === 'cr_restricted_roles') {
        $roles = wp_roles()->roles;
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <label><span class="title"><?php esc_html_e('Restrict Types'); ?></span></label>
                <select multiple name="cr_restricted_roles[]">
                    <?php foreach ($roles as $role => $details): ?>
                        <option value="<?php echo esc_attr($role); ?>"><?php echo esc_html($details['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </fieldset>
        <?php
    }
}, 10, 2);

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'edit.php') {
        ?>
        <script>
            jQuery(function ($) {
                const $editor = inlineEditPost.edit;
                inlineEditPost.edit = function (id) {
                    $editor.apply(this, arguments);
                    const postId = this.getId(id);
                    const $row = $('#post-' + postId);
                    const roles = $row.find('.column-cr_restricted_roles').text().split(',').map(role => role.trim());
                    $('select[name="cr_restricted_roles[]"]').val(roles);
                };
            });
        </script>
        <?php
    }
});
