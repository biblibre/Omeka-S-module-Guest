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
use GuestUser\Entity\GuestUserTokens;
use Omeka\Service\Mailer;
class GuestUserController extends AbstractActionController
{
    public function init()
    {
//        $this->_helper->db->setDefaultModelName('User');
        //      $this->_auth = $this->getInvokeArg('bootstrap')->getResource('Auth');
    }

    public function loginAction()
    {

        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            return;
        }


return         $this->redirect('/admin/login');
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
        $registerLabel = $this->getOption('guest_user_capabilities') ? $this->getOption('guest_user_capabilities') : $this->translate('Register');

        $view->setVariable('registerLabel',$registerLabel);
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
                    $user->setPassword($formData['new_password']);
                    $user->setIsActive(true);

                    $message = $this->translate("Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.");
                    $this->messenger()->addSuccess($message);

                    //$this->getServiceLocator()->get('Omeka\Mailer')->sendUserActivation($user);
                    $this->createGuestUserAndSendMail($formData,$user);
/*                    if ($redirectUrl = $this->params()->fromQuery('redirect'))
                        return $this->redirect()->toUrl($redirectUrl);
                        return $this->redirect('/');*/
                }
            } else {

                $this->messenger()->addError('There was an error during validation');
            }
        }



        return $view;
    }

    protected function save($entity) {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $em->persist($entity);
        $em->flush();

    }


    public function createGuestUserAndSendMail($formData,$user) {
        $guest = new GuestUserTokens;
        $guest->setEmail($formData['o:email']);
        $guest->setUser($user);
        $guest->setToken(sha1("tOkenS@1t" . microtime()));
//        $token = $this->_createToken($user);
        $this->save($guest);
      $this->_sendConfirmationEmail($user, $guest); //confirms that they registration request is legit
    }


    public function meAction()
    {
        $user = current_user();
        if(!$user) {
            $this->redirect('/');
        }
        $widgets = [];
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
        $token = $this->params()->fromQuery('token');
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $records = $em->getRepository('GuestUser\Entity\GuestUserTokens')->findBy(['token'=>$token]);

        if($record = reset($records)) {
            $record->setConfirmed(true);
            $this->save($record);
            $user = $em->find('Omeka\Entity\User',$record->getUser()->getId());
            $siteUrl='';
            $siteTitle='';
            $body = $this->translate("Thanks for joining %s!", $siteTitle);
            $body .= "<p>" . $this->translate("You can now log into %s using the password you chose.", "<a href='$siteUrl'>$siteTitle</a>") . "</p>";

            $this->messenger()->addError($body, 'success');
            $this->redirect('users/login');
        } else {
            $this->messenger()->addError($this->translate('Invalid token stop'), 'error');
        }
    }

    protected function _getForm($options)
    {
        $form = new UserForm($this->getServiceLocator(),null,$options);
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



    protected function _sendConfirmationEmail($user, $token)
    {

        $siteTitle = $this->getOption('site_title');

        $subject = $this->translate("Your request to join %s", $siteTitle);
        $url =  '/guestuser/confirm/token/' . $token->getToken();

        $body = $this->translate("You have registered for an account on %s. Please confirm your registration by following %s.  If you did not request to join %s please disregard this email.", "<a href='$url'>$siteTitle</a>", "<a href='$url'>" . $this->translate('this link') . "</a>", $siteTitle);

        $mailer = $this->getServiceLocator()->get('Omeka\Mailer');
        $message = $mailer->createMessage();
        $message->addTo($user->getEmail(), $user->getName())
            ->setSubject($subject)
            ->setBody($body);

        try {
//            $mailer->send($message);
            echo $body;

        } catch (Exception $e) {
            _log($e);
        }
    }



}
