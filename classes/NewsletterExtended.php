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

    protected static $session_preview_data = "hoja_nl_preview";
    protected static $session_token = "hoja_nl_token";
    

    protected function __construct() {
        parent::__construct();
        $this->import('BackendUser');
        $this->isFlexible = $this->BackendUser->backendTheme == 'flexible';

        $this->import ("Environment");
        $this->import ("Session");
        
        $this->import ('BackendUser', 'User');        
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
     * 
     * @param \DataContainer
     * 
     * @return string html preview and send form to include in the backend
     */
    public function preview (\DataContainer $objDc) {
        $objNewsletter = $this->_initAction ($objDc );
        
        // Return if there is no newsletter
        if (! $objNewsletter) {
            error_log ( "Newsletter not found!");
            return 'Newsletter with id ' . $objDc->id . " not found!";
        }          
        
        // otherwise, the dca of tl_content is not loaded, if the internal cache is active
        $this->loadDataContainer('tl_content');         

        $this->_initPreviewData();
        
        if (!$blnAttachmentsFormatError && \Input::post('token') != '' && \Input::post('token') == $this->Session->get(  self::$session_token ) ) {
            // form was sent
            //$referer = preg_replace('/&(amp;)?(start|mpc|token|recipient|preview|actualize)=[^&]*/', '', \Environment::get('request'));

            // send preview?
            if ( \Input::post('send_preview')) {
                if ( $_SESSION['TL_PREVIEW_MAIL_ERROR']) {
                    \Message::addError ( $GLOBALS['TL_LANG']['ERR']['email'] );
                } else {
                    $this->_sendPreview ( $objNewsletter );
                }
            }
        }

        return $this->_showPreviewForm ( $objNewsletter );
    }
    
    
    /**
     * @brief send the newsletter as a preview
     * 
     * @param <unknown> $objNewsletter 
     * @return  
     */
    protected function _sendPreview ( $objNewsletter ) {
        $this->_setSMTPConfig ( $objNewsletter );
        
        $email = $this->generateEmailObject ( $objNewsletter, $this->_getAttachments( $objNewsletter ));
        
        $text = $this->_getTextContent( $objNewsletter );
        $text = $this->addPiwikCampaignText ( $text, $objNewsletter->hoja_piwik_campaign );
        $email->text = $text;
        
        $html = $this->_getHtmlContent( $objNewsletter );
        $html = $this->addPiwikCampaignHtml ( $html, $objNewsletter->hoja_piwik_campaign );
        $email->html = $html;
        
        $email->imageDir = TL_ROOT . '/';        
        
        $previewData = $this->Session->get(self::$session_preview_data);
        $recipient = $previewData['email'];
        
        if ( $email->sendTo($recipient) ) {
            \Message::addInfo ( sprintf ( $GLOBALS['TL_LANG']['tl_newsletter']['hoja_preview_sent_message'], $recipient ));
        } else {
            \Message::addError ( sprintf ( $GLOBALS['TL_LANG']['tl_newsletter']['hoja_preview_sent_error'], $recipient ));
        }        
    }
    
    /**
     * @brief send the next bulk of newsletter emails
     * 
     * @param \DataContainer $obcDc 
     * @return  string  html content to show in the backend after sending the current bulk
     */
    public function send ( \DataContainer $objDc ) {
        $objNewsletter = $this->_initAction ($objDc );
        
        // Return if there is no newsletter
        if (! $objNewsletter) {
            error_log ( "Newsletter not found!");
            return 'Newsletter with id ' . $objDc->id . " not found!";
        }  
        
        
        $bulkSize = \Input::get('mpc');
        $pause = \Input::get('timeout');
        $counter = \Input::get('counter');
        
        if ( ! $counter ) $counter = 1;
        
        $this->loadDataContainer('tl_content'); 
 

        $objRecipients = $this->Database->prepare ( 
            "SELECT r.* FROM tl_newsletter_recipients r"
            ." LEFT JOIN tl_hoja_newsletter_sent s ON r.id = s.recipient and s.pid = ?"
            ." WHERE r.pid = ? AND r.active=1 AND s.sent_at IS NULL"
        )->limit ( $bulkSize ) -> execute( $objNewsletter->id, $objNewsletter->pid );
         
        $sent = array ();
        while ( $objRecipients->next() ) {
            $sendTo = $this->_sendNewsletter ( $objNewsletter, $objRecipients );
            if ( $sendTo )
                $sent[] = $sendTo;
            else
                $sent[] = "failure in " . $objRecipients->email;
        }
 

        $objTotal = $this->Database->prepare("SELECT COUNT(DISTINCT id) AS count FROM tl_newsletter_recipients WHERE pid=? AND active=1")
            ->execute($objNewsletter->pid);
        $numberOfRecipients = $objTotal->count;
        
        $objSent = $this->Database->prepare("SELECT COUNT(DISTINCT recipient) AS count FROM tl_hoja_newsletter_sent WHERE pid=? AND sent_at IS NOT NULL")
            ->execute($objNewsletter->id);
        $numberSent = $objSent->count;
        
        $objFailure = $this->Database->prepare("SELECT COUNT(DISTINCT recipient) AS count FROM tl_hoja_newsletter_sent WHERE pid=? AND sent_at IS NOT NULL AND status_code > 0")
            ->execute($objNewsletter->id);
        $numberFailure = $objFailure->count;
        

        
        if ( ! $sent ) {
            // we're finished!
            if ( ! $objNewsletter->sent ) {
                $dbRes = $this->Database->prepare ( 
                    "UPDATE tl_newsletter SET sent=1, date=?, hoja_recipients=?, hoja_rejected=? WHERE id=?"
                )->execute ( time(), $numberSent, $numberFailure, $objNewsletter->id );
            }
            
            $backUrl = $this->addToUrl ( 'key=&counter=');
            return 
                '<div id="tl_buttons">'
                .'<a href="'.$backUrl.'". class="header_back" title="" accesskey="b" onclick="Backend.getScrollOffset()">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>'
                .'</div>'                
            
                .'<div class="tl_formbody_edit tl_newsletter_send">'
                .'<div>'
                .'<h2>'.$GLOBALS['TL_LANG']['tl_newsletter']['headline_finished'].'</h2>'
                .'<table class="prev_header">'
                .'<tbody><tr class="row_0">'
                .'<td class="col_0">Newsletter</td>'
                .'<td class="col_1">'.$objNewsletter->subject.'</td>'
                .'</tr>'
                .'<tr class="row_1">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_of_recipients'].'</td>'
                .'<td class="col_1">'.$numberOfRecipients.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_sent'].'</td>'
                .'<td class="col_1">'.$numberSent.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_failure'].'</td>'
                .'<td class="col_1">'.$numberFailure.'</td>'
                .'</tr>'
                .'</tbody></table>'
                .'<p/>'
                .'</div>'
                .'</div>';
        } else {
            $refreshUrl = $this->addToUrl ( "counter=" . ($counter+1));
            $backUrl = $this->addToUrl ( 'key=preview&counter=');
            
            $result =
                '<div class="tl_formbody_edit tl_newsletter_send">'
                .'<div>'
                .'<h2>'.$GLOBALS['TL_LANG']['tl_newsletter']['headline_step'].'</h2>'
                .'<table class="prev_header">'
                .'<tbody><tr class="row_0">'
                .'<td class="col_0">Newsletter</td>'
                .'<td class="col_1">'.$objNewsletter->subject.'</td>'
                .'</tr>'
                .'<tr class="row_1">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_of_recipients'].'</td>'
                .'<td class="col_1">'.$numberOfRecipients.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_sent'].'</td>'
                .'<td class="col_1">'.$numberSent.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_failure'].'</td>'
                .'<td class="col_1">'.$numberFailure.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['step'].'</td>'
                .'<td class="col_1">'.$counter.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['mailsPerCycle'][0].'</td>'
                .'<td class="col_1">'.$bulkSize.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['timeout'][0].'</td>'
                .'<td class="col_1">'.$pause.'</td>'
                .'</tr>'
                .'<tr class="row_2">'
                .'<td class="col_0">'.$GLOBALS['TL_LANG']['tl_newsletter']['just_sent'].'</td>'
                .'<td class="col_1"><ul>';
                
                
            foreach ( $sent as $email ) {
                $result .= '<li>' . $email . '</li>';
            }
                
                
            $result .=  '</ul></td>'
                .'</tr>'
                .'</tbody></table>'               
                .'<p class="info">'.sprintf($GLOBALS['TL_LANG']['tl_newsletter']['message_step'], $refreshUrl).'</p>'
                .'<meta http-equiv="refresh" content="' . $pause . '; URL='.$refreshUrl.'" />'
                
                
                .'<div id="tl_buttons">'
                .'<a href="'.$backUrl.'". class="header_back" title="" accesskey="b" onclick="Backend.getScrollOffset()">'.$GLOBALS['TL_LANG']['MSC']['cancelBT'].'</a>'
                .'</div>'                
                .'<p></p>'
                .'</div>'
                .'</div>'
                ;
                
            return $result;
        }
    }
    
    
    /**
     * @brief actually send the given newsletter to the current recipient, the result will be logged into the hoja_newsletter_sent table
     * @param <unknown> $objNewsletter 
     * @param <unknown> $objRecipient 
     * @return  mixed   email address as confirmation, false on any error
     */
    protected function _sendNewsletter ( $objNewsletter, $objRecipient ) {
        $email = $this->generateEmailObject ( $objNewsletter, $this->_getAttachments( $objNewsletter ));
        
        $text = $this->_getTextContent( $objNewsletter, $objRecipient );
        $text = $this->addPiwikCampaignText ( $text, $objNewsletter->hoja_piwik_campaign );
        $email->text = $text;
        
        $html = $this->_getHtmlContent( $objNewsletter, $objRecipient );
        $html = $this->addPiwikCampaignHtml ( $html, $objNewsletter->hoja_piwik_campaign );
        $email->html = $html;
        
        $email->imageDir = TL_ROOT . '/';        
        
        $sendTo = $objRecipient->email;
        
        try {
            if ( $email->sendTo( $sendTo ) ) {
                if ( ! $email->hasFailures () ) {
                    $code = 0;
                } else {
                    $code = 30;
                }
            } else {
                $code = 10;
            }
        } catch (\Swift_RfcComplianceException $e) {
            $code = 20;
        }

        // something went wrong!
        $dbRes = $this->Database->prepare (
            "INSERT INTO tl_hoja_newsletter_sent (pid, tstamp, recipient, sent_at, status_code) VALUES (?,?,?,?,?)"
        )->execute ($objNewsletter->id, time(), $objRecipient->id, time(), $code);
        
        if ( $code )
            return false;
        else
            return $sendTo;
    }
        
        
    /**
     * @brief (key) action to show the sending protocol of an newsletter
     * 
     * @param \DataContainer $objDc 
     * @return string   html content for the backend  
     */
    public function protocol ( \DataContainer $objDc ) {
        $objNewsletter = $this->_initAction ($objDc );
        
        // Return if there is no newsletter
        if (! $objNewsletter) {
            error_log ( "Newsletter not found!");
            return 'Newsletter with id ' . $objDc->id . " not found!";
        }        

        $this->loadLanguageFile('tl_newsletter_recipients');  
        $this->loadLanguageFile('tl_hoja_newsletter_sent');  


        $objEntries = $this->Database->prepare ( 
            "SELECT r.email, s.sent_at, s.status_code, r.hoja_nl_unsubscribed_date"
            ." FROM tl_hoja_newsletter_sent s "
            ." LEFT JOIN tl_newsletter_recipients r ON r.id = s.recipient"
            ." WHERE s.pid = ?"
            ." ORDER BY s.status_code desc, sent_at"
        )->execute ( $objNewsletter->id );
        
        $objTotal = $this->Database->prepare("SELECT COUNT(DISTINCT id) AS count FROM tl_newsletter_recipients WHERE pid=? AND active=1")
            ->execute($objNewsletter->pid);
        $numberOfRecipients = $objTotal->count;
        
        $objSent = $this->Database->prepare("SELECT COUNT(DISTINCT recipient) AS count FROM tl_hoja_newsletter_sent WHERE pid=? AND sent_at IS NOT NULL")
            ->execute($objNewsletter->id);
        $numberSent = $objSent->count;        
        
        $backUrl = $this->addToUrl ( 'key=');
        
        $result =                 
            '<div id="tl_buttons">'
            .'<a href="'.$backUrl.'". class="header_back" title="" accesskey="b" onclick="Backend.getScrollOffset()">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>'
            .'</div>'                
        
            .'<div class="tl_listing_container parent_view">'
            .'<div class="tl_header">'
            .'<table class="tl_header_table">'
            .'<tbody><tr>'
            .'<td><span class="tl_label">Newsletter</span></td>'
            .'<td>'.$objNewsletter->subject.'</td>'
            .'</tr>'
            .'<tr>'
            .'<td><span class="tl_label">'.$GLOBALS['TL_LANG']['tl_newsletter']['mailingDate'].'</span></td>'
            .'<td>'.$this->parseDate ('d.m.Y H:i:s', $objNewsletter->date ).'</td>'
            .'</tr>'
            .'<tr>'
            .'<td><span class="tl_label">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_of_recipients'].'</span></td>'
            .'<td>'.$numberOfRecipients.'</td>'
            .'</tr>'
            .'<tr>'
            .'<td><span class="tl_label">'.$GLOBALS['TL_LANG']['tl_newsletter']['number_sent'].'</span></td>'
            .'<td>'.$numberSent.'</td>'
            .'</tr>'
            
            .'</tbody></table>'
            .'</div>'
            
            //.'<div class="tl_listing_container list_view">'
            .'<table class="tl_listing showColumns">'
            .'<tbody><tr>'
            .'<th class="tl_folder_tlist col_recipient">Empfänger</th>'
            .'<th class="tl_folder_tlist col_sent_at">'.$GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['sent_at'][0].'</th>'
            .'<th class="tl_folder_tlist col_unsubscribed_date">'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_unsubscribed_date'][0].'</th>'
            .'<th class="tl_folder_tlist col_sent_at">'.$GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['status_code'][0].'</th>'
            .'<th class="tl_folder_tlist tl_right_nowrap">&nbsp;</th>'
            .'</tr>';
            
        while ( $objEntries->next() ) {
            $result .=
                '<tr class="'. (($even=1-$even)?'even':'odd') .' click2edit toggle_select" onmouseover="Theme.hoverRow(this,1)" onmouseout="Theme.hoverRow(this,0)">'
                .'<td class="tl_file_list col_recipient">'.$objEntries->email.'</td>'
                .'<td class="tl_file_list col_sent_at">'.$this->parseDate ( "d.m.Y H:i:s", $objEntries->sent_at ).'</td>'
                .'<td class="tl_file_list col_hoja_nl_unsubscribed_date">'.($objEntries->hoja_nl_unsubscribed_date ? $this->parseDate('d.m.Y', $objEntries->hoja_nl_unsubscribed_date) : '&ndash;').'</td>'
                .'<td class="tl_file_list col_recipient">'.$GLOBALS['TL_LANG']['tl_hoja_newsletter_sent']['status_options'][ $objEntries->status_code].'</td>'
                .'<td class="tl_file_list">&nbsp;</td>'
                .'</tr>';
        }
            
            
        $result .= 
            '</tbody></table>'
            //.'</div>'          
            .'</div>'
            .'</div>';
    
        return $result;
        
    }
    
    /**
     * @brief common initialization steps for all (key) actions, e.g. the newsletter object is loaded
     * 
     * @param \DataContainer $objDc 
     * @return  object  the newsletter to perform the action with  
     */
    protected function _initAction ( \DataContainer $objDc ) {
        $this->loadDataContainer('tl_content'); 
        if (TL_MODE == 'BE') { /** is this not be by definition? */
            $GLOBALS['TL_CSS'][] = 'system/modules/hoja_newsletter_extended/assets/css/style.css';

            if ($this->isFlexible) {
                $GLOBALS['TL_CSS'][] = 'system/modules/hoja_newsletter_extended/assets/css/style-flexible.css';
            }
        }
        
        $objNewsletter = $this->Database->prepare(
            "SELECT n.*, c.useSMTP, c.smtpHost, c.smtpPort, c.smtpUser, c.smtpPass"
            ." FROM tl_newsletter n"
            ." LEFT JOIN tl_newsletter_channel c ON n.pid=c.id WHERE n.id=?"
        )->limit(1)->execute($objDc->id);        
            
        return $objNewsletter;
    }
        


    /**
     * @brief get the url for the newsletter html preview file to be shown in the preview action via iframe
 
     * @param <unknown> $objNewsletter 
     * @return  string (relative) url  
     */
    protected function _getPreviewUrl ( $objNewsletter ) {
        $name = $objNewsletter->alias;
        if ( ! $name )
            $name = $objNewsletter->id;

        return 'system/cache/newsletter/' . $objNewsletter->alias . '.html';
    }



    /**
     * @brief get the name of the cache file for the given newsletter
     * @param <unknown> $objNewsletter 
     * @return  string  file name with complete local path
     */
    protected function _getPreviewCacheFileName ( $objNewsletter ) {
        return TL_ROOT . '/' . $this->_getPreviewUrl ( $objNewsletter) ; 
    }


    /**
     * @brief create a cached version of the newsletter's html content to be shown in an iframe inside the backend
     * @param <unknown> $objNewsletter 
     * @return  string  name of the cache file
     */
    protected function _createPreviewCacheFile ( $objNewsletter ) {
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
                
        $html_preview_content = $this->_getHtmlContent( $objNewsletter );
        
        // circumvent problem with set base and anquor links (add iframe url before)
        $html_preview_content = preg_replace( '/href="#/', 'href="'.$this->Environment->url. "/".$this->_getPreviewUrl($objNewsletter).'#', $html_preview_content );        
                
        $fileName = $this->_getPreviewCacheFileName($objNewsletter);

		file_put_contents(
            $fileName, 
            preg_replace('/^\s+|\n|\r|\s+$/m', '', $html_preview_content)
        );
        
        return $fileName;
    }




    /**
     * @brief show the form with a preview of the newsletter, the form fields to actualize the preview and the buttons to send a preview by email 
     *  and to send the newsletter eventually
     * 
     * @return  html code of the form inclufing the preview
     */
    protected function _showPreviewForm ( $objNewsletter ) {
        $this->_createPreviewCacheFile ( $objNewsletter ) ;
        
        $textContent = $this->_getTextContent ( $objNewsletter );

        $strToken = md5(uniqid(mt_rand(), true));
        $this->Session->set( self::$session_token, $strToken);

        $arrAttachments = $this->_getAttachments($objNewsletter );
        $blnAttachmentsFormatError = ($arrAttachments === false); 

        $sender = sprintf ( ($objNewsletter->senderName != '') ? $objNewsletter->senderName . ' &lt;%s&gt;' : '%s', $objNewsletter->sender );
        
        $previewData = $this->Session->get(self::$session_preview_data);
        
        
        // statistical data
        $objTotal = $this->Database->prepare("SELECT COUNT(DISTINCT id) AS count FROM tl_newsletter_recipients WHERE pid=? AND active=1")
            ->execute($objNewsletter->pid);
        $numberOfRecipients = $objTotal->count;
        
        $objSent = $this->Database->prepare("SELECT COUNT(DISTINCT recipient) AS count FROM tl_hoja_newsletter_sent WHERE pid=? AND sent_at IS NOT NULL")
            ->execute($objNewsletter->id);
        $numberSent = $objSent->count;
        
        
        // Preview newsletter
        $this->loadLanguageFile('tl_newsletter_recipients');
        $return = '
<div id="tl_buttons">
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>
</div>

<h2 class="sub_headline">'.sprintf($GLOBALS['TL_LANG']['tl_newsletter']['preview_headline'], $objNewsletter->id).'</h2>
'.\Message::generate().'
<form action="'.$this->addToUrl ('key=preview') .'" id="tl_newsletter_send" class="tl_form" method="post">
<div class="tl_formbody_edit tl_newsletter_send">
<input type="hidden" name="token" value="' . $strToken . '">
<table class="prev_header">
  <tr class="row_0">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['from'] . '</td>
    <td class="col_1">' . $sender . '</td>
  </tr>
  <tr class="row_1">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['subject'][0] . '</td>
    <td class="col_1">' . $objNewsletter->subject . '</td>
  </tr>
  <tr class="row_2">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['template'][0] . '</td>
    <td class="col_1">' . $objNewsletter->template . '</td>
  </tr>';


    if ( ! empty ( $arrAttachments ) && is_array($arrAttachments )) {
        $return .= '<tr class="row_3">
    <td class="col_0">' . $GLOBALS['TL_LANG']['tl_newsletter']['attachments'] . '</td>
    <td class="col_1">' . implode(', ', $arrAttachments) . '</td>
  </tr>';
    }

    $return .= '
</table>' . (!$objNewsletter->sendText ? '
<iframe class="preview_html" id="preview_html" seamless border="0" width="703px" height="503px" style="padding:0" src="'.$this->_getPreviewUrl( $objNewsletter).'"></iframe>
' : '') . '
<div class="preview_text">
' . nl2br_html5($textContent).'
</div>

<div class="tl_tbox">


<fieldset id="pal_test_legend" class="tl_box">
<legend onclick="AjaxRequest.toggleFieldset(this,\'test_legend\',\'tl_content\')">Testsendung</legend>


<div class="w50 clr">
  <h3><label for="ctrl_recipient">' . $GLOBALS['TL_LANG']['tl_newsletter']['sendPreviewTo'][0] . '</label></h3>
  <input type="text" name="recipient" id="ctrl_recipient" value="'.$previewData['email'].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (isset($_SESSION['TL_PREVIEW_MAIL_ERROR']) ? '
  <div class="tl_error">' . $GLOBALS['TL_LANG']['ERR']['email'] . '</div>' : (($GLOBALS['TL_LANG']['tl_newsletter']['sendPreviewTo'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter']['sendPreviewTo'][1] . '</p>' : '')) . '
