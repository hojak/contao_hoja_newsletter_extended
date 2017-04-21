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
 * Run in a custom namespace, so the class can be replaced
 */
namespace HoJa\NLExtended;



/**
 * Class NewsletterContent
 *
 * Front end module "newsletter content reader".
 * @copyright  Holger Janßen 2015
 * @author     Holger Janßen <phpdevel@holgerjanssen.de>
 * @package    hoja_newsletter_extended
 */
class NewsletterExtended extends \Newsletter {

	protected $isFlexible = false;


	protected function __construct() {
		parent::__construct();
		$this->import('BackendUser');
		$this->isFlexible = $this->BackendUser->backendTheme == 'flexible';

        $this->import ( "Environment");
	}


    /**
     * overwrite SMTP configuration if applicable
     * @param <unknown> $objNewsletter
     * @return
     */
    protected function _setSMTPConfig ( $objNewsletter ) {
		if ($objNewsletter->useSMTP) {
			$GLOBALS['TL_CONFIG']['useSMTP'] = true;

			$GLOBALS['TL_CONFIG']['smtpHost'] = $objNewsletter->smtpHost;
			$GLOBALS['TL_CONFIG']['smtpUser'] = $objNewsletter->smtpUser;
			$GLOBALS['TL_CONFIG']['smtpPass'] = $objNewsletter->smtpPass;
			$GLOBALS['TL_CONFIG']['smtpEnc']  = $objNewsletter->smtpEnc;
			$GLOBALS['TL_CONFIG']['smtpPort'] = $objNewsletter->smtpPort;
		}
    }



