<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015Leo Feyer
 *
 * @package Newsletter
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * adaption for the newsletter display to show the content of a newsletter correctly
 */
namespace HoJa\NLExtended;

class ModuleNewsletterReader extends \Contao\ModuleNewsletterReader
{
	
	protected $strTemplate = 'mod_hoja_newsletter_reader';

	public function generate()
	{
		return parent::generate();
	}
	
	
	
	protected function compile () {
		parent::compile ();
	
		$objNewsletter = \NewsletterModel::findSentByParentAndIdOrAlias(\Input::get('items'), $this->nl_channels);
	
		$html = '';
		$objContentElements = \ContentModel::findPublishedByPidAndTable($objNewsletter->id, 'tl_newsletter');
		
		if ($objContentElements !== null) {
			while ($objContentElements->next()) {
				$html.= $this->getContentElement($objContentElements->id);
			}
		}
        
        // add css
        if ( $css = $objNewsletter->hoja_css_file) {
            $file = \FilesModel::findByUuid ( $css );
            $GLOBALS['TL_CSS'][] = $file->path;         
        }

        // parse simple newslettertokens
        $html = NewsletterExtended::parseMySimpleTokens( $html, array (
            'pid'         => $objNewsletter->pid,
            'unsubscribe' => NewsletterExtended::getUnsubscriptionLink($objNewsletter->pid),
        ));


		// Replace insert tags
		$html = $this->replaceInsertTags($html);
        
        // handle base - #-link problem
        $html = preg_replace ( '/href="#/', 'href="{{env::path}}{{env::request}}#', $html);
        
		$this->Template->htmlContent = $html;
	}
}
