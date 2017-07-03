<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 */

/**
 * @copyright  Holger Janßen
 * @author     Holger Janßen <phpdevel@holgerjanssen.de>
 * @package    hoja_newsletter_extended
 */




$GLOBALS['BE_MOD']['content']['newsletter']['tables'][]  = 'tl_content';
$GLOBALS['BE_MOD']['content']['newsletter']['tables'][]  = 'tl_hoja_newsletter_sent';

$GLOBALS['BE_MOD']['content']['newsletter']['send'] = array('HoJa\\NLExtended\\NewsletterExtended', 'send');

$GLOBALS['TL_CTE']['hoja_newsletter']['hoja_nl_header'] = 'HoJa\\NLExtended\\ContentNLHeader';

$GLOBALS['FE_MOD']['hoja_newsletter']['hoja_nl_reader'] = 'HoJa\\NLExtended\\ModuleNewsletterReader';

$GLOBALS['FE_MOD']['hoja_newsletter']['hoja_nl_unsubscribe_double'] = 'HoJa\\NLExtended\\ModuleUnsubscribeDouble';

$GLOBALS['FE_MOD']['hoja_newsletter']['hoja_nl_subscribe_with_name'] = 'HoJa\\NLExtended\\ModuleSubscribeWithName';



/**
 * hook to use the newsletter defined prefix for the template names
 */
$GLOBALS['TL_HOOKS']['parseTemplate'][] = array('tl_content_newsletter_extended', 'adaptTemplate');



/**
 * overwrite CSV-Import
 **/
$GLOBALS['BE_MOD']['content']['newsletter']['import'] = array ('HoJa\\NLExtended\\NewsletterExtended', 'importRecipients' );
