<?php
namespace GuestUser\Form;

use Omeka\Form\Element\CkeditorInline;
use Zend\Form\Element\Checkbox;
use Zend\Form\Element\Radio;
use Zend\Form\Element\Text;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'guestuser_capabilities',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Registration Features', // @translate
                'info' => 'Add some text to the registration screen so people will know what they get for registering. As you enable and configure plugins that make use of the guest user, please give them guidance about what they can and cannot do.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-capabilities',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_short_capabilities',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Short Registration Features', // @translate
                'info' => 'Add a shorter version to use as a dropdown from the user bar. If empty, no dropdown will appear.', // @translate
            ],
            'attributes' => [
                 'id' => 'guestuser-short-capabilities',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_dashboard_label',
            'type' => Text::class,
            'options' => [
                'label' => 'Dashboard Label', // @translate
                'info' => 'The text to use for the label on the user’s dashboard', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_login_text',
            'type' => Text::class,
            'options' => [
                'label' => 'Login Text', // @translate
                'info' => 'The text to use for the "Login" link in the user bar', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_register_text',
            'type' => Text::class,
            'options' => [
                'label' => 'Register Text', // @translate
                'info' => 'The text to use for the "Register" link in the user bar', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_open',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Allow open registration?', // @translate
                'info' => 'Allow guest user registration without administrator approval. The link to use is "/s/my-site/guest-user/register".', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_recaptcha',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Require ReCaptcha?', // @translate
                'info' => 'Check this to require passing a ReCaptcha test when registering', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_reset_agreement_terms',
            'type' => Radio::class,
            'options' => [
                'label' => 'Reset terms agreement for all guest users', // @translate
                'info' => 'When terms and conditions are updated, you may want guest users agree them one more time. Warning: to set false will impact all guest users. So warn them some time before.', // @translate
                'value_options' => [
                    'keep' => 'No change', // @translate
                    'unset' => 'Set false', // @translate
                    'set' => 'Set true', // @translate
                ],
            ],
            'attributes' => [
                'value' => 'keep',
                'required' => false,
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_text',
            'type' => CkeditorInline::class,
            'options' => [
                'label' => 'Text for terms and conditions', // @translate
                'info' => 'The text to display to accept condtions.', // @translate
            ],
            'attributes' => [
                'id' => 'guestuser-terms-text',
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_page',
            'type' => Text::class,
            'options' => [
                'label' => 'Page slug of the terms and conditions', // @translate
                'info' => 'If the text is on a specific page, or for other usage.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'guestuser_terms_request_regex',
            'type' => Text::class,
            'options' => [
                'label' => 'Pages not to redirect', // @translate
                'info' => 'Allows to keep some pages available when terms are not yet agreed. Default pages are included (logout, terms page…). This is a regex, with "~" delimiter, checked against the end of the url.', // @translate
            ],
        ]);
    }
}
