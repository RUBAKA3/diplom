<!DOCTYPE html>
<html>
<head>
    <title>Подтверждение Email - {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3490dc;
            color: white !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Подтверждение Email</h2>
        <p>Здравствуйте, {{ $user->name }}!</p>
        
        <p>Для завершения регистрации на {{ config('app.name') }} подтвердите ваш email, нажав на кнопку ниже:</p>
        
        <a href="{{ $url }}" class="button">Подтвердить Email</a>
        
        <p>Если вы не регистрировались на нашем сервисе, пожалуйста, проигнорируйте это письмо.</p>
        
        <p>С уважением,<br>
        Команда {{ config('app.name') }}</p>
    </div>
</body>
</html>