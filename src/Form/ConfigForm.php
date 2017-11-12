<?php
namespace GuestUser\Form;

use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form
{
    protected $local_storage = '';
    protected $allow_unicode = false;
    protected $settings;
    use TranslatorAwareTrait;

    public function init()
    {
        $this->setAttribute('id', 'config-form');

        $this->add([
            'name' => 'guestuser_capabilities',
            'type' => 'Textarea',
            'options' => [
                'label' => $this->translate('Registration Features'),
                'info' => $this->translate("Add some text to the registration screen so people will know what they get for registering. As you enable and configure plugins that make use of the guest user, please give them guidance about what they can and cannot do."),
            ],
            'attributes' => [
                'id' => 'guestuser_capabilities',
                'value' => $this->getSetting('guestuser_capabilities'),
                'rows' => 5,
                'cols' => 60,
                'class' => 'media-html',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_short_capabilities',
            'type' => 'Textarea',
            'options' => [
                'label' => $this->translate("Short Registration Features"),
                'info' => $this->translate("Add a shorter version to use as a dropdown from the user bar. If empty, no dropdown will appear."),
            ],
            'attributes' => [
                 'id' => 'guestuser_short_capabilities',
                 'value' => $this->getSetting('guestuser_short_capabilities'),
                 'rows' => 5,
                 'cols' => 60,
                 'class' => 'media-html',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_dashboard_label',
            'type' => 'Text',
            'options' => [
                'label' => $this->translate("Dashboard Label"),
                'info' => $this->translate("The text to use for the label on the user's dashboard"),
            ],
            'attributes' => [
                'id' => 'guestuser_dashboard_label',
                'value' => $this->getSetting('guestuser_dashboard_label'),
                ],
        ]);

        $this->add([
            'name' => 'guestuser_login_text',
            'type' => 'Text',
            'options' => [
                'label' => $this->translate("Login Text"),
                'info' => $this->translate("The text to use for the 'Login' link in the user bar"),
            ],
            'attributes' => [
                'id' => 'guestuser_login_text',
                'value' => $this->getSetting('guestuser_login_text'),
            ],
        ]);

        $this->add([
            'name' => 'guestuser_register_text',
            'type' => 'Text',
            'options' => [
                'label' => $this->translate("Register Text"),
                'info' => $this->translate("The text to use for the 'Register' link in the user bar"),
            ],
            'attributes' => [
                'id' => 'guestuser_register_text',
                'value' => $this->getSetting('guestuser_register_text'),
            ],
        ]);

        $this->add([
            'name' => 'guestuser_open',
            'type' => 'Checkbox',
            'options' => [
                'label' => $this->translate("Allow open registration ?"),
                'info' => $this->translate("Allow guest user registration without administrator approval?"),
            ],
            'attributes' => [
                'value' => $this->getSetting('guestuser_open'),
            ],
        ]);

        $this->add([
            'name' => 'guestuser_recaptcha',
            'type' => 'Checkbox',
            'options' => [
                'label' => $this->translate("Require ReCaptcha ?"),
                'info' => $this->translate("Check this to require passing a ReCaptcha test when registering"),
            ],
            'attributes' => [
                'value' => $this->getSetting('guestuser_recaptcha'),
            ],

        ]);
    }

    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    protected function getSetting($name)
    {
        return $this->settings->get($name);
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }
}
