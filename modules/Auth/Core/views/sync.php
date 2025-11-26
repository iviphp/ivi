<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <!-- Viewport unique -->
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Connecting...</title>
  <!-- Page de transition : éviter l’indexation -->
  <meta name="robots" content="noindex, nofollow" />
  <meta name="theme-color" content="#ff9000" />

  <!-- Google Tag Manager (GTM only — pas de gtag.js direct) -->
  <script>
    (function(w, d, s, l, i) {
      w[l] = w[l] || [];
      w[l].push({
        'gtm.start': new Date().getTime(),
        event: 'gtm.js'
      });
      var f = d.getElementsByTagName(s)[0],
        j = d.createElement(s),
        dl = l != 'dataLayer' ? '&l=' + l : '';
      j.async = true;
      j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
      f.parentNode.insertBefore(j, f);
    })(window, document, 'script', 'dataLayer', 'GTM-KG8G9436');
  </script>
  <!-- End Google Tag Manager -->

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      padding: 0;
      font-family: "Segoe UI", Roboto, sans-serif;
      background: linear-gradient(135deg, #ff9000, #ffc107);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
      color: #fff;
      overflow: hidden;
    }

    .card {
      background: #fff;
      color: #333;
      border-radius: 20px;
      padding: 2rem;
      max-width: 400px;
      width: 90%;
      box-shadow: 0 10px 25px rgba(0, 0, 0, .1);
      text-align: center;
      animation: fadeIn .6s ease-out;
    }

    .card h1 {
      font-size: 1.6rem;
      margin-bottom: .8rem;
    }

    .card p {
      font-size: 1rem;
      color: #555;
    }

    .loader {
      margin: 2rem auto 0;
      width: 40px;
      height: 40px;
      border: 4px solid #ff9000;
      border-top: 4px solid transparent;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: scale(.95);
      }

      to {
        opacity: 1;
        transform: scale(1);
      }
    }
  </style>
</head>

<body>
  <!-- Google Tag Manager (noscript) -->
  <noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KG8G9436"
      height="0" width="0" style="display:none;visibility:hidden"></iframe>
  </noscript>
  <!-- End Google Tag Manager (noscript) -->

  <div class="card">
    <h1>Connecting...</h1>
    <p>Please wait, you will be redirected to your dashboard.</p>
    <div class="loader"></div>
  </div>

  <script>
    const redirectUrl = "/user";
    if (window.opener) {
      try {
        window.opener.location.href = redirectUrl;
        window.close();
      } catch (e) {
        window.location.href = redirectUrl;
      }
    } else {
      setTimeout(() => {
        window.location.href = redirectUrl;
      }, 100);
    }
  </script>
</body>

</html>