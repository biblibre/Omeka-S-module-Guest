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

use GuestUser\Form\Config as ConfigForm;
use Omeka\Module\AbstractModule;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\Event;

class Module extends AbstractModule
{
    /**
     * Settings and their default values.
     *
     * @var array
     */
    protected $settings = [
        'guest_user_capabilities' => '',
        'guest_user_short_capabilities' => '',
        'guest_user_login_text' => 'Login', // @translate
        'guest_user_register_text' => 'Register', // @translate
        'guest_user_dashboard_label' => 'My Account', // @translate
        'guest_user_open' => false,
        'guest_user_recaptcha' => false,
    ];

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
        $this->serviceLocator = $serviceLocator;
        $sql = "CREATE TABLE IF NOT EXISTS `guest_user_tokens` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `token` text COLLATE utf8_unicode_ci NOT NULL,
                  `user_id` int NOT NULL,
                  `email` tinytext COLLATE utf8_unicode_ci NOT NULL,
                  `created` datetime NOT NULL,
                  `confirmed` tinyint(1) DEFAULT '0',
                  PRIMARY KEY (`id`)
                ) ENGINE=MyISAM CHARSET=utf8 COLLATE=utf8_unicode_ci;
                ";

        $connection->exec($sql);

        //if plugin was uninstalled/reinstalled, reactivate the guest users
        $sql = "UPDATE user set is_active=true WHERE role='guest'";
        $connection->exec($sql);

        $settings = $serviceLocator->get('Omeka\Settings');
        $t = $serviceLocator->get('MvcTranslator');
        foreach ($this->settings as $name => $value) {
            $settings->set($name, $t->translate($value));
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->deactivateGuestUsers($serviceLocator);
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
        $acl->allow(Permissions\Acl::ROLE_GUEST, 'Omeka\Entity\User');
        $acl->allow(Permissions\Acl::ROLE_GUEST, 'Omeka\Api\Adapter\UserAdapter');
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

        $params = $controller->getRequest()->getPost();
        foreach ($params as $name => $value) {
            if (isset($this->settings[$name])) {
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
        if ($user = $em->getRepository('GuestUser\Entity\GuestUserTokens')->findOneBy(['user' => $id])) {
            $em->remove($user);
            $em->flush();
        }
    }
}
