<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PlatformDigest extends Mailable
{
    public function __construct(public array $items) {}

    public function build(): self
    {
        return $this->subject('Portfolio studio: notifications to review')
            ->view('mail.platform-digest');
    }
}
