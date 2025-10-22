<?php

  // The entire sites' CSS
  if (!is_admin() && ($GLOBALS['pagenow'] !== 'wp-login.php')) {
    wp_enqueue_style('april-coppini-style', get_template_directory_uri() . '/style.css', array(), 'cache_bust=6546612' );
  }

  add_theme_support('menus');