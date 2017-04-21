<?php


$GLOBALS['TL_DCA']['tl_newsletter_channel']['palettes']['default'] .=
        ';{hoja_salutation_legend},hoja_override_salutation,hoja_salutation_default,hoja_salutation_formal,hoja_salutation_informal';

$GLOBALS['TL_DCA']['tl_newsletter_channel']['palettes']['default'] 	= str_replace(
    "jumpTo;",
    "jumpTo,hoja_unsubscribe_page;",
    $GLOBALS['TL_DCA']['tl_newsletter_channel']['palettes']['default']
);


$GLOBALS['TL_DCA']['tl_newsletter_channel']['fields']['hoja_override_salutation'] = array(
	'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_override_salutation_label'],
	'exclude'                 => true,
	'inputType'               => 'select',
    'options'                 => array (
        'formal'      => &$GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_override_salutation_formal'],
        'informal'    => &$GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_override_salutation_informal'],
    ),
	'eval'                    => array( 'includeBlankOption' => true, ),
	'sql'                     => "varchar(10) NULL"
);

$GLOBALS['TL_DCA']['tl_newsletter_channel']['fields']['hoja_salutation_informal'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_salutation_informal'],
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => array('decodeEntities' => true,),
    'sql'                     => "varchar(255) NULL",
    'load_callback' => array (
        array('tl_newsletter_channel_extended', 'getDefaultInformalSalutation')
    ),
);
$GLOBALS['TL_DCA']['tl_newsletter_channel']['fields']['hoja_salutation_formal'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_salutation_formal'],
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => array('decodeEntities' => true, ),
    'sql'                     => "varchar(255) NULL",
    'load_callback' => array (
        array('tl_newsletter_channel_extended', 'getDefaultFormalSalutation')
    ),
);
$GLOBALS['TL_DCA']['tl_newsletter_channel']['fields']['hoja_salutation_default'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_salutation_default'],
    'exclude'                 => true,
    'inputType'               => 'text',
    'eval'                    => array(),
    'sql'                     => "varchar(255) NULL",
    'load_callback' => array (
        array('tl_newsletter_channel_extended', 'getDefaultSalutation')
    ),
);


$GLOBALS['TL_DCA']['tl_newsletter_channel']['fields']['hoja_unsubscribe_page'] = array(
    'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_unsubscribe_page'],
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'foreignKey'              => 'tl_page.title',
    'eval'                    => array('fieldType'=>'radio'),
    'sql'                     => "int(10) unsigned NULL",
    'relation'                => array('type'=>'hasOne', 'load'=>'lazy')
);




class tl_newsletter_channel_extended extends tl_newsletter_channel {


    public function getDefaultSalutation ($currentValue) {
        if (!trim($currentValue)) {
            $currentValue = $GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_default_salutation'];
        }

        return $currentValue;
    }

    public function getDefaultFormalSalutation ($currentValue) {
        if (!trim($currentValue)) {
            $currentValue = $GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_default_formal_salutation'];
        }

        return $currentValue;
    }

    public function getDefaultInformalSalutation ($currentValue) {
        if (!trim($currentValue)) {
            $currentValue = $GLOBALS['TL_LANG']['tl_newsletter_channel']['hoja_default_informal_salutation'];
        }

        return $currentValue;
    }

}
