<?php

/**
 * Plugin Name: Mutual Fund Data Sheet Collector
 * Plugin URI: https://iamnaveenoff.in
 * Description: A comprehensive mutual fund application form with enhanced SMTP email notifications
 * Version: 1.0.0
 * Author: Naveen Kumar M
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MFF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MFF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MFF_VERSION', '1.2.0');
define('MFF_DB_VERSION', '1.0');

class MutualFundForm
{
    private $settings_table_name;

    public function __construct()
    {
        global $wpdb;
        $this->settings_table_name = $wpdb->prefix . 'mff_settings';

        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('mutual_fund_form', array($this, 'display_form'));

        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));

        // AJAX hooks for form submission
        add_action('wp_ajax_submit_mutual_fund_form', array($this, 'handle_form_submission'));
        add_action('wp_ajax_nopriv_submit_mutual_fund_form', array($this, 'handle_form_submission'));

        // AJAX hook for test email
        add_action('wp_ajax_mff_test_email', array($this, 'handle_test_email'));

        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Check if database needs updating
        add_action('plugins_loaded', array($this, 'check_database_version'));
    }

    public function init()
    {
        // Create custom post type for storing submissions
        $this->create_submission_post_type();
    }

    public function activate()
    {
        // Create settings table
        $this->create_settings_table();

        // Set default options in both WordPress options and custom table
        $this->set_default_settings();

        // Create submissions table or post type
        $this->create_submission_post_type();
        flush_rewrite_rules();

        // Store current database version
        update_option('mff_db_version', MFF_DB_VERSION);
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    public function check_database_version()
    {
        $installed_ver = get_option('mff_db_version');

        if ($installed_ver != MFF_DB_VERSION) {
            $this->create_settings_table();
            update_option('mff_db_version', MFF_DB_VERSION);
        }
    }

    /**
     * Create custom settings table
     */
    private function create_settings_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->settings_table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Log table creation for debugging
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->settings_table_name}'") == $this->settings_table_name) {
            error_log('MFF: Settings table created successfully');
        } else {
            error_log('MFF: Failed to create settings table');
        }
    }

    /**
     * Set default settings in both options and custom table
     */
    private function set_default_settings()
    {
        $default_options = array(
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'to_email' => get_option('admin_email'),
            'email_subject' => 'New Mutual Fund Application - {name}',
            'success_message' => 'Thank you! Your form has been submitted successfully. We will contact you soon.',
            'enable_smtp' => '0',
            'store_submissions' => '1'
        );

        // Save to WordPress options (fallback)
        add_option('mff_settings', $default_options);

        // Save to custom table
        foreach ($default_options as $key => $value) {
            $this->save_setting($key, $value, false); // false = don't overwrite existing
        }
    }

    /**
     * Save setting to custom table
     */
    private function save_setting($key, $value, $overwrite = true)
    {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$this->settings_table_name} WHERE setting_key = %s",
            $key
        ));

        if ($existing === null) {
            // Insert new setting
            $result = $wpdb->insert(
                $this->settings_table_name,
                array(
                    'setting_key' => $key,
                    'setting_value' => $value
                ),
                array('%s', '%s')
            );

            if ($result === false) {
                error_log('MFF: Failed to insert setting: ' . $key . ' - ' . $wpdb->last_error);
            }
        } elseif ($overwrite) {
            // Update existing setting
            $result = $wpdb->update(
                $this->settings_table_name,
                array('setting_value' => $value),
                array('setting_key' => $key),
                array('%s'),
                array('%s')
            );

            if ($result === false) {
                error_log('MFF: Failed to update setting: ' . $key . ' - ' . $wpdb->last_error);
            }
        }
    }

    /**
     * Get setting from custom table
     */
    private function get_setting($key, $default = '')
    {
        global $wpdb;

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$this->settings_table_name} WHERE setting_key = %s",
            $key
        ));

        return $value !== null ? $value : $default;
    }

    /**
     * Get all settings from custom table with fallback
     */
    public function get_all_settings()
    {
        global $wpdb;

        // First try to get from custom table
        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM {$this->settings_table_name}", ARRAY_A);

        $settings = array();

        // If custom table has data, use it
        if (!empty($results)) {
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        // Get WordPress options as fallback and merge
        $wp_options = get_option('mff_settings', array());

        // Merge with defaults to ensure all keys exist
        $default_options = array(
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_email' => get_option('admin_email'),
            'from_name' => get_bloginfo('name'),
            'to_email' => get_option('admin_email'),
            'email_subject' => 'New Mutual Fund Application - {name}',
            'success_message' => 'Thank you! Your form has been submitted successfully. We will contact you soon.',
            'enable_smtp' => '0',
            'store_submissions' => '1'
        );

        // Priority: custom table -> wp options -> defaults
        return array_merge($default_options, $wp_options, $settings);
    }

    /**
     * Save all settings to custom table
     */
    public function save_all_settings($settings)
    {
        foreach ($settings as $key => $value) {
            $this->save_setting($key, $value, true);
        }

        // Also save to WordPress options as backup
        update_option('mff_settings', $settings);
    }

    private function create_submission_post_type()
    {
        register_post_type('mff_submission', array(
            'labels' => array(
                'name' => 'Form Submissions',
                'singular_name' => 'Form Submission',
                'menu_name' => 'Submissions',
                'all_items' => 'All Submissions',
                'view_item' => 'View Submission',
                'search_items' => 'Search Submissions',
                'not_found' => 'No submissions found',
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'mutual-fund-form', // This will show under our custom menu
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => false,
            ),
            'map_meta_cap' => true,
            'supports' => array('title', 'custom-fields'),
        ));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_style('bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css');
        wp_enqueue_style('font-awesome', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css');
        wp_enqueue_style('mff-style', MFF_PLUGIN_URL . 'assets/style.css', array(), MFF_VERSION);

        wp_enqueue_script('mff-script', MFF_PLUGIN_URL . 'assets/script.js', array('jquery'), MFF_VERSION, true);
        wp_localize_script('mff-script', 'mff_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mff_nonce')
        ));
    }

    public function display_form($atts)
    {
        ob_start();
        include MFF_PLUGIN_PATH . 'templates/form-template.php';
        return ob_get_clean();
    }

    public function handle_form_submission()
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mff_nonce')) {
            wp_die('Security check failed');
        }

        require_once MFF_PLUGIN_PATH . 'includes/form-handler.php';
        $handler = new MFF_FormHandler();

        $result = $handler->process_form($_POST);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['errors']);
        }
    }

    public function handle_test_email()
    {
        // Verify nonce and capability
        if (!wp_verify_nonce($_POST['nonce'], 'mff_test_email_nonce') || !current_user_can('manage_options')) {
            wp_die('Security check failed');
        }

        require_once MFF_PLUGIN_PATH . 'includes/form-handler.php';
        $handler = new MFF_FormHandler();

        $result = $handler->test_email_settings();

        if ($result) {
            wp_send_json_success('Test email sent successfully!');
        } else {
            wp_send_json_error('Failed to send test email. Please check your SMTP settings.');
        }
    }

    public function add_admin_menu()
    {
        // Add main menu
        add_menu_page(
            'Mutual Fund Form',           // Page title
            'Mutual Fund Form',           // Menu title
            'manage_options',             // Capability
            'mutual-fund-form',           // Menu slug
            array($this, 'admin_page'),   // Function
            'dashicons-email-alt',        // Icon
            30                            // Position
        );

        // Add settings submenu
        add_submenu_page(
            'mutual-fund-form',           // Parent slug
            'Settings',                   // Page title
            'Settings',                   // Menu title
            'manage_options',             // Capability
            'mutual-fund-form',           // Menu slug (same as parent for default page)
            array($this, 'admin_page')    // Function
        );
    }

    public function admin_init()
    {
        // FIXED: Register settings with proper callback that handles custom table saving
        register_setting(
            'mff_settings_group',
            'mff_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_and_save_settings'),
            )
        );

        // SMTP Settings Section
        add_settings_section(
            'mff_smtp_section',
            'SMTP Settings',
            array($this, 'smtp_section_callback'),
            'mff_settings'
        );

        // Email Settings Section
        add_settings_section(
            'mff_email_section',
            'Email Settings',
            array($this, 'email_section_callback'),
            'mff_settings'
        );

        // General Settings Section
        add_settings_section(
            'mff_general_section',
            'General Settings',
            array($this, 'general_section_callback'),
            'mff_settings'
        );

        // Add settings fields
        $this->add_settings_fields();

        // Enqueue admin scripts only on our pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook)
    {
        // Only load on our plugin pages
        if (strpos($hook, 'mutual-fund-form') === false) {
            return;
        }

        wp_enqueue_script('mff-admin-script', MFF_PLUGIN_URL . 'assets/admin.js', array('jquery'), MFF_VERSION, true);
        wp_localize_script('mff-admin-script', 'mff_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'test_email_nonce' => wp_create_nonce('mff_test_email_nonce')
        ));

        wp_enqueue_style('mff-admin-style', MFF_PLUGIN_URL . 'assets/admin.css', array(), MFF_VERSION);
    }

    // FIXED: Completely rewritten sanitization callback to properly handle form data
    public function sanitize_and_save_settings($input)
    {
        // Get current options as fallback
        $current_options = $this->get_all_settings();
        $sanitized = array();

        // Define all possible settings keys with their types
        $setting_definitions = array(
            'smtp_host' => 'text',
            'smtp_port' => 'number',
            'smtp_username' => 'text',
            'smtp_password' => 'password',
            'smtp_encryption' => 'select',
            'from_email' => 'email',
            'from_name' => 'text',
            'to_email' => 'email',
            'email_subject' => 'text',
            'success_message' => 'textarea',
            'enable_smtp' => 'checkbox',
            'store_submissions' => 'checkbox'
        );

        // Process each setting
        foreach ($setting_definitions as $key => $type) {
            switch ($type) {
                case 'checkbox':
                    // Checkboxes are only present in $_POST if checked
                    $sanitized[$key] = isset($input[$key]) ? '1' : '0';
                    break;

                case 'number':
                    if (isset($input[$key])) {
                        $value = intval($input[$key]);
                        if ($key === 'smtp_port') {
                            $sanitized[$key] = ($value >= 1 && $value <= 65535) ? $value : 587;
                        } else {
                            $sanitized[$key] = $value;
                        }
                    } else {
                        $sanitized[$key] = isset($current_options[$key]) ? $current_options[$key] : '587';
                    }
                    break;

                case 'email':
                    if (isset($input[$key])) {
                        $email = sanitize_email($input[$key]);
                        if (!empty($email) && !is_email($email)) {
                            add_settings_error('mff_settings', 'invalid_' . $key, ucwords(str_replace('_', ' ', $key)) . ' is not valid.');
                            $sanitized[$key] = isset($current_options[$key]) ? $current_options[$key] : get_option('admin_email');
                        } else {
                            $sanitized[$key] = $email;
                        }
                    } else {
                        $sanitized[$key] = isset($current_options[$key]) ? $current_options[$key] : get_option('admin_email');
                    }
                    break;

                case 'select':
                    if (isset($input[$key])) {
                        if ($key === 'smtp_encryption') {
                            $sanitized[$key] = in_array($input[$key], array('tls', 'ssl', '')) ? $input[$key] : 'tls';
                        } else {
                            $sanitized[$key] = sanitize_text_field($input[$key]);
                        }
                    } else {
                        $sanitized[$key] = isset($current_options[$key]) ? $current_options[$key] : 'tls';
                    }
                    break;

                case 'textarea':
                    if (isset($input[$key])) {
                        $sanitized[$key] = sanitize_textarea_field($input[$key]);
                    } else {
                        $sanitized[$key] = isset($current_options[$key]) ? $current_options[$key] : '';
                    }
                    break;

                case 'password':
                    // Handle passwords carefully - don't sanitize, just store
                    if (isset($input[$key])) {
                        $sanitized[$key] = $input[$key];
                    } else {
                        $sanitized[$key] = isset($current_options[$key]) ? $current_options[$key] : '';
                    }
                    break;

                default: // 'text'
                    if (isset($input[$key])) {
                        $sanitized[$key] = sanitize_text_field($input[$key]);
                    } else {
                        $sanitized[$key] = isset($current_options[$key]) ? $current_options[$key] : '';
                    }
                    break;
            }
        }

        // Save to custom table and WordPress options
        $this->save_all_settings($sanitized);

        // Add success message
        add_settings_error('mff_settings', 'settings_updated', 'Settings saved successfully!', 'updated');

        // Log for debugging
        error_log('MFF: Settings saved - Keys: ' . implode(', ', array_keys($sanitized)));

        // Return the sanitized array for WordPress
        return $sanitized;
    }

    private function add_settings_fields()
    {
        $fields = array(
            // SMTP Section
            'enable_smtp' => array('Enable SMTP', 'checkbox', 'smtp'),
            'smtp_host' => array('SMTP Host', 'text', 'smtp'),
            'smtp_port' => array('SMTP Port', 'number', 'smtp'),
            'smtp_username' => array('SMTP Username', 'text', 'smtp'),
            'smtp_password' => array('SMTP Password', 'password', 'smtp'),
            'smtp_encryption' => array('Encryption', 'select', 'smtp'),

            // Email Section
            'from_email' => array('From Email', 'email', 'email'),
            'from_name' => array('From Name', 'text', 'email'),
            'to_email' => array('Recipient Email', 'email', 'email'),
            'email_subject' => array('Email Subject', 'text', 'email'),

            // General Section
            'success_message' => array('Success Message', 'textarea', 'general'),
            'store_submissions' => array('Store Submissions', 'checkbox', 'general')
        );

        foreach ($fields as $field_id => $field_data) {
            add_settings_field(
                $field_id,
                $field_data[0],
                array($this, 'field_callback'),
                'mff_settings',
                'mff_' . $field_data[2] . '_section',
                array('field' => $field_id, 'type' => $field_data[1])
            );
        }
    }

    public function smtp_section_callback()
    {
        echo '<p>Configure SMTP settings for reliable email delivery. Popular SMTP services include Gmail, Outlook, SendGrid, etc.</p>';
        echo '<div class="mff-test-email-section">';
        echo '<button type="button" id="mff-test-email" class="button button-secondary">Send Test Email</button>';
        echo '<span id="mff-test-result" style="margin-left: 10px;"></span>';
        echo '</div>';
    }

    public function email_section_callback()
    {
        echo '<p>Configure email settings and templates. Use <code>{name}</code> in subject to include applicant name.</p>';
    }

    public function general_section_callback()
    {
        echo '<p>General plugin settings and configurations.</p>';
    }

    public function field_callback($args)
    {
        // Get options from custom table
        $options = $this->get_all_settings();
        $field = $args['field'];
        $type = $args['type'];
        $value = isset($options[$field]) ? $options[$field] : '';

        // For checkboxes, ensure we're checking the right value
        if ($type === 'checkbox') {
            $value = ($value === '1' || $value === 1 || $value === true) ? 1 : 0;
        }

        switch ($type) {
            case 'checkbox':
                echo '<input type="checkbox" name="mff_settings[' . $field . ']" value="1" ' . checked(1, $value, false) . ' />';
                break;

            case 'select':
                if ($field === 'smtp_encryption') {
                    echo '<select name="mff_settings[' . $field . ']">';
                    echo '<option value="tls"' . selected('tls', $value, false) . '>TLS</option>';
                    echo '<option value="ssl"' . selected('ssl', $value, false) . '>SSL</option>';
                    echo '<option value=""' . selected('', $value, false) . '>None</option>';
                    echo '</select>';
                }
                break;

            case 'textarea':
                echo '<textarea name="mff_settings[' . $field . ']" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;

            case 'password':
                echo '<input type="password" name="mff_settings[' . $field . ']" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
                break;

            case 'number':
                echo '<input type="number" name="mff_settings[' . $field . ']" value="' . esc_attr($value) . '" class="small-text" min="1" max="65535" />';
                break;

            default:
                echo '<input type="' . $type . '" name="mff_settings[' . $field . ']" value="' . esc_attr($value) . '" class="regular-text" />';
        }

        // Add descriptions for specific fields
        $descriptions = array(
            'enable_smtp' => 'Enable this to use custom SMTP server instead of WordPress default mail function',
            'email_subject' => 'Use {name} to include applicant name in subject line',
            'smtp_host' => 'Examples: smtp.gmail.com, smtp.office365.com, smtp.sendgrid.net',
            'smtp_port' => 'Common ports: 587 (TLS), 465 (SSL), 25 (No encryption)',
            'smtp_username' => 'Usually your email address for most SMTP services',
            'smtp_password' => 'Your email password or app-specific password',
            'smtp_encryption' => 'TLS is recommended for most modern SMTP servers',
            'from_email' => 'Email address that will appear as sender',
            'from_name' => 'Name that will appear as sender',
            'to_email' => 'Email address where form submissions will be sent',
            'success_message' => 'Message displayed to user after successful form submission',
            'store_submissions' => 'Store form submissions in WordPress database for backup'
        );

        if (isset($descriptions[$field])) {
            echo '<p class="description">' . $descriptions[$field] . '</p>';
        }
    }

    public function admin_notices()
    {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'mutual-fund-form') !== false) {
            // Get options from custom table
            $options = $this->get_all_settings();

            // Check if custom table exists
            global $wpdb;
            if ($wpdb->get_var("SHOW TABLES LIKE '{$this->settings_table_name}'") != $this->settings_table_name) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Error:</strong> Settings table not found. Please deactivate and reactivate the plugin.</p>';
                echo '</div>';
            }

            // Check if SMTP is enabled but not configured
            if (!empty($options['enable_smtp']) && $options['enable_smtp'] === '1' && empty($options['smtp_host'])) {
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Warning:</strong> SMTP is enabled but no SMTP host is configured. Emails may not be sent properly.</p>';
                echo '</div>';
            }

            // Check if recipient email is set
            if (empty($options['to_email'])) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Error:</strong> No recipient email address is set. Form submissions will not be delivered.</p>';
                echo '</div>';
            }
        }
    }

    public function admin_page()
    {
        // Get options from custom table
        $options = $this->get_all_settings();

        // Handle settings errors and success messages
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            add_settings_error('mff_settings', 'settings_updated', 'Settings saved successfully!', 'success');
        }
