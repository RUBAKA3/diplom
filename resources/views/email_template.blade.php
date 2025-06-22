<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Template</title>
</head>
<body>
    <h1>Добрый день, меня зовут {{$mailData['name']}}</h1>
    <h3>Мой номер телефона {{$mailData['number']}}</h3>
    <h3>я хотел узнать: {{$mailData['question']}} </h3>
</body>
</html>