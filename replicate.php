<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function replicate_replicate_block_init() {
	register_block_type( __DIR__ . '/build' );
}
add_action( 'init', 'replicate_replicate_block_init' );

// Register the REST API endpoint
add_action('rest_api_init', function () {
	register_rest_route('replicate-api/v1', '/generate', array(
        'methods' => 'POST',
        'callback' => 'handle_replicate_request',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'input' => array(
                'required' => true,
                'type' => 'object',
            ),
            'model' => array(
                'required' => true,
                'type' => 'string',
            ),
        ),
    ));
});

function handle_replicate_request($request) {
    $params = $request->get_params();
    $api_key = get_option('replicate_api_key');

    error_log('Replicate API Key: ' . ($api_key ? 'exists' : 'missing'));

    if (!$api_key) {
        return new WP_Error('api_error', 'Replicate API key not configured');
    }

    // Changed the endpoint URL and updated headers
    $response = wp_remote_post('https://api.replicate.com/v1/models/black-forest-labs/flux-schnell/predictions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,  // Changed from 'Token' to 'Bearer'
            'Content-Type' => 'application/json',
            'Prefer' => 'wait'  // Added this header
        ),
        'body' => json_encode(array(
            'input' => $params['input']
            // Removed the version parameter as it's now part of the URL
        ))
    ));

    if (is_wp_error($response)) {
        return new WP_Error('api_error', $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['id'])) {
        return new WP_Error('api_error', 'No prediction ID received from Replicate: ' . print_r($body, true));
    }

    return rest_ensure_response(array(
        'success' => true,
        'output' => $body['output']
    ));
}

// Optional: Add a settings page to store the API key
function replicate_add_admin_menu() {
    add_options_page(
        'Replicate Settings',
        'Replicate',
        'manage_options',
        'replicate-settings',
        'replicate_settings_page'
    );
}
add_action('admin_menu', 'replicate_add_admin_menu');

function replicate_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('replicate_options');
            do_settings_sections('replicate-settings');
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}

function replicate_register_settings() {
    register_setting('replicate_options', 'replicate_api_key');

    add_settings_section(
        'replicate_settings_section',
        'API Settings',
        'replicate_settings_section_callback',
        'replicate-settings'
    );

    add_settings_field(
        'replicate_api_key',
        'Replicate API Key',
        'replicate_api_key_field_callback',
        'replicate-settings',
        'replicate_settings_section'
    );
}
add_action('admin_init', 'replicate_register_settings');

function replicate_settings_section_callback() {
    echo '<p>Enter your Replicate API settings below:</p>';
}

function replicate_api_key_field_callback() {
    $api_key = get_option('replicate_api_key');
    ?>
    <input type="password"
           name="replicate_api_key"
           value="<?php echo esc_attr($api_key); ?>"
           class="regular-text"
    >
    <?php
}

// Add this to your plugin's PHP file
function replicate_enqueue_editor_assets() {
    wp_enqueue_script(
        'replicate-editor-script',
        plugins_url('build/index.js', __FILE__),
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-components')
    );

    wp_localize_script('replicate-editor-script', 'wpApiSettings', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ));
}
add_action('enqueue_block_editor_assets', 'replicate_enqueue_editor_assets');
