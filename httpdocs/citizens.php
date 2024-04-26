<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>notary.directdemocracy.vote</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
    <link rel="stylesheet" href="//cdn.jsdelivr.net/npm/bulma@1.0.0/css/bulma.min.css">
    <link rel="stylesheet" href="//app.directdemocracy.vote/app/css/framework7-icons.css">
    <link rel="stylesheet" href="//app.directdemocracy.vote/app/css//leaflet.css">
    <link rel="stylesheet" href="//directdemocracy.vote/css/directdemocracy.css">
    <script src="//app.directdemocracy.vote/app/js/leaflet.js"></script>
    <script type="module" src="/js/citizens.js"></script>
    <script type="module" src="//app.directdemocracy.vote/js/index.js"></script>
  </head>
  <body class="has-navbar-fixed-top">
    <nav class="navbar is-fixed-top is-light" role="navigation" aria-label="main navigation">
      <div class="container">
        <div class="navbar-brand">
          <a id="main-menu" class="navbar-item">
            <i class="f7-icons" style="rotate:-90deg;margin-right:4px">hand_point_right</i>
            direct<b>democracy</b>
          </a>
          <a role="button" id="navbar-burger" class="navbar-burger" aria-label="menu" aria-expanded="false"
            data-target="navbar-menu">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
          </a>
        </div>
        <div class="navbar-menu" id="navbar-menu">
          <div class="navbar-end">
            <div class="navbar-item has-dropdown is-hoverable">
              <a class="navbar-link"><span class="is-size-4" id="language"></span></a>
              <div class="navbar-dropdown" id="language-dropdown">
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>
    <section class="hero">
      <div class="hero-body">
        <div class="columns is-mobile">
          <a class="column is-narrow" href="/"><i class="f7-icons">book</i></a>
          <div class="column">
            <p class="title">direct<b>democracy</b></p>
            <p class="subtitle" data-i18n="moto"></p>
          </div>
        </div>
      </div>
    </section>
    <section class="section">
      <nav class="panel">
      </nav>
    </section>
    <footer class="footer">
      <div class="content has-text-centered" data-i18n="more-info"></div>
      <div id="logout-div" class="content has-text-centered">
      </div>
    </footer>
    <div id="modal" class="modal" style="z-index:1000">
      <div class="modal-background"></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <i class="f7-icons has-text-link mr-3">qrcode_viewfinder</i>
          <p id="modal-title" class="modal-card-title"></p>
          <button id="modal-close-button" class="delete" aria-label="close"></button>
        </header>
        <section id="modal-content" class="modal-card-body">
        </section>
      </div>
    </div>
  </body>
</html>
