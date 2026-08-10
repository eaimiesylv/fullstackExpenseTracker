<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $data['subject'] ?? ($data['company_name'] ?? 'Email') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7fb; margin: 0; padding: 24px; color: #111827; }
        .wrapper { max-width: 640px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); }
        .header { background: #0f172a; color: #ffffff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 32px; }
        .greeting { font-size: 16px; margin-bottom: 16px; }
        .button { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 8px; margin-top: 12px; }
        .footer { margin-top: 24px; font-size: 13px; color: #6b7280; border-top: 1px solid #e5e7eb; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $data['company_name'] ?? config('app.company_name', config('app.name')) }}</h1>
        </div>

        <div class="content">
            <div class="greeting">{{ $data['salutation'] ?? 'Hello' }}</div>

            @if(!empty($data['content1']))
                {!! $data['content1'] !!}
            @endif

            @if(!empty($data['content2']))
                {!! $data['content2'] !!}
            @endif

            @if(!empty($data['frontend_url']) && !empty($data['btn_label']))
                <p>
                    <a href="{{ $data['frontend_url'] }}" class="button">{{ $data['btn_label'] }}</a>
                </p>
            @endif

            <div class="footer">
                <p>Regards,<br>{{ $data['sender'] ?? config('app.sender_name', config('app.name')) }}</p>
                <p>© {{ date('Y') }} {{ $data['company_name'] ?? config('app.company_name', config('app.name')) }}. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
