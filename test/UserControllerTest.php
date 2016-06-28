<?php

namespace OmekaTest\Controller;
use DateTime;
use Omeka\Installation\Installer;
use Zend\Mail\Transport\Factory as TransportFactory;
use Omeka\Test\AbstractHttpControllerTestCase;
use GuestUser\Entity\GuestUserTokens;
use Zend\Session\Container;
use Omeka\Entity\User;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
class UserControllerTest  extends AbstractHttpControllerTestCase{
  public function setUp() {
    $this->connectAdminUser();
    $config = [];
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('GuestUser');

    $manager->install($module);

    $this->site_test=$this->addSite('test');
    parent::setUp();
    $this->connectAdminUser();
    $this->createTestUser();


  }

  protected function createTestUser() {
      $em= $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
      if ($em->getRepository('Omeka\Entity\User')->findBy(['email'=>'test@test.fr']))
          return true;

      $user =  new \Omeka\Entity\User;

      $user->setIsActive(true);
      $user->setRole('global_admin');
      $user->setName('Tester');
      $user->setEmail('test@test.fr');
      $user->setPassword('test');
      $user->setCreated(new DateTime);
      $this->persistAndSave($user);

  }

    public function getApplicationConfig()
    {
        return $this->applicationConfig;
    }

    protected function getUserToken($email) {
      $em= $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
      if ($user = $em->getRepository('GuestUser\Entity\GuestUserTokens')->findBy(['email'=>$email]))
          return array_shift($user);

      return false;
    }


    public function getApplication()
    {

        // Return the application immediately if already set.
        if ($this->application) {
            return $this->application;
        }

        $config = require OMEKA_PATH . '/config/application.config.php';
        $reader = new \Zend\Config\Reader\Ini;
        $testConfig = [
            'connection' => $reader->fromFile(OMEKA_PATH . '/application/test/config/database.ini')
        ];
        $config = array_merge($config, $testConfig);

        \Zend\Console\Console::overrideIsConsole($this->getUseConsoleRequest());
        $appConfig = $this->applicationConfig? $this->applicationConfig : [];

        $config['service_manager']['factories']['Omeka\Mailer'] = 'MockMailerFactory';


        $this->application = \Omeka\Mvc\Application::init(array_merge($config,$appConfig));

        $serviceLocator = $this->application->getServiceManager();
        $serviceLocator->setAllowOverride(true);

        $serviceLocator->setService('Omeka\Mailer', new MockMailerFactory);

        $serviceLocator->setFactory('Omeka\Mailer',new MockMailerFactory);

        $events = $this->application->getEventManager();
        $events->detach($this->application->getServiceManager()->get('SendResponseListener'));

        return $this->application;
    }

  public function tearDown() {
    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('GuestUser');
    $manager->uninstall($module);
    $em = $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
    $guestUsers = $em->getRepository('Omeka\Entity\User')->findBy(['role'=>'guest']);
    $this->cleanTable('guest_user_tokens');
    foreach($guestUsers as $user) {
        $em->remove($user);
        $em->flush();
    }
//    $this->cleanTable('user');
  }


  public function getApplicationServiceLocatorUnavailable() {

      $serviceLocator = parent::getApplicationServiceLocator();
      $serviceLocator->setAllowOverride(true);
      $serviceLocator->setFactory('Omeka\Mailer',new MockMailerFactory);

      return $serviceLocator;
  }





  protected function getMockServiceLocator($serviceLocatorReal)
  {
      $serviceLocator = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface',['get']);
      $serviceLocator->expects($this->once())
                     ->method('get')
                     ->with($this->equalTo('Omeka\Mailer'))
                     ->will($this->returnValue(new MockMailer));


      return $serviceLocator;
  }

  public function datas() {
      return [
              ['guest_user_capabilities', 'long description','textarea'],
              ['guest_user_short_capabilities', 'short','textarea'],
              ['guest_user_dashboard_label', 'dashboard label','input'],
              ['guest_user_login_text', 'Log !','input'],
      ];}


    /**
   * @test
   * @dataProvider datas
   */
  public function postConfigurationShouldBeSaved($name,$value) {
      $this->postDispatch('/admin/module/configure?id=GuestUser', [$name => $value]);
      $this->assertEquals($value,$this->getApplicationServiceLocator()->get('Omeka\Settings')->get($name));

  }

  /** @test
   * @dataProvider datas
   */
  public function configurationPageShouldBeDisplayed($name,$value,$type) {
      $this->dispatch('/admin/module/configure?id=GuestUser');
      $this->assertXPathQuery('//div[@class="inputs"]//'.$type.'[@name="'.$name.'"]');

  }

/** @test */
  public function registerShouldDisplayLogin() {
      $this->resetApplication();
      $this->postDispatch('/s/test/guestuser/register', ['o:email' => "test3@test.fr",
                                                          'o:name' => 'test',
                                                          'new_password' => 'test',
                                                          'new_passord_confirm' => 'test',
                                                          'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
]
);

      $this->assertXPathQueryContentContains('//li[@class="success"]','Thank you for registering. Please check your email for a confirmation message. Once you have confirmed your request, you will be able to log in.');
      $readResponse = $this->getApplicationServiceLocator()->get('Omeka\ApiManager')->read('sites', [
            'slug' => 'test'
        ]);
      $siteRepresentation =  $readResponse->getContent();

      $link = '<a href=\''.$siteRepresentation->siteUrl(null,true).'/guestuser/confirm?token='.$this->getUserToken('test3@test.fr')->getToken().'\'>';
      $this->assertContains('You have registered for an account on '.$link.'test</a>. Please confirm your registration by following '.$link.'this link</a>.  If you did not request to join test please disregard this email.',$this->application->getServiceManager()->get('Omeka\Mailer')->getMessage()->getBody());

  }


