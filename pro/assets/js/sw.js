self.addEventListener('push', function(e){
  var data = {};
  if (e.data) data = e.data.json();
  var title = data.title || 'Recovero';
  var options = {
    body: data.body || '',
    icon: '/wp-content/plugins/recovero/assets/images/icon-192.png'
  };
  e.waitUntil(self.registration.showNotification(title, options));
});
