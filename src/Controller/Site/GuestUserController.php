<?php
namespace GuestUser\Controller\Site;

use Doctrine\ORM\EntityManager;
use GuestUser\Entity\GuestUserToken;
use GuestUser\Form\AcceptTermsForm;
use GuestUser\Form\EmailForm;
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

    /**
     * @param AuthenticationService $authenticationService
     * @param EntityManager $entityManager
     */
    public function __construct(AuthenticationService $authenticationService, EntityManager $entityManager)
    {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
    }

    public function loginAction()
    {
        $auth = $this->getAuthenticationService();
        if ($auth->hasIdentity()) {
            return $this->redirect()->toRoute('site', [], true);
        }

        $view = new ViewModel;

        // Check if there is a fail from an external authenticator.
        $externalAuth = $this->params()->fromQuery('auth');
        if ($externalAuth === 'error') {
            $view->setVariable('form', null);
            return $view;
        }

        $form = $this->getForm(LoginForm::class);
        $view->setVariable('form', $form);

        if ($externalAuth === 'local') {
            return $view;
        }

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

        $user = $auth->getIdentity();

        $this->messenger()->addSuccess('Successfully logged in'); // @translate

        $agreedTerms = $this->userSettings()->get('guestuser_agreed_terms', null, $user->getId());

        if (!$agreedTerms) {
            $this->messenger()->addWarning(
                'Terms and conditions changed: you must accept them.'); // @translate
            return $this->redirect()->toRoute('site/guest-user', ['action' => 'accept-terms'], [], true);
        }

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
            $userSettings = $this->userSettings();
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
            }
        }

        $message = $this->translate('Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.'); // @translate
        $this->messenger()->addSuccess($message);

        $this->createGuestUserTokenAndSendMail($user);
        return $this->redirect()->toRoute('site/guest-user', ['action' => 'login'], [], true);
    }

    protected function createGuestUserTokenAndSendMail(User $user, $type = 'create', $email = null)
    {
        $guestUserToken = new GuestUserToken;
        $guestUserToken->setEmail($email ?: $user->getEmail());
        $guestUserToken->setUser($user);
        $guestUserToken->setToken(sha1("tOkenS@1t" . microtime()));
        $em = $this->getEntityManager();
        $em->persist($guestUserToken);
        $em->flush();

        switch ($type) {
            case 'update-email':
                $action = 'confirm-email';
                $subject = 'Update email on %1$s / %2$s'; // @translate
                $body = 'You have requested to update email on %1$s / %2$s (%3$s). Please confirm your email by following this link: %4$s. If you did not request to update your email on %1$s, please disregard this email.'; // @translate
                break;
            case 'create':
            default:
                $email = $user->getEmail();
                $action = 'confirm';
                $subject = 'Your request to join %1$s / %2$s'; // @translate
                $body = 'You have registered for an account on %1$s / %2$s (%3$s). Please confirm your registration by following this link: %4$s. If you did not request to join %1$s please disregard this email.'; // @translate
                break;
        }

        // Confirms that they registration request is legit.
        $this->_sendConfirmationEmail($user, $guestUserToken, $email, $action, $subject, $body);
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

        // The email is updated separately for security.
        $emailField = $form->get('user-information')->get('o:email');
        $emailField->setAttribute('disabled', true);
        $emailField->setAttribute('required', false);

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
        unset($postData['user-information']['o:email']);
        unset($postData['user-information']['o:role']);
        unset($postData['user-information']['o:is_active']);
        unset($postData['edit-keys']);
        $postData['user-information'] = array_replace(
            $data,
            array_intersect_key($postData['user-information'], $data)
        );
        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Password invalid'); // @translate
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

        // The values were filtered: no hack is possible with added values.
        if (!empty($values['user-settings'])) {
            $userSettings = $this->userSettings();
            foreach ($values['user-settings'] as $settingId => $settingValue) {
                $userSettings->set($settingId, $settingValue, $id);
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

    public function updateEmailAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }
        $id = $user->getId();

        $userRepr = $this->api()->read('users', $id)->getContent();
        $data = $userRepr->jsonSerialize();

        $form = $this->getForm(EmailForm::class, []);
        $form->populateValues($data);

        $view = new ViewModel;
        $view->setVariable('user', $user);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Email invalid'); // @translate
            return $view;
        }

        $values = $form->getData();
        $email = $values['o:email'];

        $message = new Message($this->translate('Check your email "%s" to confirm the change.'), $email); // @translate
        $this->messenger()->addSuccess($message);

        $this->createGuestUserTokenAndSendMail($user, 'update-email', $email);
        return $this->redirect()->toRoute('site/guest-user', ['action' => 'me'], [], true);
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
        $url = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'update-email',
        ]);
        $html .= '<li><a href="' . $url . '">';
        $html .= $this->translate('Update email'); // @translate
        $html .= '</a></li>';
        $html .= '</ul>';
        $widget['content'] = $html;
        return $widget;
    }

    public function acceptTermsAction()
    {
        $user = $this->identity();
        if (empty($user)) {
            return $this->redirect()->toUrl($this->currentSite()->url());
        }

        $userSettings = $this->userSettings();
        $agreed = $userSettings->get('guestuser_agreed_terms');
        if ($agreed) {
            $message = new Message($this->translate('You already agreed the terms and conditions.')); // @translate
            $this->messenger()->addSuccess($message);
            return $this->redirect()->toRoute('site/guest-user', ['action' => 'me'], [], true);
        }

        $form = $this->getForm(AcceptTermsForm::class, []);

        $text = $this->settings()->get('guestuser_terms_text');
        $page = $this->settings()->get('guestuser_terms_page');

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('text', $text);
        $view->setVariable('page', $page);

        if (!$this->getRequest()->isPost()) {
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);

        if (!$form->isValid()) {
            $this->messenger()->addError('Form invalid'); // @translate
            return $view;
        }

        $message = new Message($this->translate('Thanks for accepting the terms and condtions.')); // @translate
        $this->messenger()->addSuccess($message);

        $userSettings->set('guestuser_agreed_terms', true);

        switch ($this->settings()->get('guestuser_terms_redirect')) {
            case 'home':
                return $this->redirect()->toRoute('top');
            case 'site':
                return $this->redirect()->toRoute('site', [], [], true);
            case 'me':
            default:
                return $this->redirect()->toRoute('site/guest-user', ['action' => 'me'], [], true);
        }
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

    public function confirmEmailAction()
    {
        $token = $this->params()->fromQuery('token');
        $em = $this->getEntityManager();
        $guestUserToken = $em->getRepository(GuestUserToken::class)->findOneBy(['token' => $token]);
        if (empty($guestUserToken)) {
            return $this->messenger()->addError($this->translate('Invalid token stop'), 'error'); // @translate
        }

        $guestUserToken->setConfirmed(true);
        $em->persist($guestUserToken);
        $email = $guestUserToken->getEmail();
        $user = $em->find(User::class, $guestUserToken->getUser()->getId());
        // Bypass api, so no check of acl 'activate-user' for the user himself.
        $user->setEmail($email);
        $em->persist($user);
        $em->flush();

        $siteTitle = $this->currentSite()->title();
        $body = new Message('Your new email "%s" is confirmed.', // @translate
            $email);

        $this->messenger()->addSuccess($body);
        $redirectUrl = $this->url()->fromRoute('site/guest-user', [
            'site-slug' => $this->currentSite()->slug(),
            'action' => 'me',
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
                'is_public' => true,
                'user_id' => $user ? $user->getId() : 0,
                'include_password' => true,
                'include_role' => false,
                'include_key' => false,
            ],
            $options
        );
        $form = $this->getForm(UserForm::class, $options);

        // Remove elements from the admin user form, that shouldn’t be available
        // in public guest form.
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

    protected function _sendConfirmationEmail(User $user, $token, $email, $action, $subject, $body)
    {
        $mailer = $this->mailer();
        $message = $mailer->createMessage();

        $mainTitle = $mailer->getInstallationTitle();
        $siteTitle = $this->currentSite()->title();
        $siteUrl = $this->currentSite()->siteUrl(null, true);
        $url = $this->url()->fromRoute('site/guest-user',
            [
                'site-slug' => $this->currentSite()->slug(),
                'action' => $action,
            ],
            [
                'query' => [
                    'token' => $token->getToken(),
                ],
                'force_canonical' => true,
            ]
        );

        $subject = new Message($subject, $mainTitle, $siteTitle);
        $body = new Message(
            $body,
            $mainTitle,
            $siteTitle,
            $siteUrl,
            $url,
            $mainTitle
        );

        $message->addTo($email, $user->getName())
            ->setSubject($subject)
            ->setBody($body);
        try {
            $mailer->send($message);
        } catch (\Exception $e) {
            $this->logger()->err((string) $e);
        }
    }

    protected function getAuthenticationService()
    {
        return $this->authenticationService;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }
}
