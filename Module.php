<?php
/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2019
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Guest;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Guest\Entity\GuestToken;
use Guest\Permissions\Acl;
use Guest\Stdlib\PsrMessage;
use Omeka\Form\Element\SiteSelect;
use Omeka\Permissions\Assertion\IsSelfAssertion;
use Omeka\Settings\SettingsInterface;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Element;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\Permissions\Acl\Acl as ZendAcl;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    /**
     * {@inheritDoc}
     * @see \Omeka\Module\AbstractModule::onBootstrap()
     * @todo Find the right way to load Guest before other modules in order to add role.
     */
    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $this->addAclRoleAndRules();
        $this->checkAgreement($event);
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        $hasOldGuestUser = $this->checkOldGuestUser();

        parent::install($serviceLocator);

        $settings = $serviceLocator->get('Omeka\Settings');
        $t = $serviceLocator->get('MvcTranslator');
        $config = $this->getConfig();
        $space = strtolower(__NAMESPACE__);
        foreach ($config[$space]['config'] as $name => $value) {
            switch ($name) {
                case 'guest_login_text':
                case 'guest_register_text':
                case 'guest_dashboard_label':
                    $value = $t->translate($value);
                    $settings->set($name, $value);
                    break;
            }
        }

        if ($hasOldGuestUser) {
            require_once __DIR__ . '/data/scripts/upgrade_guest_user.php';
        }
    }

    protected function preUninstall()
    {
        $this->deactivateGuests();
    }

    /**
     * Add ACL role and rules for this module.
     */
    protected function addAclRoleAndRules()
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // This check allows to add the role "guest" by dependencies without
        // complex process. It avoids issues when the module is disabled too.
        // TODO Find a way to set the role "guest" during init or via Omeka\Service\AclFactory (allowing multiple delegators).
        if (!$acl->hasRole(Acl::ROLE_GUEST)) {
            $acl->addRole(Acl::ROLE_GUEST);
        }
        $acl->addRoleLabel(Acl::ROLE_GUEST, 'Guest'); // @translate

        $settings = $services->get('Omeka\Settings');
        $isOpenRegister = $settings->get('guest_open', 'moderate');
        $this->addRulesForAnonymous($acl, $isOpenRegister);
        $this->addRulesForGuest($acl);
    }

    /**
     * Add ACL rules for sites.
     *
     * @param ZendAcl $acl
     * @param bool $isOpenRegister
     */
    protected function addRulesForAnonymous(ZendAcl $acl, $isOpenRegister = 'moderate')
    {
        $acl
            ->allow(
                null,
                [\Guest\Controller\Site\AnonymousController::class]
            );
        if ($isOpenRegister !== 'closed') {
            $acl
                ->allow(
                    null,
                    [\Omeka\Entity\User::class],
                    // Change role and Activate user should be set to allow external
                    // logging (ldap, saml, etc.), not only guest registration here.
                    ['create', 'change-role', 'activate-user']
                )
                ->allow(
                    null,
                    [\Omeka\Api\Adapter\UserAdapter::class],
                    ['create']
                );
        } else {
            $acl
                ->deny(
                    null,
                    [\Guest\Controller\Site\AnonymousController::class],
                    ['register']
                );
        }
    }

    /**
     * Add ACL rules for "guest" role.
     *
     * @param ZendAcl $acl
     */
    protected function addRulesForGuest(ZendAcl $acl)
    {
        $roles = $acl->getRoles();
        $acl
            ->allow(
                $roles,
                [\Guest\Controller\Site\GuestController::class]
            )
            ->allow(
                [Acl::ROLE_GUEST],
                [\Omeka\Entity\User::class],
                ['read', 'update', 'change-password'],
                new IsSelfAssertion
            )
            ->allow(
                [Acl::ROLE_GUEST],
                [\Omeka\Api\Adapter\UserAdapter::class],
                ['read', 'update']
            )
            ->deny(
                [Acl::ROLE_GUEST],
                [
                    'Omeka\Controller\Admin\Asset',
                    'Omeka\Controller\Admin\Index',
                    'Omeka\Controller\Admin\Item',
                    'Omeka\Controller\Admin\ItemSet',
                    'Omeka\Controller\Admin\Job',
                    'Omeka\Controller\Admin\Media',
                    'Omeka\Controller\Admin\Module',
                    'Omeka\Controller\Admin\Property',
                    'Omeka\Controller\Admin\ResourceClass',
                    'Omeka\Controller\Admin\ResourceTemplate',
                    'Omeka\Controller\Admin\Setting',
                    'Omeka\Controller\Admin\SystemInfo',
                    'Omeka\Controller\Admin\User',
                    'Omeka\Controller\Admin\Vocabulary',
                    'Omeka\Controller\SiteAdmin\Index',
                    'Omeka\Controller\SiteAdmin\Page',
                ]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // TODO How to attach all public events only?
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'appendLoginNav']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\UserAdapter::class,
            'api.delete.post',
            [$this, 'deleteGuestToken']
        );

        // Add the guest element form to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            [$this, 'addUserFormElement']
        );
        // Add the guest element form to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_input_filters',
            [$this, 'addUserFormElementFilter']
        );
        // FIXME Use the autoset of the values (in a fieldset) and remove this.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.edit.form.before',
            [$this, 'addUserFormValue']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_input_filters',
            [$this, 'handleMainSettingsFilters']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'handleMainSettingsFilters']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $form = $services->get('FormElementManager')->get(\Guest\Form\ConfigForm::class);
        $form->init();
        return $renderer->formCollection($form);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $result = parent::handleConfigForm($controller);
        if ($result === false) {
            return false;
        }

        $services = $this->getServiceLocator();
        $params = $controller->getRequest()->getPost();
        switch ($params['guest_reset_agreement_terms']) {
            case 'unset':
                $this->resetAgreementsBySql($services, false);
                $message = new PsrMessage('All guests must agreed the terms one more time.'); // @translate
                $controller->messenger()->addSuccess($message);
                break;
            case 'set':
                $this->resetAgreementsBySql($services, true);
                $message = new PsrMessage('All guests agreed the terms.'); // @translate
                $controller->messenger()->addSuccess($message);
                break;
            default:
                break;
        }
    }

    protected function prepareDataToPopulate(SettingsInterface $settings, $settingsType)
    {
        $data = parent::prepareDataToPopulate($settings, $settingsType);
        if (in_array($settingsType, ['settings', 'site_settings'])) {
            if (isset($data['guest_notify_register']) && is_array($data['guest_notify_register'])) {
                $data['guest_notify_register'] = implode("\n", $data['guest_notify_register']);
            }
        }
        return $data;
    }

    public function handleMainSettingsFilters(Event $event)
    {
        $event->getParam('inputFilter')->get('guest')
            ->add([
                'name' => 'guest_notify_register',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ]);
    }

    public function appendLoginNav(Event $event)
    {
        $view = $event->getTarget();
        if ($view->params()->fromRoute('__ADMIN__')) {
            return;
        }
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            $view->headStyle()->appendStyle('li a.registerlink, li a.loginlink { display:none; }');
        } else {
            $view->headStyle()->appendStyle('li a.logoutlink { display:none; }');
        }
    }

    public function addUserFormElement(Event $event)
    {
        /** @var \Omeka\Form\UserForm $form */
        $form = $event->getTarget();
        $services = $this->getServiceLocator();

        $auth = $services->get('Omeka\AuthenticationService');

        // Public form.
        if ($form->getOption('is_public')) {
            // Don't add the agreement checkbox in public when registered.
            if ($auth->hasIdentity()) {
                return;
            }

            $fieldset = $form->get('user-settings');
            $fieldset
                ->add([
                    'name' => 'guest_agreed_terms',
                    'type' => Element\Checkbox::class,
                    'options' => [
                        'label' => 'Agreed terms', // @translate
                    ],
                    'attributes' => [
                        'id' => 'guest_agreed_terms',
                        'value' => false,
                        'required' => true,
                    ],
                ]);
            return;
        }

        // The user is not the current user, but the user in the form.
        $userId = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('id');
        if (!$userId) {
            return;
        }

        $entityManager = $services->get('Omeka\EntityManager');
        /** @var \Omeka\Entity\User $user */
        $user = $entityManager->getRepository(\Omeka\Entity\User::class)->find($userId);

        $userSettings = $services->get('Omeka\Settings\User');
        $userSettings->setTargetId($userId);
        $agreedTerms = $userSettings->get('guest_agreed_terms');
        $siteRegistration = $userSettings->get('guest_site', $services->get('Omeka\Settings')->get('default_site', 1));

        // Admin board.
        $fieldset = $form->get('user-settings');
        $fieldset
            ->add([
                'name' => 'guest_site',
                'type' => SiteSelect::class,
                'options' => [
                    'label' => 'Guest site', // @translate
                    'info' => 'This parameter is used to manage some site related features, in particular messages.', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'guest_site',
                    'class' => 'chosen-select',
                    'value' => $siteRegistration,
                    'required' => false,
                    'multiple' => false,
                    'data-placeholder' => 'Select site…', // @translate
                ],
            ])
            ->add([
                'name' => 'guest_agreed_terms',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Agreed terms', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_agreed_terms',
                    'value' => $agreedTerms,
                ],
            ]);

        /** @var \Guest\Entity\GuestToken $guestToken */
        $guestToken = $entityManager->getRepository(GuestToken::class)
            ->findOneBy(['email' => $user->getEmail()], ['id' => 'DESC']);
        if (!$guestToken || $guestToken->isConfirmed()) {
            return;
        }

        $fieldset
            ->add([
                'name' => 'guest_clear_token',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Clear registration token', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_clear_token',
                    'value' => false,
                ],
            ]);
    }

    public function addUserFormElementFilter(Event $event)
    {
        /** @var \Omeka\Form\UserForm $form */
        $form = $event->getTarget();
        if ($form->getOption('is_public')) {
            return;
        }

        $services = $this->getServiceLocator();
        // The user is not the current user, but the user in the form.
        $userId = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('id');
        if (!$userId) {
            return;
        }

        $entityManager = $services->get('Omeka\EntityManager');
        /** @var \Omeka\Entity\User $user */
        $user = $entityManager->getRepository(\Omeka\Entity\User::class)->find($userId);

        /** @var \Guest\Entity\GuestToken $guestToken */
        $guestToken = $entityManager->getRepository(GuestToken::class)
            ->findOneBy(['email' => $user->getEmail()], ['id' => 'DESC']);
        if (!$guestToken || $guestToken->isConfirmed()) {
            return;
        }

        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('user-settings')
            ->add([
                'name' => 'guest_site',
                'required' => false,
            ])
            ->add([
                'name' => 'guest_clear_token',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'clearToken'],
                        ],
                    ],
                ],
            ]);
    }

    public function clearToken($value)
    {
        if (!$value) {
            return;
        }

        $services = $this->getServiceLocator();
        // The user is not the current user, but the user in the form.
        $userId = $services->get('Application')->getMvcEvent()->getRouteMatch()->getParam('id');
        if (!$userId) {
            return;
        }

        $entityManager = $services->get('Omeka\EntityManager');
        /** @var \Omeka\Entity\User $user */
        $user = $entityManager->getRepository(\Omeka\Entity\User::class)->find($userId);

        /** @var \Guest\Entity\GuestToken $guestToken */
        $token = $entityManager->getRepository(GuestToken::class)
            ->findOneBy(['email' => $user->getEmail()], ['id' => 'DESC']);
        if (!$token || $token->isConfirmed()) {
            return;
        }
        $entityManager->remove($token);
        $entityManager->flush();
    }

    public function addUserFormValue(Event $event)
    {
        $user = $event->getTarget()->vars()->user;
        $form = $event->getParam('form');
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $userSettings->setTargetId($user->id());
        $guestSettings = [
            'guest_agreed_terms',
        ];
        $space = strtolower(__NAMESPACE__);
        $config = $services->get('Config')[$space]['user_settings'];
        $fieldset = $form->get('user-settings');
        foreach ($guestSettings as $name) {
            $fieldset->get($name)->setAttribute(
                'value',
                $userSettings->get($name, $config[$name])
            );
        }
    }

    public function deleteGuestToken(Event $event)
    {
        $request = $event->getParam('request');

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $id = $request->getId();
        $token = $em->getRepository(GuestToken::class)->findOneBy(['user' => $id]);
        if (empty($token)) {
            return;
        }
        $em->remove($token);
        $em->flush();
    }

    /**
     * Check if the guest accept agreement.
     *
     * @param MvcEvent $event
     */
    protected function checkAgreement(MvcEvent $event)
    {
        $services = $this->getServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');
        if (!$auth->hasIdentity()) {
            return;
        }

        $user = $auth->getIdentity();
        if ($user->getRole() !== \Guest\Permissions\Acl::ROLE_GUEST) {
            return;
        }

        $userSettings = $services->get('Omeka\Settings\User');
        if ($userSettings->get('guest_agreed_terms')) {
            return;
        }

        $router = $services->get('Router');
        if (!$router instanceof \Zend\Router\Http\TreeRouteStack) {
            return;
        }

        $request = $event->getRequest();
        $requestUri = $request->getRequestUri();
        $requestUriBase = strtok($requestUri, '?');

        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');
        $page = $siteSettings->get('guest_terms_page') ?: $settings->get('guest_terms_page');
        $regex = $settings->get('guest_terms_request_regex');
        if ($page) {
            $regex .= ($regex ? '|' : '') . 'page/' . $page;
        }
        $regex = '~/(|' . $regex . '|maintenance|login|logout|migrate|guest/accept-terms)$~';
        if (preg_match($regex, $requestUriBase)) {
            return;
        }

        // TODO Use routing to get the site slug.

        // Url helper can't be used, because the site slug is not set.
        // The current slug is used.
        $baseUrl = $request->getBaseUrl();
        $matches = [];
        preg_match('~' . preg_quote($baseUrl, '~') . '/s/([^/]+).*~', $requestUriBase, $matches);
        if (empty($matches[1])) {
            $acceptUri = $baseUrl;
        } else {
            $acceptUri = $baseUrl . '/s/' . $matches[1] . '/guest/accept-terms';
        }

        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $acceptUri);
        $response->setStatusCode(302);
        $response->sendHeaders();
        exit;
    }

    /**
     * Reset all guest agreements.
     *
     * @param bool $reset
     */
    protected function resetAgreements($reset)
    {
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $entityManager = $services->get('Omeka\EntityManager');
        $guests = $entityManager->getRepository(\Omeka\Entity\User::class)
            ->findBy(['role' => Acl::ROLE_GUEST]);
        foreach ($guests as $user) {
            $userSettings->setTargetId($user->getId());
            $userSettings->set('guest_agreed_terms', $reset);
        }
    }

    /**
     * Reset all guest agreements via sql (quicker for big base).
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param bool $reset
     */
    protected function resetAgreementsBySql(ServiceLocatorInterface $serviceLocator, $reset)
    {
        $reset = $reset ? 'true' : 'false';
        $sql = <<<SQL
DELETE FROM user_setting
WHERE id="guest_agreed_terms";

INSERT INTO user_setting (id, user_id, value)
SELECT "guest_agreed_terms", user.id, "$reset"
FROM user
WHERE role="guest";
SQL;
        $connection = $serviceLocator->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }
    }

    protected function deactivateGuests()
    {
        $services = $this->getServiceLocator();
        $em = $services->get('Omeka\EntityManager');
        $guests = $em->getRepository(\Omeka\Entity\User::class)->findBy(['role' => 'guest']);
        foreach ($guests as $user) {
            $user->setIsActive(false);
            $em->persist($user);
        }
        $em->flush();
    }

    /**
     * Check if an old version of module GuestUser is installed.
     *
     * @throws \Omeka\Module\Exception\ModuleCannotInstallException
     * @return bool
     */
    protected function checkOldGuestUser()
    {
        $services = $this->getServiceLocator();
        $hasGuestUser = false;
        $hasOldGuestUser = false;

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('GuestUser');
        $hasGuestUser = (bool) $module;
        if (!$hasGuestUser) {
            return false;
        }

        $translator = $services->get('MvcTranslator');
        $hasOldGuestUser = version_compare($module->getIni('version'), '3.3.5', '<');
        if ($hasOldGuestUser) {
            $message = $translator
                ->translate('This module cannot be used at the same time as module GuestUser for versions lower than 3.3.5. So it should be upgraded first, or disabled. When ready, the users and settings will be upgraded for all versions.'); // @translate
            throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
        }

        $message = new \Omeka\Stdlib\Message(
            'The module GuestUser is installed. Users and settings from this module are upgraded.' // @translate
        );
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
        $messenger->addSuccess($message);

        $message = new \Omeka\Stdlib\Message(
            'To upgrade customized templates from module GuestUser, see %sreadme%s.', // @translate
            '<a href="https://github.com/Daniel-KM/Omeka-S-module-Guest">',
            '</a>'
        );
        $message->setEscapeHtml(false);
        $messenger->addWarning($message);
        return true;
    }
}
