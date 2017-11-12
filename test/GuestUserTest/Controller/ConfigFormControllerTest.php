<?php

namespace GuestUserTest\Controller;

class ConfigFormControllerTest extends GuestUserControllerTestCase
{
    public function datas()
    {
        return [
            ['guestuser_capabilities', 'long description', 'textarea'],
            ['guestuser_short_capabilities', 'short', 'textarea'],
            ['guestuser_dashboard_label', 'dashboard label', 'input'],
            ['guestuser_login_text', 'Log !', 'input'],
        ];
    }

    /**
     * @test
     * @dataProvider datas
     */
    public function postConfigurationShouldBeSaved($name, $value)
    {
        $this->postDispatch('/admin/module/configure?id=GuestUser', [$name => $value]);
        $this->assertEquals($value, $this->getServiceLocator()->get('Omeka\Settings')->get($name));
    }

    /**
     * @test
     * @dataProvider datas
     */
    public function configurationPageShouldBeDisplayed($name, $value, $type)
    {
        $this->dispatch('/admin/module/configure?id=GuestUser');
        $this->assertXPathQuery('//div[@class="inputs"]//' . $type . '[@name="' . $name . '"]');
    }
}
