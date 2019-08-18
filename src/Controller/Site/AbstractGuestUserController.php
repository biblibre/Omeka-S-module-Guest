<?php
namespace GuestUser\Controller\Site;

use Doctrine\ORM\EntityManager;
use GuestUser\Stdlib\PsrMessage;
use Omeka\Entity\User;
use Omeka\Form\UserForm;
use Zend\Authentication\AuthenticationService;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * Manage guest users pages.
 */
abstract class AbstractGuestUserController extends AbstractActionController
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

    protected $defaultRoles = [
        \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        \Omeka\Permissions\Acl::ROLE_AUTHOR,
        \Omeka\Permissions\Acl::ROLE_REVIEWER,
        \Omeka\Permissions\Acl::ROLE_EDITOR,
        \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
    ];

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

    protected function getOption($key)
    {
        return $this->settings()->get($key);
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
                $template === 'update-email' ? 'site/guest-user/guest' : 'site/guest-user/anonymous',
                ['site-slug' => $currentSite->slug(), 'action' => $template],
                $urlOptions
            );
        }

        switch ($template) {
            case 'confirm-email':
                $subject = 'Your request to join {main_title} / {site_title}'; // @translate
                $body = $settings->get(
                    'guestuser_message_confirm_email',
                    $this->getConfig()['guestuser']['config']['guestuser_message_confirm_email']
                );
                break;

            case 'update-email':
                $subject = 'Update email on {main_title} / {site_title}'; // @translate
                $body = $settings->get(
                    'guestuser_message_update_email',
                    $this->getConfig()['guestuser']['config']['guestuser_message_update_email']
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
     * Check if a request is done via an external application, specified in the
     * config.
     *
     * @return bool
     */
    protected function isExternalApp()
    {
        $requestedWith = $this->params()->fromHeader('X-Requested-With');
        if (empty($requestedWith)) {
            return false;
        }

        $checkRequestedWith = $this->settings()->get('guestuser_check_requested_with');
        if (empty($checkRequestedWith)) {
            return false;
        }

        $requestedWith = $requestedWith->getFieldValue();
        return strpos($requestedWith, $checkRequestedWith) === 0;
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
