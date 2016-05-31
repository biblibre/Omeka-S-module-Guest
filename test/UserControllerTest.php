<?php

namespace OmekaTest\Controller;
use Omeka\Installation\Installer;
use Omeka\Test\AbstractHttpControllerTestCase;

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

    protected function getMockServiceLocator($config)
    {
        $serviceLocator = $this->getMock('Zend\ServiceManager\ServiceLocatorInterface');
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
      $this->postDispatch('/s/test/guestuser/register', ['o:email' => "test@test.fr",
                                                          'o:name' => 'test',
                                                          'new_password' => 'test',
                                                          'new_passord_confirm' => 'test',
                                                          'csrf' => (new \Zend\Form\Element\Csrf('csrf'))->getValue(),
]
);
     $this->assertXPathQuery('//div[@class="inputs"]');
  }

}