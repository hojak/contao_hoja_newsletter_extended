<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 */

/**
 * @package hoja_newsletter_extended
 *
 * @copyright  Holger Janßen 2015
 * @author     Holger Janßen <phpdevel@holgerjanssen.de>
 */


/**
 * Table tl_newsletter
 */
$GLOBALS['TL_DCA']['tl_newsletter']['config']['ctable'] = array('tl_content');
$GLOBALS['TL_DCA']['tl_newsletter']['config']['switchToEdit'] = true;
$GLOBALS['TL_DCA']['tl_newsletter']['config']['onload_callback'] = array(array('tl_newsletter_extended', 'checkPermission'));
$GLOBALS['TL_DCA']['tl_newsletter']['list']['sorting']['child_record_callback'] = array('tl_newsletter_extended', 'listNewsletterArticles');
$GLOBALS['TL_DCA']['tl_newsletter']['list']['operations']['edit']['href'] = 'table=tl_content';

array_insert($GLOBALS['TL_DCA']['tl_newsletter']['list']['operations'], 1, array(
	'editheader' => array(
		'label'               => &$GLOBALS['TL_LANG']['tl_newsletter']['editmeta'],
		'href'                => 'act=edit',
		'icon'                => 'header.gif'
	)
));

$GLOBALS['TL_DCA']['tl_newsletter']['palettes']['default'] 	= str_replace(
		';{html_legend},content;', 
		';{hoja_files_legend},hoja_template_prefix,hoja_css_file;{hoja_tracking_legend},hoja_piwik_campaign;', 
		$GLOBALS['TL_DCA']['tl_newsletter']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['hoja_template_prefix'] = array (
	'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter']['hoja_template_prefix'],
	'exclude'                 => true,
	'inputType'               => 'text',
	'eval'                    => array('class' => ''),
	'sql'                     => "varchar(255) NOT NULL default ''"
);
$GLOBALS['TL_DCA']['tl_newsletter']['fields']['hoja_css_file'] = array (
	'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter']['hoja_css_file'],
	'exclude'                 => true,
	'inputType'               => 'fileTree',
	'eval'                    => array('fieldType'=>'checkbox', 'filesOnly'=>true, 'extensions'=>'css'),
	'sql'                     => "blob NULL"
);

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['hoja_piwik_campaign'] = array (
	'label'                   => &$GLOBALS['TL_LANG']['tl_newsletter']['hoja_piwik_campaign'],
	'exclude'                 => true,
	'inputType'               => 'text',
	'eval'                    => array(),
	'sql'                     => "varchar(255) NULL"
);

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['hoja_rejected'] = array(
	'sql'                     => "int(10) unsigned NOT NULL default '0'"
);

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['hoja_recipients'] = array(
	'sql'                     => "int(10) unsigned NOT NULL default '0'"
);


class tl_newsletter_extended extends tl_newsletter {

	/**
	 * Import the back end user object
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Add the type of input field
	 * @param array
	 * @return string
	 */
	public function listNewsletterArticles($arrRow) {
		$strStats = '';
		$strContents = '';

		$objContents = \ContentModel::findPublishedByPidAndTable($arrRow['id'], 'tl_newsletter');
		if (!is_null($objContents)) {
			foreach ($objContents as $objContent) {
				$strContents.= $this->getContentElement($objContent->id) . '<hr>';
			}
		}

		$strStats = sprintf ( 
			$GLOBALS['TL_LANG']['tl_newsletter']['hoja_status_string'], 
			$arrRow['hoja_recipients'], 
			$arrRow['hoja_rejected'] 
		);


		return '
<div class="cte_type ' . (($arrRow['sent'] && $arrRow['date']) ? 'published' : 'unpublished') . '"><strong>' . $arrRow['subject'] . '</strong> - ' . (($arrRow['sent'] && $arrRow['date']) ? sprintf($GLOBALS['TL_LANG']['tl_newsletter']['sentOn'], Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $arrRow['date'])) . '<br>' . $strStats : $GLOBALS['TL_LANG']['tl_newsletter']['notSent']) . '</div>
<div class="limit_height' . (!$GLOBALS['TL_CONFIG']['doNotCollapse'] ? ' h128' : '') . '">
' . (!$arrRow['sendText'] && strlen($strContents) ? '
' . $strContents : '' ) . '
' . nl2br_html5($arrRow['text']) . '
</div>' . "\n";
	}


	public function checkPermission() {
		if (Input::get('key') == 'stats') {
			if ($this->User->isAdmin)
			{
				return;
			}

			// Set root IDs
			if (!is_array($this->User->newsletters) || empty($this->User->newsletters)) {
				$root = array(0);
			} else {
				$root = $this->User->newsletters;
			}

			$id = strlen(Input::get('id')) ? Input::get('id') : CURRENT_ID;

			$objChannel = $this->Database->prepare("SELECT pid FROM tl_newsletter WHERE id=?")
										 ->limit(1)
										 ->execute($id);

			if ($objChannel->numRows < 1) {
				$this->log('Invalid newsletter ID "'.$id.'"', __METHOD__, TL_ERROR);
				$this->redirect('contao/main.php?act=error');
			}

			if (!in_array($objChannel->pid, $root)) {
				$this->log('Not enough permissions to show stats of newsletter ID "'.$id.'" of newsletter channel ID "'.$objChannel->pid.'"', __METHOD__, TL_ERROR);
				$this->redirect('contao/main.php?act=error');
			}
		} else {
			parent::checkPermission();
		}
	}
	
	
	public function getTemplateFolders () {
		return $this->getTemplateFoldersRec ( 'templates');
	}
	
	
	/**
	 * Return all template folders as array
	 *
	 * @param string  $path
	 * @param integer $level
	 *
	 * @return array
	 */
	protected function getTemplateFoldersRec($path, $level=0)
	{
		$return = array();

		foreach (scan(TL_ROOT . '/' . $path) as $file)
		{
			if (is_dir(TL_ROOT . '/' . $path . '/' . $file))
			{
				$return[$path . '/' . $file] = str_repeat(' &nbsp; &nbsp; ', $level) . $file;
				$return = array_merge($return, $this->getTemplateFoldersRec($path . '/' . $file, $level+1));
			}
		}

		return $return;
	}


}
