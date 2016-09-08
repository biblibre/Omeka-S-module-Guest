<?php

namespace GuestUser\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Omeka\Form\LoginForm;
use Search\Form\BasicForm;
use Omeka\Entity\ApiKey;
use Omeka\Entity\User;
use Omeka\Form\UserForm;
use Omeka\Form\ForgotPasswordForm;
use Omeka\Form\UserKeyForm;
use Omeka\Form\UserPasswordForm;
use GuestUser\Entity\GuestUserTokens;
use Omeka\Service\Mailer;
use Zend\Session\Container;

class GuestUserController extends AbstractActionController
{
    protected $authenticationService;
    protected $entityManager;
    protected $logger;

    public function loginAction()
    {
        $auth = $this->getAuthenticationService();
        if ($auth->hasIdentity()) {
            return $this->redirect()->toRoute('site', [], true);
        }

        $form = $this->getForm(LoginForm::class);
        $view = new ViewModel;
        $view->setVariable('form', $form);
        if (!$this->checkPostAndValidForm($form))
            return $view;

        $validatedData = $form->getData();
        $sessionManager = Container::getDefaultManager();
        $sessionManager->regenerateId();

        $adapter = $auth->getAdapter();
        $adapter->setIdentity($validatedData['email']);
        $adapter->setCredential($validatedData['password']);
        $result = $auth->authenticate();
        if (!$result->isValid()) {
            $this->messenger()->addError(implode(';',$result->getMessages()));
            return $view;
        }

        $this->messenger()->addSuccess('Successfully logged in');
        $redirectUrl = $this->params()->fromQuery('redirect');
        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }
        return $this->redirect()->toUrl($this->currentSite()->url());
    }

    public function logoutAction()
    {
        $auth = $this->getAuthenticationService();
        $auth->clearIdentity();
        $sessionManager = Container::getDefaultManager();
        $sessionManager->destroy();
        $this->messenger()->addSuccess('Successfully logged out');
        $redirectUrl = $this->params()->fromQuery('redirect');
        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        return $this->redirect()->toUrl($this->currentSite()->url());
    }


    public function forgotPasswordAction()
    {
        $authentication = $this->getAuthenticationService();
        if ($authentication->hasIdentity()) {
            return $this->redirect()->toRoute('admin');
        }

        $form = $this->getForm(ForgotPasswordForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $data = $this->getRequest()->getPost();
        $form->setData($data);
        if (!$form->isValid()) {
            $this->messenger()->addError('Activation unsuccessful');
            return $view;
        }
        $entityManager = $this->getEntityManager();
        $user =  $entityManager->getRepository('Omeka\Entity\User')
                               ->findOneBy([
                                            'email' => $data['email'],
                                            'isActive' => true,
                                            ]);
        $entityManager->persist($user);
        if ($user) {
            $passwordCreation = $entityManager
                ->getRepository('Omeka\Entity\PasswordCreation')
                ->findOneBy(['user' => $user]);
            if ($passwordCreation) {
                $entityManager->remove($passwordCreation);
                $entityManager->flush();
            }
            $this->mailer()->sendResetPassword($user);
        }

        $this->messenger()->addSuccess('Check your email for instructions on how to reset your password');

        return $view;
    }


    protected function checkPostAndValidForm($form) {
        if (!$this->getRequest()->isPost())
            return false;

        $form->setData($this->params()->fromPost());
        if (!$form->isValid()) {
            $this->messenger()->addError('Email or Password invalid');
            return false;
        }
        return true;
    }

    protected function getOption($key)
    {
        return $this->settings()->get($key);
    }

    public function registerAction()
    {
        $user = new User();
        $user->setRole('guest');
        $form = $this->_getForm(['user'=>$user, 'include_role' => false]);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $registerLabel = $this->getOption('guest_user_capabilities') ? $this->getOption('guest_user_capabilities') : $this->translate('Register');

        $view->setVariable('registerLabel',$registerLabel);

        if (!$this->checkPostAndValidForm($form))
            return $view;

        $formData = $form->getData();
        $formData['o:role'] = 'guest';
        $response = $this->api()->create('users', $formData);
        if ($response->isError()) {
            $form->setMessages($response->getErrors());
            return $view;
        }
        $user = $response->getContent()->getEntity();
        $user->setPassword($formData['new_password']);
        $user->setIsActive(true);

        $message = $this->translate("Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.");
        $this->messenger()->addSuccess($message);

        $this->createGuestUserAndSendMail($formData,$user);


        return $view;

    }

    protected function save($entity)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }


    public function createGuestUserAndSendMail($formData,$user) {
        $guest = new GuestUserTokens;
        $guest->setEmail($formData['o:email']);
        $guest->setUser($user);
        $guest->setToken(sha1("tOkenS@1t" . microtime()));
        $this->save($guest);

        $this->_sendConfirmationEmail($user, $guest); //confirms that they registration request is legit
    }



    public function updateAccountAction() {
        if (!($user = $this->getAuthenticationService()->getIdentity())) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $view = new ViewModel;
        $readResponse = $this->api()->read('users', $user->getId());
        $user_content = $readResponse->getContent();
        $em = $this->getEntityManager();
        $label = $this->getOption('guest_user_dashboard_label') ? $this->getOption('guest_user_dashboard_label') : $this->translate('My account');
        $form = $this->_getForm(['user'=>$user, 'include_role' => false]);

        $form->setData($user_content->jsonSerialize());
        $view->setVariable('form', $form);
        $view->setVariable('label',$label);
        if (!$this->getRequest()->isPost())
            return $view;
        $data_post=$this->params()->fromPost();
        if ($data_post['new_password']=='') {
            unset($data_post['new_password']);
            unset($data_post['new_password_confirm']);
        }
        else if ($data_post['new_password']===$data_post['new_password_confirm']) {
            $user->setPassword($data_post['new_password']);
            $em->flush();
        }
        $form->setData(array_merge($user_content->jsonSerialize(),$data_post));

        if (!$form->isValid()) {
            $this->messenger()->addError('Email or Password invalid');
            return false;
        }

        $response = $this->api()->update('users', $user->getId(),$form->getData());
        if ($response->isError()) {
            $form->setMessages($response->getErrors());
            return $view;
        }


        $message = $this->translate("Your modifications has been saved.");
        $this->messenger()->addSuccess($message);
        return $view;

    }


    public function meAction()
    {

        $auth = $this->getAuthenticationService();
        if (!$auth->hasIdentity())
            $this->redirect()->toUrl($this->currentSite()->url());

        $widgets = [];

        $widget = ['label'=> $this->translate('My Account')];
        $accountUrl = $this->currentSite()->url().'/guestuser/update-account';
        $html = "<ul>";
        $html .= "<li><a href='$accountUrl'>" . $this->translate("Update account info and password") . "</a></li>";
        $html .= "</ul>";
        $widget['content'] = $html;
        $widgets[] = $widget;
        $view = new ViewModel;

        $view->setVariable('widgets', $widgets);
        return $view;
    }

    public function staleTokenAction()
    {
        $auth = $this->getInvokeArg('bootstrap')->getResource('Auth');
        $auth->clearIdentity();
    }

    public function confirmAction()
    {
        $token = $this->params()->fromQuery('token');
        $em = $this->getEntityManager();
        $records = $em->getRepository('GuestUser\Entity\GuestUserTokens')->findBy(['token'=>$token]);

        if(!($record = reset($records)))
            return $this->messenger()->addError($this->translate('Invalid token stop'), 'error');

        $record->setConfirmed(true);
        $this->save($record);
        $user = $em->find('Omeka\Entity\User',$record->getUser()->getId());
        $siteTitle= $this->currentSite()->title();
        $body = sprintf($this->translate("Thanks for joining %s! "), $siteTitle);
        $body .= $this->translate("You can now log using the password you chose.");

        $this->messenger()->addSuccess($body);
        $this->redirect()->toUrl($this->currentSite()->url().'/guestuser/login');
    }

    protected function _getForm($options)
    {
        $form = $this->getForm(UserForm::class, $options);
        $form->add(['name' => 'new_password',
                    'type' => 'password',
                    'options' => [
                                  'label'         => $this->translate('Password'),
                                  'required'      => true,
                                  'class'         => 'textinput',
                                  'errorMessages' => array($this->translate('New password must be typed correctly twice.'))
                    ]

                    ]);

        $form->add(['name' => 'new_password_confirm',
                    'type' => 'password',
                    'options' => [
                                  'label'         => $this->translate('Password again for match'),
                                  'required'      => true,
                                  'class'         => 'textinput',
                                  'errorMessages' => array($this->translate('New password must be typed correctly twice.')) ]
                    ]);

        return $form;
    }



    protected function _sendConfirmationEmail($user, $token)
    {

        $siteTitle = $this->currentSite()->title();

        $subject = sprintf($this->translate("Your request to join %s"), $siteTitle);
        $url =  $this->currentSite()->siteUrl(null,true).'/guestuser/confirm?token=' . $token->getToken();
        $body = sprintf($this->translate("You have registered for an account on %s. Please confirm your registration by following %s.  If you did not request to join %s please disregard this email."), "<a href='$url'>$siteTitle</a>", "<a href='$url'>" . $this->translate('this link') . "</a>", $siteTitle);

        $mailer = $this->mailer();
        $message = $mailer->createMessage();
        $message->addTo($user->getEmail(), $user->getName())
                ->setSubject($subject)
                ->setBody($body);
        try {
            $mailer->send($message);
        } catch (Exception $e) {
            $logger = $this->getLogger();
            $logger->err((string) $e);
        }
    }

    public function setAuthenticationService($authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    public function getAuthenticationService()
    {
        return $this->authenticationService;
    }

    public function setEntityManager($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager()
    {
        return $this->entityManager;
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function getLogger()
    {
        return $this->logger;
    }
}
