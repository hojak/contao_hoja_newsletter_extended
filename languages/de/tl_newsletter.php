<?php

$GLOBALS['TL_LANG']['tl_newsletter']['editmeta'] = array('Newsletter-Einstellungen bearbeiten', 'Die Newsletter-Einstellungen bearbeiten');

$GLOBALS['TL_LANG']['tl_newsletter']['preview'] = array('Vorschau ansehen und verschicken', 'Vorschau ansehen und verschicken, Newsletter versenden.');
$GLOBALS['TL_LANG']['tl_newsletter']['send_preview'] = 'Vorschau als E-Mail verschicken';
$GLOBALS['TL_LANG']['tl_newsletter']['show_sent'] = array('Versandprotokoll ansehen', 'Versandprotokoll dieser Newsletter-Ausgabe ansehen.');


$GLOBALS['TL_LANG']['tl_newsletter']['hoja_template_prefix'] = array('(optional) Prefix für Template-Dateien', 'Beim Rendern des Newsletters wird für alle Templats überprüft, ob eine Version des Templates mit dem genannten Prefix im Namen existiert. Falls ja, wird diese verwendet.');
$GLOBALS['TL_LANG']['tl_newsletter']['hoja_css_file'] = array('CSS-Datei (optional)', 'Diese CSS-Datei kann in den Newsletter(-Header) eingebettet werden, anstatt die Angaben direkt in das mail-template zu schreiben.');
$GLOBALS['TL_LANG']['tl_newsletter']['hoja_piwik_campaign'] = array ('Piwki-Kampagne', 'Allen Links auf die Webseite wird diese Piwik-Kampagne angehängt.');

$GLOBALS['TL_LANG']['tl_newsletter']['hoja_tracking_legend'] = 'Empfänger-Verfolgung';
$GLOBALS['TL_LANG']['tl_newsletter']['hoja_files_legend'] = 'Datei-Einstellungen';

$GLOBALS['TL_LANG']['tl_newsletter']['hoja_status_string'] = "Empfänger: %d, Zurückgewiesen: %d";


$GLOBALS['TL_LANG']['tl_newsletter']['actualize_preview'] = "Vorschau aktualisieren";

$GLOBALS['TL_LANG']['tl_newsletter']['skip_first_line_label'] = "Erste Zeile überspringen?";
$GLOBALS['TL_LANG']['tl_newsletter']['skip_first_line_hint'] = "Soll die erste Zeile des Imports übersprungen werden (weil sie die Spaltenüberschriften enthält)?";

$GLOBALS['TL_LANG']['tl_newsletter']['csv_submit_hint'] =
'<p>Hinweis: Die Spalten der CVS-Datei sollten die folgenden Informationen beinhalten:</p>'
.'<ol>'
.'<li style="list-style: decimal">E-Mail-Adresse (email)</li>'
.'<li style="list-style: decimal">Vorname (first name)</li>'
.'<li style="list-style: decimal">Nachname (last name)</li>'
.'<li style="list-style: decimal">Geschlecht (gender (m or f))</li>'
.'<li style="list-style: decimal">Titel/Anrede (title)</li>'
.'<li style="list-style: decimal">Anredeform (form of address (formal or informal))</li>'
.'<li style="list-style: decimal">Bemerkungen (additional remarks)</li>'
.'</ol>';

$GLOBALS['TL_LANG']['tl_newsletter']['preview_headline'] = 'Vorschau für den Newsletter \'%s\'';
$GLOBALS['TL_LANG']['tl_newsletter']['send_headline'] = 'Newsletter \'%s\' verschicken';
$GLOBALS['TL_LANG']['tl_newsletter']['send_text'] = 'Mit dem Betätigen der folgenden SChaltfläche wird der Newsletter verschickt. Der Versand kann unterbrochen und über dieselbe Schaltfläche später fortgesetzt werden.<br />Von den <b>%d</b> eingetragenen Adressen wurden bisher <b>%d</b> Mails abgeschickt!';

$GLOBALS['TL_LANG']['tl_newsletter']['hoja_preview_sent_message'] = 'Vorschau-Nachricht verschickt an \'%s\'';
$GLOBALS['TL_LANG']['tl_newsletter']['hoja_preview_sent_error'] = 'Fehler beim Versand an \'%s\'';



/*** headlines in sending window **/
$GLOBALS['TL_LANG']['tl_newsletter']['just_sent'] = 'Gerade verschickt';
$GLOBALS['TL_LANG']['tl_newsletter']['step'] = 'Schritt';
$GLOBALS['TL_LANG']['tl_newsletter']['number_sent'] = 'Verschickt';
$GLOBALS['TL_LANG']['tl_newsletter']['number_of_recipients'] = 'Anzahl Empfänger';
$GLOBALS['TL_LANG']['tl_newsletter']['number_failure'] = 'Fehlerhafter Versand';
$GLOBALS['TL_LANG']['tl_newsletter']['headline_finished'] = 'Newsletter komplett verschickt';
$GLOBALS['TL_LANG']['tl_newsletter']['headline_step'] = 'Newsletter wird verschickt';
$GLOBALS['TL_LANG']['tl_newsletter']['message_step'] = 'Bitte lassen Sie dieses Fenster geöffnet, bis der Versand abgeschlossen ist. Sollte der Versand nicht automatisch fortgesetzt werden, klicken Sie <a href="%s">hier</a>.';

