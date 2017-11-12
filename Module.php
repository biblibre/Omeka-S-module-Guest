<?php
/*
 * Copyright BibLibre, 2016
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
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\Event;

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
        foreach ($config[strtolower(__NAMESPACE__)]['settings'] as $name => $value) {
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

        $conn = $serviceLocator->get('Omeka\Connection');
        $sql = <<<'SQL'
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS guest_user_token;
SET FOREIGN_KEY_CHECKS = 1;
SQL;
        $conn->exec($sql);

        $settings = $serviceLocator->get('Omeka\Settings');
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['settings'];
        foreach ($defaultSettings as $name => $value) {
            $settings->delete($name);
        }
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        if (version_compare($oldVersion, '0.1.3', '<')) {
            $settings = $serviceLocator->get('Omeka\Settings');
            $config = include __DIR__ . '/config/module.config.php';
            foreach ($config['guestuser']['settings'] as $name => $value) {
                $oldName = str_replace('guestuser_', 'guest_user_', $name);
                $settings->set($name, $settings->get($oldName, $value));
                $settings->delete($oldName);
            }
        }
        if (version_compare($oldVersion, '0.1.4', '<')) {
            $conn = $serviceLocator->get('Omeka\Connection');
            $sql = <<<'SQL'
ALTER TABLE guest_user_tokens RENAME TO guest_user_token, ENGINE='InnoDB' COLLATE 'utf8mb4_unicode_ci';
ALTER TABLE guest_user_token CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE token token VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE confirmed confirmed TINYINT(1) NOT NULL;
ALTER TABLE guest_user_token ADD CONSTRAINT FK_80ED0AF2A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE;
CREATE INDEX IDX_80ED0AF2A76ED395 ON guest_user_token (user_id);
SQL;
            $conn->exec($sql);
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

        $acl->allow(null, 'GuestUser\Controller\Site\GuestUser');
        $acl->allow(
            Permissions\Acl::ROLE_GUEST,
            'Omeka\Entity\User',
            ['read', 'update', 'change-password', 'edit-keys'],
            new IsSelfAssertion
        );
        $acl->allow(
            Permissions\Acl::ROLE_GUEST,
            'Omeka\Api\Adapter\UserAdapter',
            ['read', 'update']
        );
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
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $form = $this->getServiceLocator()->get('FormElementManager')
            ->get(ConfigForm::class);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');
        $params = $controller->getRequest()->getPost();
        foreach ($params as $name => $value) {
            if (isset($config[strtolower(__NAMESPACE__)]['settings'][$name])) {
                $settings->set($name, $value);
            }
        }
    }

    public function appendLoginNav(Event $event)
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $view = $event->getTarget();
        if ($auth->hasIdentity()) {
            return $view->headStyle()->appendStyle("li a.registerlink, li a.loginlink { display:none;} ");
        }
        $view->headStyle()->appendStyle("li a.logoutlink { display:none;} ");
    }

    public function deleteGuestToken($event)
    {
        $request = $event->getParam('request');

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $id = $request->getId();
        if ($user = $em->getRepository(GuestUserToken::class)->findOneBy(['user' => $id])) {
            $em->remove($user);
            $em->flush();
        }
    }
}
