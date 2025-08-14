<?php
// templates/form-template.php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="mff-container">
    <div class="mff-form-wrapper">
        <h2 class="text-center mb-4">Mutual Fund Application Form</h2>

        <div id="mff-messages" style="display: none;"></div>

        <form id="mutual-fund-form" method="POST" novalidate>
            <?php wp_nonce_field('mff_nonce', 'nonce'); ?>

            <!-- Personal Information -->
            <div class="section-header">Personal Information</div>

            <div class="form-group required">
                <label class="control-label" for="f1">NAME</label>
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-user"></i></span>
                    <input type="text" class="form-control" id="f1" name="f1" required>
                </div>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f8">MOBILE NUMBER</label>
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-phone"></i></span>
                    <input type="tel" class="form-control" id="f8" name="f8" placeholder="xxx-xxx-xxxx" required>
                </div>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f6">EMAIL</label>
                <input type="email" class="form-control" id="f6" name="f6" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f9">PAN NUMBER</label>
                <input type="text" class="form-control" id="f9" name="f9" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f10">DATE OF BIRTH</label>
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                    <input type="date" class="form-control" id="f10" name="f10" required>
                </div>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f20">PLACE OF BIRTH</label>
                <input type="text" class="form-control" id="f20" name="f20" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f11">FATHER'S NAME</label>
                <input type="text" class="form-control" id="f11" name="f11" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f12">MOTHER'S NAME</label>
                <input type="text" class="form-control" id="f12" name="f12" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f14">MARITAL STATUS</label>
                <select class="form-control" id="f14" name="f14" required>
                    <option value="">- Select -</option>
                    <option value="Married">Married</option>
                    <option value="UnMarried">Un Married</option>
                    <option value="Divorced">Divorced</option>
                </select>
            </div>

            <div class="form-group" id="spouse-field" style="display: none;">
                <label class="control-label" for="f15">SPOUSE NAME</label>
                <input type="text" class="form-control" id="f15" name="f15">
            </div>

            <div class="form-group required">
                <label class="control-label" for="f16">RESIDENTIAL STATUS</label>
                <select class="form-control" id="f16" name="f16" required>
                    <option value="">- Select -</option>
                    <option value="Resident">Resident</option>
                    <option value="NRI">Non Resident (NRI)</option>
                </select>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f17">GENDER</label>
                <select class="form-control" id="f17" name="f17" required>
                    <option value="">- Select -</option>
                    <option value="MALE">MALE</option>
                    <option value="FEMALE">FEMALE</option>
                </select>
            </div>

            <!-- Address Information -->
            <div class="section-header">Address Information</div>

            <div class="form-group required">
                <label class="control-label">RESIDENTIAL ADDRESS</label>
                <input type="text" class="form-control" name="f21_addressLine1" placeholder="Address Line 1" required style="margin-bottom: 10px;">
                <input type="text" class="form-control" name="f21_city" placeholder="City" required style="margin-bottom: 10px;">
                <input type="text" class="form-control" name="f21_state" placeholder="State" required style="margin-bottom: 10px;">
                <input type="text" class="form-control" name="f21_postalCode" placeholder="Postal Code" required>
            </div>

            <!-- Professional Information -->
            <div class="section-header">Professional Information</div>

            <div class="form-group required">
                <label class="control-label" for="f18">OCCUPATION</label>
                <input type="text" class="form-control" id="f18" name="f18" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f19">GROSS ANNUAL INCOME</label>
                <input type="text" class="form-control" id="f19" name="f19" required>
            </div>

            <!-- Nominee Information -->
            <div class="section-header">Nominee Information</div>

            <div class="form-group required">
                <label class="control-label" for="f7">NOMINEE NAME</label>
                <input type="text" class="form-control" id="f7" name="f7" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f22">RELATIONSHIP WITH NOMINEE</label>
                <input type="text" class="form-control" id="f22" name="f22" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f23">NOMINEE TYPE</label>
                <select class="form-control" id="f23" name="f23" required>
                    <option value="">- Select -</option>
                    <option value="MINOR">MINOR</option>
                    <option value="MAJOR">MAJOR</option>
                </select>
            </div>

            <div class="form-group" id="guardian-field" style="display: none;">
                <label class="control-label" for="f24">GUARDIAN NAME</label>
                <input type="text" class="form-control" id="f24" name="f24">
            </div>

            <div class="form-group required">
                <label class="control-label" for="f25">NOMINEE PAN NO</label>
                <input type="text" class="form-control" id="f25" name="f25" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f27">NOMINEE DATE OF BIRTH</label>
                <input type="date" class="form-control" id="f27" name="f27" required>
            </div>

            <div class="form-group required">
                <label class="control-label">NOMINEE ADDRESS</label>
                <div class="radio">
                    <label><input type="radio" name="f29" value="As Above"> As Above</label>
                </div>
                <div class="radio">
                    <label><input type="radio" name="f29" value="Different"> Different</label>
                </div>
            </div>

            <div id="nominee-address-fields" style="display: none;">
                <div class="form-group">
                    <label class="control-label">NOMINEE ADDRESS DETAILS</label>
                    <input type="text" class="form-control" name="f30_addressLine1" placeholder="Address Line 1" style="margin-bottom: 10px;">
                    <input type="text" class="form-control" name="f30_city" placeholder="City" style="margin-bottom: 10px;">
                    <input type="text" class="form-control" name="f30_state" placeholder="State" style="margin-bottom: 10px;">
                    <input type="text" class="form-control" name="f30_postalCode" placeholder="Postal Code">
                </div>
            </div>

            <!-- Bank Information -->
            <div class="section-header">Bank Information (Optional)</div>

            <div class="form-group">
                <label class="control-label" for="f31">Bank Name</label>
                <input type="text" class="form-control" id="f31" name="f31">
            </div>

            <div class="form-group">
                <label class="control-label" for="f32">Account Type</label>
                <input type="text" class="form-control" id="f32" name="f32">
            </div>

            <div class="form-group">
                <label class="control-label" for="f34">Account Number</label>
                <input type="text" class="form-control" id="f34" name="f34">
            </div>

            <div class="form-group">
                <label class="control-label" for="f35">IFSC Code</label>
                <input type="text" class="form-control" id="f35" name="f35">
            </div>

            <!-- Additional Information -->
            <div class="section-header">Additional Information</div>

            <div class="form-group">
                <label class="control-label" for="f37">APPLICANT INCOME VALUE</label>
                <select class="form-control" id="f37" name="f37">
                    <option value="">- Select -</option>
                    <option value="BELOW 1 LAKH">BELOW 1 LAKH</option>
                    <option value="1 <= 5 Lacs">1 <= 5 Lacs</option>
                    <option value="5 <= 10 Lacs">5 <= 10 Lacs</option>
                    <option value="10 <= 25 Lacs">10 <= 25 Lacs</option>
                    <option value="25 <= 1 Crore">25 <= 1 Crore</option>
                    <option value="Above 1 Crore">Above 1 Crore</option>
                </select>
            </div>

            <!-- PEP Section -->
            <div class="section-header">PEP (Politically Exposed Person)</div>

            <div class="form-group required">
                <label class="control-label" for="f41">OCCUPATION</label>
                <input type="text" class="form-control" id="f41" name="f41" required>
            </div>

            <div class="form-group required">
                <label class="control-label" for="f42">SOURCE OF WEALTH</label>
                <input type="text" class="form-control" id="f42" name="f42" required>
            </div>

            <!-- FATCA Section -->
            <div class="section-header">Foreign Account Tax Compliance Act (FATCA)</div>

            <div class="form-group">
                <label class="control-label" for="f44">COUNTRY OF BIRTH</label>
                <input type="text" class="form-control" id="f44" name="f44">
            </div>

            <div class="form-group">
                <label class="control-label">TAX RESIDENCY OTHER THAN INDIA?</label>
                <div class="radio">
                    <label><input type="radio" name="f45" value="YES"> YES</label>
                </div>
                <div class="radio">
                    <label><input type="radio" name="f45" value="NO"> NO</label>
                </div>
            </div>

            <div id="tax-residency-fields" style="display: none;">
                <div class="form-group">
                    <label class="control-label" for="f46">COUNTRY OF TAX RESIDENCY</label>
                    <input type="text" class="form-control" id="f46" name="f46">
                </div>

                <div class="form-group">
                    <label class="control-label" for="f47">TAX PAYER IDENTIFICATION NO</label>
                    <input type="text" class="form-control" id="f47" name="f47">
                </div>
            </div>

            <!-- Declaration -->
            <div class="form-group">
                <div class="well">
                    <strong>Declaration:</strong> Tax Regulations require us to collect information about each investor's tax residency. If you are a US citizen or resident, please indicate United States in the "Country of Tax Residency" along with your US Tax Identification Number. Foreign Account Tax Compliance provisions commonly known as FATCA are contained in the US Hire Act, 2010.
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-group text-center">
                <button type="submit" class="btn btn-primary btn-lg" id="submit-btn">
                    <i class="fa fa-send"></i> Submit Application
                </button>
                <!-- FIXED: Better loading spinner that gets properly hidden -->
                <div class="loading-spinner" id="loading-spinner" style="display: none; margin-top: 10px;">
                    <i class="fa fa-spinner fa-spin"></i> Processing...
                </div>
            </div>
        </form>
    </div>
</div>