	/**
	 * Return a form to choose an existing style sheet and import it
	 * @param \DataContainer
	 * @return string
	 */
	public function send(\DataContainer $objDc) {
		// otherwise, the dca of tl_content is not loaded, if the internal cache is active
		$this->loadDataContainer('tl_content'); 

		if (TL_MODE == 'BE') {
			$GLOBALS['TL_CSS'][] = 'system/modules/hoja_newsletter_extended/assets/css/style.css';

			if ($this->isFlexible) {
				$GLOBALS['TL_CSS'][] = 'system/modules/hoja_newsletter_extended/assets/css/style-flexible.css';
			}
		}

		$objNewsletter = $this->Database->prepare("SELECT n.*, c.useSMTP, c.smtpHost, c.smtpPort, c.smtpUser, c.smtpPass FROM tl_newsletter n LEFT JOIN tl_newsletter_channel c ON n.pid=c.id WHERE n.id=?")
										->limit(1)
										->execute($objDc->id);

		// Return if there is no newsletter
		if ($objNewsletter->numRows < 1) {
			return '';
		}

        $this->_setSMTPConfig ( $objNewsletter );

		// Add default sender address
		if ($objNewsletter->sender == '') {
			list($objNewsletter->senderName, $objNewsletter->sender) = \String::splitFriendlyEmail($GLOBALS['TL_CONFIG']['adminEmail']);
		}

		$arrAttachments = array();
		$blnAttachmentsFormatError = false;

		// Add attachments
		if ($objNewsletter->addFile) {
			$files = deserialize($objNewsletter->files);

			if (!empty($files) && is_array($files)) {
				$objFiles = \FilesModel::findMultipleByUuids($files);

				if ($objFiles === null) {
					if (!\Validator::isUuid($files[0])) {
						$blnAttachmentsFormatError = true;
						\Message::addError($GLOBALS['TL_LANG']['ERR']['version2format']);
					}
				} else {
					while ($objFiles->next()) {
						if (is_file(TL_ROOT . '/' . $objFiles->path)) {
							$arrAttachments[] = $objFiles->path;
						}
					}
				}
			}
		}

		// Get content
		$html = '';
		$objContentElements = \ContentModel::findPublishedByPidAndTable($objNewsletter->id, 'tl_newsletter');

		if ($objContentElements !== null) {
			if (!defined('NEWSLETTER_CONTENT_PREVIEW')) {
				define('NEWSLETTER_CONTENT_PREVIEW', true);
			}

			while ($objContentElements->next()) {
                // prevent <pre> enclosure of html content elements
                if ( $objContentElements->type == "html") {
                    $html .= $objContentElements->html;
                } else {
                    $html.= $this->getContentElement($objContentElements->id);
                }
			}
		}

		// Replace insert tags
		$text = $this->replaceInsertTags($objNewsletter->text);
		$html = $this->replaceInsertTags($html);

		// Convert relative URLs
		$html = $this->convertRelativeUrls($html);
				
		// Set back to object
		$objNewsletter->content = $html;



        // actualize preview data
        $this->_initPreviewData();

        // Send newsletter
        if (!$blnAttachmentsFormatError && \Input::get('token') != '' && \Input::get('token') == $this->Session->get('tl_newsletter_send')) {
            $referer = preg_replace('/&(amp;)?(start|mpc|token|recipient|preview|actualize)=[^&]*/', '', \Environment::get('request'));

            // Preview ((=> send test ))
            if (isset($_GET['preview'])) {
                // get preview recipient
                if (!\Validator::isEmail(\Input::get('recipient', true))) {
                    $_SESSION['TL_PREVIEW_MAIL_ERROR'] = true;
                    $this->redirect($referer);
                }

                $strEmail = \Input::get('recipient');
                $arrRecipient = array_merge($_SESSION['hoja_preview'], array(
                    'extra' => '&preview=1',
                    'tracker_png' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $strEmail . '&preview=1&t=png',
                    'tracker_gif' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $strEmail . '&preview=1&t=gif',
                    'tracker_css' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $strEmail . '&preview=1&t=css',
                    'tracker_js'  => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $strEmail . '&preview=1&t=js',
                    'pid'   => $objNewsletter->pid,
                ));

                // Send
                $objEmail = $this->generateEmailObject($objNewsletter, $arrAttachments);
                $objNewsletter->email = $strEmail;
                $replaceData = array_merge( $_SESSION['hoja_preview'], array (
                    'tracker_png' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=png',
                    'tracker_gif' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=gif',
                    'tracker_css' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=css',
                    'tracker_js' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=js',
                    'linkWebView' => self::getWebViewLink( $objNewsletter ),
                    'pid' => $objNewsletter->pid,
                    'unsubscribe' => 'about:blank',
                ));
                $replaceData['salutation'] = self::getSalutation( $replaceData );
                $textContent = $this->parseSimpleTokens($text, $replaceData );
                $this->sendNewsletter($objEmail, $objNewsletter, $arrRecipient, $textContent, $html);

                // Redirect
                \Message::addConfirmation(sprintf($GLOBALS['TL_LANG']['tl_newsletter']['confirm'], 1));


                // abort sending due to preview...
                $this->redirect($referer);
            }

            // Get the total number of recipients
            $objTotal = $this->Database->prepare("SELECT COUNT(DISTINCT email) AS count FROM tl_newsletter_recipients WHERE pid=? AND active=1")
                                       ->execute($objNewsletter->pid);

			// Return if there are no recipients
			if ($objTotal->count < 1) {
				$this->Session->set('tl_newsletter_send', null);
				\Message::addError($GLOBALS['TL_LANG']['tl_newsletter']['error']);
				$this->redirect($referer);
			}

			$intTotal = $objTotal->count;

			// Get page and timeout
			$intTimeout = (\Input::get('timeout') > 0) ? \Input::get('timeout') : 1;
			$intStart = \Input::get('start') ? \Input::get('start') : 0;
			$intPages = \Input::get('mpc') ? \Input::get('mpc') : 10;

			// Get recipients
            $query =
                "SELECT *, r.email FROM tl_newsletter_recipients r "
                ."LEFT JOIN tl_member m ON(r.email=m.email) "
                ."WHERE r.pid=? AND r.active=1 "
                //."GROUP BY r.email "
                ."ORDER BY r.email";

			$objRecipients = $this->Database->prepare($query)
											->limit($intPages, $intStart)
											->execute($objNewsletter->pid);

			echo '<div style="font-family:Verdana,sans-serif;font-size:11px;line-height:16px;margin-bottom:12px">';

			// Send newsletter
			if ($objRecipients->numRows > 0) {
				// Update status
				if ($intStart == 0) {
					$this->Database->prepare("UPDATE tl_newsletter SET sent=1, date=? WHERE id=?")
								   ->execute(time(), $objNewsletter->id);

					$_SESSION['REJECTED_RECIPIENTS'] = array();
				}

				while ($objRecipients->next()) {
					$objEmail = $this->generateEmailObject($objNewsletter, $arrAttachments);
					$objNewsletter->email = $objRecipients->email;
					$arrRecipient = array_merge($objRecipients->row(), array(
						'tracker_png' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $objRecipients->email . '&t=png',
						'tracker_gif' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $objRecipients->email . '&t=gif',
						'tracker_css' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $objRecipients->email . '&t=css',
						'tracker_js' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $objRecipients->email . '&t=js',
                        'unsubscribe' => $this->getUnsubscriptionLink( $objRecipients ),
					));
					$this->sendNewsletter($objEmail, $objNewsletter, $arrRecipient, $text, $html);

					echo 'Sending newsletter to <strong>' . $objRecipients->email . '</strong><br>';
				}
			}

			echo '<div style="margin-top:12px">';

			// Redirect back home
			if ($objRecipients->numRows < 1 || ($intStart + $intPages) >= $intTotal) {
				$this->Session->set('tl_newsletter_send', null);

				// Deactivate rejected addresses
				if (!empty($_SESSION['REJECTED_RECIPIENTS']))
				{
					$intRejected = count($_SESSION['REJECTED_RECIPIENTS']);
					\Message::addInfo(sprintf($GLOBALS['TL_LANG']['tl_newsletter']['hoja_rejected'], $intRejected));
					$intTotal -= $intRejected;

					foreach ($_SESSION['REJECTED_RECIPIENTS'] as $strRecipient)
					{
						$this->Database->prepare("UPDATE tl_newsletter_recipients SET active='' WHERE email=?")
									   ->execute($strRecipient);

						$this->log('Recipient address "' . $strRecipient . '" was rejected and has been deactivated', __METHOD__, TL_ERROR);
					}
				} else {
                    $intRejected = 0;
                }

				$this->Database->prepare("UPDATE tl_newsletter SET hoja_recipients=?, hoja_rejected=? WHERE id=?")
							   ->execute($intTotal, $intRejected, $objNewsletter->id);

				\Message::addConfirmation(sprintf($GLOBALS['TL_LANG']['tl_newsletter']['confirm'], $intTotal));

				echo '<script>setTimeout(\'window.location="' . \Environment::get('base') . $referer . '"\',1000)</script>';
				echo '<a href="' . \Environment::get('base') . $referer . '">Please click here to proceed if you are not using JavaScript</a>';
			}

			// Redirect to the next cycle
			else {
				$url = preg_replace('/&(amp;)?(start|mpc|recipient)=[^&]*/', '', \Environment::get('request')) . '&start=' . ($intStart + $intPages) . '&mpc=' . $intPages;

				echo '<script>setTimeout(\'window.location="' . \Environment::get('base') . $url . '"\',' . ($intTimeout * 1000) . ')</script>';
				echo '<a href="' . \Environment::get('base') . $url . '">Please click here to proceed if you are not using JavaScript</a>';
			}

			echo '</div></div>';
			exit;
		}

		$strToken = md5(uniqid(mt_rand(), true));
		$this->Session->set('tl_newsletter_send', $strToken);
		$sprintf = ($objNewsletter->senderName != '') ? $objNewsletter->senderName . ' &lt;%s&gt;' : '%s';
		$this->import('BackendUser', 'User');

		// prepare preview
		$preview = $text;
		if (!$objNewsletter->sendText) {
			// Default template
			if ($objNewsletter->template == '') {
				$objNewsletter->template = 'mail_default';
			}

			// Load the mail template
			$objTemplate = new \BackendTemplate($objNewsletter->template);
			$objTemplate->setData($objNewsletter->row());

			$objTemplate->title = $objNewsletter->subject;
			$objTemplate->body = $html;
			$objTemplate->charset = $GLOBALS['TL_CONFIG']['characterSet'];
			$objTemplate->css = $css; // Backwards compatibility

			$this->addCssToTemplate ( $objTemplate );
			
			// Parse template
			$preview = $objTemplate->parse();
			
			$preview = $this->addPiwikCampaignHtml ( $preview, $objNewsletter->hoja_piwik_campaign );
		}

        $iframeUrl = 'system/cache/newsletter/' . $objNewsletter->alias . '.html';

        // get rid of base <> #-link problem
        $preview = preg_replace( '/href="#/', 'href="'.$this->Environment->url. "/".$iframeUrl.'#', $preview );

        $preview = $this->replaceInsertTags($preview);
        //$preview = $this->prepareLinkTracking($preview, $objNewsletter->id, $this->User->email, '&preview=1');

        $simpleTokenData = array_merge ( $_SESSION['hoja_preview'], array (
            'tracker_png' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=png',
            'tracker_gif' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=gif',
            'tracker_css' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=css',
            'tracker_js'  => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=js',
            'linkWebView' => self::getWebViewLink( $objNewsletter ),
            'pid'         => $objNewsletter->pid,
            'unsubscribe' => 'about::blank',
        ));
        $simpleTokenData['salutation'] = self::getSalutation( $simpleTokenData );

        $preview = $this->parseSimpleTokens($preview, $simpleTokenData );
        $textContent = $this->parseSimpleTokens( $text, $simpleTokenData );


		// Create cache folder
		if (!file_exists(TL_ROOT . '/system/cache/newsletter')) {
			mkdir(TL_ROOT . '/system/cache/newsletter');
			file_put_contents(TL_ROOT . '/system/cache/newsletter/.htaccess',
'<IfModule !mod_authz_core.c>
  Order allow,deny
  Allow from all
</IfModule>
<IfModule mod_authz_core.c>
  Require all granted
</IfModule>');
		}

		// Cache preview
		file_put_contents(TL_ROOT . '/system/cache/newsletter/' . $objNewsletter->alias . '.html', preg_replace('/^\s+|\n|\r|\s+$/m', '', $preview));

		// Preview newsletter
        $this->loadLanguageFile('tl_newsletter_recipients');
		$return = '
<div id="tl_buttons">
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>
</div>

<h2 class="sub_headline">'.sprintf($GLOBALS['TL_LANG']['tl_newsletter']['send'][1], $objNewsletter->id).'</h2>
'.\Message::generate().'
<form action="'.ampersand(\Environment::get('script'), true).'" id="tl_newsletter_send" class="tl_form" method="get">
<div class="tl_formbody_edit tl_newsletter_send">
<input type="hidden" name="do" value="' . \Input::get('do') . '">
<input type="hidden" name="table" value="' . \Input::get('table') . '">
<input type="hidden" name="key" value="' . \Input::get('key') . '">
<input type="hidden" name="id" value="' . \Input::get('id') . '">
<input type="hidden" name="token" value="' . $strToken . '">
<table class="prev_header">
  <tr class="row_0">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['from'] . '</td>
    <td class="col_1">' . sprintf($sprintf, $objNewsletter->sender) . '</td>
  </tr>
  <tr class="row_1">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['subject'][0] . '</td>
    <td class="col_1">' . $objNewsletter->subject . '</td>
  </tr>
  <tr class="row_2">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['template'][0] . '</td>
    <td class="col_1">' . $objNewsletter->template . '</td>
  </tr>' . ((!empty($arrAttachments) && is_array($arrAttachments)) ? '
  <tr class="row_3">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['attachments'] . '</td>
    <td class="col_1">' . implode(', ', $arrAttachments) . '</td>
  </tr>' : '') . '
</table>' . (!$objNewsletter->sendText ? '
<iframe class="preview_html" id="preview_html" seamless border="0" width="703px" height="503px" style="padding:0" src="'.$iframeUrl.'"></iframe>
' : '') . '
<div class="preview_text">
' . nl2br_html5($textContent).'
</div>

<div class="tl_tbox">

<fieldset id="pal_send_legend" class="tl_box">
<legend onclick="AjaxRequest.toggleFieldset(this,\'test_legend\',\'tl_content\')">Auslieferung</legend>

<div class="w50">
  <h3><label for="ctrl_mpc">' . $GLOBALS['TL_LANG']['tl_newsletter']['mailsPerCycle'][0] . '</label></h3>
  <input type="text" name="mpc" id="ctrl_mpc" value="10" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter']['mailsPerCycle'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter']['mailsPerCycle'][1] . '</p>' : '') . '
</div>
<div class="w50">
  <h3><label for="ctrl_timeout">' . $GLOBALS['TL_LANG']['tl_newsletter']['timeout'][0] . '</label></h3>
  <input type="text" name="timeout" id="ctrl_timeout" value="1" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter']['timeout'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter']['timeout'][1] . '</p>' : '') . '
