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
use Zend\Mvc\MvcEvent;
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
        $auth = $this->getAuthenticationService();
        if ($auth->hasIdentity()) {
            return $this->redirect()->toRoute('site', [], true);
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
        $auth = $this->getAuthenticationService();
        if ($auth->hasIdentity()) {
            return $this->redirect()->toRoute('site', [], true);
        }

        $user = new User();
        $user->setRole(\GuestUser\Permissions\Acl::ROLE_GUEST);

        $form = $this->_getForm($user);

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $registerLabel = $this->getOption('guestuser_capabilities')
            ? $this->getOption('guestuser_capabilities')
            : $this->translate('Register'); // @translate

        $view->setVariable('registerLabel', $registerLabel);

        if (!$this->checkPostAndValidForm($form)) {
            return $view;
        }

        // TODO Add password required only for login.
        $values = $form->getData();
        if (empty($values['change-password']['password'])) {
            $this->messenger()->addError('A password must be set.'); // @translate
            return $view;
        }

        $userInfo = $values['user-information'];
        // TODO Avoid to set the right to change role (fix core).
        $userInfo['o:role'] = \GuestUser\Permissions\Acl::ROLE_GUEST;
        $userInfo['o:is_active'] = false;
        $response = $this->api()->create('users', $userInfo);
        if (!$response) {
            $entityManager = $this->getEntityManager();
            $user = $entityManager->getRepository(User::class)->findOneBy([
                'email' => $userInfo['o:email'],
            ]);
            if ($user) {
                $token = $entityManager->getRepository(GuestUserToken::class)->findOneBy([
                    'email' => $userInfo['o:email'],
                ]);
                if (empty($token) || $token->isConfirmed()) {
                    $this->messenger()->addError('Already registered.'); // @translate
                } else {
                    $this->messenger()->addError('Check your email to confirm your registration.'); // @translate
                }
                return $this->redirect()->toRoute('site/guest-user', ['action' => 'login'], true);
            }

            $this->messenger()->addError('Unknown error.'); // @translate
            return $view;
        }

        $user = $response->getContent()->getEntity();
        $user->setPassword($values['change-password']['password']);
        $user->setRole(\GuestUser\Permissions\Acl::ROLE_GUEST);
        // The account is active, but not confirmed, so login is not possible.
        // Guest user has no right to set active his account.
        $user->setIsActive(true);

        $id = $user->getId();
        if (!empty($values['user-settings'])) {
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $this->userSettings()->set($settingId, $settingValue, $id);
            }
        }

        $message = $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.'); // @translate
        $this->messenger()->addSuccess($message);

        $this->createGuestUserAndSendMail($user);
        return $this->redirect()->toRoute('site/guest-user', ['action' => 'login'], [], true);
    }

    protected function createGuestUserAndSendMail(User $user)
    {
        $guestUserToken = new GuestUserToken;
        $guestUserToken->setEmail($user->getEmail());
        $guestUserToken->setUser($user);
        $guestUserToken->setToken(sha1("tOkenS@1t" . microtime()));
        $em = $this->getEntityManager();
        $em->persist($guestUserToken);
        $em->flush();

        // Confirms that they registration request is legit.
        $this->_sendConfirmationEmail($user, $guestUserToken);
    }

    public function updateAccountAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }
        $id = $user->getId();

        $label = $this->getOption('guestuser_dashboard_label')
            ? $this->getOption('guestuser_dashboard_label')
            : $this->translate('My account'); // @translate

        $userRepr = $this->api()->read('users', $id)->getContent();
        $data = $userRepr->jsonSerialize();

        $form = $this->_getForm($user);
        $form->get('user-information')->populateValues($data);
        $form->get('change-password')->populateValues($data);

        $view = new ViewModel;
        $view->setVariable('user', $user);
        $view->setVariable('form', $form);
        $view->setVariable('label', $label);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        // A security.
        unset($postData['user-information']['o:id']);
        unset($postData['user-information']['o:role']);
        unset($postData['user-information']['o:is_active']);
        $postData['user-information'] = array_replace(
            $data,
            array_intersect_key($postData['user-information'], $data)
        );
        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Email or Password invalid'); // @translate
            return $view;
        }

        $values = $form->getData();
        $response = $this->api($form)->update('users', $user->getId(), $values['user-information']);

        // Stop early if the API update fails.
        if (!$response) {
            $this->messenger()->addFormErrors($form);
            return $view;
        }

        $successMessages = [];
        $successMessages[] = 'Your modifications have been saved.'; // @translate

        if (!empty($values['user-settings'])) {
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $this->userSettings()->set($settingId, $settingValue, $id);
            }
        }

        $passwordValues = $values['change-password'];
        if (!empty($passwordValues['password'])) {
            // TODO Add a current password check when update account.
            // if (!$user->verifyPassword($passwordValues['current-password'])) {
            //     $this->messenger()->addError('The current password entered was invalid'); // @translate
            //     return $view;
            // }
            $user->setPassword($passwordValues['password']);
            $successMessages[] = 'Password successfully changed'; // @translate
        }

        $this->entityManager->flush();

        foreach ($successMessages as $message) {
            $this->messenger()->addSuccess($message);
        }
        return $view;
    }

    public function meAction()
    {
        $auth = $this->getAuthenticationService();
        if (!$auth->hasIdentity()) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $eventManager = $this->getEventManager();
        $args = $eventManager->prepareArgs(['widgets' => []]);
        $args['widgets'][] = $this->widgetUpdateAccount();
        $event = new MvcEvent('guestuser.widgets', $this, $args);
        $this->getEventManager()->triggerEvent($event);

        $view = new ViewModel;
        $view->setVariable('widgets', $args['widgets']);
        return $view;
    }

    protected function widgetUpdateAccount()
    {
        $widget = [];
        $widget['label'] = $this->translate('My Account'); // @translate
        $url = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'update-account',
        ]);
        $html = '<ul>';
        $html .= '<li><a href="' . $url . '">';
        $html .= $this->translate('Update account info and password'); // @translate
        $html .= '</a></li>';
        $html .= '</ul>';
        $widget['content'] = $html;
        return $widget;
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
        $guestUserToken = $em->getRepository(GuestUserToken::class)->findOneBy(['token' => $token]);
        if (empty($guestUserToken)) {
            return $this->messenger()->addError($this->translate('Invalid token stop'), 'error'); // @translate
        }

        $guestUserToken->setConfirmed(true);
        $em->persist($guestUserToken);
        $user = $em->find(User::class, $guestUserToken->getUser()->getId());
        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setIsActive(true);
        $em->persist($user);
        $em->flush();

        $siteTitle = $this->currentSite()->title();
        $body = new Message('Thanks for joining %s! You can now log in using the password you chose.', // @translate
            $siteTitle);

        $this->messenger()->addSuccess($body);
        $redirectUrl = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'login',
        ]);
        return $this->redirect()->toUrl($redirectUrl);
    }

    protected function checkPostAndValidForm(\Zend\Form\Form $form)
    {
        if (!$this->getRequest()->isPost()) {
            return false;
        }

        $postData = $this->params()->fromPost();
        $form->setData($postData);
        if (!$form->isValid()) {
            $this->messenger()->addError('Email or password invalid'); // @translate
            return false;
        }
        return true;
    }

    protected function getOption($key)
    {
        return $this->settings()->get($key);
    }

    protected function _getForm(User $user = null, array $options = [])
    {
        $options = array_merge(
            [
                'user_id' => $user ? $user->getId() : 0,
                'include_password' => true,
                'include_role' => false,
                'include_key' => false,
            ],
            $options
        );
        $form = $this->getForm(UserForm::class, $options);

        $elements = [
            'default_resource_template' => 'user-settings',
        ];
        foreach ($elements as $element => $fieldset) {
            if ($fieldset) {
                $fieldset = $form->get($fieldset);
                $fieldset ? $fieldset->remove($element) : null;
            } else {
                $form->remove($element);
            }
        }
        return $form;
    }

    protected function _sendConfirmationEmail(User $user, $token)
    {
        $mailer = $this->mailer();
        $message = $mailer->createMessage();

        $mainTitle = $mailer->getInstallationTitle();
        $siteTitle = $this->currentSite()->title();
        $siteUrl = $this->currentSite()->siteUrl(null, true);
        $url = $this->url()->fromRoute('site/guest-user',
            [
                'site-slug' => $this->currentSite()->slug(),
                'action' => 'confirm',
            ],
            [
                'query' => [
                    'token' => $token->getToken(),
                ],
                'force_canonical' => true,
            ]
        );

        $subject = new Message('Your request to join %s / %s', $mainTitle, $siteTitle); // @translate
        $body = new Message(
            'You have registered for an account on %s / %s (%s). Please confirm your registration by following this link: %s. If you did not request to join %s please disregard this email.', // @translate
            $mainTitle,
            $siteTitle,
            $siteUrl,
            $url,
            $mainTitle
        );

        $message->addTo($user->getEmail(), $user->getName())
            ->setSubject($subject)
            ->setBody($body);
        try {
            $mailer->send($message);
        } catch (\Exception $e) {
            $this->logger()->err((string) $e);
        }
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
