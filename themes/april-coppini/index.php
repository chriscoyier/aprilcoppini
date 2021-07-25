<?php get_header(); ?>

<main class="site-main">

  test

  <?php get_template_part( 'template-parts/hero', get_post_type() ); ?>
  <section class="block block-blog has-light-bg">
    <div class="container">

      <?php if ( have_posts() ) : ?>

        <?php if ( is_home() && ! is_front_page() ) : ?>
          <h1 id="content" class="screen-reader-text"><?php single_post_title(); ?></h1>
        <?php endif; ?>

        <?php while ( have_posts() ) : the_post(); ?>
          <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <h2><a href="<?php echo esc_url( get_the_permalink() ); ?>"><?php the_title(); ?></a></h2>
            <p><time datetime="<?php the_time( 'c' ); ?>"><?php echo get_the_date( get_option( 'date_format' ) ); ?></time></p>

            <div class="content">
              <?php
                the_content();
                entry_footer();
              ?>
            </div>
          </article>
        <?php endwhile; ?>

        <?php the_posts_pagination(); ?>

      <?php endif; ?>

    </div>
  </section>

</main>

<?php get_footer();
