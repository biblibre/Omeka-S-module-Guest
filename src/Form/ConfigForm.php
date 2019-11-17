<?php
namespace Guest\Form;

use Omeka\Form\Element\CkeditorInline;
use Zend\Form\Element;
use Zend\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        $this
            ->add([
                'name' => 'guest_open',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Registration', // @translate
                    'info' => 'Allow guest registration without administrator approval. The link to use is "/s/my-site/guest/register".', // @translate
                    'value_options' => [
                        'open' => 'Open to everyone', // @translate
                        'moderate' => 'Open with moderation', // @translate
                        'closed' => 'Closed to visitors', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'guest-open',
                ],
            ])

            ->add([
                'name' => 'guest_notify_register',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Notify registrations by email', // @translate
                    'info' => 'The list of emails to notify when a user registers, one by row.', // @translate
                ],
                'attributes' => [
                    'required' => false,
                    'placeholder' => 'contact@example.org
info@example2.org',
                ],
            ])

            ->add([
                'name' => 'guest_recaptcha',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Require ReCaptcha', // @translate
                    'info' => 'Check this to require passing a ReCaptcha test when registering', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-recaptcha',
                ],
            ])

            ->add([
                'name' => 'guest_login_text',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Login Text', // @translate
                    'info' => 'The text to use for the "Login" link in the user bar', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-login-text',
                ],
            ])

            ->add([
                'name' => 'guest_register_text',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Register Text', // @translate
                    'info' => 'The text to use for the "Register" link in the user bar', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-register-text',
                ],
            ])

            ->add([
                'name' => 'guest_dashboard_label',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Dashboard Label', // @translate
                    'info' => 'The text to use for the label on the user’s dashboard', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-dashboard-label',
                ],
            ])

            ->add([
                'name' => 'guest_capabilities',
                'type' => CkeditorInline::class,
                'options' => [
                    'label' => 'Registration Features', // @translate
                    'info' => 'Add some text to the registration screen so people will know what they get for registering. As you enable and configure plugins that make use of the guest, please give them guidance about what they can and cannot do.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-capabilities',
                ],
            ])

            /* // From Omeka classic, but not used.
            ->add([
                'name' => 'guest_short_capabilities',
                'type' => CkeditorInline::class,
                'options' => [
                    'label' => 'Short Registration Features', // @translate
                    'info' => 'Add a shorter version to use as a dropdown from the user bar. If empty, no dropdown will appear.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-short-capabilities',
                ],
            ])
            */

            ->add([
                'name' => 'guest_message_confirm_email',
                'type' => CkeditorInline::class,
                'options' => [
                    'label' => 'Email sent to confirm registration', // @translate
                    'info' => 'The text of the email to confirm the registration and to send the token.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_confirm_email',
                    'placeholder' => 'Hi {user_name},
You have registered for an account on {main_title} / {site_title} ({site_url}).
Please confirm your registration by following this link: {token_url}.
If you did not request to join {main_title} please disregard this email.', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_message_update_email',
                'type' => CkeditorInline::class,
                'options' => [
                    'label' => 'Email sent to update email', // @translate
                    'info' => 'The text of the email sent when the user wants to update it.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest_message_update_email',
                    'placeholder' => 'Hi {user_name},
You have requested to update email on {main_title} / {site_title} ({site_url}).
Please confirm your email by following this link: {token_url}.
If you did not request to update your email on {main_title}, please disregard this email.', // @translate
                ],
            ])

            ->add([
                'name' => 'guest_terms_text',
                'type' => CkeditorInline::class,
                'options' => [
                    'label' => 'Text for terms and conditions', // @translate
                    'info' => 'The text to display to accept condtions.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-terms-text',
                ],
            ])

            ->add([
                'name' => 'guest_terms_page',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Page slug of the terms and conditions', // @translate
                    'info' => 'If the text is on a specific page, or for other usage.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-terms-page',
                ],
            ])

            ->add([
                'name' => 'guest_terms_redirect',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Redirect page after acceptance', // @translate
                    'value_options' => [
                        'home' => 'Main home page', // @translate
                        'site' => 'Home site', // @translate
                        'me' => 'User account', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'guest-terms-redirect',
                    'required' => false,
                ],
            ])

            ->add([
                'name' => 'guest_terms_request_regex',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Pages not to redirect', // @translate
                    'info' => 'Allows to keep some pages available when terms are not yet agreed. Default pages are included (logout, terms page…). This is a regex, with "~" delimiter, checked against the end of the url.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-terms-request-regex',
                ],
            ])

            ->add([
                'name' => 'guest_terms_force_agree',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Force terms agreement', // @translate
                    'info' => 'If unchecked, the user will be logged out if terms are not accepted.', // @translate
                ],
                'attributes' => [
                    'id' => 'guest-terms-force-agree',
                ],
            ])

            ->add([
                'name' => 'guest_reset_agreement_terms',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Reset terms agreement for all guests', // @translate
                    'info' => 'When terms and conditions are updated, you may want guests agree them one more time. Warning: to set false will impact all guests. So warn them some time before.', // @translate
                    'value_options' => [
                        'keep' => 'No change', // @translate
                        'unset' => 'Set false', // @translate
                        'set' => 'Set true', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'guest-reset-agreement-terms',
                    'value' => 'keep',
                    'required' => false,
                ],
            ])
        ;

        $this->getInputFilter()
            ->add([
                'name' => 'guest_notify_register',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ])
        ;
    }

    public function populateValues($data, $onlyBase = false)
    {
        if (isset($data['guest_notify_register']) && is_array($data['guest_notify_register'])) {
            $data['guest_notify_register'] = implode("\n", $data['guest_notify_register']);
        }
        parent::populateValues($data, $onlyBase);
    }

    public function stringToList($string)
    {
        if (is_array($string)) {
            return $string;
        }
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))));
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Windows and Apple copy/paste from a textarea input.
     *
     * @param string $string
     * @return string
     */
    protected function fixEndOfLine($string)
    {
        return str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $string);
    }
}
