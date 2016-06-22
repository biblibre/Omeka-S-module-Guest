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


    protected $_filters = array(
                                'public_navigation_admin_bar',
                                'public_show_admin_bar',
                                'guest_user_widgets',
                                'admin_navigation_main'
    );
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
        /* $guestUsers = $this->_db->getTable('User')->findBy(array('role'=>'guest')); */
        /* //skip activation emails when reinstalling */
        /* if(count($guestUsers) != 0) { */
        /*     set_option('guest_user_skip_activation_email', true); */
        /*     foreach($guestUsers as $user) { */
        /*         $user->active = true; */
        /*         $user ->save(); */
        /*     } */
        /*     $this->setOption('guest_user_skip_activation_email', false); */
        /* } */

        $this->setOption('guest_user_login_text', $this->translate('Login'));
        $this->setOption('guest_user_register_text', $this->translate('Register'));
        $this->setOption('guest_user_dashboard_label', $this->translate('My Account'));
    }


    public function onBootstrap(MvcEvent $event)
    {

        parent::onBootstrap($event);
        $services = $this->getServiceLocator();
        $manager = $services->get('ViewHelperManager');

//        $navigation = $manager->get('Navigation');
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        //      if ($auth->hasIdentity())

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
    public function hookPublicHead($args)
    {
        queue_css_file('guest-user');
        queue_js_file('guest-user');
    }

    public function hookPublicHeader($args)
    {
        $html = "<div id='guest-user-register-info'>";
        $user = current_user();
        if(!$user) {
            $shortCapabilities = $this->getOption('guest_user_short_capabilities');
            if($shortCapabilities != '') {
                $html .= $shortCapabilities;
            }
        }
        $html .= "</div>";
        echo $html;
    }

    public function hookBeforeSaveUser($args)
    {

        $post = $args['post'];
        $record = $args['record'];
        //compare the active status being set with what's actually in the database
        if($record->exists()) {
            $dbUser = get_db()->getTable('User')->find($record->id);
            if($record->role == 'guest' && $record->active && !$dbUser->active) {
                try {
                    $this->_sendMadeActiveEmail($record);
                } catch (Exception $e) {
                    _log($e);
                }
            }
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

    public function filterPublicNavigationAdminBar($navLinks)
    {
        //Clobber the default admin link if user is guest
        $user = current_user();
        if($user) {
            if($user->role == 'guest') {
                unset($navLinks[1]);
            }
            $navLinks[0]['id'] = 'admin-bar-welcome';
            $meLink = array('id'=>'guest-user-me',
                            'uri'=>url('guest-user/user/me'),
                            'label' => $this->getOption('guest_user_dashboard_label')
            );
            $filteredLinks = apply_filters('guest_user_links' , array('guest-user-me'=>$meLink) );
            $navLinks[0]['pages'] = $filteredLinks;

            return $navLinks;
        }
        $loginLabel = $this->getOption('guest_user_login_text') ? $this->getOption('guest_user_login_text') : $this->translate('Login');
        $registerLabel = $this->getOption('guest_user_register_text') ? $this->getOption('guest_user_register_text') : $this->translate('Register');
        $navLinks = array(
                          'guest-user-login' => array(
                                                      'id' => 'guest-user-login',
                                                      'label' => $loginLabel,
                                                      'uri' => url('guest-user/user/login')
                          ),
                          'guest-user-register' => array(
                                                         'id' => 'guest-user-register',
                                                         'label' => $registerLabel,
                                                         'uri' => url('guest-user/user/register'),
                          )
        );
        return $navLinks;
    }

    public function filterGuestUserWidgets($widgets)
    {
        $widget = array('label'=> $this->translate('My Account'));
        $passwordUrl = url('guest-user/user/change-password');
        $accountUrl = url('guest-user/user/update-account');
        $html = "<ul>";
        $html .= "<li><a href='$accountUrl'>" . $this->translate("Update account info and password") . "</a></li>";
        $html .= "</ul>";
        $widget['content'] = $html;
        $widgets[] = $widget;
        return $widgets;
    }


    public static function guestUserWidget($widget)
    {
        if(is_array($widget)) {
            $html = "<h2 class='guest-user-widget-label'>" . $widget['label'] . "</h2>";
            $html .= $widget['content'];
            return $html;
        } else {
            return $widget;
        }
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
        $newview=new ViewModel();
        $newview->setTemplate('guest-user/guest-user/navigation.phtml');
        if ($auth->hasIdentity())
            return $view->headStyle()->appendStyle("li a.registerlink ,li a.loginlink { display:none;} ");
        $view->headStyle()->appendStyle("li a.logoutlink { display:none;} ");
    }

    public function translate($string,$options='',$serviceLocator=null) {

        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();

        return $serviceLocator->get('MvcTranslator')->translate($string,$options);
    }


    public function attachListeners(SharedEventManagerInterface $sharedEventManager) {
        $sharedEventManager->attach('*', 'view.layout', [$this, 'appendLoginNav']);
    }

}
