<?php

$GLOBALS['TL_DCA']['tl_module']['palettes']['hoja_nl_reader']    =
    '{title_legend},name,headline,type;'
    .'{config_legend},nl_channels;{template_legend:hide},customTpl;'
    .'{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';

$GLOBALS['TL_DCA']['tl_module']['palettes']['hoja_nl_unsubscribe_double'] =
    '{title_legend},name,headline,type;'
    .'{redirect_legend},jumpTo;'
    .'{template_legend:hide},customTpl;'
    .'{hoja_nl_mail_legend},hoja_nl_unsubscribe_mail;'
    .'{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';


$GLOBALS['TL_DCA']['tl_module']['palettes']['hoja_nl_subscribe_with_name'] =
    '{title_legend},name,headline,type;'
    .'{config_legend},nl_channels,nl_hideChannels;'
    .'{redirect_legend},jumpTo;{email_legend:hide},nl_subscribe;'
    .'{template_legend:hide},customTpl;'
    //.'{template_legend:hide},nl_template,customTpl;'
    .'{protected_legend:hide},protected;{expert_legend:hide},guests,cssID,space';



$GLOBALS['TL_DCA']['tl_module']['fields']['hoja_nl_unsubscribe_mail'] = array (
    'label'		=> &$GLOBALS['TL_LANG']['tl_module']['hoja_nl_unsubscribe_mail'],
    'exclude'	=> true,
    'inputType' => 'textarea',
    'eval'       => array('style'=>'height:120px', 'decodeEntities'=>true, 'alwaysSave'=>true),
    'sql'		=> "text NULL",
    'load_callback' => array (
        array('tl_module_hoja_newsletter', 'getUnsubscribeDefault')
    ),
);




class tl_module_hoja_newsletter {

	/**
	 * Load the default unsubscribe text
	 *
	 * @param mixed $varValue
	 *
	 * @return mixed
	 */
	public function getUnsubscribeDefault($varValue)
	{
		if (!trim($varValue))
		{
			$varValue = $GLOBALS['TL_LANG']['tl_module']['text_hoja_unsubscribe'][1];
		}

		return $varValue;
	}
}
