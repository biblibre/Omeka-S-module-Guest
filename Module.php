<?php
namespace GuestUser;
use Omeka\Module\AbstractModule;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use GuestUser\Form\ConfigGuestUserForm;
use Zend\View\Model\ViewModel;
use ArchiveRepertory\Service\FileArchiveManagerFactory;
use Zend\Mvc\MvcEvent;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\View\HelperPluginManager;
use Zend\Permissions\Acl\Acl;
use Zend\Permissions\Acl\Role\GenericRole;
use Zend\Permissions\Acl\Resource\GenericResource;
use Omeka\Event\Event;
//use Omeka\Event\Event;

//include(FORM_DIR . '/User.php');


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
        //deactivate the guest users
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

    public function hookAdminThemeHeader($args)
    {
        $request = $args['request'];
        if($request->getControllerName() == 'plugins' && $request->getParam('name') == 'GuestUser') {
            queue_js_file('tiny_mce/tiny_mce');
            $js = "if (typeof(Omeka) !== 'undefined'){
                Omeka.wysiwyg();
            };";
            queue_js_string($js);
        }

    }


    public function filterPublicShowAdminBar($show)
    {
        return true;
    }

    public function filterAdminNavigationMain($navLinks)
    {
        $navLinks['Guest User'] = array('label' => $this->translate("Guest Users"),
                                        'uri' => url("guest-user/user/browse?role=guest"));
        return $navLinks;
    }

    public function setConfig($config) {
        $this->config=$config;
    }

    public function getConfig() {
        if ($this->config)
            return  $this->config;
        return include __DIR__ . '/config/module.config.php';
    }


    public function setOption($name,$value,$serviceLocator = null) {
        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();

        return  $serviceLocator->get('Omeka\Settings')->set($name,$value);
    }

    public function getOption($key,$serviceLocator=null) {
        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();
        return $serviceLocator->get('Omeka\Settings')->get($key);
    }

    public function appendLoginNav(Event $event) {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $view = $event->getTarget();
        if ($auth->hasIdentity())
            return $view->headStyle()->appendStyle("li a.registerlink ,li a.loginlink { display:none;} ");
        $view->headStyle()->appendStyle("li a.logoutlink { display:none;} ");
    }

    public function translate($string,$options='',$serviceLocator=null) {

        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();

        return $serviceLocator->get('MvcTranslator')->translate($string,$options);
    }


    public function deleteGuestToken($event) {
        $request = $event->getParam('request');

        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $id = $request->getId();
        if ($user = $em->getRepository('GuestUser\Entity\GuestUserTokens')->findOneBy(['user' => $id])) {
            $em->remove($user);
            $em->flush();
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager) {
        $sharedEventManager->attach('*', 'view.layout', [$this, 'appendLoginNav']);
        $sharedEventManager->attach(['Omeka\Api\Adapter\UserAdapter'], 'api.delete.post', [$this, 'deleteGuestToken']);
    }

}
