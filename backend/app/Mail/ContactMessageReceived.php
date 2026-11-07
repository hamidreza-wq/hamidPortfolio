<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Mail\Mailable;

class ContactMessageReceived extends Mailable
{
    public function __construct(public ContactMessage $contactMessage) {}

    public function build(): self
    {
        return $this->subject('Portfolio contact: '.$this->contactMessage->subject)
            ->replyTo($this->contactMessage->email, $this->contactMessage->name)
            ->view('mail.contact-message');
    }
}
