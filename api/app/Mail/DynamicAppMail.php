<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DynamicAppMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function build()
    {
        $subject = $this->data['subject'] ?? config('app.name');

        return $this->subject($subject)
            ->view('mail.dynamic')
            ->with([
                'data' => $this->data,
            ]);
    }
}
