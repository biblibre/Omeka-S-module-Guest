<?php

namespace GuestUserTest\Service;

use Omeka\Service\Mailer;

class MockMailer extends Mailer
{
    protected $message = '';

    public function send($message)
    {
        $this->message = $message;
        return true;
    }

    public function getMessage()
    {
        return $this->message;
    }
}