?>
        <div class="wrap">
            <h1><i class="dashicons dashicons-email-alt"></i> Mutual Fund Form Settings</h1>

            <div class="notice notice-info">
                <p><strong>Shortcode:</strong> Use <code>[mutual_fund_form]</code> to display the form on any page or post.</p>
                <p><strong>Form Submissions:</strong> <a href="<?php echo admin_url('edit.php?post_type=mff_submission'); ?>">View all submissions</a></p>
                <p><strong>Database:</strong> Settings stored in custom table: <code><?php echo $this->settings_table_name; ?></code></p>
            </div>

            <?php
            // Display settings errors/success messages
            settings_errors('mff_settings');

            // Display connection status
            if (!empty($options['enable_smtp']) && $options['enable_smtp'] === '1' && !empty($options['smtp_host'])) {
                echo '<div class="mff-connection-status">';
                echo '<h3>Current SMTP Configuration</h3>';
                echo '<table class="form-table">';
                echo '<tr><td><strong>SMTP Host:</strong></td><td>' . esc_html($options['smtp_host']) . ':' . esc_html($options['smtp_port']) . '</td></tr>';
                echo '<tr><td><strong>Username:</strong></td><td>' . esc_html($options['smtp_username']) . '</td></tr>';
                echo '<tr><td><strong>Encryption:</strong></td><td>' . esc_html(strtoupper($options['smtp_encryption'] ?: 'None')) . '</td></tr>';
                echo '<tr><td><strong>Status:</strong></td><td><span class="mff-status-indicator">Click "Send Test Email" to verify</span></td></tr>';
                echo '</table>';
                echo '</div>';
            }
            ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('mff_settings_group');
                do_settings_sections('mff_settings');
                submit_button('Save Settings', 'primary', 'mff-save-settings', true);
                ?>
            </form>

            <div class="mff-help-section">
                <h3>Common SMTP Settings</h3>
                <div class="mff-smtp-examples">
                    <div class="mff-example">
                        <h4>Gmail</h4>
                        <p>Host: smtp.gmail.com<br>Port: 587<br>Encryption: TLS<br>Note: Use app-specific password</p>
                    </div>
                    <div class="mff-example">
                        <h4>Outlook/Hotmail</h4>
                        <p>Host: smtp.office365.com<br>Port: 587<br>Encryption: TLS</p>
                    </div>
                    <div class="mff-example">
                        <h4>Yahoo</h4>
                        <p>Host: smtp.mail.yahoo.com<br>Port: 587<br>Encryption: TLS</p>
                    </div>
                </div>
            </div>
        </div>
<?php
    }
}

// Initialize the plugin
new MutualFundForm();
