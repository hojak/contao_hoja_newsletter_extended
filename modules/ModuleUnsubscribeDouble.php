<?php


/**
 * adaption of the unsubscription module to introduce a double opt out and personal unsubscription links in sent letters
 */
namespace HoJa\NLExtended;

class ModuleUnsubscribeDouble extends \Module
{
	
	protected $strTemplate = 'mod_hoja_unsubscribe_double';


	public function generate()
	{
		return parent::generate();
	}
	
	
	
	protected function compile () {
		parent::compile ();
	}
}
