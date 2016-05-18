<?php

namespace OmekaTest\Controller;

use Omeka\Test\AbstractHttpControllerTestCase;

class UserControllerTest  extends AbstractHttpControllerTestCase{
  public function setUp() {
    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('GuestUser');
    $manager->install($module);

//    $this->site_test=$this->addSite('test');
    //  $this->site_test2=$this->addSite('test2');
    parent::setUp();
    $this->connectAdminUser();
  }

  public function tearDown() {
    $this->connectAdminUser();
    $manager = $this->getApplicationServiceLocator()->get('Omeka\ModuleManager');
    $module = $manager->getModule('GuestUser');
    $manager->uninstall($module);

//    $this->cleanTable('user');
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
      $this->dispatch('/guest-user/user/register');
  }

}