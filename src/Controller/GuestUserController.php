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
    protected function getSite() {
        $readResponse = $this->api()->read('sites', [
                                                     'slug' => $this->params('site-slug')
                                                     ]);
        return $readResponse->getContent();
    }

    public function loginAction()
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            return;
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
        return $this->redirect()->toUrl($this->getSite()->url());

    }

    public function logoutAction()
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
        $sessionManager = Container::getDefaultManager();
        $sessionManager->destroy();
        $this->messenger()->addSuccess('Successfully logged out');
        $redirectUrl = $this->params()->fromQuery('redirect');
        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        return $this->redirect()->toUrl($this->getSite()->url());
    }


    public function forgotPasswordAction()
    {

        $serviceLocator = $this->getServiceLocator();
        $authentication = $serviceLocator->get('Omeka\AuthenticationService');
        if ($authentication->hasIdentity()) {
            return $this->redirect()->toRoute('admin');
        }

        $form = $this->getForm(ForgotPasswordForm::class);

        $view = new ViewModel;
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost())

            return $view;
        $data = $this->getRequest()->getPost();
        $form->setData($data);
        if (!$form->isValid()) {
            $this->messenger()->addError('Activation unsuccessful');
            return $view;
        }
        $entityManager = $serviceLocator->get('Omeka\EntityManager');
        $user =  $entityManager->getRepository('Omeka\Entity\User')
                               ->findOneBy([
                                            'email' => $data['email'],
                                            'isActive' => true,
                                            ]);
        if ($user) {
            $passwordCreation = $entityManager
                ->getRepository('Omeka\Entity\PasswordCreation')
                ->findOneBy(['user' => $user]);
            if ($passwordCreation) {
                $entityManager->remove($passwordCreation);
                $entityManager->flush();
            }
            $serviceLocator->get('Omeka\Mailer')->sendResetPassword($user);
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

    protected function getOption($key) {
        return $this->getServiceLocator()->get('Omeka\Settings')->get($key);
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
        $this->save($guest);

        $this->_sendConfirmationEmail($user, $guest); //confirms that they registration request is legit
    }


    public function meAction()
    {

        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        if (!$auth->hasIdentity())
            $this->redirect('/');

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

        if(!($record = reset($records)))
            return $this->messenger()->addError($this->translate('Invalid token stop'), 'error');

        $record->setConfirmed(true);
        $this->save($record);
        $user = $em->find('Omeka\Entity\User',$record->getUser()->getId());
        $siteUrl='';
        $siteTitle='';
        $body = $this->translate("Thanks for joining %s!", $siteTitle);
        $body .= "<p>" . $this->translate("You can now log into %s using the password you chose.", "<a href='$siteUrl'>$siteTitle</a>") . "</p>";

        $this->messenger()->addError($body, 'success');
        $this->redirect('users/login');
    }

    protected function _getForm($options)
    {
        $form = $this->getForm(UserForm::class);//new UserForm('userform',$options);
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

        return $form;
    }



    protected function _sendConfirmationEmail($user, $token)
    {

        $siteTitle = $this->getSite()->title();

        $subject = $this->translate("Your request to join %s", $siteTitle);
        $url =  $this->getSite()->siteUrl(null,true).'/guestuser/confirm?token=' . $token->getToken();
        $body = sprintf($this->translate("You have registered for an account on %s. Please confirm your registration by following %s.  If you did not request to join %s please disregard this email."), "<a href='$url'>$siteTitle</a>", "<a href='$url'>" . $this->translate('this link') . "</a>", $siteTitle);

        $mailer = $this->getServiceLocator()->get('Omeka\Mailer');
        $message = $mailer->createMessage();
        $message->addTo($user->getEmail(), $user->getName())
                ->setSubject($subject)
                ->setBody($body);

        try {
            $mailer->send($message);

        } catch (Exception $e) {
            _log($e);
        }
    }



}
