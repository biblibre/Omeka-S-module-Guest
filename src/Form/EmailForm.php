<?php
namespace GuestUser\Form;

use Zend\EventManager\Event;
use Zend\Form\Element\Email;

class EmailForm extends \Omeka\Form\UserForm
{
    public function init()
    {
        $this->add([
            'name' => 'o:email',
            'type' => Email::class,
            'options' => [
                'label' => 'Email', // @translate
            ],
            'attributes' => [
                'id' => 'email',
                'required' => true,
            ],
        ]);

        $addEvent = new Event('form.add_elements', $this);
        $this->getEventManager()->triggerEvent($addEvent);

        $inputFilter = $this->getInputFilter();

        $filterEvent = new Event('form.add_input_filters', $this, ['inputFilter' => $inputFilter]);
        $this->getEventManager()->triggerEvent($filterEvent);
    }
}
