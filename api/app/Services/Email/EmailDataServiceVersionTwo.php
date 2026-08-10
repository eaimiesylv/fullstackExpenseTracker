<?php

namespace App\Services\Email;

class EmailDataServiceVersionTwo
{
    protected static string $salutation = 'Dear';

    public static function getEmailData(string $type, array $parameters = [])
    {
        if (method_exists(self::class, $type)) {
            return self::$type($parameters);
        }

        return 0;
    }

    private static function resolveFullName(array $parameters): string
    {
        $fullName = data_get($parameters, 'user_data.full_name')
            ?? data_get($parameters, 'user_data.fullname')
            ?? data_get($parameters, 'user_data.name')
            ?? data_get($parameters, 'user_data.requester_name')
            ?? trim(
                (data_get($parameters, 'user_data.first_name') ?: '')
                . ' '
                . (data_get($parameters, 'user_data.last_name') ?: '')
            );

        return trim((string) $fullName);
    }

    private static function buildSalutation(string $fullName = ''): string
    {
        $name = trim($fullName);

        if ($name === '') {
            return 'Hello,';
        }

        return self::$salutation . ' ' . $name . ',';
    }

    private static function formatEmailContent(string $content): string
    {
        $content = trim((string) $content);

        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\R/', $content) ?: [];
        $lines = array_values(array_filter(array_map(static fn ($line) => trim((string) $line), $lines), static fn ($line) => $line !== ''));

        if ($lines === []) {
            return '';
        }

        return implode('', array_map(static fn ($line) => '<p>' . e($line) . '</p>', $lines));
    }

    private static function email_verification(array $parameters = []): array
    {
        $fullName = self::resolveFullName($parameters);

        $defaults = [
            'company_name' => config('app.company_name'),
            'salutation' => self::buildSalutation($fullName),
            'full_name' => $fullName ?: null,
            'subject' => 'Verify your account',
            'content1' => self::formatEmailContent('Thank you for registering with ' . config('app.company_name') . '.'),
            'content2' => self::formatEmailContent('Please verify your email address to activate your account.'),
            'sender' => config('app.sender_name'),
            'frontend_url' => '',
            'btn_label' => 'Verify Email',
        ];

        return array_merge($defaults, $parameters);
    }

    private static function email_verification_otp(array $parameters = []): array
    {
        $fullName = self::resolveFullName($parameters);

        $defaults = [
            'company_name' => config('app.company_name'),
            'salutation' => self::buildSalutation($fullName),
            'full_name' => $fullName ?: null,
            'subject' => 'Your verification code',
            'content1' => self::formatEmailContent('Your email verification code is:'),
            'content2' => self::formatEmailContent('This code will expire in 10 minutes and can only be used once. Please do not share it with anyone.'),
            'sender' => config('app.sender_name'),
            'frontend_url' => '',
            'btn_label' => '',
        ];

        return array_merge($defaults, $parameters);
    }
}
