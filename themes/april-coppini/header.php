<!doctype html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width">

  <title><?php wp_title(''); ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Alumni+Sans:wght@500&display=swap" rel="stylesheet">

  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22256%22 height=%22256%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2220%22 fill=%22%23f8d773%22></rect><text x=%2250%%22 y=%2250%%22 dominant-baseline=%22central%22 text-anchor=%22middle%22 font-size=%2290%22>🐝</text></svg>" />

  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
  
  <header class="site-header">
    <h1>
      <a href="/">April Coppini</a>
    </h1>

    <nav class="main-menu">
      <?php wp_nav_menu([
        'menu' => 17,
      ]); ?>
    </nav>
  </header>

  <main id="main-content">
