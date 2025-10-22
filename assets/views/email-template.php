<html>
  <body style="font-family: Arial; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 600px; background: #fff; padding: 20px; border-radius: 10px;">
      <h2><?php esc_html_e( 'You left something behind!', 'recovero' ); ?></h2>
      <p><?php esc_html_e( 'Looks like you forgot items in your cart. Complete your order before they\'re gone!', 'recovero' ); ?></p>
      
      <?php if (!empty($items_html)): ?>
      <h3><?php esc_html_e( 'Items in your cart:', 'recovero' ); ?></h3>
      <ul><?php echo $items_html; ?></ul>
      <?php endif; ?>
      
      <a href="<?php echo esc_url($recovery_link); ?>" style="display:inline-block; background:#0073aa; color:#fff; padding:10px 20px; border-radius:5px; text-decoration:none;">
        <?php esc_html_e( 'Recover My Cart', 'recovero' ); ?>
      </a>
      <p><?php esc_html_e( 'Thanks for shopping with us!', 'recovero' ); ?></p>
    </div>
  </body>
</html>
