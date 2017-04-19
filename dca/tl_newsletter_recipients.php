<?php

$GLOBALS['TL_DCA']['tl_newsletter_recipients']['palettes']['default'] 	.=
		';{hoja_address_legend},hoja_nl_form_of_address,hoja_nl_gender,hoja_nl_title,hoja_nl_firstname,hoja_nl_lastname;';




$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_unsubscribe_id'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_unsubscribe_id'],
	'exclude'	=> true,
	'inputType' => 'text',
	'eval'       => array(),
	'sql'		=> "varchar(255) NULL",
);
$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_firstname'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'],
	'exclude'	=> true,
	'inputType' => 'text',
	'eval'       => array('tl_class' => 'w50 clr'),
	'sql'		=> "varchar(255) NULL",
);
$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_lastname'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'],
	'exclude'	=> true,
	'inputType' => 'text',
	'eval'       => array('tl_class' => 'w50'),
	'sql'		=> "varchar(255) NULL",
);
$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_gender'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_label'],
	'exclude'	=> true,
	'inputType' => 'select',
	'eval'      => array('tl_class' => 'w50 clr', 'includeBlankOption' => true),
	'sql'		=> "varchar(1) NULL",
    'options'   => array (
      'm' => &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_m'],
      'f' => &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_f'],
    ),
);
$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_title'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'],
	'exclude'	=> true,
	'inputType' => 'text',
	'eval'       => array('tl_class' => 'w50'),
	'sql'		=> "varchar(255) NULL",
);
$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_form_of_address'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_label'],
	'exclude'	=> true,
	'inputType' => 'select',
	'eval'       => array('tl_class' => 'w50', 'mandatory' => true),
	'sql'		=> "varchar(10) NOT NULL default 'informal'",
    'default'   => 'informal',

    'options' => array (
        'informal' => &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_informal'],
        'formal'   => &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_formal'],
    ),
);


$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_unsubscribed_date'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_unsubscribed_date'],
	'exclude'	=> true,
	'inputType' => 'text',
	'sql'		=> "int(10) unsigned NULL",
);

$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_unsubscribed_pending'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_unsubscribed_pending'],
	'exclude'	=> true,
	'inputType' => 'text',
	'sql'		=> "int(10) unsigned NULL",
);

$GLOBALS['TL_DCA']['tl_newsletter_recipients']['fields']['hoja_nl_remarks'] = array (
	'label'		=> &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_remarks'],
	'exclude'	=> true,
	'inputType' => 'text',
	'sql'		=> "text NULL",
);

