<?php

/**
 * Plugin Name: test Naveen
 * Plugin URI: https://yoursite.com
 * Description: A comprehensive mutual fund application form with enhanced SMTP email notifications
 * Version: 1.1.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MFF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MFF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MFF_VERSION', '1.1.0');

class MutualFundForm
{

    public function __construct()
    {
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
    }

    public function init()
    {
        // Create custom post type for storing submissions
        $this->create_submission_post_type();
    }

    public function activate()
    {
        // Set default options
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

        add_option('mff_settings', $default_options);

        // Create submissions table or post type
        $this->create_submission_post_type();
        flush_rewrite_rules();
    }

    public function deactivate()
    {
        flush_rewrite_rules();
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

        // Note: Submissions submenu will be added automatically by the post type
    }

    public function admin_init()
    {
        // Register settings with proper option group
        register_setting(
            'mff_settings_group',
            'mff_settings',
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => array()
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

    public function sanitize_settings($input)
    {
        $sanitized = array();

        // Get current options to preserve existing values
        $current_options = get_option('mff_settings', array());

        // Text fields
        $text_fields = array('smtp_host', 'smtp_username', 'from_email', 'from_name', 'to_email', 'email_subject');
        foreach ($text_fields as $field) {
            if (isset($input[$field])) {
                $sanitized[$field] = sanitize_text_field($input[$field]);
            } else {
                $sanitized[$field] = isset($current_options[$field]) ? $current_options[$field] : '';
            }
        }

        // Textarea fields
        if (isset($input['success_message'])) {
            $sanitized['success_message'] = sanitize_textarea_field($input['success_message']);
        } else {
            $sanitized['success_message'] = isset($current_options['success_message']) ? $current_options['success_message'] : '';
        }

        // Email validation
        if (!empty($sanitized['from_email']) && !is_email($sanitized['from_email'])) {
            add_settings_error('mff_settings', 'invalid_from_email', 'From Email is not valid.');
            $sanitized['from_email'] = get_option('admin_email');
        }

        if (!empty($sanitized['to_email']) && !is_email($sanitized['to_email'])) {
            add_settings_error('mff_settings', 'invalid_to_email', 'Recipient Email is not valid.');
            $sanitized['to_email'] = get_option('admin_email');
        }

        // Numeric fields
        if (isset($input['smtp_port'])) {
            $sanitized['smtp_port'] = intval($input['smtp_port']);
            if ($sanitized['smtp_port'] < 1 || $sanitized['smtp_port'] > 65535) {
                $sanitized['smtp_port'] = 587;
            }
        } else {
            $sanitized['smtp_port'] = isset($current_options['smtp_port']) ? $current_options['smtp_port'] : 587;
        }

        // Password field (don't sanitize, just store)
        if (isset($input['smtp_password'])) {
            $sanitized['smtp_password'] = $input['smtp_password'];
        } else {
            $sanitized['smtp_password'] = isset($current_options['smtp_password']) ? $current_options['smtp_password'] : '';
        }

        // Select fields
        if (isset($input['smtp_encryption'])) {
            $sanitized['smtp_encryption'] = in_array($input['smtp_encryption'], array('tls', 'ssl', '')) ? $input['smtp_encryption'] : 'tls';
        } else {
            $sanitized['smtp_encryption'] = isset($current_options['smtp_encryption']) ? $current_options['smtp_encryption'] : 'tls';
        }

        // Checkbox fields - these need special handling
        $sanitized['enable_smtp'] = isset($input['enable_smtp']) ? '1' : '0';
        $sanitized['store_submissions'] = isset($input['store_submissions']) ? '1' : '0';

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
        $options = get_option('mff_settings', array());
        $field = $args['field'];
        $type = $args['type'];
        $value = isset($options[$field]) ? $options[$field] : '';

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
            $options = get_option('mff_settings', array());

            // Check if SMTP is enabled but not configured
            if (!empty($options['enable_smtp']) && empty($options['smtp_host'])) {
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
        $options = get_option('mff_settings', array());

        // Check if settings were just updated
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            add_settings_error('mff_settings', 'settings_updated', 'Settings saved successfully!', 'success');
        }
?>
        <div class="wrap">
            <h1><i class="dashicons dashicons-email-alt"></i> Mutual Fund Form Settings</h1>

            <div class="notice notice-info">
                <p><strong>Shortcode:</strong> Use <code>[mutual_fund_form]</code> to display the form on any page or post.</p>
                <p><strong>Form Submissions:</strong> <a href="<?php echo admin_url('edit.php?post_type=mff_submission'); ?>">View all submissions</a></p>
            </div>

            <?php
            // Display connection status
            if (!empty($options['enable_smtp']) && !empty($options['smtp_host'])) {
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

            // Display any settings errors
            settings_errors('mff_settings');
            ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('mff_settings_group');
                do_settings_sections('mff_settings');
                // Change this line - use the correct ID
                submit_button('Save Settings', 'primary', 'submit', true, array('id' => 'mff-save-settings'));
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

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Re-enable the save button after page load (in case of browser back button)
                $('#mff-save-settings').prop('disabled', false).val('Save Settings');
            });
        </script>
<?php
    }
}

// Initialize the plugin
new MutualFundForm();
