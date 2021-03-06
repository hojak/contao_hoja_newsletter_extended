<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2015 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'HoJa',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'HoJa\NLExtended\NewsletterExtended' => 'system/modules/hoja_newsletter_extended/classes/NewsletterExtended.php',

	// Elements
	'HoJa\NLExtended\ContentNLHeader'    => 'system/modules/hoja_newsletter_extended/elements/ContentNLHeader.php',
	
	// Modules
	'HoJa\NLExtended\ModuleNewsletterReader'  => 'system/modules/hoja_newsletter_extended/modules/ModuleNewsletterReader.php',
	'HoJa\NLExtended\ModuleUnsubscribeDouble' => 'system/modules/hoja_newsletter_extended/modules/ModuleUnsubscribeDouble.php',
	'HoJa\NLExtended\ModuleSubscribeWithName' => 'system/modules/hoja_newsletter_extended/modules/ModuleSubscribeWithName.php',


    'tl_content_newsletter_extended' => 'system/modules/hoja_newsletter_extended/dca/tl_content.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'ce_hoja_nl_header'            => 'system/modules/hoja_newsletter_extended/templates',

	'mod_hoja_newsletter_reader'   => 'system/modules/hoja_newsletter_extended/templates',
	'mod_hoja_unsubscribe_double'  => 'system/modules/hoja_newsletter_extended/templates',
	'mod_hoja_subscribe_with_name' => 'system/modules/hoja_newsletter_extended/templates',
));
