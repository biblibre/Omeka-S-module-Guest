<?php
namespace GuestUser\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Search\Form\BasicForm;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;
use Omeka\Form\UserForm;
use Omeka\Form\UserKeyForm;
use Omeka\Form\UserPasswordForm;


class GuestUserController extends AbstractActionController
{
    public function init()
    {
//        $this->_helper->db->setDefaultModelName('User');
        //      $this->_auth = $this->getInvokeArg('bootstrap')->getResource('Auth');
    }

    public function loginAction()
    {
        $session = new Zend_Session_Namespace;
        if(!$session->redirect) {
            $session->redirect = $_SERVER['HTTP_REFERER'];
        }

        $this->redirect('users/login');
    }

    protected function getOption($key) {
        return $this->getServiceLocator()->get('Omeka\Settings')->get($key);
    }


    public function registerAction()
    {
//        if($this->identity()) {
        //          return $this->getRequest()->getServer('HTTP_REFERER');
//        }

        $user = new User();
        $user->setRole('guest');
        $form = $this->_getForm(['user'=>$user, 'include_role' => false]);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        xdebug_break();

        if ($this->getRequest()->isPost()) {
            $form->setData($this->params()->fromPost());

            if ($form->isValid()) {
                $formData = $form->getData();
                $formData['o:role'] = 'guest';
                $response = $this->api()->create('users', $formData);
                if ($response->isError()) {
                    $form->setMessages($response->getErrors());
                } else {
                    $user = $response->getContent()->getEntity();

                    $user->setIsActive(true);

                    $message = $this->translate("Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.");
                    $this->messenger()->addSuccess($message);

//                    $token = $this->_createToken($user);
                    //                  $this->_sendConfirmationEmail($user, $token); //confirms that they registration request is legit

                    $this->getServiceLocator()->get('Omeka\Mailer')->sendUserActivation($user);


                    return $this->redirect()->toUrl($response->getContent()->url());
                }
            } else {

                $this->messenger()->addError('There was an error during validation');
            }
        }



        return $view;
    }

    public function updateAccountAction()
    {
        $user = current_user();

        $form = $this->_getForm(array('user'=>$user));
        $form->getElement('new_password')->setLabel($this->translate("New Password"));
        $form->getElement('new_password')->setRequired(false);
        $form->getElement('new_password_confirm')->setRequired(false);
        $form->addElement('password', 'current_password',
                        array(
                                'label'         => $this->translate('Current Password'),
                                'required'      => true,
                                'class'         => 'textinput',
                        )
        );

        $oldPassword = $form->getElement('current_password');
        $oldPassword->setOrder(0);
        $form->addElement($oldPassword);

        $form->setDefaults($user->toArray());
        $this->view->form = $form;

        if (!$this->getRequest()->isPost() || !$form->isValid($_POST)) {
            return;
        }

        if($user->password != $user->hashPassword($_POST['current_password'])) {
            $this->messenger()->addError($this->translate("Incorrect password"), 'error');
            return;
        }

        $user->setPassword($_POST['new_password']);
        $user->setPostData($_POST);
        try {
            $user->save($_POST);
        } catch (Omeka_Validator_Exception $e) {
            $this->flashValidationErrors($e);
        }
    }

    public function meAction()
    {
        $user = current_user();
        if(!$user) {
            $this->redirect('/');
        }
        $widgets = array();
        $widgets = apply_filters('guest_user_widgets', $widgets);
        $this->view->widgets = $widgets;
    }

    public function staleTokenAction()
    {
        $auth = $this->getInvokeArg('bootstrap')->getResource('Auth');
        $auth->clearIdentity();
    }

    public function confirmAction()
    {
        $db = get_db();
        $token = $this->getRequest()->getParam('token');
        $records = $db->getTable('GuestUserToken')->findBy(array('token'=>$token));
        $record = $records[0];
        if($record) {
            $record->confirmed = true;
            $record->save();
            $user = $db->getTable('User')->find($record->user_id);
            $this->_sendAdminNewConfirmedUserEmail($user);
            $this->_sendConfirmedEmail($user);
            $message = $this->translate("Please check the email we just sent you for the next steps! You're almost there!");
            $this->messenger()->addError($message, 'success');
            $this->redirect('users/login');
        } else {
            $this->messenger()->addError($this->translate('Invalid token'), 'error');
        }
    }