</div>


<div class="clr">
  <h3><label for="ctrl_form_of_address">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_label'][0] . '</label></h3>
  <select name="form_of_address" id="ctrl_hoja_nl_form_of_address" class="tl_select" onfocus="Backend.getScrollOffset()" style="opacity: 0;">
   <option value="formal"'.($previewData['hoja_nl_form_of_address']=='formal' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_formal'].'</option>
   <option value="informal"'.($previewData['hoja_nl_form_of_address']=='informal' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_informal'].'</option>
  </select>'
  . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_label'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
      <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_form_of_address_label'][1] . '</p>' : '') . '
</div>

<div class="w50 clr">
  <h3><label for="ctrl_gender">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_label'][0] . '</label></h3>
  <select name="gender" id="ctrl_hoja_nl_gender" class="tl_select" onfocus="Backend.getScrollOffset()" style="opacity: 0;">
   <option value="">-</option>
   <option value="m"'.($previewData['hoja_nl_gender']=='m' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_m'].'</option>
   <option value="f"'.($previewData['hoja_nl_gender']=='f' ? ' selected="selected"' : '').'>'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_f'].'</option>
  </select>'
  . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_label'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
      <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_gender_label'][1] . '</p>' : '') . '
</div>
<div class="w50">
  <h3><label for="ctrl_title">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][0] . '</label></h3>
  <input type="text" name="title" id="ctrl_title" value="'.$previewData['hoja_nl_title'].'" placeholder="'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][0].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_title'][1] . '</p>' : '') . '
