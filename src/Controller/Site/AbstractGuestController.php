<?php
namespace Guest\Controller\Site;

use Doctrine\ORM\EntityManager;
use Guest\Stdlib\PsrMessage;
use Omeka\Entity\User;
use Omeka\Form\UserForm;
use Omeka\View\Model\ApiJsonModel;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * Manage guests pages.
 */
abstract class AbstractGuestController extends AbstractActionController
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
     * @var array
     */
    protected $config;

    /**
     * @param AuthenticationService $authenticationService
     * @param EntityManager $entityManager
     * @param array $config
     */
    public function __construct(
        AuthenticationService $authenticationService,
        EntityManager $entityManager,
        array $config
    ) {
        $this->authenticationService = $authenticationService;
        $this->entityManager = $entityManager;
        $this->config = $config;
    }

    public function apiSessionTokenAction()
    {
        $sessionToken = $this->prepareSessionToken();
        $response = new \Omeka\Api\Response;
        $response->setContent($sessionToken ?: []);
        return new ApiJsonModel($response, $this->getViewOptions());
    }

    protected function getOption($key)
    {
        return $this->settings()->get($key);
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

    protected function prepareSessionToken()
    {
        /** @var \Omeka\Entity\User $user */
        $user = $this->getAuthenticationService()->getIdentity();
        if (!$user) {
            return null;
        }

        // Remove all existing session tokens.
        $keys = $user->getKeys();
        foreach ($keys as $keyId => $key) {
            if ($key->getLabel() === 'guest_session') {
                $keys->remove($keyId);
            }
        }

        // Create a new session token.
        $key = new \Omeka\Entity\ApiKey;
        $key->setId();
        $key->setLabel('guest_session');
        $key->setOwner($user);
        $keyId = $key->getId();
        $keyCredential = $key->setCredential();
        $this->entityManager->persist($key);

        $this->entityManager->flush();

        $user = $this->api()->read('users', ['id' => $user->getId()], [], ['responseContent' => 'reference'])->getContent();
        return [
            'o:user' => $user,
            'key_identity' => $keyId,
            'key_credential' => $keyCredential,
        ];
    }

    /**
     * Prepare the user form for public view.
     *
     * @param User $user
     * @param array $options
     * @return UserForm
     */
    protected function _getForm(User $user = null, array $options = [])
    {
        $defaultOptions = [
            'is_public' => true,
            'user_id' => $user ? $user->getId() : 0,
            'include_password' => true,
            'include_role' => false,
            'include_key' => false,
        ];
        $options += $defaultOptions;

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

    /**
     * Prepare the template.
     *
     * @param string $template In case of a token message, this is the action.
     * @param array $data
     * @return array Filled subject and body as PsrMessage, from templates
     * formatted with moustache style.
     */
    protected function prepareMessage($template, array $data)
    {
        $settings = $this->settings();
        $currentSite = $this->currentSite();
        $default = [
            'main_title' => $settings->get('installation_title', 'Omeka S'),
            'site_title' => $currentSite->title(),
            'site_url' => $currentSite->siteUrl(null, true),
            'user_email' => '',
            'user_name' => '',
            'token' => null,
        ];

        $data += $default;

        if (isset($data['token'])) {
            $data['token'] = $data['token']->getToken();
            $urlOptions = ['force_canonical' => true];
            $urlOptions['query']['token'] = $data['token'];
            $data['token_url'] = $this->url()->fromRoute(
                $template === 'update-email' ? 'site/guest/guest' : 'site/guest/anonymous',
                ['site-slug' => $currentSite->slug(), 'action' => $template],
                $urlOptions
            );
        }

        switch ($template) {
            case 'confirm-email':
                $subject = 'Your request to join {main_title} / {site_title}'; // @translate
                $body = $settings->get(
                    'guest_message_confirm_email',
                    $this->getConfig()['guest']['config']['guest_message_confirm_email']
                );
                break;

            case 'update-email':
                $subject = 'Update email on {main_title} / {site_title}'; // @translate
                $body = $settings->get(
                    'guest_message_update_email',
                    $this->getConfig()['guest']['config']['guest_message_update_email']
                );
                break;

            // Allows to manage derivative modules.
            default:
                $subject = !empty($data['subject']) ? $data['subject'] : '[No subject]'; // @translate
                $body = !empty($data['body']) ? $data['body'] : '[No message]'; // @translate
                break;
        }

        unset($data['subject']);
        unset($data['body']);
        $subject = new PsrMessage($subject, $data);
        $body = new PsrMessage($body, $data);

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * @return \Zend\Authentication\AuthenticationService
     */
    protected function getAuthenticationService()
    {
        return $this->authenticationService;
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return array
     */
    protected function getConfig()
    {
        return $this->config;
    }
}
