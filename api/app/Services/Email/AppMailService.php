<?php

namespace App\Services\Email;

use App\Mail\DynamicAppMail;
use Illuminate\Support\Facades\Mail;

class AppMailService
{
    public function send(string $type, string $toEmail, array $parameters = []): bool
    {
        $data = EmailDataServiceVersionTwo::getEmailData($type, $parameters);

        if ($data === 0) {
            return false;
        }

        Mail::to($toEmail)->send(new DynamicAppMail($data));

        return true;
    }
}
