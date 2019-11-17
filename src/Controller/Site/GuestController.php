<?php
namespace Guest\Controller\Site;

use Guest\Form\AcceptTermsForm;
use Guest\Form\EmailForm;
use Guest\Stdlib\PsrMessage;
use Omeka\Entity\User;
use Omeka\View\Model\ApiJsonModel;
use Zend\Mvc\MvcEvent;
use Zend\Session\Container;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

/**
 * Manage guests pages.
 */
class GuestController extends AbstractGuestController
{
    public function logoutAction()
    {
        $this->removeSessionTokens();

        $auth = $this->getAuthenticationService();
        $auth->clearIdentity();

        $sessionManager = Container::getDefaultManager();

        $eventManager = $this->getEventManager();
        $eventManager->trigger('user.logout');

        $sessionManager->destroy();

        $this->messenger()->addSuccess('Successfully logged out'); // @translate
        $redirectUrl = $this->params()->fromQuery('redirect');

        if ($redirectUrl) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        return $this->redirect()->toUrl($this->currentSite()->url());
    }

    public function meAction()
    {
        $eventManager = $this->getEventManager();
        $partial = $this->viewHelpers()->get('partial');

        $widget = [];
        $widget['label'] = $this->translate('My Account'); // @translate
        $widget['content'] = $partial('guest/site/guest/widget/account');

        $args = $eventManager->prepareArgs(['widgets' => []]);
        $args['widgets']['account'] = $widget;

        $eventManager->triggerEvent(new MvcEvent('guest.widgets', $this, $args));

        $view = new ViewModel;
        $view->setVariable('widgets', $args['widgets']);
        return $view;
    }

