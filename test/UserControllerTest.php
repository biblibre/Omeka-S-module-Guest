<?php

namespace OmekaTest\Controller;
use Omeka\Installation\Installer;
use Zend\Mail\Transport\Factory as TransportFactory;
use Omeka\Test\AbstractHttpControllerTestCase;
use Zend\Session\Container;
use Omeka\Entity\User;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
class UserControllerTest  extends AbstractHttpControllerTestCase{
  public function setUp() {
    $this->connectAdminUser();
    $config = [];
$config['service_manager']['factories']['Omeka\Mailer'] = 'MockMailer';
$this->setApplicationConfig($config);
    xdebug_break();


    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('GuestUser');
//    $config = $module->getConfig();

//    $module->setConfig($config);

    $manager->install($module);

    $this->site_test=$this->addSite('test');
    //  $this->site_test2=$this->addSite('test2');
    parent::setUp();
    $this->connectAdminUser();
    $this->createTestUser();
  }

  protected function createTestUser() {
      $user =  new \Omeka\Entity\User;

      $user->setIsActive(true);
      $user->setRole('global_admin');
      $user->setName('Tester');
      $user->setEmail('test@test.fr');
      $user->setPassword('test');

      $this->persistAndSave($user);

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


  public function getApplicationServiceLocator() {

      $serviceLocator = parent::getApplicationServiceLocator();
      $serviceLocator->setAllowOverride(true);
      xdebug_break();
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
      $this->postDispatch('/s/test/guestuser/register', ['o:email' => "test@test.fr",
                                                          'o:name' => 'test',
                                                          'new_password' => 'test',
                                                          'new_passord_confirm' => 'test',
                                                          'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
]
);
     $this->assertXPathQuery('//div[@class="inputs"]');
  }



  /** @test */
  public function loginShouldDisplayWrongEmailOrPassword() {
      $this->resetApplication();
      $this->postDispatch('/s/test/guestuser/login', ['email' => "test@test.fr",
                                           'password' => 'test',
                                           'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
                                           'submit' => 'Log+in'
]
);
      $this->assertXPathQueryContentContains('//li[@class="error"]', 'Email or password is invalid');

 }



  /** @test */
  public function forgotPasswordShouldDisplay() {
      $this->resetApplication();
      $this->postDispatch('/s/test/guestuser/forgot-password', ['email' => "test@test.fr",
                                                                'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue()]
);
      echo $this->getResponse()->getBody();
      $this->assertXPathQueryContentContains('//li[@class="error"]', 'Email or password is invalid');

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

     public function send($message) {
         xdebug_break();
         return true;
     }


     public function sendResetPassword(User $user) {
         return true;
     }
  }
