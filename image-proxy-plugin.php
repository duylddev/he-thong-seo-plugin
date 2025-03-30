<?php
/**
 * Plugin Name: Hệ thống SEO
 * Description: Proxy external images through internal URLs and use custom feature images from post metadata.
 * Version: 1.2
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'github_plugin_updater_test_init');
function github_plugin_updater_test_init()
{

    include_once 'updater.php';

    define('WP_GITHUB_FORCE_UPDATE', true);

    if (is_admin()) { // note the use of is_admin() to double check that this is happening in the admin

        $config = array(
            'slug' => plugin_basename(__FILE__),
            'proper_folder_name' => 'he-thong-seo',
            'api_url' => 'https://api.github.com/repos/duylddev/he-thong-seo-plugin',
            'raw_url' => 'https://raw.github.com/duylddev/he-thong-seo-plugin/main',
            'github_url' => 'https://github.com/duylddev/he-thong-seo-plugin',
            'zip_url' => 'https://github.com/duylddev/he-thong-seo-plugin/archive/refs/heads/main.zip',
            'sslverify' => true,
            'requires' => '3.0',
            'tested' => '3.3',
            'readme' => 'README.md',
        );

        new WP_GitHub_Updater($config);

    }

}

function image_proxy_register_meta()
{
    // Get all post types with featured image support
    $post_types = get_post_types_by_support('thumbnail');

    // Register meta for each post type
    foreach ($post_types as $post_type) {
        register_post_meta(
            $post_type,
            'feature_image_url',
            array(
                'show_in_rest' => true,
                'single' => true,
                'type' => 'string',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                }
            )
        );
    }
}
add_action('init', 'image_proxy_register_meta');

// Replace feature image with custom URL from post metadata
function image_proxy_replace_featured_image($html, $post_id, $post_thumbnail_id, $size, $attr)
{
    // Get custom image URL from post metadata
    $feature_image_url = get_post_meta($post_id, 'feature_image_url', true);

    // If custom image URL exists, replace the default HTML with proxy URL
    if (!empty($feature_image_url)) {
        // Get the post slug
        $post = get_post($post_id);
        $slug = $post->post_name;

        // Generate the proxy URL
        $proxy_url = site_url('/featured-image/' . $slug . '.png');

        // Get image alt text
        $alt_text = '';
        if (isset($attr['alt'])) {
            $alt_text = $attr['alt'];
        } elseif ($img_alt = get_post_meta($post_thumbnail_id, '_wp_attachment_image_alt', true)) {
            $alt_text = $img_alt;
        }

        // Get image classes
        $classes = 'wp-post-image';
        if (isset($attr['class'])) {
            $classes .= ' ' . $attr['class'];
        }

        // Create new HTML
        $html = sprintf(
            '<img src="%s" alt="%s" class="%s">',
            esc_url($proxy_url),
            esc_attr($alt_text),
            esc_attr($classes)
        );
    }

    return $html;
}
add_filter('post_thumbnail_html', 'image_proxy_replace_featured_image', 10, 5);

// Handle the image proxy request
function image_proxy_handle_request()
{
    // Check if this is a proxy image request
    if (preg_match('/\/featured-image\/([^\/]+)\.png$/', $_SERVER['REQUEST_URI'], $matches)) {
        $post_slug = $matches[1];

        // Find post with this slug
        $args = array(
            'post_type' => 'any',
            'name' => $post_slug,
            'posts_per_page' => 1
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $real_url = get_post_meta($post_id, 'feature_image_url', true);

            if (!empty($real_url)) {
                // Get the remote image
                $response = wp_remote_get($real_url);

                if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
                    $image_data = wp_remote_retrieve_body($response);
                    $content_type = wp_remote_retrieve_header($response, 'content-type');

                    // Output the image with appropriate headers
                    header('Content-Type: ' . $content_type);
                    echo $image_data;
                    exit;
                }
            }
        }

        // If we get here, we couldn't find or proxy the image
        status_header(404);
        die('Image not found');
    }
}
add_action('parse_request', 'image_proxy_handle_request');

// Add rewrite rule for image proxy URLs
function image_proxy_add_rewrite_rules()
{
    add_rewrite_rule(
        'featured-image/([^/]+)\.png$',
        'index.php?proxy_image=$matches[1]',
        'top'
    );
}
add_action('init', 'image_proxy_add_rewrite_rules');

// Add query vars for the image proxy
function image_proxy_add_query_vars($vars)
{
    $vars[] = 'proxy_image';
    return $vars;
}
add_filter('query_vars', 'image_proxy_add_query_vars');

// Function to flush rewrite rules on plugin activation
function image_proxy_activate()
{
    image_proxy_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'image_proxy_activate');

// Flush rewrite rules on plugin deactivation
function image_proxy_deactivate()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'image_proxy_deactivate');

// Add meta box for feature image URL
function image_proxy_add_meta_boxes()
{
    // Get all post types with featured image support
    $post_types = get_post_types_by_support('thumbnail');

    // Add meta box to all post types with thumbnail support
    foreach ($post_types as $post_type) {
        add_meta_box(
            'image_proxy_meta_box',                 // ID
            'Feature Image Proxy Settings',         // Title
            'image_proxy_meta_box_callback',        // Callback function
            $post_type,                             // Post type
            'side',                                 // Context (side, normal, advanced)
            'default'                               // Priority
        );
    }
}
add_action('add_meta_boxes', 'image_proxy_add_meta_boxes');

// Callback function to display the meta box
function image_proxy_meta_box_callback($post)
{
    // Add a nonce field for security
    wp_nonce_field('image_proxy_save_meta', 'image_proxy_meta_nonce');

    // Get current value if it exists
    $feature_image_url = get_post_meta($post->ID, 'feature_image_url', true);

    // Generate the proxy URL (for display only)
    $slug = $post->post_name;
    if (empty($slug)) {
        $slug = sanitize_title($post->post_title);
    }
    $proxy_url = site_url('/featured-image/' . $slug . '.png');

    // Output the fields
    ?>
    <p>
        <label for="feature_image_url">Real External Image URL:</label>
        <input type="url" id="feature_image_url" name="feature_image_url"
            value="<?php echo esc_attr($feature_image_url); ?>" style="width: 100%;">
        <span class="description">The actual external image URL</span>
    </p>

    <p>
        <strong>Proxy URL:</strong><br>
        <code><?php echo esc_html($proxy_url); ?></code>
        <span class="description">This URL will display in the HTML when the post is viewed</span>
    </p>

    <p class="description">Note: The proxy URL is automatically generated from your post slug.</p>
    <?php
}

// Save the meta box data
function image_proxy_save_meta($post_id)
{
    // Check if our nonce is set and verify it
    if (
        !isset($_POST['image_proxy_meta_nonce']) ||
        !wp_verify_nonce($_POST['image_proxy_meta_nonce'], 'image_proxy_save_meta')
    ) {
        return;
    }

    // If this is an autosave, we don't want to do anything
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions
    if (isset($_POST['post_type']) && 'page' === $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Update the meta field
    if (isset($_POST['feature_image_url'])) {
        update_post_meta($post_id, 'feature_image_url', sanitize_url($_POST['feature_image_url']));
    }
}
add_action('save_post', 'image_proxy_save_meta');
