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

use Omeka\Module\AbstractModule;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\Event;

class Module extends AbstractModule
{
    protected $config;

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $this->serviceLocator=$serviceLocator;
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

        $this->setOption('guest_user_login_text', $this->translate('Login'));
        $this->setOption('guest_user_register_text', $this->translate('Register'));
        $this->setOption('guest_user_dashboard_label', $this->translate('My Account'));
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $services = $this->getServiceLocator();
        $manager = $services->get('ViewHelperManager');

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(null, 'GuestUser\Controller\GuestUser');
        $acl->allow(null, 'Omeka\Entity\User');
        $acl->allow(null, 'Omeka\Api\Adapter\UserAdapter');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->deactivateGuestUsers($serviceLocator);
    }

    protected function deactivateGuestUsers($serviceLocator) {
        $em = $serviceLocator->get('Omeka\EntityManager');
        $guestUsers = $em->getRepository('Omeka\Entity\User')->findBy(['role'=>'guest']);
        foreach($guestUsers as $user) {
            $user->setIsActive(false);
            $em->persist($user);
            $em->flush();
        }
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $post =$controller->getRequest()->getPost();
        foreach($post as $option=>$value) {
            $this->setOption($option, $value);
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $form =  $this->getServiceLocator()->get('FormElementManager')->get('GuestUser\Form\ConfigForm');
        return $renderer->render( 'config_guest_user_form',
                                 [
                                  'form' => $form
                                 ]);
    }


    public function setConfig($config) {
        $this->config=$config;
    }

    public function getConfig() {
        if ($this->config) {
            return $this->config;
        }
        return include __DIR__ . '/config/module.config.php';
    }


    public function setOption($name,$value,$serviceLocator = null) {
        if (!$serviceLocator) {
            $serviceLocator = $this->getServiceLocator();
        }

        return  $serviceLocator->get('Omeka\Settings')->set($name,$value);
    }

    public function getOption($key,$serviceLocator=null)
    {
        if (!$serviceLocator) {
            $serviceLocator = $this->getServiceLocator();
        }
        return $serviceLocator->get('Omeka\Settings')->get($key);
    }

    public function appendLoginNav(Event $event)
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $view = $event->getTarget();
        if ($auth->hasIdentity()) {
            return $view->headStyle()->appendStyle("li a.registerlink ,li a.loginlink { display:none;} ");
        }
        $view->headStyle()->appendStyle("li a.logoutlink { display:none;} ");
    }

    public function translate($string,$options='',$serviceLocator=null)
    {
        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();

        return $serviceLocator->get('MvcTranslator')->translate($string,$options);
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

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach('*', 'view.layout', [$this, 'appendLoginNav']);
        $sharedEventManager->attach('Omeka\Api\Adapter\UserAdapter', 'api.delete.post', [$this, 'deleteGuestToken']);
    }

}