    public function updateAccountAction()
    {
        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();
        $id = $user->getId();

        $label = $this->getOption('guest_dashboard_label')
            ? $this->getOption('guest_dashboard_label')
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

        // Manage old and new user forms (Omeka 1.4).
        if (array_key_exists('password', $values['change-password'])) {
            $passwordValues = $values['change-password'];
        } else {
            $passwordValues = $values['change-password']['password-confirm'];
        }
        if (!empty($passwordValues['password'])) {
            // TODO Add a current password check when update account. Check is done in Omeka 1.4.
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
        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();

        $isExternalApp = $this->isExternalApp();

        $form = $this->getForm(EmailForm::class, []);
        $form->populateValues(['o:email' => $user->getEmail()]);

        $view = new ViewModel;
        $view->setVariable('user', $user);
        $view->setVariable('form', $form);

        if (!$this->getRequest()->isPost()) {
            if ($isExternalApp) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new PsrMessage('The request should be a POST.'), // @translate
                ]);
            }
            return $view;
        }

        $postData = $this->params()->fromPost();

        $form->setData($postData);

        // TODO Check if the csrf is managed to check validity of the form for external app.
        if ($isExternalApp) {
            $values = $postData;
            $email = $values['o:email'];
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new PsrMessage('"{email}" is not an email.', ['email' => $email]), // @translate
                ]);
            }

            if ($email === $user->getEmail()) {
                return new JsonModel([
                    'result' => 'error',
                    'message' => new PsrMessage('The new email is the same than the current one.'), // @translate
                ]);
            }

            $existUser = $this->getEntityManager()->getRepository(User::class)
                ->findOneBy(['email' => $email]);
            if ($existUser) {
                // Avoid a hack of the database.
                sleep(2);
                return new JsonModel([
                    'result' => 'error',
                    'message' => new PsrMessage('The email "{email}" is not yours.', ['email' => $email]), // @translate
                ]);
            }

            $guestToken = $this->createGuestToken($user);
            $message = $this->prepareMessage('update-email', [
                'user_email' => $email,
                'user_name' => $user->getName(),
                'token' => $guestToken,
            ]);
            $result = $this->sendEmail($email, $message['subject'], $message['body'], $user->getName());
            if (!$result) {
                $message = new PsrMessage('An error occurred when the email was sent.'); // @translate
                $this->logger()->err('[Guest] ' . $message);
                return new JsonModel([
                    'result' => 'error',
                    'message' => $message,
                ]);
            }

            $message = new PsrMessage('Check your email "{email}" to confirm the change.', ['email' => $email]); // @translate
            return new JsonModel([
                'result' => 'success',
                'message' => $message,
            ]);
        }

        if (!$form->isValid()) {
            $this->messenger()->addError('Email invalid'); // @translate
            return $view;
        }

        $values = $form->getData();
        $email = $values['o:email'];

        if ($email === $user->getEmail()) {
            $this->messenger()->addWarning(new PsrMessage('The new email is the same than the current one.')); // @translate
            return $view;
        }

        $existUser = $this->getEntityManager()->getRepository(User::class)
            ->findOneBy(['email' => $email]);
        if ($existUser) {
            // Avoid a hack of the database.
            sleep(2);
            $this->messenger()->addError(new PsrMessage('The email "{email}" is not yours.', ['email' => $email])); // @translate
            return $view;
        }

        $guestToken = $this->createGuestToken($user);
        $message = $this->prepareMessage('update-email', [
            'user_email' => $email,
            'user_name' => $user->getName(),
            'token' => $guestToken,
        ]);
        $result = $this->sendEmail($email, $message['subject'], $message['body'], $user->getName());
        if (!$result) {
            $message = new PsrMessage('An error occurred when the email was sent.'); // @translate
            $this->messenger()->addError($message);
            $this->logger()->err('[Guest] ' . $message);
            return $view;
        }

        $message = new PsrMessage('Check your email "{email}" to confirm the change.', ['email' => $email]); // @translate
        $this->messenger()->addSuccess($message);
        return $this->redirect()->toRoute('site/guest', ['action' => 'me'], [], true);
    }

    public function acceptTermsAction()
    {
        $userSettings = $this->userSettings();
        $agreed = $userSettings->get('guest_agreed_terms');
        if ($agreed) {
            $message = new PsrMessage('You already agreed the terms and conditions.'); // @translate
            $this->messenger()->addSuccess($message);
            return $this->redirect()->toRoute('site/guest', ['action' => 'me'], [], true);
        }

        $forced = $this->settings()->get('guest_terms_force_agree');

        /** @var \Guest\Form\AcceptTermsForm $form */
        // $form = $this->getForm(AcceptTermsForm::class, null, ['forced' => $forced]);
        $form = new AcceptTermsForm();
        $form->setOption('forced', $forced);
        $form->init();

        $text = $this->settings()->get('guest_terms_text');
        $page = $this->settings()->get('guest_terms_page');

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

        $data = $form->getData();
        $accept = (bool) $data['guest_agreed_terms'];
        $userSettings->set('guest_agreed_terms', $accept);

        if (!$accept) {
            if ($forced) {
                $message = new PsrMessage('The access to this website requires you accept the current terms and conditions.'); // @translate
                $this->messenger()->addError($message);
                return $view;
            }
            return $this->redirect()->toRoute('site/guest/guest', ['action' => 'logout'], [], true);
        }

        $message = new PsrMessage('Thanks for accepting the terms and condtions.'); // @translate
        $this->messenger()->addSuccess($message);
        switch ($this->settings()->get('guest_terms_redirect')) {
            case 'home':
                return $this->redirect()->toRoute('top');
            case 'site':
                return $this->redirect()->toRoute('site', [], [], true);
            case 'me':
            default:
                return $this->redirect()->toRoute('site/guest', ['action' => 'me'], [], true);
        }
    }

    public function apiSessionTokenAction()
    {
        $sessionToken = $this->prepareSessionToken();
        $response = new \Omeka\Api\Response;
        $response->setContent($sessionToken);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    protected function removeSessionTokens()
    {
        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();

        // Remove all existing session tokens.
        $keys = $user->getKeys();
        foreach ($keys as $keyId => $key) {
            if ($key->getLabel() === 'guest_session') {
                $keys->remove($keyId);
            }
        }
        $this->entityManager->flush();
    }

    protected function getViewOptions()
    {
        // In a json view (see Omeka\Controller\ApiController), these options
        // are managed via onDispatch(). Here, they are only used with
        // apiSessionTokenAction().
        $viewOptions = [];

        $request = $this->getRequest();

        // Set pretty print.
        $prettyPrint = $request->getQuery('pretty_print');
        if (null !== $prettyPrint) {
            $viewOptions('pretty_print', true);
        }

        // Set the JSONP callback.
        $callback = $request->getQuery('callback');
        if (null !== $callback) {
            $viewOptions('callback', $callback);
        }

        return $viewOptions;
    }
}
