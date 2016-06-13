<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LocaTrack API</title>
  <link rel="icon"
    href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🌍</text></svg>">

  <!-- Primary Meta Tags -->
  <title>LocaTrack API - Real-Time Location Tracking Service</title>
  <meta name="title" content="LocaTrack API - Real-Time Location Tracking Service">
  <meta name="description"
    content="Integrate fast, reliable and privacy-first location tracking into your app with LocaTrack REST API. Real-time coordinates, geofencing and webhooks supported.">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://locatrack.zalfyan.my.id/">
  <meta property="og:title" content="LocaTrack API - Real-Time Location Tracking Service">
  <meta property="og:description"
    content="Integrate fast, reliable and privacy-first location tracking into your app with LocaTrack REST API. Real-time coordinates, geofencing and webhooks supported.">
  <meta property="og:image" content="https://locatrack.zalfyan.my.id/og-image.png">

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="https://locatrack.zalfyan.my.id/">
  <meta property="twitter:title" content="LocaTrack API - Real-Time Location Tracking Service">
  <meta property="twitter:description"
    content="Integrate fast, reliable and privacy-first location tracking into your app with LocaTrack REST API. Real-time coordinates, geofencing and webhooks supported.">
  <meta property="twitter:image" content="https://locatrack.zalfyan.my.id/og-image.png">

  <!-- Canonical URL -->
  <link rel="canonical" href="https://locatrack.zalfyan.my.id/">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --bg: #0d1117;
      --card: #161b22;
      --border: #30363d;
      --text-primary: #f0f6fc;
      --text-secondary: #8b949e;
      --accent: #58a6ff;
      --accent-glow: rgba(88, 166, 255, 0.35);
      --success: #3fb950;
      --grid: rgba(88, 166, 255, 0.06);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--bg);
      background-image:
        linear-gradient(var(--grid) 1px, transparent 1px),
        linear-gradient(90deg, var(--grid) 1px, transparent 1px);
      background-size: 90px 90px;
      color: var(--text-primary);
      display: grid;
      place-items: center;
      min-height: 100vh;
      padding: 2rem;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(circle at 100% 0, rgba(88, 166, 255, 0.15) 0%, transparent 40%);
    }

    /* cahaya pojok */
    body::after {
      content: "";
      position: fixed;
      inset: 0;
      pointer-events: none;
      background: radial-gradient(circle at 0 100%, rgba(63, 185, 80, 0.12) 0%, transparent 35%);
    }

    .card {
      width: 100%;
      max-width: 420px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 2.5rem 2rem;
      position: relative;
      overflow: hidden;
      box-shadow: 0 0 0 1px var(--border), 0 0 20px var(--accent-glow);
      animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(12px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      background: var(--accent);
      color: var(--bg);
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.25rem 0.6rem;
      border-radius: 9999px;
      margin-bottom: 1rem;
    }

    h1 {
      font-size: 1.75rem;
      font-weight: 700;
      letter-spacing: -0.025em;
      margin-bottom: 0.5rem;
    }

    .subtitle {
      color: var(--text-secondary);
      font-size: 0.95rem;
      margin-bottom: 1.75rem;
    }

    .status-grid {
      display: grid;
      grid-template-columns: auto 1fr auto;
      gap: 0.75rem 1rem;
      font-size: 0.875rem;
      margin-top: 1.5rem;
      padding: 1rem;
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid var(--border);
      border-radius: 8px;
    }

    .status-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--success);
      box-shadow: 0 0 0 3px rgba(63, 185, 80, 0.25);
      animation: blink 1.4s infinite;
      place-self: center;
    }

    @keyframes blink {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.35;
      }
    }

    .status-name {
      color: var(--text-secondary);
      font-weight: 500;
    }

    .status-state {
      color: var(--text-primary);
      font-weight: 600;
    }

    .footer {
      margin-top: 2rem;
      text-align: center;
      font-size: 0.75rem;
      color: var(--text-secondary);
    }

    .footer a {
      color: var(--accent);
      text-decoration: none;
    }

    .footer a:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <div class="card">
    <div class="badge">
      <i class="fa-solid fa-arrows-to-dot"></i>
      Active
    </div>

    <h1>LocaTrack API</h1>
    <p class="subtitle">Reliable location-tracking endpoints ready for integration.</p>

    <div class="status-grid">
      <span class="status-dot"></span>
      <span class="status-name">Server</span>
      <span class="status-state">Running</span>

      <span class="status-dot"></span>
      <span class="status-name">Database</span>
      <span class="status-state">Connected</span>

      <span class="status-dot"></span>
      <span class="status-name">Endpoints</span>
      <span class="status-state">Available</span>
    </div>

    <div class="footer">
      Docs: <a href="#">locatrack.zalfyan.my.id/docs</a>
    </div>
  </div>

</body>

</html>