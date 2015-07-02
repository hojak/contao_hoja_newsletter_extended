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

$GLOBALS['BE_MOD']['content']['newsletter']['send'] = array('HoJa\\NLExtended\\NewsletterExtended', 'send');

$GLOBALS['TL_CTE']['newsletter']['hoja_nl_header'] = 'HoJa\\NLExtended\\ContentNLHeader';

