<?php

global $_MODULE;
$_MODULE = array();

// Module Info
$_MODULE['<{internautenav}prestashop>module_display_name'] = 'Internauten AV';
$_MODULE['<{internautenav}prestashop>module_description'] = 'Vérification MRZ pour les modes de livraison sélectionnés (Carte d\'identité CH, Passeport CH, Passeport UE).';

// Backoffice - Configuration
$_MODULE['<{internautenav}prestashop>backoffice_settings_saved'] = 'Paramètres enregistrés.';
$_MODULE['<{internautenav}prestashop>backoffice_title'] = 'Vérification MRZ par mode de livraison';
$_MODULE['<{internautenav}prestashop>backoffice_description'] = 'Sélectionnez les modes de livraison pour lesquels la vérification MRZ doit être obligatoire au moment du paiement.';
$_MODULE['<{internautenav}prestashop>backoffice_label'] = 'Modes de livraison avec vérification MRZ requise';
$_MODULE['<{internautenav}prestashop>backoffice_help'] = 'Enregistré à l\'aide de id_reference pour une sélection stable lors de la création de nouveaux transporteurs.';
$_MODULE['<{internautenav}prestashop>backoffice_save_button'] = 'Enregistrer';

// Backoffice - Debug Log
$_MODULE['<{internautenav}prestashop>debug_log_title'] = 'Débogage : Journal de vérification (50 dernières entrées)';
$_MODULE['<{internautenav}prestashop>debug_log_empty'] = 'Aucune entrée.';
$_MODULE['<{internautenav}prestashop>debug_log_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_log_col_timestamp'] = 'Horodatage';
$_MODULE['<{internautenav}prestashop>debug_log_col_reference'] = 'Référence';
$_MODULE['<{internautenav}prestashop>debug_log_col_customer'] = 'Client';
$_MODULE['<{internautenav}prestashop>debug_log_col_cart'] = 'id_cart';
$_MODULE['<{internautenav}prestashop>debug_log_col_doc'] = 'Document';
$_MODULE['<{internautenav}prestashop>debug_log_col_result'] = 'Résultat';
$_MODULE['<{internautenav}prestashop>debug_log_col_message'] = 'Message';
$_MODULE['<{internautenav}prestashop>debug_log_result_ok'] = 'OK';
$_MODULE['<{internautenav}prestashop>debug_log_result_fail'] = 'Erreur';

// Backoffice - Persistent Verifications
$_MODULE['<{internautenav}prestashop>debug_persistent_title'] = 'Débogage : Vérifications enregistrées (clients connectés)';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_customer_id'] = 'id_customer';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_name'] = 'Client';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_email'] = 'Email';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_doc'] = 'Document';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_birth'] = 'Date de naissance';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_verified'] = 'verified_at';

// Frontend - Payment Gate
$_MODULE['<{internautenav}prestashop>payment_title'] = 'Vérification d\'âge pour ce mode de livraison';
$_MODULE['<{internautenav}prestashop>payment_intro'] = 'Pour le mode de livraison sélectionné, une vérification d\'âge et d\'identité par MRZ est requise avant le paiement.';
$_MODULE['<{internautenav}prestashop>payment_link'] = 'Commencer la vérification MRZ';
$_MODULE['<{internautenav}prestashop>payment_success'] = 'Vérification MRZ réussie. Le paiement est débloqué.';
$_MODULE['<{internautenav}prestashop>payment_locked'] = 'Les champs de paiement restent verrouillés jusqu\'à la réception côté serveur d\'une vérification réussie.';

// Frontend - Modal
$_MODULE['<{internautenav}prestashop>modal_title'] = 'Saisir les données MRZ';
$_MODULE['<{internautenav}prestashop>modal_close'] = 'Fermer';
$_MODULE['<{internautenav}prestashop>modal_submit'] = 'Vérifier maintenant';
$_MODULE['<{internautenav}prestashop>modal_hint'] = 'Veuillez saisir les lignes exactement comme dans le document, y compris <.';

// Frontend - Form Fields
$_MODULE['<{internautenav}prestashop>form_doc_label'] = 'Type de document';
$_MODULE['<{internautenav}prestashop>form_doc_ch_id'] = 'Carte d\'identité suisse (3 lignes)';
$_MODULE['<{internautenav}prestashop>form_doc_ch_pass'] = 'Passeport suisse (2 lignes)';
$_MODULE['<{internautenav}prestashop>form_doc_eu_pass'] = 'Passeport UE (2 lignes)';
$_MODULE['<{internautenav}prestashop>form_line1_label'] = 'Ligne MRZ 1';
$_MODULE['<{internautenav}prestashop>form_line2_label'] = 'Ligne MRZ 2';
$_MODULE['<{internautenav}prestashop>form_line3_label'] = 'Ligne MRZ 3 (carte d\'identité suisse uniquement)';

// Validation Errors
$_MODULE['<{internautenav}prestashop>error_invalid_carrier'] = 'Transporteur invalide.';
$_MODULE['<{internautenav}prestashop>error_carrier_not_found'] = 'Transporteur non trouvé.';
$_MODULE['<{internautenav}prestashop>error_mrz_invalid'] = 'MRZ invalide.';
$_MODULE['<{internautenav}prestashop>error_address_not_found'] = 'L\'adresse de livraison n\'a pas pu être chargée.';
$_MODULE['<{internautenav}prestashop>error_name_mismatch'] = 'Le nom et le prénom de l\'adresse de livraison ne correspondent pas aux données MRZ.';
$_MODULE['<{internautenav}prestashop>error_age_check'] = 'Commandes réservées aux adultes (18+).';
$_MODULE['<{internautenav}prestashop>error_default'] = 'La vérification n\'est actuellement pas disponible. Veuillez réessayer.';