</div>
<div class="w50">
  <h3><label for="ctrl_start">' . $GLOBALS['TL_LANG']['tl_newsletter']['start'][0] . '</label></h3>
  <input type="text" name="start" id="ctrl_start" value="0" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter']['start'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter']['start'][1] . '</p>' : '') . '
</div>

</fieldset>

<fieldset id="pal_test_legend" class="tl_box">
<legend onclick="AjaxRequest.toggleFieldset(this,\'test_legend\',\'tl_content\')">Testsendung</legend>


<div class="w50 clr">
  <h3><label for="ctrl_recipient">' . $GLOBALS['TL_LANG']['tl_newsletter']['sendPreviewTo'][0] . '</label></h3>
  <input type="text" name="recipient" id="ctrl_recipient" value="'.$simpleTokenData['email'].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (isset($_SESSION['TL_PREVIEW_MAIL_ERROR']) ? '
  <div class="tl_error">' . $GLOBALS['TL_LANG']['ERR']['email'] . '</div>' : (($GLOBALS['TL_LANG']['tl_newsletter']['sendPreviewTo'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter']['sendPreviewTo'][1] . '</p>' : '')) . '
</div>


<div class="clr">
  <h3><label for="ctrl_form_of_address">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_label'][0] . '</label></h3>
  <select name="form_of_address" id="ctrl_hoja_nl_form_of_address" class="tl_select" onfocus="Backend.getScrollOffset()" style="opacity: 0;">
   <option value="formal"'.($simpleTokenData['hoja_nl_form_of_address']=='formal' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_formal'].'</option>
   <option value="informal"'.($simpleTokenData['hoja_nl_form_of_address']=='informal' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_informal'].'</option>
  </select>'
  . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_label'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
      <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_label'][1] . '</p>' : '') . '
