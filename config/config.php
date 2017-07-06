<?php

/**
 * @copyright  Holger Janßen
 * @author     Holger Janßen <phpdevel@holgerjanssen.de>
 * @package    hoja_newsletter_extended
 */


$GLOBALS['BE_MOD']['content']['newsletter']['tables'][]  = 'tl_content';
$GLOBALS['BE_MOD']['content']['newsletter']['tables'][]  = 'tl_hoja_newsletter_sent';


/* overwrite actions send and import **/
$GLOBALS['BE_MOD']['content']['newsletter']['preview'] = array('HoJa\\NLExtended\\NewsletterExtended', 'preview');
$GLOBALS['BE_MOD']['content']['newsletter']['send'] = array('HoJa\\NLExtended\\NewsletterExtended', 'send');
$GLOBALS['BE_MOD']['content']['newsletter']['import'] = array ('HoJa\\NLExtended\\NewsletterExtended', 'importRecipients' );
$GLOBALS['BE_MOD']['content']['newsletter']['protocol'] = array ('HoJa\\NLExtended\\NewsletterExtended', 'protocol' );


/* add content element for newsletter headers including a table of contents **/
$GLOBALS['TL_CTE']['hoja_newsletter']['hoja_nl_header'] = 'HoJa\\NLExtended\\ContentNLHeader';

/* adapted frontend modules for reader and subscription handling **/ 
$GLOBALS['FE_MOD']['hoja_newsletter']['hoja_nl_reader'] = 'HoJa\\NLExtended\\ModuleNewsletterReader';
$GLOBALS['FE_MOD']['hoja_newsletter']['hoja_nl_unsubscribe_double'] = 'HoJa\\NLExtended\\ModuleUnsubscribeDouble';
$GLOBALS['FE_MOD']['hoja_newsletter']['hoja_nl_subscribe_with_name'] = 'HoJa\\NLExtended\\ModuleSubscribeWithName';



/**
 * hook to use the newsletter defined prefix for the template names
 */
$GLOBALS['TL_HOOKS']['parseTemplate'][] = array('tl_content_newsletter_extended', 'adaptTemplate');
