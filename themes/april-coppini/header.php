<!doctype html>
<html <?php language_attributes(); ?>>

<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Alumni+Sans:wght@500&display=swap" rel="stylesheet">

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
