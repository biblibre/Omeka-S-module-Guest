<?php

namespace GuestUserTest\Service;

use Zend\Mail\Message;
use Omeka\Service\Mailer;

class MockMailer extends Mailer
{
    protected $message;

    public function send($message)
    {
        if ($message instanceof Message) {
            $this->message = $message;
        } else {
            $this->message = $this->createMessage($message);
        }
    }

    public function getMessage()
    {
        return $this->message;
    }
}
