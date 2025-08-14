// assets/admin.js - Fixed version
jQuery(document).ready(function($) {
    'use strict';
    
    let isSubmitting = false;
    
    // Test email functionality
    $('#mff-test-email').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const resultSpan = $('#mff-test-result');
        
        // Show loading state
        button.prop('disabled', true).text('Sending...');
        resultSpan.html('<span style="color: #666;">Testing email configuration...</span>');
        
        // Send AJAX request
        $.ajax({
            url: mff_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mff_test_email',
                nonce: mff_admin_ajax.test_email_nonce
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span class="success" style="color: #00a32a; font-weight: bold;">✓ ' + response.data + '</span>');
                    
                    // Update status indicator if present
                    $('.mff-status-indicator').removeClass('warning error').addClass('success')
                        .html('✓ SMTP Working').css({
                            'background': '#d4edda',
                            'border-color': '#c3e6cb',
                            'color': '#155724'
                        });
                } else {
                    resultSpan.html('<span class="error" style="color: #d63638; font-weight: bold;">✗ ' + response.data + '</span>');
                    
                    // Update status indicator if present
                    $('.mff-status-indicator').removeClass('warning success').addClass('error')
                        .html('✗ SMTP Error').css({
                            'background': '#f8d7da',
                            'border-color': '#f5c6cb',
                            'color': '#721c24'
                        });
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Request failed. Please try again.';
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Check your SMTP settings.';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                }
                
                resultSpan.html('<span class="error" style="color: #d63638; font-weight: bold;">✗ ' + errorMessage + '</span>');
                
                // Update status indicator if present
                $('.mff-status-indicator').removeClass('warning success').addClass('error')
                    .html('✗ SMTP Error').css({
                        'background': '#f8d7da',
                        'border-color': '#f5c6cb',
                        'color': '#721c24'
                    });
            },
            complete: function() {
                // Reset button
                button.prop('disabled', false).text('Send Test Email');
            }
        });
    });
    
    // SMTP settings toggle
    $('input[name="mff_settings[enable_smtp]"]').on('change', function() {
        const smtpFields = $(this).closest('table').find('tr').slice(1, 6); // SMTP related fields
        
        if ($(this).is(':checked')) {
            smtpFields.fadeIn(300);
            $('#mff-test-email').fadeIn(300);
        } else {
            smtpFields.fadeOut(300);
            $('#mff-test-email').fadeOut(300);
            $('#mff-test-result').empty();
        }
    }).trigger('change');
    
    // Real-time validation for email fields
    $('input[type="email"]').on('blur', function() {
        const email = $(this).val();
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        
        // Remove any existing error messages
        $(this).next('.email-error').remove();
        $(this).css('border-color', '');
        
        if (email && !emailRegex.test(email)) {
            $(this).css('border-color', '#d63638');
            $(this).after('<div class="email-error">Please enter a valid email address</div>');
        }
    });
    
    // Port validation
    $('input[name="mff_settings[smtp_port]"]').on('input', function() {
        const port = parseInt($(this).val());
        
        if (port < 1 || port > 65535) {
            $(this).css('border-color', '#d63638');
        } else {
            $(this).css('border-color', '');
        }
    });
    
    // Add show/hide password toggle
    const passwordField = $('input[name="mff_settings[smtp_password]"]');
    if (passwordField.length && !passwordField.next('.mff-toggle-password').length) {
        passwordField.after('<button type="button" class="button button-small mff-toggle-password">Show</button>');
    }
    
    $(document).on('click', '.mff-toggle-password', function(e) {
        e.preventDefault();
        const passwordField = $(this).prev('input');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            $(this).text('Hide');
        } else {
            passwordField.attr('type', 'password');
            $(this).text('Show');
        }
    });
    
    // Auto-suggest SMTP settings based on email domain
    $('input[name="mff_settings[from_email]"]').on('blur', function() {
        const email = $(this).val();
        if (!email || email.indexOf('@') === -1) return;
        
        const domain = email.split('@')[1];
        if (!domain) return;
        
        const smtpSettings = {
            'gmail.com': {
                host: 'smtp.gmail.com',
                port: 587,
                encryption: 'tls'
            },
            'outlook.com': {
                host: 'smtp.office365.com',
                port: 587,
                encryption: 'tls'
            },
            'hotmail.com': {
                host: 'smtp.office365.com',
                port: 587,
                encryption: 'tls'
            },
            'yahoo.com': {
                host: 'smtp.mail.yahoo.com',
                port: 587,
                encryption: 'tls'
            },
            'live.com': {
                host: 'smtp.office365.com',
                port: 587,
                encryption: 'tls'
            }
        };
        
        if (smtpSettings[domain]) {
            const settings = smtpSettings[domain];
            let suggested = false;
            
            // Only suggest if fields are empty
            const hostField = $('input[name="mff_settings[smtp_host]"]');
            if (!hostField.val()) {
                hostField.val(settings.host).addClass('auto-suggested');
                suggested = true;
            }
            
            const portField = $('input[name="mff_settings[smtp_port]"]');
            if (!portField.val()) {
                portField.val(settings.port).addClass('auto-suggested');
                suggested = true;
            }
            
            const encryptionField = $('select[name="mff_settings[smtp_encryption]"]');
            if (!encryptionField.val()) {
                encryptionField.val(settings.encryption).addClass('auto-suggested');
                suggested = true;
            }
            
            // Show a helpful notice
            if (suggested && $('.mff-auto-suggest-notice').length === 0) {
                $(this).after('<div class="mff-auto-suggest-notice">📧 Auto-suggested SMTP settings for ' + domain + '</div>');
                setTimeout(function() {
                    $('.mff-auto-suggest-notice').fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    });
    
    // FIXED: Form submission handling
    $('#mff-save-settings').on('click', function(e) {
        // Don't prevent default - let WordPress handle the form submission
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        
        // Clear any previous error styles
        $('.form-table input, .form-table select, .form-table textarea').css('border-color', '');
        $('.email-error').remove();
        
        const smtpEnabled = $('input[name="mff_settings[enable_smtp]"]').is(':checked');
        let hasErrors = false;
        const errors = [];
        
        // Validate only if SMTP is enabled
        if (smtpEnabled) {
            const requiredFields = [
                { name: 'mff_settings[smtp_host]', label: 'SMTP Host' },
                { name: 'mff_settings[smtp_username]', label: 'SMTP Username' },
                { name: 'mff_settings[smtp_password]', label: 'SMTP Password' }
            ];
            
            requiredFields.forEach(function(fieldInfo) {
                const field = $('input[name="' + fieldInfo.name + '"]');
                if (!field.val().trim()) {
                    field.css('border-color', '#d63638');
                    errors.push(fieldInfo.label + ' is required when SMTP is enabled');
                    hasErrors = true;
                }
            });
            
            // Validate port
            const portField = $('input[name="mff_settings[smtp_port]"]');
            const port = parseInt(portField.val());
            if (isNaN(port) || port < 1 || port > 65535) {
                portField.css('border-color', '#d63638');
                errors.push('SMTP Port must be between 1 and 65535');
                hasErrors = true;
            }
        }
        
        // Validate email fields
        const emailFields = [
            { name: 'mff_settings[from_email]', label: 'From Email' },
            { name: 'mff_settings[to_email]', label: 'Recipient Email' }
        ];
        
        const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
        
        emailFields.forEach(function(fieldInfo) {
            const field = $('input[name="' + fieldInfo.name + '"]');
            const email = field.val();
            
            if (email && !emailRegex.test(email)) {
                field.css('border-color', '#d63638');
                field.after('<div class="email-error">' + fieldInfo.label + ' is not valid</div>');
                hasErrors = true;
                errors.push(fieldInfo.label + ' is not valid');
            }
        });
        
        // If there are errors, prevent submission
        if (hasErrors) {
            e.preventDefault();
            
            // Show error notice
            const errorHtml = '<div class="notice notice-error is-dismissible"><p><strong>Please fix the following errors:</strong><br>' + 
                errors.slice(0, 5).map(error => '• ' + error).join('<br>') + '</p></div>';
            
            // Remove any existing notices
            $('.notice').remove();
            $('.wrap h1').after(errorHtml);
            
            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 500);
            
            return false;
        }
        
        // If no errors, show saving state
        isSubmitting = true;
        $(this).prop('disabled', true).val('Saving Settings...');
        
        // Let the form submit naturally - WordPress will handle it
    });
        
    // REMOVED: Form submission handler that was interfering
    // Let WordPress Settings API handle form submission naturally
    
    // Auto-dismiss notices after 5 seconds
    setTimeout(function() {
        $('.notice.is-dismissible:not(.notice-error)').fadeOut();
    }, 5000);
    
    // Handle dismissible notices
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut();
    });
    
    // Clear auto-suggested styling after a delay
    setTimeout(function() {
        $('.auto-suggested').removeClass('auto-suggested');
    }, 3000);
    
    // Debug information for developers
    if (window.console && window.console.log) {
        console.log('Mutual Fund Form Admin JS loaded successfully');
    }
});