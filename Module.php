<?php
namespace GuestUser;
use Omeka\Module\AbstractModule;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use GuestUser\Form\ConfigGuestUserForm;
use Zend\View\Model\ViewModel;
use ArchiveRepertory\Service\FileArchiveManagerFactory;

use Zend\EventManager\SharedEventManagerInterface;
use Omeka\Event\Event;

//include(FORM_DIR . '/User.php');


class Module extends AbstractModule
{
    protected $_hooks = array(
        'install',
        'uninstall',
        'define_acl',
        'public_header',
        'public_head',
        'admin_theme_header',
        'config',
        'config_form',
        'before_save_user',
        'initialize',
        'users_browse_sql'
    );

    protected $_filters = array(
        'public_navigation_admin_bar',
        'public_show_admin_bar',
        'guest_user_widgets',
        'admin_navigation_main'
    );


    /**
     * Add the translations.
     */
    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . '/languages');
    }

    public function setUp()
    {
        parent::setUp();
        require_once(GUEST_USER_PLUGIN_DIR . '/libraries/GuestUser_ControllerPlugin.php');
        Zend_Controller_Front::getInstance()->registerPlugin(new GuestUser_ControllerPlugin);
    }

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

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        //deactivate the guest users
        /* $guestUsers = $this->_db->getTable('User')->findBy(array('role'=>'guest')); */
        /* foreach($guestUsers as $user) { */
        /*     $user->active = false; */
        /*     $user->save(); */
        /* } */
    }

    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];
        $acl->addRole(new Zend_Acl_Role('guest'), null);
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
        $form = new ConfigGuestUserForm($this->getServiceLocator());


        return $renderer->render( 'config_form',
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
        if($this->getOption('guest_user_skip_activation_email')) {
            return;
        }
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

    public function hookUsersBrowseSql($args)
    {
        $select = $args['select'];
        $params = $args['params'];

        if(isset($params['sort_field']) && $params['sort_field'] == 'added') {
            $db = get_db();
            $sortDir = 'ASC';
            if (array_key_exists('sort_dir', $params)) {
                $sortDir = trim($params['sort_dir']);

                if ($sortDir === 'a') {
                    $dir = 'ASC';
                } else if ($sortDir === 'd') {
                    $dir = 'DESC';
                }
            } else {
                $dir = 'ASC';
            }
            $uaAlias = $db->getTable('UsersActivations')->getTableAlias();
            $select->join(array($uaAlias => $db->UsersActivations),
                            "$uaAlias.user_id = users.id", array());
            $select->order("$uaAlias.added $dir");
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

    private function _sendMadeActiveEmail($record)
    {
        $email = $record->email;
        $name = $record->name;

        $siteTitle  = $this->getOption('site_title');
        $subject = $this->translate("Your %s account", $siteTitle);
        $body = "<p>";
        $body .= $this->translate("An admin has made your account on %s active. You can now log in to with your password at this link:", $siteTitle );
        $body .= "</p>";
        $body .= "<p><a href='" . WEB_ROOT . "/users/login'>$siteTitle</a></p>";
        $from = $this->getOption('administrator_email');
        $mail = new Zend_Mail('UTF-8');
        $mail->setBodyHtml($body);
        $mail->setFrom($from, "$siteTitle Administrator");
        $mail->addTo($email, $name);
        $mail->setSubject($subject);
        $mail->addHeader('X-Mailer', 'PHP/' . phpversion());
        $mail->send();
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



    public function getConfig() {
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


    public function translate($string,$options='',$serviceLocator=null) {

        if (!$serviceLocator)
            $serviceLocator = $this->getServiceLocator();

        return $serviceLocator->get('MvcTranslator')->translate($string,$options);
    }

}
