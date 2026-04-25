<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Offline — Mavdee</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f4f4f5;
            font-family: 'DM Sans', system-ui, sans-serif;
            color: #1c1c1c;
            padding: 24px;
            text-align: center;
        }
        .offline-icon { font-size: 4rem; margin-bottom: 16px; }
        h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 8px; }
        p  { font-size: .95rem; color: #94969f; max-width: 340px; line-height: 1.6; margin-bottom: 24px; }
        .btn {
            background: #ff3f6c;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 28px;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #e0355e; }
        .logo { font-size: 1.4rem; font-weight: 800; color: #ff3f6c; margin-bottom: 32px; letter-spacing: -0.03em; }
    </style>
</head>
<body>
    <div class="logo">Mavdee</div>
    <div class="offline-icon">📡</div>
    <h1>You're offline</h1>
    <p>It looks like you don't have an internet connection. Check your Wi-Fi or mobile data and try again.</p>
    <button class="btn" onclick="window.location.reload()">Try Again</button>
</body>
</html>
