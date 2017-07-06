<?php

$GLOBALS['TL_DCA']['tl_hoja_newsletter_sent'] = array
(
	// Config
	'config' => array
	(
		'dataContainer'               => 'Table',
		'enableVersioning'            => false,
		'ptable'                      => 'tl_newsletter',
		// 'notEditable'                 => true,
		'notDeletable'                => true,
		'notCopyable'                 => true,
		'notCreatale'                 => true,
		'doNotCopyRecords'            => true,
		
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
			)
		)
	),
    
    // List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => 1,
			'fields'                  => array('recipient, sent_at'),
            'headerFields'            => array ('subject', 'sent', 'date'),
		),
		'label' => array
		(
			'fields'                => array('recipient','sent_at'),
            'showColumns'           => true,
            // 'label_callback'        => array ('tl_hoja_newsletter_sent', 'getRowLabel' ),
		),        
		'global_operations' => array
		(
		),
		'operations' => array
		(
            /*
			'delete' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['delete'],
				'href'                => 'act=delete',
				'icon'                => 'delete.gif',
				'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            */
		)
	),

	// Palettes
	'palettes' => array
	(
        '__selector__'                => array(), 
		'default'                     => ''
	),

	// Subpalettes
	'subpalettes' => array
	(
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'pid' => array (
			'foreignKey'              => 'tl_newsletter.subject',
			'relation'                => array ('type' => 'hasOne', 'load' => 'eager'),
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
		),		
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
        'recipient' => array (
            'label'                   => &$GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['recipient'],
			'foreignKey'              => 'tl_newsletter_recipients.email',
			'relation'                => array ('type' => 'hasOne', 'load' => 'eager',),
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
        ),
        'sent_at' => array (
            'label'                   => &$GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['sent_at'],
            'sql'                     => "int(10) unsigned NOT NULL default '0'", 
            'eval'                    => array ('rgxp' => 'datim',),
        ),
        'status_code' => array (
            'label'                   => &$GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['status_code'],
            'sql'                     => "smallint(5) unsigned NOT NULL default '0'",
            'inputType'               => 'select',
            'options'                 => $GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['status_options'],
        ),
	),

);


/*

class tl_hoja_newsletter_sent extends \Backend {
    
    public function getRowLabel ($row, $label, DataContainer $dc, $args ) {
        return $label;
    }
}

*/