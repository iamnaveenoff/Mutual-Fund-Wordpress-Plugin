<?php
// includes/form-handler.php - Enhanced version with custom table support

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MFF_FormHandler
{
    private $options;
    private $errors = array();
    private $form_data = array();
    private $debug_info = array();
    private $smtp_debug = '';
    private $settings_table_name;

    public function __construct()
    {
        global $wpdb;
        $this->settings_table_name = $wpdb->prefix . 'mff_settings';
        
        // Get settings from custom table first, fallback to options
        $this->options = $this->get_settings_from_table();
        
        if (empty($this->options)) {
            $this->options = get_option('mff_settings', array());
        }

        // Set default options if still empty
        if (empty($this->options)) {
            $this->options = array(
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
                'enable_smtp' => '0'
            );
        }
    }
    
    /**
     * Get settings from custom table
     */
    private function get_settings_from_table()
    {
        global $wpdb;
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->settings_table_name}'") != $this->settings_table_name) {
            return array();
        }
        
        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM {$this->settings_table_name}", ARRAY_A);
        
        if (empty($results)) {
            return array();
        }
        
        $settings = array();
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    }

    public function process_form($post_data)
    {
        $this->validate_form($post_data);

        if (empty($this->errors)) {
            $email_result = $this->send_email();

            if ($email_result['success']) {
                // Log successful submission
                $this->log_submission($post_data);

                return array(
                    'success' => true,
                    'message' => $this->options['success_message']
                );
            } else {
                // Include debug info in error response for troubleshooting
                $error_message = 'Failed to send email. Please try again.';
                if (current_user_can('administrator') && !empty($email_result['debug'])) {
                    $error_message .= ' Debug info: ' . $email_result['debug'];
                }

                return array(
                    'success' => false,
                    'errors' => array($error_message)
                );
            }
        }

        return array(
            'success' => false,
            'errors' => $this->errors
        );
    }

    private function validate_form($post_data)
    {
        // Required fields
        $required_fields = array(
            'f1' => 'Name',
            'f8' => 'Mobile Number',
            'f6' => 'Email',
            'f9' => 'PAN Number',
            'f10' => 'Date of Birth',
            'f20' => 'Place of Birth',
            'f11' => "Father's Name",
            'f12' => "Mother's Name",
            'f14' => 'Marital Status',
            'f16' => 'Residential Status',
            'f21_addressLine1' => 'Address Line 1',
            'f21_city' => 'City',
            'f21_state' => 'State',
            'f21_postalCode' => 'Postal Code',
            'f17' => 'Gender',
            'f18' => 'Occupation',
            'f19' => 'Gross Annual Income',
            'f7' => 'Nominee Name',
            'f22' => 'Relationship with Nominee',
            'f23' => 'Nominee Type',
            'f25' => 'Nominee PAN No',
            'f27' => 'Nominee Date of Birth',
            'f29' => 'Nominee Address',
            'f41' => 'Occupation (PEP)',
            'f42' => 'Source of Wealth'
        );

        // Validate required fields
        foreach ($required_fields as $field => $label) {
            $value = isset($post_data[$field]) ? trim($post_data[$field]) : '';

            if ($this->should_skip_field($field, $post_data)) {
                continue;
            }

            if (empty($value)) {
                $this->errors[] = $label . ' is required.';
            } else {
                $this->form_data[$field] = sanitize_text_field($value);
            }
        }

        // Conditional field validation
        if (!empty($post_data['f14']) && $post_data['f14'] === 'Married') {
            if (empty(trim($post_data['f15']))) {
                $this->errors[] = 'Spouse Name is required when married.';
            } else {
                $this->form_data['f15'] = sanitize_text_field($post_data['f15']);
            }
        }

        if (!empty($post_data['f23']) && $post_data['f23'] === 'MINOR') {
            if (empty(trim($post_data['f24']))) {
                $this->errors[] = 'Guardian Name is required for minor nominee.';
            } else {
                $this->form_data['f24'] = sanitize_text_field($post_data['f24']);
            }
        }

        // Email validation
        if (!empty($post_data['f6']) && !is_email($post_data['f6'])) {
            $this->errors[] = 'Please enter a valid email address.';
        }

        // Phone validation
        if (!empty($post_data['f8']) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $post_data['f8'])) {
            $this->errors[] = 'Please enter a valid phone number.';
        }

        // PAN validation
        if (!empty($post_data['f9']) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($post_data['f9']))) {
            $this->errors[] = 'Please enter a valid PAN number (format: ABCDE1234F).';
        }

        // Store all non-empty values
        foreach ($post_data as $key => $value) {
            if (!empty($value) && $key !== 'nonce' && $key !== 'action') {
                $this->form_data[$key] = sanitize_text_field($value);
            }
        }
    }

    private function should_skip_field($field, $post_data)
    {
        switch ($field) {
            case 'f15': // Spouse name
                return empty($post_data['f14']) || $post_data['f14'] !== 'Married';
            case 'f24': // Guardian name
                return empty($post_data['f23']) || $post_data['f23'] !== 'MINOR';
            default:
                return false;
        }
    }

    private function send_email()
    {
        // Validate email settings
        if (empty($this->options['to_email']) || !is_email($this->options['to_email'])) {
            return array(
                'success' => false,
                'debug' => 'Invalid recipient email address'
            );
        }

        $to = $this->options['to_email'];
        $subject = isset($this->form_data['f1']) ?
            str_replace('{name}', $this->form_data['f1'], $this->options['email_subject']) :
            $this->options['email_subject'];
        $message = $this->build_email_message();

        // Set up headers
        $headers = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        // Set from header
        if (!empty($this->options['from_email']) && is_email($this->options['from_email'])) {
            $from_name = !empty($this->options['from_name']) ? $this->options['from_name'] : get_bloginfo('name');
            $headers[] = 'From: ' . $from_name . ' <' . $this->options['from_email'] . '>';
        }

        // Add reply-to header if form data exists
        if (!empty($this->form_data['f6'])) {
            $headers[] = 'Reply-To: ' . $this->form_data['f6'];
        }

        // Configure SMTP if enabled
        if ($this->options['enable_smtp'] == '1' && !empty($this->options['smtp_host'])) {
            add_action('phpmailer_init', array($this, 'configure_smtp'));
        }

        // Enable error logging
        add_action('wp_mail_failed', array($this, 'wp_mail_failed'));

        $sent = wp_mail($to, $subject, $message, $headers);

        // Remove the SMTP configuration after sending
        if ($this->options['enable_smtp'] == '1' && !empty($this->options['smtp_host'])) {
            remove_action('phpmailer_init', array($this, 'configure_smtp'));
        }

        remove_action('wp_mail_failed', array($this, 'wp_mail_failed'));

        $debug_message = '';
        if (!$sent) {
            $debug_message = 'Mail function returned false.';
            if (!empty($this->debug_info)) {
                $debug_message .= ' Errors: ' . implode(' | ', $this->debug_info);
            }
            if (!empty($this->smtp_debug)) {
                $debug_message .= ' SMTP Debug: ' . $this->smtp_debug;
            }
        }

        return array(
            'success' => $sent,
            'debug' => $debug_message
        );
    }

    public function configure_smtp($phpmailer)
    {
        try {
            $phpmailer->isSMTP();
            $phpmailer->Host = trim($this->options['smtp_host']);
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = trim($this->options['smtp_username']);
            $phpmailer->Password = $this->options['smtp_password']; // Don't trim passwords
            $phpmailer->Port = intval($this->options['smtp_port']);

            // Set encryption
            if (!empty($this->options['smtp_encryption'])) {
                if (strtolower($this->options['smtp_encryption']) === 'ssl') {
                    $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif (strtolower($this->options['smtp_encryption']) === 'tls') {
                    $phpmailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }
            }

            // Set From address if available
            if (!empty($this->options['from_email']) && is_email($this->options['from_email'])) {
                $from_name = !empty($this->options['from_name']) ? $this->options['from_name'] : get_bloginfo('name');
                $phpmailer->setFrom($this->options['from_email'], $from_name);
            }

            // Enable debug for administrators or during test
            if (current_user_can('administrator') || defined('MFF_DEBUG_EMAIL')) {
                $phpmailer->SMTPDebug = 2;
                $phpmailer->Debugoutput = function ($str, $level) {
                    $this->smtp_debug .= "Level $level: " . trim($str) . "\n";
                };
            }

            // Additional SMTP options for better compatibility
            $phpmailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Set timeout
            $phpmailer->Timeout = 30;
        } catch (Exception $e) {
            $this->debug_info[] = 'SMTP Configuration Error: ' . $e->getMessage();
            error_log('MFF SMTP Config Error: ' . $e->getMessage());
        }
    }

    public function wp_mail_failed($wp_error)
    {
        $error_message = $wp_error->get_error_message();
        $this->debug_info[] = 'WordPress Mail Error: ' . $error_message;
        error_log('MFF Mail Failed: ' . $error_message);
    }

    private function build_email_message()
    {
        // If no form data (test email), return simple message
        if (empty($this->form_data)) {
            return $this->get_test_email_content();
        }

        $html = '<!DOCTYPE html>
<html>
<head>
   <meta charset="UTF-8">
   <title>Mutual Fund Form Submission</title>
   <style>
       body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
       .container { max-width: 800px; margin: 0 auto; }
       table { border-collapse: collapse; width: 100%; margin: 20px 0; }
       th, td { border: 1px solid #ddd; padding: 12px; text-align: left; vertical-align: top; }
       th { background-color: #f8f9fa; font-weight: bold; width: 30%; }
       .header { background-color: #007bff; color: white; text-align: center; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
       .section { background-color: #e9ecef; font-weight: bold; text-align: center; }
       .info { margin-bottom: 20px; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #007bff; }
       .footer { margin-top: 30px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; font-size: 12px; color: #666; }
   </style>
</head>
<body>
   <div class="container">
       <div class="header">
           <h2>New Mutual Fund Application Received</h2>
       </div>
       
       <div class="info">
           <strong>Submission Details:</strong><br>
           <strong>Date & Time:</strong> ' . current_time('F j, Y \a\t g:i A T') . '<br>
           <strong>IP Address:</strong> ' . $this->get_client_ip() . '<br>
           <strong>Browser:</strong> ' . esc_html($this->get_user_agent()) . '
       </div>
       
       <table>';

        // Field labels mapping
        $field_labels = array(
            // Personal Information
            'f1' => 'Full Name',
            'f8' => 'Mobile Number',
            'f6' => 'Email Address',
            'f9' => 'PAN Number',
            'f10' => 'Date of Birth',
            'f20' => 'Place of Birth',
            'f11' => "Father's Name",
            'f12' => "Mother's Name",
            'f14' => 'Marital Status',
            'f15' => 'Spouse Name',
            'f16' => 'Residential Status',
            'f17' => 'Gender',

            // Address Information
            'f21_addressLine1' => 'Address Line 1',
            'f21_city' => 'City',
            'f21_state' => 'State',
            'f21_postalCode' => 'Postal Code',

            // Professional Information
            'f18' => 'Occupation',
            'f19' => 'Gross Annual Income',
            'f37' => 'Income Range',

            // Nominee Information
            'f7' => 'Nominee Name',
            'f22' => 'Relationship with Nominee',
            'f23' => 'Nominee Type',
            'f24' => 'Guardian Name',
            'f25' => 'Nominee PAN Number',
            'f27' => 'Nominee Date of Birth',
            'f29' => 'Nominee Address Preference',
            'f30_addressLine1' => 'Nominee Address Line 1',
            'f30_city' => 'Nominee City',
            'f30_state' => 'Nominee State',
            'f30_postalCode' => 'Nominee Postal Code',

            // Bank Information
            'f31' => 'Bank Name',
            'f32' => 'Account Type',
            'f34' => 'Account Number',
            'f35' => 'IFSC Code',

            // PEP Information
            'f41' => 'PEP Occupation',
            'f42' => 'Source of Wealth',

            // FATCA Information
            'f44' => 'Country of Birth',
            'f45' => 'Tax Residency Other Than India',
            'f46' => 'Country of Tax Residency',
            'f47' => 'Tax Payer Identification Number'
        );

        // Group fields by sections for better organization
        $sections = array(
            'Personal Information' => array(
                'f1',
                'f8',
                'f6',
                'f9',
                'f10',
                'f20',
                'f11',
                'f12',
                'f14',
                'f15',
                'f16',
                'f17'
            ),
            'Address Information' => array(
                'f21_addressLine1',
                'f21_city',
                'f21_state',
                'f21_postalCode'
            ),
            'Professional Information' => array(
                'f18',
                'f19',
                'f37'
            ),
            'Nominee Information' => array(
                'f7',
                'f22',
                'f23',
                'f24',
                'f25',
                'f27',
                'f29',
                'f30_addressLine1',
                'f30_city',
                'f30_state',
                'f30_postalCode'
            ),
            'Bank Information' => array(
                'f31',
                'f32',
                'f34',
                'f35'
            ),
            'PEP Information' => array(
                'f41',
                'f42'
            ),
            'FATCA Information' => array(
                'f44',
                'f45',
                'f46',
                'f47'
            )
        );

        foreach ($sections as $section_name => $fields) {
            $section_has_data = false;
            $section_html = '';

            foreach ($fields as $field) {
                if (!empty($this->form_data[$field])) {
                    $section_has_data = true;
                    $label = isset($field_labels[$field]) ? $field_labels[$field] : ucwords(str_replace('_', ' ', $field));
                    $value = $this->form_data[$field];

                    // Format specific fields
                    if ($field === 'f10' || $field === 'f27') {
                        $value = date('F j, Y', strtotime($value));
                    }

                    $section_html .= "<tr><th>{$label}</th><td>" . nl2br(esc_html($value)) . "</td></tr>";
                }
            }

            if ($section_has_data) {
                $html .= "<tr><td colspan='2' class='section'>{$section_name}</td></tr>";
                $html .= $section_html;
            }
        }

        $html .= '</table>
       
       <div class="footer">
           <p><strong>Important:</strong> Please review this application carefully and contact the applicant for any clarifications needed.</p>
           <p>This email was generated automatically by the Mutual Fund Application Form on ' . get_bloginfo('name') . '</p>
       </div>
       
   </div>
</body>
</html>';

        return $html;
    }

    private function get_test_email_content()
    {
        return '
       <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
           <h2 style="color: #007bff; text-align: center; margin-bottom: 20px;">SMTP Test Email</h2>
           
           <p><strong>This is a test email from your Mutual Fund Form plugin.</strong></p>
           
           <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
               <tr>
                   <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold; background: #f8f9fa;">SMTP Host:</td>
                   <td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($this->options['smtp_host']) . '</td>
               </tr>
               <tr>
                   <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold; background: #f8f9fa;">Port:</td>
                   <td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($this->options['smtp_port']) . '</td>
               </tr>
               <tr>
                   <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold; background: #f8f9fa;">Encryption:</td>
                   <td style="padding: 8px; border: 1px solid #ddd;">' . esc_html(strtoupper($this->options['smtp_encryption'] ?: 'None')) . '</td>
               </tr>
               <tr>
                   <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold; background: #f8f9fa;">Username:</td>
                   <td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($this->options['smtp_username']) . '</td>
               </tr>
               <tr>
                   <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold; background: #f8f9fa;">From Email:</td>
                   <td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($this->options['from_email']) . '</td>
               </tr>
               <tr>
                   <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold; background: #f8f9fa;">To Email:</td>
                   <td style="padding: 8px; border: 1px solid #ddd;">' . esc_html($this->options['to_email']) . '</td>
               </tr>
           </table>
           
           <p style="margin-top: 20px;"><strong>Sent at:</strong> ' . current_time('Y-m-d H:i:s T') . '</p>
           <p style="margin-top: 10px;"><strong>Website:</strong> ' . get_bloginfo('name') . ' (' . home_url() . ')</p>
           
           <hr style="margin: 20px 0; border: none; border-top: 1px solid #ddd;">
           <p style="font-size: 12px; color: #666;">If you received this email, your SMTP configuration is working correctly!</p>
       </div>';
    }

    private function log_submission($post_data)
    {
        // Only log if storage is enabled
        if (empty($this->options['store_submissions'])) {
            return;
        }

        // Log the submission to WordPress database for record keeping
        $log_entry = array(
            'post_title' => 'Form Submission - ' . (isset($this->form_data['f1']) ? $this->form_data['f1'] : 'Unknown') . ' - ' . current_time('Y-m-d H:i:s'),
            'post_content' => wp_json_encode($this->form_data, JSON_PRETTY_PRINT),
            'post_status' => 'private',
            'post_type' => 'mff_submission',
            'post_author' => 1,
            'meta_input' => array(
                'submission_ip' => $this->get_client_ip(),
                'submission_date' => current_time('Y-m-d H:i:s'),
                'user_agent' => $this->get_user_agent(),
                'form_version' => '1.2.0'
            )
        );

        wp_insert_post($log_entry);
    }

    private function get_client_ip()
    {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
    }

    private function get_user_agent()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 200) : 'Unknown';
    }

    // Enhanced test email functionality
    public function test_email_settings()
    {
        // Temporarily enable debug
        if (!defined('MFF_DEBUG_EMAIL')) {
            define('MFF_DEBUG_EMAIL', true);
        }

        $test_message = $this->get_test_email_content();

        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Set from header
        if (!empty($this->options['from_email']) && is_email($this->options['from_email'])) {
            $from_name = !empty($this->options['from_name']) ? $this->options['from_name'] : get_bloginfo('name');
            $headers[] = 'From: ' . $from_name . ' <' . $this->options['from_email'] . '>';
        }

        // Configure SMTP if enabled
        if ($this->options['enable_smtp'] == '1' && !empty($this->options['smtp_host'])) {
            add_action('phpmailer_init', array($this, 'configure_smtp'));
        }

        // Enable error logging
        add_action('wp_mail_failed', array($this, 'wp_mail_failed'));

        $sent = wp_mail(
            $this->options['to_email'],
            'Test Email - Mutual Fund Form SMTP Settings - ' . current_time('H:i:s'),
            $test_message,
            $headers
        );

        // Remove hooks
        if ($this->options['enable_smtp'] == '1' && !empty($this->options['smtp_host'])) {
            remove_action('phpmailer_init', array($this, 'configure_smtp'));
        }
        remove_action('wp_mail_failed', array($this, 'wp_mail_failed'));

        // Log debug info for administrators
        if (!$sent && current_user_can('administrator')) {
            error_log('MFF Test Email Failed - Debug: ' . print_r(array(
                'smtp_enabled' => $this->options['enable_smtp'],
                'smtp_host' => $this->options['smtp_host'],
                'smtp_port' => $this->options['smtp_port'],
                'smtp_username' => $this->options['smtp_username'],
                'smtp_encryption' => $this->options['smtp_encryption'],
                'from_email' => $this->options['from_email'],
                'to_email' => $this->options['to_email'],
                'errors' => $this->debug_info,
                'smtp_debug' => $this->smtp_debug
            ), true));
        }

        return $sent;
    }
}