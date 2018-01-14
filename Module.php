<?php
/*
 * Copyright BibLibre, 2016
 * Copyright Daniel Berthereau, 2017-2018
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

namespace GuestUser;

use GuestUser\Entity\GuestUserToken;
use GuestUser\Form\ConfigForm;
use Omeka\Module\AbstractModule;
use Omeka\Permissions\Assertion\IsSelfAssertion;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Form\Element\Checkbox;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
        $this->checkAgreement($event);
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $sql = <<<'SQL'
CREATE TABLE `guest_user_token` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL,
  `confirmed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_80ED0AF2A76ED395` (`user_id`),
  CONSTRAINT `FK_80ED0AF2A76ED395` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->exec($sql);

        // If module was uninstalled/reinstalled, reactivate the guest users.
        $sql = "UPDATE user SET is_active=true WHERE role='guest'";
        $connection->exec($sql);

        $settings = $serviceLocator->get('Omeka\Settings');
        $t = $serviceLocator->get('MvcTranslator');
        $config = require __DIR__ . '/config/module.config.php';
        foreach ($config[strtolower(__NAMESPACE__)]['config'] as $name => $value) {
            switch ($name) {
                case 'guestuser_login_text':
                case 'guestuser_register_text':
                case 'guestuser_dashboard_label':
                    $value = $t->translate($value);
                    break;
            }
            $settings->set($name, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->deactivateGuestUsers($serviceLocator);

        $sql = <<<'SQL'
DROP TABLE IF EXISTS guest_user_token;
SQL;
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec($sql);

        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');

        if (version_compare($oldVersion, '0.1.3', '<')) {
            $settings = $serviceLocator->get('Omeka\Settings');
            $config = include __DIR__ . '/config/module.config.php';
            foreach ($config[strtolower(__NAMESPACE__)]['config'] as $name => $value) {
                $oldName = str_replace('guestuser_', 'guest_user_', $name);
                $settings->set($name, $settings->get($oldName, $value));
                $settings->delete($oldName);
            }
        }

        if (version_compare($oldVersion, '0.1.4', '<')) {
            $sql = <<<'SQL'
ALTER TABLE guest_user_tokens RENAME TO guest_user_token, ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE guest_user_token CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE token token VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE confirmed confirmed TINYINT(1) NOT NULL;
ALTER TABLE guest_user_token ADD CONSTRAINT FK_80ED0AF2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE;
CREATE INDEX IDX_80ED0AF2A76ED395 ON guest_user_token (user_id);
SQL;
            $connection->exec($sql);
        }

        if (version_compare($oldVersion, '3.2.0', '<')) {
            $this->resetAgreementsBySql($serviceLocator, true);
        }
    }

    protected function deactivateGuestUsers($serviceLocator)
    {
        $em = $serviceLocator->get('Omeka\EntityManager');
        $guestUsers = $em->getRepository('Omeka\Entity\User')->findBy(['role' => 'guest']);
        foreach ($guestUsers as $user) {
            $user->setIsActive(false);
            $em->persist($user);
            $em->flush();
        }
    }

    protected function addAclRules()
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $settings = $services->get('Omeka\Settings');

        $acl->allow(
            null,
            'GuestUser\Controller\Site\GuestUser',
            ['login', 'forgot-password', 'stale-token', 'confirm']
        );

        $acl->allow(
            Permissions\Acl::ROLE_GUEST,
            'GuestUser\Controller\Site\GuestUser',
            [
                'logout', 'update-account', 'update-email', 'confirm-email',
                'me', 'accept-terms',
            ]
        );

        $acl->allow(
            Permissions\Acl::ROLE_GUEST,
            \Omeka\Entity\User::class,
            ['read', 'update', 'change-password'],
            new IsSelfAssertion
        );
        $acl->allow(
            null,
            \Omeka\Api\Adapter\UserAdapter::class,
            ['read', 'update']
        );
        $isOpenRegister = $settings->get('guestuser_open', false);
        if ($isOpenRegister) {
            $acl->allow(
                null,
                'GuestUser\Controller\Site\GuestUser',
                ['register']
            );
            $acl->allow(
                null,
                \Omeka\Entity\User::class,
                // Change role and Activate user should be set to allow external
                // logging (ldap, saml, etc.), not only guest registration here.
                ['create', 'change-role', 'activate-user']
            );
            $acl->allow(
                null,
                \Omeka\Api\Adapter\UserAdapter::class,
                'create'
            );
        }
        $acl->deny(
            Permissions\Acl::ROLE_GUEST,
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
                'Omeka\Controller\Admin\Vocabulary',
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
            'Omeka\Api\Adapter\UserAdapter',
            'api.delete.post',
            [$this, 'deleteGuestToken']
        );

        // Add the guest user element form to the user form.
        $sharedEventManager->attach(
            \Omeka\Form\UserForm::class,
            'form.add_elements',
            [$this, 'addUserFormElement']
        );
        // FIXME Use the autoset of the values (in a fieldset) and remove this.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.edit.form.before',
            [$this, 'addUserFormValue']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $formElementManager = $services->get('FormElementManager');

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name);
        }

        $renderer->ckEditor();

        $form = $formElementManager->get(ConfigForm::class);
        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();

        $form = $this->getServiceLocator()->get('FormElementManager')
            ->get(ConfigForm::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $this->messenger()->addErrors($form->getMessages());
            return false;
        }

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($params as $name => $value) {
            if (isset($defaultSettings[$name])) {
                $settings->set($name, $value);
            }
        }

        switch ($params['guestuser_reset_agreement_terms']) {
            case 'unset':
                $t = $services->get('MvcTranslator');
                $this->resetAgreementsBySql($services, false);
                $controller->messenger()->addSuccess($t->translate('All guest users must agreed the terms one more time.')); // @translate
                break;
            case 'set':
                $t = $services->get('MvcTranslator');
                $this->resetAgreementsBySql($services, true);
                $controller->messenger()->addSuccess($t->translate('All guest users agreed the terms.')); // @translate
                break;
        }
    }

    /**
     * Check if the guest user accept agreement.
     *
     * @param MvcEvent $event
     */
    protected function checkAgreement(MvcEvent $event)
    {
        $serviceLocator = $this->getServiceLocator();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        if (!$auth->hasIdentity()) {
            return;
        }

        $user = $auth->getIdentity();
        if ($user->getRole() !== \GuestUser\Permissions\Acl::ROLE_GUEST) {
            return;
        }

        $userSettings = $serviceLocator->get('Omeka\Settings\User');
        if ($userSettings->get('guestuser_agreed_terms')) {
            return;
        }

        $router = $serviceLocator->get('Router');
        if (!$router instanceof \Zend\Router\Http\TreeRouteStack) {
            return;
        }

        $request = $event->getRequest();
        $requestUri = $request->getRequestUri();
        $requestUriBase = strtok($requestUri, '?');
        // Keep the home page available?
        if (preg_match('~/(|maintenance|login|logout|migrate|guest-user/accept-terms)$~', $requestUriBase)) {
            return;
        }

        // TODO Use routing to get the site slug.

        // Url helper can't be used, because the site slug is not set.
        // The current slug is used.
        $baseUrl = $request->getBaseUrl();
        preg_match('~' . preg_quote($baseUrl, '~') . '/s/([^/]+).*~', $requestUriBase, $matches);
        if (empty($matches[1])) {
            $acceptUri = $baseUrl;
        } else {
            $acceptUri = $baseUrl . '/s/' . $matches[1] . '/guest-user/accept-terms';
        }

        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $acceptUri);
        $response->setStatusCode(302);
        $response->sendHeaders();
        exit;
    }

    public function appendLoginNav(Event $event)
    {
        $view = $event->getTarget();
        if ($view->params()->fromRoute('__ADMIN__')) {
            return;
        }
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        if ($auth->hasIdentity()) {
            return $view->headStyle()->appendStyle('li a.registerlink, li a.loginlink { display:none; }');
        }
        $view->headStyle()->appendStyle('li a.logoutlink { display:none; }');
    }

    public function addUserFormElement(Event $event)
    {
        /** @var \Omeka\Form\UserForm $form */
        $form = $event->getTarget();
        $services = $this->getServiceLocator();

        if ($form->getOption('is_public')) {
            $auth = $services->get('Omeka\AuthenticationService');
            // Don't add the agreement checkbox in public when registered.
            if ($auth->hasIdentity()) {
                return;
            }

            $fieldset = $form->get('user-settings');
            $fieldset->add([
                'name' => 'guestuser_agreed_terms',
                'type' => Checkbox::class,
                'options' => [
                    'label' => 'Agreed terms', // @translate
                ],
                'attributes' => [
                    'value' => false,
                    'required' => true,
                ],
            ]);
            return;
        }

        // Admin board.
        $fieldset = $form->get('user-settings');
        $fieldset->add([
            'name' => 'guestuser_agreed_terms',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Agreed terms', // @translate
            ],
        ]);
    }

    public function addUserFormValue(Event $event)
    {
        $user = $event->getTarget()->vars()->user;
        $form = $event->getParam('form');
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $userSettings->setTargetId($user->id());
        $guestUserSettings = [
            'guestuser_agreed_terms',
        ];
        $config = $services->get('Config')[strtolower(__NAMESPACE__)]['user_settings'];
        $fieldset = $form->get('user-settings');
        foreach ($guestUserSettings as $name) {
            $fieldset->get($name)->setAttribute('value',
                $userSettings->get($name, $config[$name]));
        }
    }

    public function deleteGuestToken(Event $event)
    {
        $request = $event->getParam('request');

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $id = $request->getId();
        $user = $em->getRepository(GuestUserToken::class)->findOneBy(['user' => $id]);
        if (empty($user)) {
            return;
        }
        $em->remove($user);
        $em->flush();
    }

    /**
     * Reset all guest user agreements.
     *
     * @param bool $reset
     */
    protected function resetAgreements($reset)
    {
        $services = $this->getServiceLocator();
        $userSettings = $services->get('Omeka\Settings\User');
        $entityManager = $services->get('Omeka\EntityManager');
        $guestUsers = $entityManager->getRepository('Omeka\Entity\User')
            ->findBy(['role' => Permissions\Acl::ROLE_GUEST]);
        foreach ($guestUsers as $user) {
            $userSettings->setTargetId($user->getId());
            $userSettings->set('guestuser_agreed_terms', $reset);
        }
    }

    /**
     * Reset all guest user agreements via sql (quicker for big base).
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param bool $reset
     */
    protected function resetAgreementsBySql(ServiceLocatorInterface $serviceLocator, $reset)
    {
        $reset = $reset ? 'true' : 'false';
        $sql = <<<SQL
DELETE FROM user_setting
WHERE id="guestuser_agreed_terms";

INSERT INTO user_setting (id, user_id, value)
SELECT "guestuser_agreed_terms", user.id, "$reset"
FROM user
WHERE role="guest";
SQL;
        $connection = $serviceLocator->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }
    }
}
