<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PlatformAlert extends Mailable
{
    /** @param list<array{title: string, message: string, category: string}> $items */
    public function __construct(public array $items) {}

    public function build(): self
    {
        $subject = count($this->items) === 1
            ? 'Portfolio alert: '.$this->items[0]['title']
            : 'Portfolio alert: '.count($this->items).' things need attention';

        return $this->subject($subject)->view('mail.platform-alert');
    }
}
