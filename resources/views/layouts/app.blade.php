<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Slot Mania')</title>
    <style>
        body {
            margin: 0;
            background: #121212;
            color: white;
            font-family: sans-serif;
            text-align: center;
        }

        /* .container {
            padding: 20px;
        } */

    </style>
</head>

<body>
    <div class="container">
        @yield('content')
    </div>
</body>

</html>