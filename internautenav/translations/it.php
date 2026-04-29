<?php

global $_MODULE;
$_MODULE = array();

// Module Info
$_MODULE['<{internautenav}prestashop>module_display_name'] = 'Internauten AV';
$_MODULE['<{internautenav}prestashop>module_description'] = 'Verifica MRZ per i metodi di spedizione selezionati (Carta d\'identità CH, Passaporto CH, Passaporto UE).';

// Backoffice - Configuration
$_MODULE['<{internautenav}prestashop>backoffice_settings_saved'] = 'Impostazioni salvate.';
$_MODULE['<{internautenav}prestashop>backoffice_title'] = 'Verifica MRZ per metodo di spedizione';
$_MODULE['<{internautenav}prestashop>backoffice_description'] = 'Selezionare i metodi di spedizione per i quali la verifica MRZ deve essere obbligatoria al momento del pagamento.';
$_MODULE['<{internautenav}prestashop>backoffice_label'] = 'Metodi di spedizione con verifica MRZ richiesta';
$_MODULE['<{internautenav}prestashop>backoffice_help'] = 'Salvato utilizzando id_reference per una selezione stabile durante la creazione di nuovi corrieri.';
$_MODULE['<{internautenav}prestashop>backoffice_save_button'] = 'Salva';

// Backoffice - Debug Log
$_MODULE['<{internautenav}prestashop>debug_log_title'] = 'Debug: Log di verifica (ultimi 200 elementi)';
$_MODULE['<{internautenav}prestashop>debug_log_empty'] = 'Nessun elemento.';
$_MODULE['<{internautenav}prestashop>debug_log_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_log_col_timestamp'] = 'Data/Ora';
$_MODULE['<{internautenav}prestashop>debug_log_col_reference'] = 'Riferimento';
$_MODULE['<{internautenav}prestashop>debug_log_col_customer'] = 'Cliente';
$_MODULE['<{internautenav}prestashop>debug_log_col_cart'] = 'id_cart';
$_MODULE['<{internautenav}prestashop>debug_log_col_doc'] = 'Documento';
$_MODULE['<{internautenav}prestashop>debug_log_col_result'] = 'Risultato';
$_MODULE['<{internautenav}prestashop>debug_log_col_message'] = 'Messaggio';
$_MODULE['<{internautenav}prestashop>debug_log_result_ok'] = 'OK';
$_MODULE['<{internautenav}prestashop>debug_log_result_fail'] = 'Errore';

// Backoffice - Persistent Verifications
$_MODULE['<{internautenav}prestashop>debug_persistent_title'] = 'Debug: Verifiche archiviate (clienti collegati)';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_customer_id'] = 'id_customer';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_name'] = 'Cliente';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_email'] = 'Email';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_doc'] = 'Documento';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_birth'] = 'Data di nascita';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_verified'] = 'verified_at';

// Frontend - Payment Gate
$_MODULE['<{internautenav}prestashop>payment_title'] = 'Verifica dell\'età per questo metodo di spedizione';
$_MODULE['<{internautenav}prestashop>payment_intro'] = 'Per il metodo di spedizione selezionato è richiesta la verifica dell\'età e dell\'identità tramite MRZ prima del pagamento.';
$_MODULE['<{internautenav}prestashop>payment_link'] = 'Avvia verifica MRZ';
$_MODULE['<{internautenav}prestashop>payment_success'] = 'Verifica MRZ completata con successo. Il pagamento è sbloccato.';
$_MODULE['<{internautenav}prestashop>payment_locked'] = 'I campi di pagamento rimangono bloccati fino alla ricezione lato server di una verifica riuscita.';

// Frontend - Modal
$_MODULE['<{internautenav}prestashop>modal_title'] = 'Inserisci dati MRZ';
$_MODULE['<{internautenav}prestashop>modal_close'] = 'Chiudi';
$_MODULE['<{internautenav}prestashop>modal_submit'] = 'Verifica ora';
$_MODULE['<{internautenav}prestashop>modal_hint'] = 'Inserisci le righe esattamente come nel documento, incluso <.';

// Frontend - Form Fields
$_MODULE['<{internautenav}prestashop>form_doc_label'] = 'Tipo di documento';
$_MODULE['<{internautenav}prestashop>form_doc_ch_id'] = 'Carta d\'identità svizzera (3 righe)';
$_MODULE['<{internautenav}prestashop>form_doc_ch_pass'] = 'Passaporto svizzero (2 righe)';
$_MODULE['<{internautenav}prestashop>form_doc_eu_pass'] = 'Passaporto UE (2 righe)';
$_MODULE['<{internautenav}prestashop>form_line1_label'] = 'Riga MRZ 1';
$_MODULE['<{internautenav}prestashop>form_line2_label'] = 'Riga MRZ 2';
$_MODULE['<{internautenav}prestashop>form_line3_label'] = 'Riga MRZ 3 (solo carta d\'identità svizzera)';

// Validation Errors
$_MODULE['<{internautenav}prestashop>error_invalid_carrier'] = 'Corriere non valido.';
$_MODULE['<{internautenav}prestashop>error_carrier_not_found'] = 'Corriere non trovato.';
$_MODULE['<{internautenav}prestashop>error_mrz_invalid'] = 'MRZ non valido.';
$_MODULE['<{internautenav}prestashop>error_address_not_found'] = 'Impossibile caricare l\'indirizzo di spedizione.';
$_MODULE['<{internautenav}prestashop>error_name_mismatch'] = 'Il nome e cognome dell\'indirizzo di spedizione non corrisponde ai dati MRZ.';
$_MODULE['<{internautenav}prestashop>error_age_check'] = 'Ordini solo per maggiorenni (18+).';
$_MODULE['<{internautenav}prestashop>error_default'] = 'La verifica non è al momento disponibile. Riprovare.';
