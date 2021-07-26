<?php
the_post();
get_header(); ?>

<main class="site-main">

  <section class="block block-page has-light-bg">
    <div class="container">

      <h1 id="content"><?php the_title(); ?></h1>
      <?php the_content(); ?>

    </div><!-- .container -->
  </section>

</main>

<?php get_footer();
