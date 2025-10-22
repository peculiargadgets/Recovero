(function($){
  $(function(){
    // register push subscription flow
    if (!('serviceWorker' in navigator)) return;

    navigator.serviceWorker.ready.then(function(reg) {
      // ask permission
      Notification.requestPermission(function(status){
        if (status === 'granted') {
          // Normally use pushManager.subscribe with VAPID public key
          // For demo we send dummy subscription to server
          var subscription = { demo: true, ts: Date.now() };
          $.post(recoveroPush.ajax_url, {
            action: 'recovero_save_subscription',
            nonce: recoveroPush.nonce,
            subscription: JSON.stringify(subscription),
            sid: document.cookie.match('(^|;) ?recovero_sid=([^;]*)(;|$)') ? RegExp.$2 : ''
          });
        }
      });
    });
  });
})(jQuery);