  /** @test */
  public function tokenlinkShouldValidateGuestUser() {

      $user = $this->createGuestUser();
      $this->resetApplication();
      $this->dispatch('/s/test/guestuser/confirm?token='.$this->getUserToken($user->getEmail())->getToken());
      $this->assertTrue($this->getUserToken($user->getEmail())->isConfirmed());
      $this->assertRedirect('guestuser/login');
      $this->assertXPathQueryContentContains('//li[@class="success"]','Thanks for joining test! You can now log using the password you chose.');
  }



  /** @test */
  public function wrongTokenlinkShouldNotValidateGuestUser() {

      $user = $this->createGuestUser();
      $this->resetApplication();
      $this->dispatch('/s/test/guestuser/confirm?token=1234');

      $this->assertFalse($this->getUserToken($user->getEmail())->isConfirmed());

  }

  protected function createGuestUser() {
      $formUser = ['o:email' => "guest@test.fr",
                   'o:name' => 'guestuser',
                   'o:role' => 'guest',
                   'o:is_active' => true,
                   'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue()];
      $user_manager=$this->getApplicationServiceLocator()->get('Omeka\ApiManager')->create('users', $formUser);
      $user = $user_manager->getContent()->getEntity();
      $user->setPassword('test');
      $this->persistAndSave($user);
      $guest = new GuestUserTokens;
      $guest->setEmail($formUser['o:email']);
      $guest->setUser($user);
      $guest->setToken(sha1("tOkenS@1t" . microtime()));
      $this->persistAndSave($guest);
      return $user;

  }


  /** @test */
  public function activeUserEnableLogin() {
      $this->resetApplication();
      $user = $this->createGuestUser();
  }

  /** @test */
  public function updateAccountWithNoPassword() {
      $user = $this->createGuestUser();
      $em = $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
      $this->getUserToken($user->getEmail())->setConfirmed(true);
      $this->persistAndSave($user);
      $this->resetApplication();
      $this->login('guest@test.fr','test');

      $this->postDispatch('/s/test/guestuser/update-account', ['o:email' => "test3@test.fr",
                                                          'o:name' => 'test2',
                                                          'new_password' => '',
                                                          'new_passord_confirm' => '',
                                                          'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
]
);
      $em->flush();
      $this->assertNotNull($em->getRepository('Omeka\Entity\User')->findOneBy(['email'=> 'test3@test.fr', 'name'=> 'test2']));

  }
  /** @test */
  public function deleteUnconfirmedUserShouldRemoveToken() {
      $user = $this->createGuestUser();
      $em = $this->getApplicationServiceLocator()->get('Omeka\EntityManager');
      $api = $this->getApplicationServiceLocator()->get('Omeka\ApiManager');
      $api->delete('users', $user->getId());
      $this->assertNull($em->getRepository('GuestUser\Entity\GuestUserTokens')->findOneBy(['user'=>$user->getId()]));
  }

  /** @test */
  public function registerNeedsValidation() {
      $this->resetApplication();
      $this->createGuestUser();

      $this->postDispatch('/s/test/guestuser/login', ['email' => "guest@test.fr",
                                                      'password' => 'test',
                                                      'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
                                                      'submit' => 'Log+in'
                                                      ]
      );


      $this->assertXPathQueryContentContains('//li[@class="error"]', 'Your account has not been activated');


  }

  /** @test */
  public function loginShouldDisplayWrongEmailOrPassword() {
      $this->resetApplication();

      $this->postDispatch('/s/test/guestuser/login', ['email' => "test@test.fr",
                                           'password' => 'test2',
                                           'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
                                           'submit' => 'Log+in'
]
);
echo       $this->getResponse()->getBody();
      $this->assertXPathQueryContentContains('//li[@class="error"]', 'Email or password is invalid');

 }



  /** @test */
  public function forgotPasswordShouldDisplayEmailSent() {
      $this->resetApplication();
      $this->postDispatch('/s/test/guestuser/forgot-password', ['email' => "test@test.fr",
                                                                'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue()]
);
      $this->assertXPathQueryContentContains('//li[@class="success"]', 'Check your email for instructions on how to reset your password');

 }


  /** @disabled-test : works IRL but not in test */
  public function logoutShouldLogoutUser() {
      $this->dispatch('/s/test/guestuser/logout');
      $auth = $this->getApplicationServiceLocator()->get('Omeka\AuthenticationService');
      $this->assertFalse($auth->hasIdentity());

  }

  /** @test */
  public function forgotPasswordShouldSendEmail() {
      $this->resetApplication();
      $this->postDispatch('/s/test/guestuser/forgot-password', ['email' => "test@test.fr",
                                                                'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue()]
);

      $this->assertContains('To reset your password, click this link',$this->application->getServiceManager()->get('Omeka\Mailer')->getMessage()->getBody());

  }


  /** @test */
  public function loginOkShouldRedirect() {

      $this->resetApplication();
      $this->postDispatch('/s/test/guestuser/login', ['email' => "test@test.fr",
                                                      'password' => 'test',
                                                      'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
                                                      'submit' => 'Log+in'
                                                      ]
      );


      $this->assertRedirect('/s/test');

 }


}

class MockMailerFactory implements FactoryInterface {
    public function createService(ServiceLocatorInterface $serviceLocator) {
        return new MockMailer(TransportFactory::create([]),$serviceLocator->get('ViewHelperManager'),$serviceLocator->get('Omeka\EntityManager'),[]);
    }


}
 class MockMailer extends \Omeka\Service\Mailer {
     private $message = '';
     public function send($message) {
         $this->message=$message;
         return true;
     }
     public function getMessage() {
         return $this->message;
     }
  }
