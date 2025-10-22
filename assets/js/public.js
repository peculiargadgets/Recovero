(function($){
  $(function(){
    // ensure session cookie
    function ensureSid() {
      var sid = getCookie('recovero_sid');
      if (!sid) {
        sid = 'r_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        document.cookie = 'recovero_sid=' + sid + '; path=/; max-age=' + (60*60*24*30);
      }
      return sid;
    }

    var sid = ensureSid();

    function saveCart() {
      if (typeof wc_cart_params === 'undefined') {
        // still send snapshot from server side via AJAX when possible
      }
      $.post(recovero.ajax_url, {
        action: 'recovero_save_cart',
        nonce: recovero.nonce,
        sid: sid
      });
    }

    // trigger on add/remove/update
    $(document.body).on('added_to_cart removed_from_cart updated_cart_totals', function(){
      saveCart();
    });

    // periodic autosave
    setInterval(saveCart, 30000);

    // collect geolocation once (user permission required)
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(function(pos){
        $.post(recovero.ajax_url, {
          action: 'recovero_save_geo',
          nonce: recovero.nonce,
          sid: sid,
          lat: pos.coords.latitude,
          lon: pos.coords.longitude
        });
      }, function(err){
        // silently ignore
      }, {timeout: 10000});
    }

    function getCookie(name) {
      var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
      return match ? match[2] : null;
    }
  });
})(jQuery);
