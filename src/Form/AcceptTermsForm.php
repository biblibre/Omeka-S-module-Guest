<?php
namespace GuestUser\Form;

use Zend\Form\Element\Checkbox;
use Zend\Form\Form;

class AcceptTermsForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'guestuser_agreed_terms',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'I agree with terms and conditions.', // @translate
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);
    }
}
