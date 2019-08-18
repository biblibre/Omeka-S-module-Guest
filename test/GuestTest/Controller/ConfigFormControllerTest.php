<?php

namespace GuestTest\Controller;

class ConfigFormControllerTest extends GuestControllerTestCase
{
    public function datas()
    {
        return [
            ['guest_capabilities', 'long description', 'textarea'],
            ['guest_short_capabilities', 'short', 'textarea'],
            ['guest_dashboard_label', 'dashboard label', 'input'],
            ['guest_login_text', 'Log !', 'input'],
        ];
    }

    /**
     * @test
     * @dataProvider datas
     */
    public function postConfigurationShouldBeSaved($name, $value)
    {
        $this->postDispatch('/admin/module/configure?id=Guest', [$name => $value]);
        $this->assertEquals($value, $this->getServiceLocator()->get('Omeka\Settings')->get($name));
    }

    /**
     * @test
     * @dataProvider datas
     */
    public function configurationPageShouldBeDisplayed($name, $value, $type)
    {
        $this->dispatch('/admin/module/configure?id=Guest');
        $this->assertXPathQuery('//div[@class="inputs"]//' . $type . '[@name="' . $name . '"]');
    }
}
