<?php
http_response_code(404);
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <?php require __DIR__ . '/includes/head-favicon.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 — Page Not Found | <?= htmlspecialchars(SITE_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/global.css">
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
      font-family: var(--font-sans);
      font-size: 14px;
      background: var(--mavdee-grey);
      color: var(--mavdee-text);
      -webkit-font-smoothing: antialiased;
    }

    .error-card {
      max-width: 480px;
      width: 100%;
      text-align: center;
      background: #fff;
      border: 1px solid var(--mavdee-border);
      padding: 56px 40px;
    }

    .error-code {
      font-size: clamp(5rem, 20vw, 8rem);
      font-weight: 800;
      line-height: 1;
      color: var(--mavdee-pink);
      margin: 0 0 16px;
      letter-spacing: -0.04em;
    }

    .error-title {
      font-size: 1rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      margin: 0 0 12px;
      color: var(--mavdee-dark);
    }

    .error-desc {
      font-size: 14px;
      color: var(--mavdee-muted);
      line-height: 1.6;
      margin: 0 0 32px;
    }

    .btn-home {
      display: inline-block;
      background: var(--mavdee-pink);
      color: #fff;
      padding: 14px 32px;
      font-family: var(--font-sans);
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      text-decoration: none;
      border-radius: 4px;
      transition: background 0.2s;
      margin: 0 6px 10px;
    }

    .btn-home:hover {
      background: #e0325a;
    }

    .btn-outline {
      display: inline-block;
      background: #fff;
      color: var(--mavdee-text);
      padding: 13px 32px;
      font-family: var(--font-sans);
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      text-decoration: none;
      border: 1px solid var(--mavdee-border);
      border-radius: 4px;
      transition: border-color 0.2s, color 0.2s;
      margin: 0 6px 10px;
    }

    .btn-outline:hover {
      border-color: var(--mavdee-pink);
      color: var(--mavdee-pink);
    }

    @media (max-width: 600px) {
      .error-card {
        padding: 40px 20px;
        border: none;
      }

      .btn-home,
      .btn-outline {
        display: block;
        margin: 0 0 10px;
      }
    }
  </style>
</head>

<body>
  <div class="error-card">
    <div class="error-code">404</div>
    <h1 class="error-title">Oops! Page Not Found</h1>
    <p class="error-desc">The page you're looking for doesn't exist or may have been moved.</p>
    <div>
      <a href="/index.php" class="btn-home">Go to Home</a>
      <a href="/shop.php" class="btn-outline">Browse Shop</a>
    </div>
  </div>
</body>

</html>