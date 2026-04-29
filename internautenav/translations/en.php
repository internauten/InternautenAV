<?php

global $_MODULE;
$_MODULE = array();

// Module Info
$_MODULE['<{internautenav}prestashop>module_display_name'] = 'Internauten AV';
$_MODULE['<{internautenav}prestashop>module_description'] = 'MRZ verification for selected shipping methods (CH ID, CH Pass, EU Pass).';

// Backoffice - Configuration
$_MODULE['<{internautenav}prestashop>backoffice_settings_saved'] = 'Settings saved.';
$_MODULE['<{internautenav}prestashop>backoffice_title'] = 'MRZ Verification by Shipping Method';
$_MODULE['<{internautenav}prestashop>backoffice_description'] = 'Select the shipping methods for which MRZ verification should be enforced at checkout.';
$_MODULE['<{internautenav}prestashop>backoffice_label'] = 'Shipping Methods with MRZ Verification Required';
$_MODULE['<{internautenav}prestashop>backoffice_help'] = 'Stored using id_reference for stable selection when creating new carriers.';
$_MODULE['<{internautenav}prestashop>backoffice_save_button'] = 'Save';

// Backoffice - Debug Log
$_MODULE['<{internautenav}prestashop>debug_log_title'] = 'Debug: Verification Log (last 200 entries)';
$_MODULE['<{internautenav}prestashop>debug_log_empty'] = 'No entries.';
$_MODULE['<{internautenav}prestashop>debug_log_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_log_col_timestamp'] = 'Timestamp';
$_MODULE['<{internautenav}prestashop>debug_log_col_reference'] = 'Reference';
$_MODULE['<{internautenav}prestashop>debug_log_col_customer'] = 'Customer';
$_MODULE['<{internautenav}prestashop>debug_log_col_cart'] = 'id_cart';
$_MODULE['<{internautenav}prestashop>debug_log_col_doc'] = 'Document';
$_MODULE['<{internautenav}prestashop>debug_log_col_result'] = 'Result';
$_MODULE['<{internautenav}prestashop>debug_log_col_message'] = 'Message';
$_MODULE['<{internautenav}prestashop>debug_log_result_ok'] = 'OK';
$_MODULE['<{internautenav}prestashop>debug_log_result_fail'] = 'Error';

// Backoffice - Persistent Verifications
$_MODULE['<{internautenav}prestashop>debug_persistent_title'] = 'Debug: Stored Verifications (logged-in customers)';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_customer_id'] = 'id_customer';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_name'] = 'Customer';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_email'] = 'Email';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_doc'] = 'Document';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_birth'] = 'Birth Date';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_verified'] = 'verified_at';

// Frontend - Payment Gate
$_MODULE['<{internautenav}prestashop>payment_title'] = 'Age Verification for this Shipping Method';
$_MODULE['<{internautenav}prestashop>payment_intro'] = 'For the selected shipping method, age and identity verification via MRZ is required before payment.';
$_MODULE['<{internautenav}prestashop>payment_link'] = 'Start MRZ Verification';
$_MODULE['<{internautenav}prestashop>payment_success'] = 'MRZ verification completed successfully. Payment is unlocked.';
$_MODULE['<{internautenav}prestashop>payment_locked'] = 'Payment fields remain locked until successful verification is received server-side.';

// Frontend - Modal
$_MODULE['<{internautenav}prestashop>modal_title'] = 'Enter MRZ Data';
$_MODULE['<{internautenav}prestashop>modal_close'] = 'Close';
$_MODULE['<{internautenav}prestashop>modal_submit'] = 'Verify Now';
$_MODULE['<{internautenav}prestashop>modal_hint'] = 'Please enter lines exactly as in the document, including <.';

// Frontend - Form Fields
$_MODULE['<{internautenav}prestashop>form_doc_label'] = 'Document Type';
$_MODULE['<{internautenav}prestashop>form_doc_ch_id'] = 'Swiss ID (3 lines)';
$_MODULE['<{internautenav}prestashop>form_doc_ch_pass'] = 'Swiss Passport (2 lines)';
$_MODULE['<{internautenav}prestashop>form_doc_eu_pass'] = 'EU Passport (2 lines)';
$_MODULE['<{internautenav}prestashop>form_line1_label'] = 'MRZ Line 1';
$_MODULE['<{internautenav}prestashop>form_line2_label'] = 'MRZ Line 2';
$_MODULE['<{internautenav}prestashop>form_line3_label'] = 'MRZ Line 3 (CH ID only)';

// Validation Errors
$_MODULE['<{internautenav}prestashop>error_invalid_carrier'] = 'Invalid carrier.';
$_MODULE['<{internautenav}prestashop>error_carrier_not_found'] = 'Carrier not found.';
$_MODULE['<{internautenav}prestashop>error_mrz_invalid'] = 'MRZ invalid.';
$_MODULE['<{internautenav}prestashop>error_address_not_found'] = 'Delivery address could not be loaded.';
$_MODULE['<{internautenav}prestashop>error_name_mismatch'] = 'Name and first name of delivery address do not match the MRZ.';
$_MODULE['<{internautenav}prestashop>error_age_check'] = 'Orders only for adults (18+).';
$_MODULE['<{internautenav}prestashop>error_default'] = 'Verification is currently unavailable. Please try again.';