    protected function _getForm($options)
    {
        $form = new UserForm($this->getServiceLocator(),null,$options);

        //need to remove submit so I can add in new elements
//        $form->removeElement('submit');
        /* $form->add('password', 'new_password', */
        /*            [ */
        /*             'label'         => $this->translate('Password'), */
        /*             'required'      => true, */
        /*             'class'         => 'textinput', */
        /*             'validators'    => array( */
        /*                 array('validator' => 'NotEmpty', 'breakChainOnFailure' => true, 'options' => */
        /*                     array( */
        /*                         'messages' => array( */
        /*                             'isEmpty' => $this->translate("New password must be entered.") */
        /*                         ) */
        /*                     ) */
        /*                 ), */
        /*                 array( */
        /*                     'validator' => 'Confirmation', */
        /*                     'options'   => array( */
        /*                         'field'     => 'new_password_confirm', */
        /*                         'messages'  => array( */
        /*                             Omeka_Validate_Confirmation::NOT_MATCH => $this->translate('New password must be typed correctly twice.') */
        /*                         ) */
        /*                      ) */
        /*                 ), */
        /*                 array( */
        /*                     'validator' => 'StringLength', */
        /*                     'options'   => array( */
        /*                         'min' => User::PASSWORD_MIN_LENGTH, */
        /*                         'messages' => array( */
        /*                             Zend_Validate_StringLength::TOO_SHORT => $this->translate("New password must be at least %min% characters long.") */
        /*                         ) */
        /*                     ) */
        /*                 ) */
        /*             ) */
        /*     ) */
        /* ); */
        $form->add(['name' => 'new_password',
                    'type' => 'text',
                    'options' => [
                                  'label'         => $this->translate('Password'),
                                  'required'      => true,
                                  'class'         => 'textinput',
                                  'errorMessages' => array($this->translate('New password must be typed correctly twice.'))
                    ]

        ]);

        $form->add(['name' => 'new_password_confirm',
                    'type' => 'text',
                    'options' => [
                                  'label'         => $this->translate('Password again for match'),
                                  'required'      => true,
                                  'class'         => 'textinput',
                                  'errorMessages' => array($this->translate('New password must be typed correctly twice.')) ]
                    ]);

        /* if(Omeka_Captcha::isConfigured() && ($this->getOption('guest_user_recaptcha') == 1)) { */
        /*     $form->addElement('captcha', 'captcha',  array( */
        /*         'class' => 'hidden', */
        /*         'style' => 'display: none;', */
        /*         'label' => $this->translate("Please verify you're a human"), */
        /*         'type' => 'hidden', */
        /*         'captcha' => Omeka_Captcha::getCaptcha() */
        /*     )); */
        /* } */
        /* if (current_user()) { */
        /*     $submitLabel = $this->translate('Update'); */
        /* } else { */
        /*     $submitLabel = $this->getOption('guest_user_register_text') ? $this->getOption('guest_user_register_text') : $this->translate('Register'); */
        /* } */
        /* $form->addElement('submit', 'submit', array('label' => $submitLabel)); */
        return $form;
    }

    protected function _sendConfirmedEmail($user)
    {
        $siteTitle = $this->getOption('site_title');
        $body = $this->translate("Thanks for joining %s!", $siteTitle);
        $siteUrl = absolute_url('/');
        if($this->getOption('guest_user_open') == 1) {
            $body .= "<p>" . $this->translate("You can now log into %s using the password you chose.", "<a href='$siteUrl'>$siteTitle</a>") . "</p>";
        } else {
            $body .= "<p>" . $this->translate("When an administrator approves your account, you will receive another message that you can use to log in with the password you chose.") . "</p>";
        }

        $subject = $this->translate("Registration for %s", $siteTitle);
        $mail = $this->_getMail($user, $body, $subject);
        try {
            $mail->send();
        } catch (Exception $e) {
            _log($e);
        }
    }

    protected function _sendConfirmationEmail($user, $token)
    {
        $siteTitle = $this->getOption('site_title');
        $url = WEB_ROOT . '/guest-user/user/confirm/token/' . $token->token;
        $siteUrl = absolute_url('/');
        $subject = $this->translate("Your request to join %s", $siteTitle);
        $body = $this->translate("You have registered for an account on %s. Please confirm your registration by following %s.  If you did not request to join %s please disregard this email.", "<a href='$siteUrl'>$siteTitle</a>", "<a href='$url'>" . $this->translate('this link') . "</a>", $siteTitle);

        if($this->getOption('guest_user_instant_access') == 1) {
            $body .= "<p>" . $this->translate("You have temporary access to %s for twenty minutes. You will need to confirm your request to join after that time.", $siteTitle) . "</p>";
        }
        $mail = $this->_getMail($user, $body, $subject);
        try {
            $mail->send();
        } catch (Exception $e) {
            _log($e);
        }
    }

    protected function _sendAdminNewConfirmedUserEmail($user)
    {
        $siteTitle = $this->getOption('site_title');
        $url = WEB_ROOT . "/admin/users/edit/" . $user->id;
        $subject = $this->translate("New request to join %s", $siteTitle);
        $body = "<p>" . $this->translate("A new user has confirmed that they want to join %s : %s" , $siteTitle, "<a href='$url'>" . $user->username . "</a>") . "</p>";
        if($this->getOption('guest_user_open') !== 1) {
            if($this->getOption('guest_user_instant_access') == 1) {
                $body .= "<p>" . $this->translate("%s has temporary access to the site.", $user->username) . "</p>";
            }
            $body .= "<p>" . $this->translate("You will need to make the user active and save the changes to complete registration for %s.", $user->username) . "</p>";
        }

        $mail = $this->_getMail($user, $body, $subject);
        $mail->clearRecipients();
        $mail->addTo($this->getOption('administrator_email'), "$siteTitle Administrator");
         try {
            $mail->send();
        } catch (Exception $e) {
            _log($e);
        }
    }

    protected function _getMail($user, $body, $subject)
    {
        $siteTitle  = $this->getOption('site_title');
        $from = $this->getOption('administrator_email');
        $mail = new Zend_Mail('UTF-8');
        $mail->setBodyHtml($body);
        $mail->setFrom($from, $this->translate("%s Administrator", $siteTitle));
        $mail->addTo($user->email, $user->name);
        $mail->setSubject($subject);
        $mail->addHeader('X-Mailer', 'PHP/' . phpversion());
        return $mail;
    }

    protected function _createToken($user)
    {
        $token = new GuestUserToken();
        $token->user_id = $user->id;
        $token->token = sha1("tOkenS@1t" . microtime());
        if(method_exists($user, 'getEntity')) {
            $token->email = $user->getEntity()->email;
        } else {
            $token->email = $user->email;
        }
        $token->save();
        return $token;
    }
}
