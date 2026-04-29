<?php

global $_MODULE;
$_MODULE = array();

// Module Info
$_MODULE['<{internautenav}prestashop>module_display_name'] = 'Internauten AV';
$_MODULE['<{internautenav}prestashop>module_description'] = 'MRZ-Verifikation fuer ausgewaehlte Versandarten (CH ID, CH Pass, EU Pass).';

// Backoffice - Configuration
$_MODULE['<{internautenav}prestashop>backoffice_settings_saved'] = 'Einstellungen gespeichert.';
$_MODULE['<{internautenav}prestashop>backoffice_title'] = 'MRZ-Verifikation nach Versandart';
$_MODULE['<{internautenav}prestashop>backoffice_description'] = 'Waehlen Sie die Versandarten aus, fuer die die MRZ-Pruefung im Checkout erzwungen werden soll.';
$_MODULE['<{internautenav}prestashop>backoffice_label'] = 'Versandarten mit MRZ-Pflicht';
$_MODULE['<{internautenav}prestashop>backoffice_help'] = 'Es wird mit id_reference gespeichert, damit die Auswahl bei Carrier-Neuanlage stabil bleibt.';
$_MODULE['<{internautenav}prestashop>backoffice_save_button'] = 'Speichern';

// Backoffice - Debug Log
$_MODULE['<{internautenav}prestashop>debug_log_title'] = 'Debug: Verifikations-Log (letzte 200 Eintraege)';
$_MODULE['<{internautenav}prestashop>debug_log_empty'] = 'Keine Eintraege.';
$_MODULE['<{internautenav}prestashop>debug_log_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_log_col_timestamp'] = 'Zeitpunkt';
$_MODULE['<{internautenav}prestashop>debug_log_col_reference'] = 'Referenz';
$_MODULE['<{internautenav}prestashop>debug_log_col_customer'] = 'Kunde';
$_MODULE['<{internautenav}prestashop>debug_log_col_cart'] = 'id_cart';
$_MODULE['<{internautenav}prestashop>debug_log_col_doc'] = 'Dokument';
$_MODULE['<{internautenav}prestashop>debug_log_col_result'] = 'Ergebnis';
$_MODULE['<{internautenav}prestashop>debug_log_col_message'] = 'Meldung';
$_MODULE['<{internautenav}prestashop>debug_log_result_ok'] = 'OK';
$_MODULE['<{internautenav}prestashop>debug_log_result_fail'] = 'Fehler';

// Backoffice - Persistent Verifications
$_MODULE['<{internautenav}prestashop>debug_persistent_title'] = 'Debug: Gespeicherte Verifikationen (eingeloggte Kunden)';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_id'] = 'ID';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_customer_id'] = 'id_customer';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_name'] = 'Kunde';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_email'] = 'E-Mail';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_doc'] = 'Dokument';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_birth'] = 'Geburtsdatum';
$_MODULE['<{internautenav}prestashop>debug_persistent_col_verified'] = 'verified_at';

// Frontend - Payment Gate
$_MODULE['<{internautenav}prestashop>payment_title'] = 'Alterspruefung fuer diese Versandart';
$_MODULE['<{internautenav}prestashop>payment_intro'] = 'Fuer die gewaehlte Versandart ist vor der Zahlung eine Alters- und Identitaetspruefung erforderlich.';
$_MODULE['<{internautenav}prestashop>payment_link'] = 'MRZ-Pruefung jetzt starten';
$_MODULE['<{internautenav}prestashop>payment_success'] = 'MRZ-Pruefung erfolgreich abgeschlossen. Die Zahlung ist freigeschaltet.';
$_MODULE['<{internautenav}prestashop>payment_locked'] = 'Solange die erfolgreiche Pruefung nicht serverseitig vorliegt, bleiben die Zahlungsfelder gesperrt.';

// Frontend - Modal
$_MODULE['<{internautenav}prestashop>modal_title'] = 'MRZ-Daten eingeben';
$_MODULE['<{internautenav}prestashop>modal_close'] = 'Schliessen';
$_MODULE['<{internautenav}prestashop>modal_submit'] = 'Jetzt pruefen';
$_MODULE['<{internautenav}prestashop>modal_hint'] = 'Bitte Zeilen exakt wie im Dokument inklusive < eingeben.';

// Frontend - Form Fields
$_MODULE['<{internautenav}prestashop>form_doc_label'] = 'Dokumenttyp';
$_MODULE['<{internautenav}prestashop>form_doc_ch_id'] = 'Schweizer ID (3 Zeilen)';
$_MODULE['<{internautenav}prestashop>form_doc_ch_pass'] = 'Schweizer Pass (2 Zeilen)';
$_MODULE['<{internautenav}prestashop>form_doc_eu_pass'] = 'EU Pass (2 Zeilen)';
$_MODULE['<{internautenav}prestashop>form_line1_label'] = 'MRZ Zeile 1';
$_MODULE['<{internautenav}prestashop>form_line2_label'] = 'MRZ Zeile 2';
$_MODULE['<{internautenav}prestashop>form_line3_label'] = 'MRZ Zeile 3 (nur CH ID)';

// Validation Errors
$_MODULE['<{internautenav}prestashop>error_invalid_carrier'] = 'Ungueltiger Carrier.';
$_MODULE['<{internautenav}prestashop>error_carrier_not_found'] = 'Carrier nicht gefunden.';
$_MODULE['<{internautenav}prestashop>error_mrz_invalid'] = 'MRZ ungueltig.';
$_MODULE['<{internautenav}prestashop>error_address_not_found'] = 'Lieferadresse konnte nicht geladen werden.';
$_MODULE['<{internautenav}prestashop>error_name_mismatch'] = 'Name und Vorname der Lieferadresse stimmen nicht mit der MRZ ueberein.';
$_MODULE['<{internautenav}prestashop>error_age_check'] = 'Bestellung nur fuer volljaehrige Personen (18+).';
$_MODULE['<{internautenav}prestashop>error_default'] = 'Die Pruefung ist momentan nicht verfuegbar. Bitte erneut versuchen.';