</div>

<div class="w50 clr">
  <h3><label for="ctrl_gender">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_label'][0] . '</label></h3>
  <select name="gender" id="ctrl_hoja_nl_gender" class="tl_select" onfocus="Backend.getScrollOffset()" style="opacity: 0;">
   <option value="">-</option>
   <option value="m"'.($simpleTokenData['hoja_nl_gender']=='m' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_m'].'</option>
   <option value="f"'.($simpleTokenData['hoja_nl_gender']=='f' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_f'].'</option>
  </select>'
  . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_label'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
      <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_label'][1] . '</p>' : '') . '
</div>
<div class="w50">
  <h3><label for="ctrl_title">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][0] . '</label></h3>
  <input type="text" name="title" id="ctrl_title" value="'.$simpleTokenData['hoja_nl_title'].'" placeholder="'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][0].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][1] . '</p>' : '') . '
</div>


<div class="w50 clr">
  <h3><label for="ctrl_firstname">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][0] . '</label></h3>
  <input type="text" name="firstname" id="ctrl_firstname" value="'.$simpleTokenData['hoja_nl_firstname'].'" placeholder="'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][0].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][1] . '</p>' : '') . '
</div>
<div class="w50">
  <h3><label for="ctrl_lastname">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][0] . '</label></h3>
  <input type="text" name="lastname" id="ctrl_lastname" value="'.$simpleTokenData['hoja_nl_lastname'].'" placeholder="'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][0].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][1] . '</p>' : '') . '
