<?php
/**
 * interactive Navigation bar
 *
 * @package   meye_ce_tabbed_content
 * @author    Holger Janßen
 * @license   proprietary, contact post@holgerjanssen.de
 * @copyright 2015, Holger Janßen and MEyeTech GmbH
 */

namespace HoJa\NLExtended;


class ContentNLHeader extends \Contao\ContentElement
{

	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'ce_hoja_nl_header';

	
	public function generate () 
	{
		if (TL_MODE == 'BE' && !defined ( 'NEWSLETTER_CONTENT_PREVIEW' ))
		{
			$objTemplate = new \BackendTemplate('be_wildcard');

			$objTemplate->wildcard = 
				"### Newsletter-Header ###";
			
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			//$objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}

		return parent::generate();
	}
	
	/**
	 * Import the back end user object
	 */
	public function __construct( $param )
	{
		parent::__construct( $param );
		$this->import('Database');
	}



	/**
	 * Generate the content element
	 */
	protected function compile()
	{
		$this->Template->image = \FilesModel::findByUuid($this->singleSRC);	
		
		// for navigation: get list of content elements	
		$nlContent = \ContentModel::findPublishedByPidAndTable ($this->pid, "tl_newsletter" );		
		$content_items = array ();

		$first = true;
		if ( $nlContent ) 
			foreach ( $nlContent as $element ) {
				if ( $element->id != $this->id ) {
					if ( $element->hoja_nl_headerlink_text) {
						$content_items[] = array ( "id" => $element->id, "linktext" => $element->hoja_nl_headerlink_text );
					}
				}
			}
		
		$this->Template->nav_items = $content_items;

        $newsletter = \NewsletterModel::findById ( $this->pid );

        $this->Template->linkWebView = NewsletterExtended::getWebViewLink ( $newsletter );
        $this->Template->newsletter = $newsletter;
	}



	

	

}
