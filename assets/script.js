// assets/script.js - Enhanced WordPress Compatible Version

jQuery(document).ready(function($) {
    'use strict';
    
    // Prevent conflicts with other plugins/themes
    const MutualFundForm = {
        init: function() {
            this.bindEvents();
            this.initializeForm();
            this.setupValidation();
        },
        
        bindEvents: function() {
            // Form submission
            $(document).on('submit', '#mutual-fund-form', this.handleSubmit.bind(this));
            
            // Field change handlers
            $(document).on('change', '#f14', this.toggleSpouseField);
            $(document).on('change', '#f23', this.toggleGuardianField);
            $(document).on('change', 'input[name="f29"]', this.toggleNomineeAddress);
            $(document).on('change', 'input[name="f45"]', this.toggleTaxFields);
            
            // Real-time validation
            $(document).on('blur', '.mff-container .form-control', this.handleFieldBlur);
            $(document).on('input', '.mff-container .form-control', this.handleFieldInput);
            $(document).on('focus', '.mff-container .form-control', this.handleFieldFocus);
        },
        
        initializeForm: function() {
            this.toggleSpouseField();
            this.toggleGuardianField();
            this.toggleNomineeAddress();
            this.toggleTaxFields();
            this.setupPlaceholders();
        },
        
        setupPlaceholders: function() {
            // Add better placeholders for better UX
            $('#f8').attr('placeholder', '10-digit mobile number');
            $('#f6').attr('placeholder', 'your.email@example.com');
            $('#f9').attr('placeholder', 'ABCDE1234F');
            $('#f25').attr('placeholder', 'ABCDE1234F');
            $('#f34').attr('placeholder', 'Account number');
            $('#f35').attr('placeholder', 'IFSC0001234');
        },
        
        setupValidation: function() {
            // PAN validation
            $(document).on('input', '#f9, #f25', function() {
                const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
                const value = $(this).val().toUpperCase();
                $(this).val(value);
                
                if (value.length === 10 && !panRegex.test(value)) {
                    $(this).addClass('error');
                } else if (value.length === 10) {
                    $(this).removeClass('error');
                }
            });
            
            // Phone number formatting
            $(document).on('input', '#f8', function() {
                let value = $(this).val().replace(/\D/g, '');
                if (value.length > 10) {
                    value = value.substr(0, 10);
                }
                $(this).val(value);
            });
            
            // IFSC validation
            $(document).on('input', '#f35', function() {
                const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
                const value = $(this).val().toUpperCase();
                $(this).val(value);
                
                if (value.length === 11 && !ifscRegex.test(value)) {
                    $(this).addClass('error');
                } else if (value.length === 11) {
                    $(this).removeClass('error');
                }
            });
        },
        
        toggleSpouseField: function() {
            const maritalStatus = $('#f14').val();
            const spouseField = $('#spouse-field');
            
            if (maritalStatus === 'Married') {
                spouseField.slideDown(300);
                $('#f15').prop('required', true);
            } else {
                spouseField.slideUp(300);
                $('#f15').prop('required', false).val('');
            }
        },
        
        toggleGuardianField: function() {
            const nomineeType = $('#f23').val();
            const guardianField = $('#guardian-field');
            
            if (nomineeType === 'MINOR') {
                guardianField.slideDown(300);
                $('#f24').prop('required', true);
            } else {
                guardianField.slideUp(300);
                $('#f24').prop('required', false).val('');
            }
        },
        
        toggleNomineeAddress: function() {
            const nomineeAddress = $('input[name="f29"]:checked').val();
            const nomineeAddressFields = $('#nominee-address-fields');
            
            if (nomineeAddress === 'Different') {
                nomineeAddressFields.slideDown(300);
                $('input[name^="f30_"]').prop('required', true);
            } else {
                nomineeAddressFields.slideUp(300);
                $('input[name^="f30_"]').prop('required', false).val('');
            }
        },
        
        toggleTaxFields: function() {
            const taxResidency = $('input[name="f45"]:checked').val();
            const taxFields = $('#tax-residency-fields');
            
            if (taxResidency === 'YES') {
                taxFields.slideDown(300);
                $('#f46, #f47').prop('required', true);
            } else {
                taxFields.slideUp(300);
                $('#f46, #f47').prop('required', false).val('');
            }
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            const form = $('#mutual-fund-form');
            const submitBtn = $('#submit-btn');
            const spinner = $('#loading-spinner');
            const messagesDiv = $('#mff-messages');
            
            // Clear previous messages
            messagesDiv.hide().empty();
            
            // Validate form
            if (!this.validateForm()) {
                return false;
            }
            
            // Disable submit button and show spinner
            submitBtn.prop('disabled', true).find('i').removeClass('fa-send').addClass('fa-spinner fa-spin');
            spinner.show();
            
            // Prepare form data
            const formData = form.serialize() + '&action=submit_mutual_fund_form';
            
            // Submit via AJAX
            $.ajax({
                url: mff_ajax.ajax_url,
                type: 'POST',
                data: formData,
                timeout: 30000,
                success: function(response) {
                    if (response.success) {
                        MutualFundForm.showMessage('success', response.data);
                        form[0].reset();
                        MutualFundForm.initializeForm();
                        // Scroll to top
                        $('html, body').animate({
                            scrollTop: $('.mff-container').offset().top - 20
                        }, 500);
                    } else {
                        let errorMsg = 'Please correct the following errors:<br>';
                        if (Array.isArray(response.data)) {
                            errorMsg += response.data.map(error => '• ' + error).join('<br>');
                        } else {
                            errorMsg += response.data;
                        }
                        MutualFundForm.showMessage('error', errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    let errorMessage = 'An error occurred while submitting the form.';
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMessage = xhr.responseJSON.data;
                    }
                    MutualFundForm.showMessage('error', errorMessage);
                },
                complete: function() {
                    // Re-enable submit button and hide spinner
                    submitBtn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-send');
                    spinner.hide();
                }
            });
        },
        
        validateForm: function() {
            let isValid = true;
            const requiredFields = $('.mff-container [required]:visible');
            const errors = [];
            
            // Clear previous error styling
            $('.mff-container .form-control').removeClass('error');
            
            // Check required fields
            requiredFields.each(function() {
                const field = $(this);
                const value = field.val() ? field.val().trim() : '';
                const fieldName = field.prev('label').text() || field.attr('placeholder') || 'Field';
                
                if (!value) {
                    field.addClass('error');
                    errors.push(fieldName.replace('*', '') + ' is required');
                    isValid = false;
                }
            });
            
            // Specific field validations
            const validations = [
                { field: '#f6', validator: this.isValidEmail, message: 'Please enter a valid email address' },
                { field: '#f8', validator: this.isValidPhone, message: 'Please enter a valid 10-digit mobile number' },
                { field: '#f9', validator: this.isValidPAN, message: 'Please enter a valid PAN number (e.g., ABCDE1234F)' },
                { field: '#f25', validator: this.isValidPAN, message: 'Please enter a valid nominee PAN number' },
                { field: '#f35', validator: this.isValidIFSC, message: 'Please enter a valid IFSC code' }
            ];
            
            validations.forEach(function(validation) {
                const field = $(validation.field);
                const value = field.val();
                
                if (value && !validation.validator(value)) {
                    field.addClass('error');
                    errors.push(validation.message);
                    isValid = false;
                }
            });
            
            // Age validation (nominee must be valid age)
            const nomineeDOB = $('#f27').val();
            if (nomineeDOB) {
                const age = this.calculateAge(nomineeDOB);
                const nomineeType = $('#f23').val();
                
                if (nomineeType === 'MINOR' && age >= 18) {
                    $('#f27').addClass('error');
                    errors.push('Nominee marked as minor but age is 18 or above');
                    isValid = false;
                } else if (nomineeType === 'MAJOR' && age < 18) {
                    $('#f27').addClass('error');
                    errors.push('Nominee marked as major but age is below 18');
                    isValid = false;
                }
            }
            
            if (!isValid && errors.length > 0) {
                this.showMessage('error', errors.slice(0, 5).join('<br>'));
            }
            
            return isValid;
        },
        
        isValidEmail: function(email) {
            const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
            return emailRegex.test(email);
        },
        
        isValidPhone: function(phone) {
            const cleanPhone = phone.replace(/\D/g, '');
            return cleanPhone.length === 10 && /^[6-9]\d{9}$/.test(cleanPhone);
        },
        
        isValidPAN: function(pan) {
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            return panRegex.test(pan.toUpperCase());
        },
        
        isValidIFSC: function(ifsc) {
            if (!ifsc || ifsc.length !== 11) return true; // Optional field
            const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
            return ifscRegex.test(ifsc.toUpperCase());
        },
        
        calculateAge: function(birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
            
            return age;
        },
        
        showMessage: function(type, message) {
            const messagesDiv = $('#mff-messages');
            const messageClass = type === 'success' ? 'success-message' : 'error-message';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
            
            const messageHtml = `
                <div class="${messageClass}">
                    <i class="fa ${icon}" style="margin-right: 8px;"></i>
                    ${message}
                </div>
            `;
            
            messagesDiv.html(messageHtml).show();
            
            // Scroll to message with smooth animation
            $('html, body').animate({
                scrollTop: messagesDiv.offset().top - 100
            }, 500);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    messagesDiv.fadeOut(500);
                }, 5000);
            }
        },
        
        handleFieldBlur: function() {
            const field = $(this);
            if (field.prop('required') && !field.val().trim()) {
                field.addClass('error');
            } else {
                field.removeClass('error');
            }
            
            // Specific field validations on blur
            if (field.attr('id') === 'f6' && field.val() && !MutualFundForm.isValidEmail(field.val())) {
                field.addClass('error');
            }
            if (field.attr('id') === 'f8' && field.val() && !MutualFundForm.isValidPhone(field.val())) {
                field.addClass('error');
            }
        },
        
        handleFieldInput: function() {
            $(this).removeClass('error');
        },
        
        handleFieldFocus: function() {
            // Clear any existing error messages when user starts typing
            $(this).removeClass('error');
        }
    };
    
    // Initialize the form
    MutualFundForm.init();
    
    // Expose for external access if needed
    window.MutualFundForm = MutualFundForm;
});