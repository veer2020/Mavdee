<?php http_response_code(500); ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Error</title>
    <style>
        body {
            font-family: 'DM Sans', sans-serif;
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            color: #3e4152;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 12px;
        }

        p {
            color: #94969f;
            font-size: 15px;
            margin-bottom: 24px;
        }

        a {
            display: inline-block;
            background: #ff3f6c;
            color: #fff;
            padding: 10px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        a:hover {
            opacity: .9;
        }
    </style>
</head>

<body>
    <h1>500 &mdash; Server Error</h1>
    <p>Something went wrong on our end. Please try again later.</p>
    <a href="/">Go to Homepage</a>
</body>

</html>