</div>
</fieldset>

<div class="clear"></div>
</div>
</div>';

		// Do not send the newsletter if there is an attachment format error
		if (!$blnAttachmentsFormatError) {
			$return .= '

<div class="tl_formbody_submit">
<div class="tl_submit_container">
<input type="submit" name="actualize" class="tl_submit" accesskey="p" value="'.specialchars($GLOBALS['TL_LANG']['tl_newsletter']['actualize_preview']).'">
<input type="submit" name="preview" class="tl_submit" accesskey="p" value="'.specialchars($GLOBALS['TL_LANG']['tl_newsletter']['preview']).'">
<input type="submit" id="send" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['tl_newsletter']['send'][0]).'" onclick="return confirm(\''. str_replace("'", "\\'", $GLOBALS['TL_LANG']['tl_newsletter']['sendConfirm']) .'\')">
</div>
</div>';
		}

		$return .= '

</form>';

		unset($_SESSION['TL_PREVIEW_MAIL_ERROR']);
		return $return;
	}


	protected function prepareLinkTracking($strString, $intId, $strEmail, $strExtra) {
		return preg_replace_callback(
			'/(\<a.*href\=")(.*)(")/Ui',
			function($arrMatches) use ($intId, $strEmail, $strExtra) {
				if ( $arrMatches[2]{0} == "#") {
					return $arrMatches[0];
				} else {
					return $arrMatches[1] . \Environment::get('base') . 'tracking/?n=' . $intId . '&e=' . $strEmail . '&t=link&l=' . rtrim(strtr(base64_encode($arrMatches[2]), '+/', '-_'), '=') . $strExtra . $arrMatches[3];
				}
			},
			$strString
		);
	}


	protected function parseSimpleTokens($strString, $arrData) {
		$strReturn = '';
		$arrTags = preg_split('/(\{[^\}]+\})/', $strString, -1, PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);

		// Replace the tags
		foreach ($arrTags as $strTag)
		{
			if (strncmp($strTag, '{if', 3) === 0)
			{
				$strReturn .= preg_replace('/\{if ([A-Za-z0-9_]+)([=!<>]+)([^;$\(\)\[\]\}]+).*\}/i', '<?php if ($arrData[\'$1\'] $2 $3): ?>', $strTag);
			}
			elseif (strncmp($strTag, '{elseif', 7) === 0)
			{
				$strReturn .= preg_replace('/\{elseif ([A-Za-z0-9_]+)([=!<>]+)([^;$\(\)\[\]\}]+).*\}/i', '<?php elseif ($arrData[\'$1\'] $2 $3): ?>', $strTag);
			}
			elseif (strncmp($strTag, '{else', 5) === 0)
			{
				$strReturn .= '<?php else: ?>';
			}
			elseif (strncmp($strTag, '{endif', 6) === 0)
			{
				$strReturn .= '<?php endif; ?>';
			}
			else
			{
				$strReturn .= $strTag;
			}
		}

		// Replace tokens
		$strReturn = str_replace('?><br />', '?>', $strReturn);
		$strReturn = preg_replace('/##([A-Za-z0-9_]+)##/i', '<?php echo $arrData[\'$1\']; ?>', $strReturn);
		$strReturn = str_replace("]; ?>\n", '] . "\n"; ?>' . "\n", $strReturn); // see #7178

		// Eval the code
		ob_start();
		$blnEval = eval("?>" . $strReturn);
		$strReturn = ob_get_contents();
		ob_end_clean();

		// Throw an exception if there is an eval() error
		if ($blnEval === false)
		{
			throw new \Exception("Error parsing simple tokens ($strReturn)");
		}

		// Return the evaled code
		return $strReturn;
	}


	/**
	 * Compile the newsletter and send it
	 * @param \Email
	 * @param \Database\Result
	 * @param array
	 * @param string
	 * @param string
	 * @param string
	 * @return string
	 */
	protected function sendNewsletter(\Email $objEmail, \Database\Result $objNewsletter, $arrRecipient, $text, $body, $css=null)
	{
        $data = $arrRecipient;
        $data['linkWebView'] = self::getWebViewLink( $objNewsletter );
        $data['salutation'] = self::getSalutation( $arrRecipient );


		// Prepare the text content
		$text = \String::parseSimpleTokens($text, $data);;
		// add piwik campaign links
		$text = $this->addPiwikCampaignText ( $text, $objNewsletter->hoja_piwik_campaign );
		
		$objEmail->text = $text;

		// Add the HTML content
		if (!$objNewsletter->sendText)
		{
			// Default template
			if ($objNewsletter->template == '')
			{
				$objNewsletter->template = 'mail_default';
			}

			// Load the mail template
			$objTemplate = new \BackendTemplate($objNewsletter->template);
			$objTemplate->setData($objNewsletter->row());

			$objTemplate->title = $objNewsletter->subject;
			$objTemplate->body = $body;
			$objTemplate->charset = \Config::get('characterSet');
			$objTemplate->css = $css; // Backwards compatibility
			$this->addCssToTemplate ( $objTemplate );
			
			$objTemplate->recipient = $arrRecipient['email'];

			// Parse template
			$html = $objTemplate->parse();
			$html = $this->convertRelativeUrls($html);
			$html = $this->replaceInsertTags($html);
			// $html = $this->prepareLinkTracking($html, $objNewsletter->id, $arrRecipient['email'], $arrRecipient['extra'] ?: '');
			$html = $this->parseSimpleTokens($html, $data );
			
			// add piwik campaign links
			$html = $this->addPiwikCampaignHtml ( $html, $objNewsletter->hoja_piwik_campaign );
						
			// Append to mail object
			$objEmail->html = $html;
			$objEmail->imageDir = TL_ROOT . '/';
		}

		// Deactivate invalid addresses
		try
		{
			$objEmail->sendTo($arrRecipient['email']);
		}
		catch (\Swift_RfcComplianceException $e)
		{
			$_SESSION['REJECTED_RECIPIENTS'][] = $arrRecipient['email'];
		}

		// Rejected recipients
		if ($objEmail->hasFailures())
		{
			$_SESSION['REJECTED_RECIPIENTS'][] = $arrRecipient['email'];
		}

		// HOOK: add custom logic
		if (isset($GLOBALS['TL_HOOKS']['sendNewsletter']) && is_array($GLOBALS['TL_HOOKS']['sendNewsletter']))
		{
			foreach ($GLOBALS['TL_HOOKS']['sendNewsletter'] as $callback)
			{
				$this->import($callback[0]);
				$this->$callback[0]->$callback[1]($objEmail, $objNewsletter, $arrRecipient, $text, $html);
			}
		}
	}
	
	
	public function addCssToTemplate ( $objTemplate ) {
		if ( $objTemplate->hoja_css_file ) {
			$file = \FilesModel::findByUuid ( $objTemplate->hoja_css_file );
			
			$objTemplate->css .= 
				"<style type=\"text/css\"><!--\n" 
				. join ( "", file ( "../".$file->path ))
				."\n--></style>\n";
		}	
	}
	
	
	
	public function addPiwikCampaignText ( $text, $campaign ) {
		if ( ! $campaign || $campaign == "") return $text;
		
		$result = preg_replace_callback ( 
			'#(https?://)([a-zA-Z0-9/\\#\\-_\\.%\\?=]+)\\b#',
			function ( $groups ) use ($campaign) {
				if ( strpos ( $groups[2], '?' ) !== false)
					$bind = '&';
				else
					$bind = '?';

				return $groups[0] . $bind . 'pk_campaign=' . $campaign;			
			},
			$text
		);
		
		return $result;
	}

	
	public function addPiwikCampaignHtml ( $text, $campaign ) {
		if ( ! $campaign || $campaign == "") return $text;
		
		$result = preg_replace_callback ( 
			'#href="(https?://[^"]+)"#',
			function ( $groups ) use ($campaign) {
				if ( strpos ( $groups[1], '?' ) !== false)
					$bind = '&';
				else
					$bind = '?';

				return 'href="' . $groups[1] . $bind . 'pk_campaign=' . $campaign . '"';			
			},
			$text
		);
		
		return $result;
	}
	

    /**
     * get the absolute weblink for the web view of the given newsletter
     * @param $newsletter (either database result or NewsletterModel instance
     * @return
     */
    public static function getWebViewLink ( $newsletter ) {
        if ( is_a ( $newsletter, '\\Database\\Result' )) {
            $temp = new \NewsletterModel ();
            $temp->setRow ( $newsletter->row());
            $newsletter = $temp;
        }


        if (($letterGroup = $newsletter->getRelated('pid')) === null)
        {
            return null;
        }

        if (intval($letterGroup->jumpTo) < 1) {
            return null;
        }

        $jumpTo = $letterGroup->getRelated('jumpTo')->loadDetails();
        if ( $jumpTo === null )
            return null;

        $baseAddress = \Controller::generateFrontendUrl($jumpTo->row(), ((\Config::get('useAutoItem') && !\Config::get('disableAlias')) ?  '/%s' : '/items/%s'));
        $alias = ($newsletter->alias != '' && !\Config::get('disableAlias')) ? $newsletter->alias : $newsletter->id;

        return \Environment::getInstance()->url . '/' . sprintf($baseAddress, $alias);
    }



    /**
     * get the correct salutation for the given recipient
     * @param <unknown> $newsletter
     * @param <unknown> $recipient
     * @return
     */
    public static function getSalutation ( $recipient ) {
        if ( is_object($recipient))
            $recipient = $recipient->row();

        $result = "";
        \System::loadLanguageFile ( "tl_newsletter_recipients");

        $channel = \NewsletterChannelModel::findById ( $recipient['pid']);
        $overrideForm = $channel->hoja_override_salutation;

        $data = array (
            "gender"    => $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_' . $recipient['hoja_nl_gender'] ],
            "firstname" => $recipient['hoja_nl_firstname'],
            "lastname"  => $recipient['hoja_nl_lastname'],
            "title"     => $recipient['hoja_nl_title'],
        );
        $template = "";
        if ( ($recipient['hoja_nl_form_of_address'] == "informal" || $overrideForm == "informal") && $overrideForm  != "formal" ) {
            if ( $recipient['hoja_nl_firstname'] ) {
                $template = $channel->hoja_salutation_informal;
            }
        } else if ( $recipient['hoja_nl_form_of_address'] == "formal" || $overrideForm == "formal" ) {
            if ( $recipient['hoja_nl_gender'] && $recipient['hoja_nl_lastname'] ) {
                $template = $channel->hoja_salutation_formal;
            }
        }

        if ( $template ) {
            $result = \Controller::parseSimpleTokens( $template, $data );
            $result = preg_replace( "# +#", " ", $result);
        } else {
            $result = $channel->hoja_salutation_default;
        }

        return $result;
    }


    protected function _initPreviewData () {
        if ( \Input::get('actualize')) {
            $_SESSION['hoja_preview'] = array (
                'email' => \Input::get('recipient'),
                'hoja_nl_title' => \Input::get('title'),
                'hoja_nl_firstname' => \Input::get('firstname'),
                'hoja_nl_lastname' => \Input::get('lastname'),
                'hoja_nl_gender' => \Input::get('gender'),
                'hoja_nl_form_of_address' => \Input::get('form_of_address'),
            );

			$referer = preg_replace('/&(amp;)?(start|mpc|token|recipient|preview|actualize)=[^&]*/', '', \Environment::get('request'));
            // abort (to not send the letter)
            $this->redirect ( $referer );
        } elseif ( ! $_SESSION['hoja_preview']) {
            $arrName = explode(' ', $this->User->name);
            $_SESSION['hoja_preview'] = array (
                'hoja_nl_firstname' => $arrName[0],
                'hoja_nl_lastname' => $arrName[sizeof($arrName)-1],
                'hoja_nl_form_of_address' => 'formal',
                'hoja_nl_gender' => null,
                'hoja_nl_title' => null,
                'email' => $this->User->email,
            );
        }
    }


    protected function getUnsubscriptionLink ( $recipient ) {
        if ( ! $recipient->hoja_nl_unsubscibe_id ) {
            \HoJa\NLExtended\ModuleUnsubscribeDouble::ensureUnsubscriptionId( \NewsletterRecipientsModel::findById ( $recipient->id ));
        }

        $channel = \NewsletterChannelModel::findById ( $recipient->pid );

        $page = $channel->getRelated('hoja_unsubscribe_page')->loadDetails();
        if ( $page === null )
            return null;

        $baseAddress = \Controller::generateFrontendUrl($page->row(), ((\Config::get('useAutoItem') && !\Config::get('disableAlias')) ?  '/%s' : '/items/%s'));

        $url = \Environment::getInstance()->url . '/' . sprintf($baseAddress, $alias);
        $bind = (strpos($url, "?") === null) ? '?' : '&';
        return $url . $bind . \HoJa\NLExtended\ModuleUnsubscribeDouble::$tokenVar . "=" . $recipient->hoja_nl_unsubscribe_id;
    }


}