</div>


<div class="w50 clr">
  <h3><label for="ctrl_firstname">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][0] . '</label></h3>
  <input type="text" name="firstname" id="ctrl_firstname" value="'.$previewData['hoja_nl_firstname'].'" placeholder="'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][0].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_firstname'][1] . '</p>' : '') . '
</div>
<div class="w50">
  <h3><label for="ctrl_lastname">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][0] . '</label></h3>
  <input type="text" name="lastname" id="ctrl_lastname" value="'.$previewData['hoja_nl_lastname'].'" placeholder="'.$GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][0].'" class="tl_text" onfocus="Backend.getScrollOffset()">' . (($GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][1] && $GLOBALS['TL_CONFIG']['showHelp']) ? '
  <p class="tl_help tl_tip">' . $GLOBALS['TL_LANG']['tl_newsletter_recipients']['hoja_nl_lastname'][1] . '</p>' : '') . '
</div>
</fieldset>

<div class="clear"></div>
</div>
</div>

<div class="tl_formbody_submit">
<div class="tl_submit_container">
<input type="submit" name="actualize" class="tl_submit" accesskey="p" value="'.specialchars($GLOBALS['TL_LANG']['tl_newsletter']['actualize_preview']).'">
<input type="submit" name="send_preview" class="tl_submit" accesskey="p" value="'.specialchars($GLOBALS['TL_LANG']['tl_newsletter']['send_preview']).'">
</div>
</div>
</form>';


        // add form for actually sending the letter
        if ( $blnAttachmentsFormatError ) {
            $return .= '<p>Sending not possible due to attachment file format error!</p>';
        } else {

            $return .= '
<h2 class="sub_headline">'.sprintf($GLOBALS['TL_LANG']['tl_newsletter']['send_headline'], $objNewsletter->id).'</h2>
<form action="'.ampersand(\Environment::get('script'), true).'" id="tl_newsletter_send" class="tl_form" method="get">
<input type="hidden" name="do" value="newsletter">
<input type="hidden" name="table" value="tl_newsletter">
<input type="hidden" name="key" value="send">
<input type="hidden" name="id" value="' . \Input::get('id') . '">
<input type="hidden" name="token" value="' . $strToken . '">

<div class="tl_formbody_edit tl_newsletter_send">

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

</fieldset>   

<p>'.sprintf ( $GLOBALS['TL_LANG']['tl_newsletter']['send_text'], $numberOfRecipients, $numberSent ).'</p>

</div> 
    
<div class="tl_formbody_submit">
<div class="tl_submit_container">
<input type="submit" id="send" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['tl_newsletter']['send'][0]).'" onclick="return confirm(\''. str_replace("'", "\\'", $GLOBALS['TL_LANG']['tl_newsletter']['sendConfirm']) .'\')">
</div>
</div>
</form>';
        }
        
        \Message::reset();

        unset($_SESSION['TL_PREVIEW_MAIL_ERROR']);
        return $return;        
    }



    /**
     * @brief render the plaintext content for the given newsletter and the given sender. If no sender is given, the preview data of the session is used
     * @param <unknown> $objNewsletter 
     * @param <unknown> $recipient a recipient or null for preview
     * @return  string  plain text content of the newsletter
     */
    protected function _getTextContent ( $objNewsletter, $recipient = null ) {
        $text = $this->replaceInsertTags($objNewsletter->text, false);
        $replaceData = $this->_getReplaceData ( $objNewsletter, $recipient );
        return self::parseMySimpleTokens($text, $replaceData );
    }
    
    
    /**
     * @brief get the html content of a newsletter for the given recipient rendered into the mail template
     * @param <unknown> $objNewsletter 
     * @param <unknown> $recipient 
     * @return  string  html content 
     */
    protected function _getHtmlContent ( $objNewsletter, $recipient = null ) {
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
                    $html .= $this->getContentElement($objContentElements->id);
                }
            }
        }

        $replaceData = $this->_getReplaceData ( $objNewsletter, $recipient );        
                
        // render the html content into the mail template
        if ($objNewsletter->template == '') {
            $objNewsletter->template = 'mail_default';
        }        
        
        $objTemplate = new \BackendTemplate($objNewsletter->template);
        $objTemplate->setData($objNewsletter->row());
        $objTemplate->title = $objNewsletter->subject;
        $objTemplate->body = $html;
        $objTemplate->charset = $GLOBALS['TL_CONFIG']['characterSet'];
        $objTemplate->css = $css; // Backwards compatibility
        
        $objTemplate->recipient = ($recipient) ? $recipient->email : $this->Session->get(self::$session_preview_data) ['email'];
        
        $this->addCssToTemplate ( $objTemplate );
        
        $content = $objTemplate->parse();
        
        $content = $this->convertRelativeUrls($content);
        $content = $this->replaceInsertTags($content, false);
        $content = self::parseMySimpleTokens($content, $replaceData );
        
        return $content;
    }
    

    /**
     * @brief get the data for replacing tokens in the content depending on the given recipient
     * @param <unknown> $objNewsletter 
     * @param <unknown> $recipient 
     * @return  hash    data to use in parseSimpleTokens
     */
    protected function _getReplaceData ( $objNewsletter, $recipient = null ) {
        if ( ! $recipient ) {
            $replaceData = array_merge( $this->Session->get ( self::$session_preview_data) , array (
                'tracker_png' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=png',
                'tracker_gif' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=gif',
                'tracker_css' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=css',
                'tracker_js'  => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $this->User->email . '&preview=1&t=js',
                'pid'         => $objNewsletter->pid,
            ));
        } else {
            $replaceData = array_merge($recipient->row(), array(
                'tracker_png' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $recipient->email . '&t=png',
                'tracker_gif' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $recipient->email . '&t=gif',
                'tracker_css' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $recipient->email . '&t=css',
                'tracker_js' => \Environment::get('base') . 'tracking/?n=' . $objNewsletter->id . '&e=' . $recipient->email . '&t=js',
            ));
        }
        
        $replaceData['salutation'] = self::getSalutation( $replaceData );
        $replaceData['linkWebView'] = self::getWebViewLink( $objNewsletter );
        $replaceData['pid'] = $objNewsletter->pid;
        $replaceData['unsubscribe'] = self::getUnsubscriptionLink( $objNewsletter->pid,  $recipient );
        
        return $replaceData;
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


    /**
     * @brief specialized version of the token replacement method, replaces ###name### width data from the given hash
     * @param string $strString 
     * @param hash $arrData 
     * @return  string  string with replaces tokens
     */
    public static function parseMySimpleTokens($strString, $arrData) {
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
     * @brief add a css file to the template, if one is selected
     * @param <unknown> $objTemplate 
     * @return  
     */
    public function addCssToTemplate ( $objTemplate ) {
        if ( $objTemplate->hoja_css_file ) {
            $file = \FilesModel::findByUuid ( $objTemplate->hoja_css_file );
            
            $objTemplate->css .= 
                "<style type=\"text/css\"><!--\n" 
                . join ( "", file ( "../".$file->path ))
                ."\n--></style>\n";
        }	
    }
    
    

    /**
     * @brief add piwik campaign addition to all urls (therefore to be used for text mode)
     * @param <unknown> $text 
     * @param <unknown> $campaign 
     * @return  string
     */
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

    /**
     * @brief add piwik campaing addition to all href attributes (therefore to be used for html mode) 
     * @param <unknown> $text 
     * @param <unknown> $campaign 
     * @return  string  
     */
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
    
    
    /**
     * @brief get the attachments of the given newsletter, returns false, if an error occurred
     * @param <unknown> $objNewsletter 
     * @return  array with attachments
     */
    protected function _getAttachments ( $objNewsletter ) {    
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
        
        if ( $blnAttachmentsFormatError)
            return false;
        else
            return $arrAttachments;
    }


    /**
     * @brief initialize the session preview data either with default values or with the ones received via form submit
     * @return  
     */
    protected function _initPreviewData () {
        if ( \Input::post('actualize') || \Input::post('send_preview')) {
            $this->Session->set( self::$session_preview_data, array (
                'email' => \Input::post('recipient'),
                'hoja_nl_title' => \Input::post('title'),
                'hoja_nl_firstname' => \Input::post('firstname'),
                'hoja_nl_lastname' => \Input::post('lastname'),
                'hoja_nl_gender' => \Input::post('gender'),
                'hoja_nl_form_of_address' => \Input::post('form_of_address'),
            ));
            
            if (!\Validator::isEmail(\Input::post('recipient', true))  && ! preg_match ( '#.*@localhost$#', \Input::post('recipient', true))) {
                $_SESSION['TL_PREVIEW_MAIL_ERROR'] = true;
            }
        } elseif ( ! $this->Session->get( self::$session_preview_data) ) {
            $arrName = explode(' ', $this->User->name);

            $this->Session->set( self::$session_preview_data, array (
                'hoja_nl_firstname' => $arrName[0],
                'hoja_nl_lastname' => $arrName[sizeof($arrName)-1],
                'hoja_nl_form_of_address' => 'formal',
                'hoja_nl_gender' => null,
                'hoja_nl_title' => null,
                'email' => $this->User->email,
            ));
        }
    }

    /**
     * @brief get the unsubscription link for the given newsletter channel
     * @param <unknown> $channelId 
     * @param <unknown> $recipient 
     * @return  string  url of unsubscription page is selected in the channel, null otherwise
     */
    public static function getUnsubscriptionLink ( $channelId, $recipient = null ) {
        $channel = \NewsletterChannelModel::findById ( $channelId );

        $unsubPage = $channel->getRelated('hoja_unsubscribe_page');
        if ( ! $unsubPage ) {
            return null;
        }

        $pageDetails = $unsubPage->loadDetails();
        $url = $pageDetails->getAbsoluteUrl();

        if ( $recipient ) {
            if ( ! $recipient->hoja_nl_unsubscribe_id ) {
                \HoJa\NLExtended\ModuleUnsubscribeDouble::ensureUnsubscriptionId( \NewsletterRecipientsModel::findById ( $recipient->id ));
            }

            $bind = (strpos($url, "?") === null) ? '?' : '&';
            $url .= $bind . \HoJa\NLExtended\ModuleUnsubscribeDouble::$tokenVar . "=" . $recipient->hoja_nl_unsubscribe_id;
        }

        return $url;
    }




    /**
     * method for key import
     *
     * overwrites the core Newsletters' import functionality with a check, that previously unsubscribed email addresses
     * won't be imported again
     *
     * most of the code comes from the original Newsletter class of the core's newsletter module
     *
     * @return
     */
    public function importRecipients()
	{
		if (\Input::get('key') != 'import')
		{
			return '';
		}

		$this->import('BackendUser', 'User');
		$class = $this->User->uploader;

		// See #4086 and #7046
		if (!class_exists($class) || $class == 'DropZone')
		{
			$class = 'FileUpload';
		}

		/** @var \FileUpload $objUploader */
		$objUploader = new $class();

		// Import CSS
		if (\Input::post('FORM_SUBMIT') == 'tl_recipients_import')
		{
			$arrUploaded = $objUploader->uploadTo('system/tmp');

			if (empty($arrUploaded))
			{
				\Message::addError($GLOBALS['TL_LANG']['ERR']['all_fields']);
				$this->reload();
			}

			$time = time();
			$intTotal = 0;
			$intInvalid = 0;
            $intIgnored = 0;

			foreach ($arrUploaded as $strCsvFile)
			{
				$objFile = new \File($strCsvFile, true);

				if ($objFile->extension != 'csv')
				{
					\Message::addError(sprintf($GLOBALS['TL_LANG']['ERR']['filetype'], $objFile->extension));
					continue;
				}

				// Get separator
				switch (\Input::post('separator'))
				{
					case 'semicolon':
						$strSeparator = ';';
						break;

					case 'tabulator':
						$strSeparator = "\t";
						break;

					case 'linebreak':
						$strSeparator = "\n";
						break;

					default:
						$strSeparator = ',';
						break;
				}

				$arrRecipients = array();
				$resFile = $objFile->handle;

				while(($arrRow = @fgetcsv($resFile, null, $strSeparator)) !== false)
				{
                    // chanched: keep csv array structure for additional data
					$arrRecipients[] = $arrRow;
				}

                // changed: don't filter array
				// $arrRecipients = array_filter(array_unique($arrRecipients));

                // changed: load all addresses berforehand to a faster check
                $addresses = $this->Database->prepare("select email from tl_newsletter_recipients WHERE pid = ? order by email")->execute ( \Input::get('id'))->fetchEach ('email');

                // changed: create a hash for a faster search
                $knownAddresses = array ();
                foreach ( $addresses as $address)
                    $knownAddresses [ $address ] = 1;

                $seenAddresses = array ();
                
                // changed: keep track of line number
                $line = 0;
                foreach ($arrRecipients as $arrData)
                {
                    $line ++;
                    // changed: skip first line, if stated via checkbox
                    if ( $line == 1 && \Input::post('skip_first_line')) {
                        \Message::addInfo ($line . ": skipped");
                        continue;
                    }

                    // changed: email address in first column
                    list($email,$firstname,$lastname,$gender,$title,$form,$remarks) = $arrData;


                    // Skip invalid entries
                    if (!\Validator::isEmail($email))
                    {
                        $this->log('Recipient address "' . $email . '" seems to be invalid and has been skipped', __METHOD__, TL_ERROR);
                        \Message::addInfo ( "$line: $email is not a valid address!");

                        ++$intInvalid;
                        continue;
                    }

                    if ( $seenAddresses[$email]) {
                        // email address already in this import
                        \Message::addInfo ( "$line: $email already seen in import file -> ignored!");
                        $intIgnored ++;
                        $seenAddresses[$email] ++;
                    } elseif ( $knownAddresses [ $email ]) {
                        // email address already known
                        \Message::addInfo ( "$line: $email already known: updated data");

                        $this->Database->prepare (
                            "UPDATE tl_newsletter_recipients set hoja_nl_firstname = ?, hoja_nl_lastname = ?, hoja_nl_gender = ?, hoja_nl_title = ?, hoja_nl_form_of_address = ? where email = ? and pid = ?"
                        )->execute (
                            $firstname, $lastname, $gender, $title, $form, $email, \Input::get('id')
                        );
                        $seenAddresses[ $email ] = 1;
                    } else {
                        // completely new address
                        \Message::addInfo ( "$line: imported $email");

                        $this->Database->prepare (
                                "INSERT INTO tl_newsletter_recipients (pid,tstamp,email,active,addedOn,hoja_nl_firstname,hoja_nl_lastname,hoja_nl_gender,hoja_nl_title,hoja_nl_form_of_address,hoja_nl_remarks)"
                                ." VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                            ) ->execute (
                                \Input::get('id'),
                                time(),
                                $email, 1, time(),
                                $firstname, $lastname, $gender, $title, $form,
                                $remarks. "-- Imported " . date('Y-m-d')
                            );

                        ++$intTotal;
                        $seenAddresses[ $email ] = 1;
                    }
                }
            }

            \Message::addConfirmation(sprintf($GLOBALS['TL_LANG']['tl_newsletter_recipients']['confirm'], $intTotal));

            if ($intInvalid > 0){
                \Message::addError(sprintf($GLOBALS['TL_LANG']['tl_newsletter_recipients']['invalid'], $intInvalid));
            }
            
            if ( $intIgnored > 0 ){
                \Message::addError(sprintf($GLOBALS['TL_LANG']['tl_newsletter_recipients']['ignored'], $intIgnored));
            }

            \System::setCookie('BE_PAGE_OFFSET', 0, 0);
            $this->reload();
        }

        // changed: load newsletter language file
        $this->loadLanguageFile('tl_newsletter');

        // changed: added hint for csv file format, added skip first line checkbox
        // Return form
        return '
<div id="tl_buttons">
<a href="'.ampersand(str_replace('&key=import', '', \Environment::get('request'))).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>
</div>
'.\Message::generate()
.'<form action="'.ampersand(\Environment::get('request'), true).'" id="tl_recipients_import" class="tl_form" method="post" enctype="multipart/form-data">'
.'<div class="tl_formbody_edit">'
.$GLOBALS['TL_LANG']['tl_newsletter']['csv_submit_hint']
.'
<input type="hidden" name="FORM_SUBMIT" value="tl_recipients_import">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<input type="hidden" name="MAX_FILE_SIZE" value="'.\Config::get('maxFileSize').'">

<div class="tl_tbox">
  <h3><label for="separator">'.$GLOBALS['TL_LANG']['MSC']['separator'][0].'</label></h3>
  <select name="separator" id="separator" class="tl_select" onfocus="Backend.getScrollOffset()">
    <option value="comma">'.$GLOBALS['TL_LANG']['MSC']['comma'].'</option>
    <option value="semicolon">'.$GLOBALS['TL_LANG']['MSC']['semicolon'].'</option>
    <option value="tabulator">'.$GLOBALS['TL_LANG']['MSC']['tabulator'].'</option>
    <option value="linebreak">'.$GLOBALS['TL_LANG']['MSC']['linebreak'].'</option>
  </select>'.(($GLOBALS['TL_LANG']['MSC']['separator'][1] != '') ? '
  <p class="tl_help tl_tip">'.$GLOBALS['TL_LANG']['MSC']['separator'][1].'</p>' : '').'

<div class="cbx">
<div id="ctrl_guests" class="tl_checkbox_single_container">
<input type="checkbox" name="skip_first_line" id="skip_first_line" class="tl_checkbox" value="1" onfocus="Backend.getScrollOffset()">
<label for="skip_first_line">'.$GLOBALS['TL_LANG']['tl_newsletter']['skip_first_line_label'].'</label></div>
<p class="tl_help tl_tip" title="">'.$GLOBALS['TL_LANG']['tl_newsletter']['skip_first_line_hint'].'</p>
</div>

  <h3>'.$GLOBALS['TL_LANG']['MSC']['source'][0].'</h3>'.$objUploader->generateMarkup().(isset($GLOBALS['TL_LANG']['MSC']['source'][1]) ? '
  <p class="tl_help tl_tip">'.$GLOBALS['TL_LANG']['MSC']['source'][1].'</p>' : '').'
</div>

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
  <input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['tl_newsletter_recipients']['import'][0]).'">
</div>

</div>
</form>';
	}


}
