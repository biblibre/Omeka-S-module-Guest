<?php
namespace GuestUser\Controller\Site;

use Doctrine\ORM\EntityManager;
use GuestUser\Entity\GuestUserToken;
use Omeka\Entity\User;
use Omeka\Form\ForgotPasswordForm;
use Omeka\Form\LoginForm;
use Omeka\Form\UserForm;
use Omeka\Stdlib\Message;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container;
use Zend\View\Model\ViewModel;

class GuestUserController extends AbstractActionController
{
    /**
     * @var AuthenticationService
     */
    protected $authenticationService;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    public function loginAction()
    {
        $auth = $this->getAuthenticationService();
        if ($auth->hasIdentity()) {
            return $this->redirect()->toRoute('site', [], true);
        }

        $form = $this->getForm(LoginForm::class);
        $view = new ViewModel;
        $view->setVariable('form', $form);
        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $validatedData = $form->getData();
        $sessionManager = Container::getDefaultManager();
        $sessionManager->regenerateId();

        $adapter = $auth->getAdapter();
        $adapter->setIdentity($validatedData['email']);
        $adapter->setCredential($validatedData['password']);
        $result = $auth->authenticate();
        if (!$result->isValid()) {
            $this->messenger()->addError(implode(';', $result->getMessages()));
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
        $this->messenger()->addSuccess('Successfully logged out'); // @translate
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
            $this->messenger()->addError('Activation unsuccessful'); // @translate
            return $view;
        }

        $entityManager = $this->getEntityManager();
        $user = $entityManager->getRepository(User::class)
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

        $this->messenger()->addSuccess('Check your email for instructions on how to reset your password'); // @translate

        return $view;
    }

    public function registerAction()
    {
        $user = new User();
        $user->setRole(\GuestUser\Permissions\Acl::ROLE_GUEST);
        $form = $this->_getForm(['user' => $user, 'include_role' => false]);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $registerLabel = $this->getOption('guestuser_capabilities')
            ? $this->getOption('guestuser_capabilities')
            : $this->translate('Register'); // @translate

        $view->setVariable('registerLabel', $registerLabel);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        $formData = $form->getData();
        $userInfo = $formData['user-information'];
        $userInfo['o:role'] = \GuestUser\Permissions\Acl::ROLE_GUEST;
        $response = $this->api()->create('users', $userInfo);
        $user = $response->getContent()->getEntity();
        $user->setPassword($formData['change-password']['password']);
        $user->setIsActive(true);

        $message = $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.'); // @translate
        $this->messenger()->addSuccess($message);

        $this->createGuestUserAndSendMail($user);

        return $view;
    }

    public function createGuestUserAndSendMail($user)
    {
        $guest = new GuestUserToken;
        $guest->setEmail($user->getEmail());
        $guest->setUser($user);
        $guest->setToken(sha1("tOkenS@1t" . microtime()));
        $this->save($guest);

        // Confirms that they registration request is legit.
        $this->_sendConfirmationEmail($user, $guest);
    }

    public function updateAccountAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $view = new ViewModel;
        $userRepr = $this->api()->read('users', $user->getId())->getContent();
        $label = $this->getOption('guestuser_dashboard_label')
            ? $this->getOption('guestuser_dashboard_label')
            : $this->translate('My account'); // @translate
        $form = $this->_getForm(['user' => $user, 'include_role' => false]);

        $form->setData($userRepr->jsonSerialize());
        $view->setVariable('form', $form);
        $view->setVariable('label', $label);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $data_post = $this->params()->fromPost();
        $form->setData(array_merge($userRepr->jsonSerialize(), $data_post));

        if (!$form->isValid()) {
            $this->messenger()->addError('Email or Password invalid'); // @translate
            return false;
        }

        $formData = $form->getData();

        $response = $this->api()->update('users', $user->getId(), $formData['user-information']);

        if (isset($formData['change-password']['password'])) {
            $user->setPassword($formData['change-password']['password']);
            $this->getEntityManager()->flush();
        }

        $message = $this->translate('Your modifications have been saved.'); // @translate
        $this->messenger()->addSuccess($message);
        return $view;
    }

    public function meAction()
    {
        $auth = $this->getAuthenticationService();
        if (!$auth->hasIdentity()) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $widgets = [];

        $widget = ['label' => $this->translate('My Account')]; // @translate
        $accountUrl = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'update-account',
        ]);
        $html = '<ul>';
        $html .= '<li><a href=' . $accountUrl . '>';
        $html .= $this->translate('Update account info and password'); // @translate
        $html .= '</a></li>';
        $html .= '</ul>';
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
        $records = $em->getRepository(GuestUserToken::class)->findBy(['token' => $token]);
        $record = reset($records);
        if (empty($record)) {
            return $this->messenger()->addError($this->translate('Invalid token stop'), 'error'); // @translate
        }

        $record->setConfirmed(true);
        $this->save($record);
        $user = $em->find(User::class, $record->getUser()->getId());

        $siteTitle = $this->currentSite()->title();
        $body = new Message('Thanks for joining %s! You can now log using the password you chose.', // @translate
            $siteTitle);

        $this->messenger()->addSuccess($body);
        $redirectUrl = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'login',
        ]);
        $this->redirect()->toUrl($redirectUrl);
    }

    protected function checkPostAndValidForm($form)
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        $form->setData($this->params()->fromPost());
        if (!$form->isValid()) {
            $this->messenger()->addError('Email or Password invalid'); // @translate
            return false;
        }
        return true;
    }

    protected function getOption($key)
    {
        return $this->settings()->get($key);
    }

    protected function _getForm($options)
    {
        $options = array_merge($options, [
            'include_password' => true,
        ]);

        $form = $this->getForm(UserForm::class, $options);

        return $form;
    }

    protected function _sendConfirmationEmail($user, $token)
    {
        $siteTitle = $this->currentSite()->title();

        $subject = new Message('Your request to join %s', $siteTitle); // @translate
        $url = $this->url()->fromRoute('site/guest-user',
            [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'confirm',
            ],
            [
                'query' => [
                    'token' => $token->getToken(),
                ],
            ]
        );
        $body = new Message(
            'You have registered for an account on %s. Please confirm your registration by following %sthis link%s. If you did not request to join %s please disregard this email.', // @translate
            $this->currentSite()->link(),
            '<a href=' . $url . '>',
            '</a>',
            $siteTitle);

        $mailer = $this->mailer();
        $message = $mailer->createMessage();
        $message->addTo($user->getEmail(), $user->getName())
            ->setSubject($subject)
            ->setBody($body);
        try {
            $mailer->send($message);
        } catch (\Exception $e) {
            $this->logger()->err((string) $e);
        }
    }

    protected function save($entity)
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function setAuthenticationService(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    public function getAuthenticationService()
    {
        return $this->authenticationService;
    }

    public function setEntityManager(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getEntityManager()
    {
        return $this->entityManager;
    }
}
