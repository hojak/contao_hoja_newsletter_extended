<?php

$GLOBALS['TL_LANG']['tl_newsletter']['editmeta'] = array('Newsletter-Einstellungen bearbeiten', 'Die Newsletter-Einstellungen bearbeiten');

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
'<p>Hint: The columns of the CSV file should contain the folliowing information:</p>'
.'<ol>'
.'<li style="list-style: decimal">email</li>'
.'<li style="list-style: decimal">first name</li>'
.'<li style="list-style: decimal">last name</li>'
.'<li style="list-style: decimal">gender (m or f)</li>'
.'<li style="list-style: decimal">title</li>'
.'<li style="list-style: decimal">form of address (formal or informal)</li>'
.'<li style="list-style: decimal">additional remarks</li>'
.'</ol>';
