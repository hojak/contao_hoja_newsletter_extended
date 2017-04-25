<?php


/**
 * adaption of the unsubscription module to introduce a double opt out and personal unsubscription links in sent letters
 */
namespace HoJa\NLExtended;

class ModuleUnsubscribeDouble extends \Module
{

    protected $strTemplate = 'mod_hoja_unsubscribe_double';

    protected static $formId = 'hoja_unsubscribe_double';

    public static $tokenVar = 'tunsub';


    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var \BackendTemplate|object $objTemplate */
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . utf8_strtoupper($GLOBALS['TL_LANG']['FMD']['hoja_nl_unsubscribe_double'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->nl_channels = deserialize($this->nl_channels);

        // Return if there are no channels
        if (!is_array($this->nl_channels) || empty($this->nl_channels))
        {
            return '';
        }

        return parent::generate();
    }



    protected function compile () {
        $blnHasError = false;

        // Unsubscribe
        if (\Input::post('FORM_SUBMIT') == self::$formId) {
            $this->sendUnsubscriptionMail ();
        } else if ( \Input::get(self::$tokenVar) ) {
            $this->unsubscribe();
        }

        $blnHasError = false;
        // Error message
        if (strlen($_SESSION['UNSUBSCRIBE_ERROR']))
        {
            $blnHasError = true;
            $this->Template->mclass = 'error';
            $this->Template->message = $_SESSION['UNSUBSCRIBE_ERROR'];
            $_SESSION['UNSUBSCRIBE_ERROR'] = '';
        }

        // Confirmation message
        if (strlen($_SESSION['UNSUBSCRIBE_CONFIRM']))
        {
            $this->Template->confirm_unsubscription;
            $this->Template->mclass = 'confirm';
            $this->Template->message = $_SESSION['UNSUBSCRIBE_CONFIRM'];
            $_SESSION['UNSUBSCRIBE_CONFIRM'] = '';
        }


		// Default template variables
		$this->Template->email = urldecode(\Input::get('email'));
		$this->Template->submit = specialchars($GLOBALS['TL_LANG']['MSC']['unsubscribe']);
		$this->Template->emailLabel = $GLOBALS['TL_LANG']['MSC']['emailAddress'];
		$this->Template->action = \Environment::get('indexFreeRequest');
		$this->Template->formId = self::$formId;
		$this->Template->id = $this->id;
		$this->Template->hasError = $blnHasError;
    }




    protected function unsubscribe () {
        $this->loadLanguageFile ('tl_module');

        $token = \Input::get(self::$tokenVar);

		if (($subscriptions = \NewsletterRecipientsModel::findBy(array("hoja_nl_unsubscribe_id=? AND active=1"), $token)) !== null) {
            foreach ( $subscriptions as $subscription ) {
                $subscription->active = 0;
                $subscription->hoja_nl_unsubscribed_date = time();
                $subscription->hoja_nl_unsubscribed_pending = null;
                $subscription->hoja_nl_remarks = $subscription->hoja_nl_remarks . "; " . date('Y-m-d H:i:s') . ' unsubscribed by user';
                $subscription->save();
            }

            $_SESSION['UNSUBSCRIBE_CONFIRM'] = $GLOBALS['TL_LANG']['tl_module']['hoja_nl_unsubscribe_confirm'];
            $this->redirect ( $this->addToUrl ( '', true));
        } else {
            $_SESSION['UNSUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['tl_module']['hoja_nl_unsubscribe_no_token'];
            $this->redirect ( $this->addToUrl ( '', true));
        }
    }


    protected function sendUnsubscriptionMail () {
        $this->loadLanguageFile ('tl_module');

        // international domains (e.g. dümmer.de)
        $email = \Idna::encodeEmail(\Input::post('email', true));

        // Validate e-mail address
        if (!\Validator::isEmail($email))
        {
            $_SESSION['UNSUBSCRIBE_ERROR'] = $GLOBALS['TL_LANG']['ERR']['email'];
            $this->reload();
        }

        if (($subscriptions = \NewsletterRecipientsModel::findBy(array("email=? AND active=1"), $email)) !== null) {
            // unify tokens
            self::ensureUnsubscrioptionId ( $subscription );

            $subscriptions->first();

            $parseData = array(
                'token'      => $token,
                'domain'     => \Idna::decode(\Environment::get('host')),
                'link'       => \Idna::decode(\Environment::get('base')) . \Environment::get('request') . ((\Config::get('disableAlias') || strpos(\Environment::get('request'), '?') !== false) ? '&' : '?') . self::$tokenVar.'=' . $token,
                'email'      => $email,
                'salutation' => NewsletterExtended::getSalutation( $subscriptions->current() ),
            );

            // Activation e-mail
            $objEmail = new \Email();
            $objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
            $objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'];
            $objEmail->subject = sprintf($GLOBALS['TL_LANG']['MSC']['nl_subject'], \Idna::decode(\Environment::get('host')));
            $objEmail->text = \StringUtil::parseSimpleTokens($this->hoja_nl_unsubscribe_mail, $parseData);
            $objEmail->sendTo($email);
        }
        

        // finish: redirect or reload
        if ($this->jumpTo && ($target = $this->objModel->getRelated('jumpTo')) !== null) {
            $this->redirect($target->getFrontendUrl());
        } else {
            $_SESSION['UNSUBSCRIBE_MAIL_SENT'] = $GLOBALS['TL_LANG']['tl_module']['hoja_nl_unsubscribe_mail_sent'];
            $this->reload();
        }
    }


    public static function ensureUnsubscriptionId ( $subscriptions ) {

        $token = null;
        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->hoja_nl_unsubscribe_id && ! $token )
                $token = $subscription->hoja_nl_unsubscribe_id;
        }

        if ( ! $token ) {
            do {
                $token = md5(uniqid(mt_rand(), true));
            } while ( \NewsletterRecipientsModel::findBy ( array ("hoja_nl_unsubscribe_id=?"), $token ));
        }

        foreach ( $subscriptions as $subscription ) {
            if ( $subscription->hoja_nl_unsubscribe_id != $token ) {
                $subscription->hoja_nl_unsubscribe_id = $token;
            }
            $subscription->hoja_nl_unsubscribed_pending = time();
            $subscription->save();
        }
    }


}
