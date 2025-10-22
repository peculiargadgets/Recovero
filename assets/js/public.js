/**
 * Recovero Public JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize Recovero
    var Recovero = {
        init: function() {
            // Check if recovero_public is defined
            if (typeof recovero_public === 'undefined') {
                console.warn('Recovero: Public variables not defined');
                return;
            }

            this.config = {
                ajaxUrl: recovero_public.ajax_url || '/wp-admin/admin-ajax.php',
                nonce: recovero_public.nonce || '',
                exitIntentEnabled: recovero_public.exit_intent_enabled || false,
                pushEnabled: recovero_public.push_enabled || false,
                whatsappEnabled: recovero_public.whatsapp_enabled || false,
                cartTrackingEnabled: recovero_public.cart_tracking_enabled || false,
                pluginUrl: recovero_public.plugin_url || '',
                cartUrl: recovero_public.cart_url || '/cart/',
                checkoutUrl: recovero_public.checkout_url || '/checkout/'
            };

            this.bindEvents();
            this.initExitIntent();
            this.initPushNotifications();
            this.initWhatsAppWidget();
            this.initCartTracking();
            this.initGeoTracking();
            this.initRecoveryBanner();
        },

        bindEvents: function() {
            // Exit intent close
            $(document).on('click', '.recovero-exit-intent-close, .recovero-exit-intent-no-thanks', this.closeExitIntent);
            
            // Exit intent form submit
            $(document).on('submit', '.recovero-exit-intent-form', this.submitExitIntentForm);
            
            // WhatsApp widget
            $(document).on('click', '.recovero-whatsapp-button', this.toggleWhatsAppPopup);
            $(document).on('click', '.recovero-whatsapp-popup-close', this.closeWhatsAppPopup);
            $(document).on('submit', '.recovero-whatsapp-form', this.submitWhatsAppForm);
            
            // Push notification actions
            $(document).on('click', '.recovero-push-notification-close', this.closePushNotification);
            $(document).on('click', '.recovero-push-notification-button', this.handlePushAction);
            
            // Cart recovery tab
            $(document).on('click', '.recovero-cart-tab', this.toggleCartTab);
            $(document).on('click', '.recovero-cart-tab-button', this.recoverCart);
            
            // Recovery banner
            $(document).on('click', '.recovero-recovery-banner-close', this.closeRecoveryBanner);
            $(document).on('click', '.recovero-recovery-banner-button', this.handleRecoveryBannerAction);
            
            // Page visibility change
            $(document).on('visibilitychange', this.handleVisibilityChange);
            
            // Before unload
            $(window).on('beforeunload', this.handleBeforeUnload);
            
            // Mouse leave detection
            $(document).on('mouseleave', this.handleMouseLeave);
        },

        // Exit Intent
        initExitIntent: function() {
            if (!this.config.exitIntentEnabled) return;

            // Check if user has seen exit intent recently
            if (this.getCookie('recovero_exit_intent_seen')) {
                return;
            }

            // Set up exit intent detection
            var self = this;
            var exitIntentShown = false;

            $(document).on('mouseleave', function(e) {
                if (exitIntentShown) return;
                
                if (e.clientY <= 0) {
                    self.showExitIntent();
                    exitIntentShown = true;
                }
            });
        },

        showExitIntent: function() {
            var $popup = $('.recovero-exit-intent');
            
            if ($popup.length === 0) {
                // Create exit intent popup if it doesn't exist
                this.createExitIntentPopup();
                $popup = $('.recovero-exit-intent');
            }

            $popup.addClass('show');
            this.setCookie('recovero_exit_intent_seen', 'true', 24); // 24 hours
        },

        createExitIntentPopup: function() {
            var template = `
                <div class="recovero-exit-intent">
                    <div class="recovero-exit-intent-content">
                        <button class="recovero-exit-intent-close">&times;</button>
                        <h2 class="recovero-exit-intent-title">Wait! Don't leave empty-handed</h2>
                        <p class="recovero-exit-intent-message">You have items in your cart. Complete your order now and get 10% off!</p>
                        <div class="recovero-exit-intent-discount">Use code: BACK10</div>
                        <form class="recovero-exit-intent-form">
                            <input type="email" class="recovero-exit-intent-email" placeholder="Enter your email" required>
                            <button type="submit" class="recovero-exit-intent-button">Get Discount & Continue</button>
                        </form>
                        <button class="recovero-exit-intent-no-thanks">No thanks, I'll pay full price</button>
                    </div>
                </div>
            `;
            
            $('body').append(template);
        },

        closeExitIntent: function() {
            $('.recovero-exit-intent').removeClass('show');
        },

        submitExitIntentForm: function(e) {
            e.preventDefault();
            
            var email = $('.recovero-exit-intent-email').val();
            if (!email) return;

            // Save email and continue
            Recovero.saveEmailForRecovery(email);
            Recovero.closeExitIntent();
            
            // Apply discount and redirect to checkout
            window.location.href = recovero_public.checkout_url + '?discount=BACK10';
        },

        // Push Notifications
        initPushNotifications: function() {
            if (!this.config.pushEnabled) return;

            // Request permission for push notifications
            if ('Notification' in window && Notification.permission === 'default') {
                this.requestPushPermission();
            }
        },

        requestPushPermission: function() {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    Recovero.subscribeToPushNotifications();
                }
            });
        },

        subscribeToPushNotifications: function() {
            // This would integrate with a push service like OneSignal or Firebase
            // For now, we'll just show a demo notification
            this.showPushNotification({
                title: 'Welcome to Recovero!',
                body: 'You\'ll receive notifications about your abandoned cart.',
                icon: recovero_public.plugin_url + '/assets/images/icon.png'
            });
        },

        showPushNotification: function(data) {
            if ('Notification' in window && Notification.permission === 'granted') {
                var notification = new Notification(data.title, {
                    body: data.body,
                    icon: data.icon,
                    tag: 'recovero'
                });

                notification.onclick = function() {
                    window.focus();
                    notification.close();
                };

                setTimeout(function() {
                    notification.close();
                }, 5000);
            } else {
                // Fallback to in-app notification
                this.showInAppNotification(data);
            }
        },

        showInAppNotification: function(data) {
            var template = `
                <div class="recovero-push-notification show">
                    <div class="recovero-push-notification-header">
                        <div class="recovero-push-notification-icon">
                            <i class="dashicons dashicons-cart"></i>
                        </div>
                        <div class="recovero-push-notification-title">${data.title}</div>
                        <button class="recovero-push-notification-close">&times;</button>
                    </div>
                    <div class="recovero-push-notification-body">${data.body}</div>
                    <div class="recovero-push-notification-actions">
                        <button class="recovero-push-notification-button primary">View Cart</button>
                        <button class="recovero-push-notification-button secondary">Dismiss</button>
                    </div>
                </div>
            `;
            
            $('body').append(template);

            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.recovero-push-notification').removeClass('show');
                setTimeout(function() {
                    $('.recovero-push-notification').remove();
                }, 300);
            }, 5000);
        },

        closePushNotification: function() {
            $('.recovero-push-notification').removeClass('show');
            setTimeout(function() {
                $('.recovero-push-notification').remove();
            }, 300);
        },

        handlePushAction: function(e) {
            var $button = $(e.target);
            var action = $button.hasClass('primary') ? 'view_cart' : 'dismiss';
            
            if (action === 'view_cart') {
                window.location.href = recovero_public.cart_url;
            } else {
                Recovero.closePushNotification();
            }
        },

        // WhatsApp Widget
        initWhatsAppWidget: function() {
            if (!this.config.whatsappEnabled) return;

            // Create WhatsApp widget if it doesn't exist
            if ($('.recovero-whatsapp-widget').length === 0) {
                this.createWhatsAppWidget();
            }
        },

        createWhatsAppWidget: function() {
            var template = `
                <div class="recovero-whatsapp-widget">
                    <div class="recovero-whatsapp-popup">
                        <div class="recovero-whatsapp-popup-header">
                            <i class="dashicons dashicons-whatsapp"></i>
                            Need help with your order?
                        </div>
                        <div class="recovero-whatsapp-popup-body">
                            <div class="recovero-whatsapp-popup-message">
                                Hi! I noticed you have items in your cart. Can I help you complete your order?
                            </div>
                            <form class="recovero-whatsapp-form">
                                <input type="tel" class="recovero-whatsapp-popup-input" placeholder="Your phone number" required>
                                <button type="submit" class="recovero-whatsapp-popup-button">Send WhatsApp</button>
                            </form>
                        </div>
                    </div>
                    <button class="recovero-whatsapp-button">
                        <i class="dashicons dashicons-whatsapp"></i>
                    </button>
                </div>
            `;
            
            $('body').append(template);
        },

        toggleWhatsAppPopup: function() {
            $('.recovero-whatsapp-popup').toggleClass('show');
        },

        closeWhatsAppPopup: function() {
            $('.recovero-whatsapp-popup').removeClass('show');
        },

        submitWhatsAppForm: function(e) {
            e.preventDefault();
            
            var phone = $('.recovero-whatsapp-popup-input').val();
            if (!phone) return;

            // Send WhatsApp message
            var message = encodeURIComponent('Hi! I need help with my order.');
            var whatsappUrl = 'https://wa.me/' + phone.replace(/[^0-9]/g, '') + '?text=' + message;
            
            window.open(whatsappUrl, '_blank');
            Recovero.closeWhatsAppPopup();
        },

        // Cart Tracking
        initCartTracking: function() {
            if (!this.config.cartTrackingEnabled) return;

            var self = this;
            
            // Track cart changes
            if (typeof wc_cart_fragments_params !== 'undefined') {
                $(document.body).on('added_to_cart removed_from_cart updated_cart_totals', function() {
                    self.trackCartUpdate();
                });
            }

            // Track page views for cart abandonment
            this.trackPageView();
        },

        trackCartUpdate: function() {
            // Get current cart data
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'recovero_track_page_view',
                    cart_data: this.getCartData(),
                    nonce: this.config.nonce
                }
            });
        },

        trackPageView: function() {
            // Track page view for potential abandonment
            if (this.hasItemsInCart()) {
                this.trackCartUpdate();
            }
        },

        getCartData: function() {
            // This would get actual cart data from WooCommerce
            // For now, return empty array
            return [];
        },

        hasItemsInCart: function() {
            // Check if cart has items
            if (typeof wc_cart_fragments_params !== 'undefined') {
                return $('.woocommerce-cart-form').length > 0 || 
                       $(document.body).hasClass('woocommerce-cart') ||
                       $(document.body).hasClass('woocommerce-checkout');
            }
            return false;
        },

        // Geo Tracking
        initGeoTracking: function() {
            if (!this.getCookie('recovero_geo_tracked')) {
                this.trackGeoLocation();
            }
        },

        trackGeoLocation: function() {
            var self = this;
            
            // Get user location
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    self.saveGeoData(position.coords.latitude, position.coords.longitude);
                }, function() {
                    // Fallback to IP-based location
                    self.saveGeoDataByIP();
                });
            } else {
                self.saveGeoDataByIP();
            }
        },

        saveGeoData: function(lat, lon) {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'recovero_save_geo',
                    lat: lat,
                    lon: lon,
                    browser: this.getBrowserInfo(),
                    device: this.getDeviceInfo(),
                    nonce: this.config.nonce
                }
            });

            this.setCookie('recovero_geo_tracked', 'true', 24); // 24 hours
        },

        saveGeoDataByIP: function() {
            // This would use an IP geolocation service
            // For now, just save basic info
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'recovero_save_geo',
                    browser: this.getBrowserInfo(),
                    device: this.getDeviceInfo(),
                    nonce: this.config.nonce
                }
            });

            this.setCookie('recovero_geo_tracked', 'true', 24);
        },

        getBrowserInfo: function() {
            var ua = navigator.userAgent;
            if (ua.indexOf('Chrome') > -1) return 'Chrome';
            if (ua.indexOf('Safari') > -1) return 'Safari';
            if (ua.indexOf('Firefox') > -1) return 'Firefox';
            if (ua.indexOf('Edge') > -1) return 'Edge';
            return 'Other';
        },

        getDeviceInfo: function() {
            var ua = navigator.userAgent;
            if (/Mobile|Android|iPhone|iPad/.test(ua)) return 'Mobile';
            if (/Tablet/.test(ua)) return 'Tablet';
            return 'Desktop';
        },

        // Recovery Banner
        initRecoveryBanner: function() {
            // Check if user has abandoned cart
            if (this.hasAbandonedCart() && !this.getCookie('recovero_banner_shown')) {
                setTimeout(function() {
                    Recovero.showRecoveryBanner();
                }, 3000); // Show after 3 seconds
            }
        },

        hasAbandonedCart: function() {
            // Check if user has abandoned cart based on cookies or session
            return this.getCookie('recovero_cart_abandoned') === 'true';
        },

        showRecoveryBanner: function() {
            var template = `
                <div class="recovero-recovery-banner show">
                    <div class="recovero-recovery-banner-content">
                        <div class="recovero-recovery-banner-text">
                            <div class="recovero-recovery-banner-title">You left something behind!</div>
                            <div class="recovero-recovery-banner-message">Complete your order before items sell out</div>
                        </div>
                        <div class="recovero-recovery-banner-actions">
                            <a href="${recovero_public.checkout_url}" class="recovero-recovery-banner-button">Complete Order</a>
                            <button class="recovero-recovery-banner-close">&times;</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').prepend(template);
            this.setCookie('recovero_banner_shown', 'true', 1); // 1 hour
        },

        closeRecoveryBanner: function() {
            $('.recovero-recovery-banner').removeClass('show');
        },

        handleRecoveryBannerAction: function(e) {
            e.preventDefault();
            window.location.href = $(e.target).attr('href');
        },

        // Cart Recovery Tab
        toggleCartTab: function() {
            $('.recovero-cart-tab-content').toggleClass('show');
        },

        recoverCart: function() {
            window.location.href = recovero_public.checkout_url;
        },

        // Event Handlers
        handleVisibilityChange: function() {
            if (document.hidden) {
                // User switched tabs or minimized window
                this.trackCartAbandonment();
            }
        },

        handleBeforeUnload: function(e) {
            if (this.hasItemsInCart()) {
                this.trackCartAbandonment();
                
                // Show custom message (browser may not display it)
                var message = 'You have items in your cart. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        },

        handleMouseLeave: function(e) {
            if (e.clientY <= 0 && this.hasItemsInCart()) {
                this.trackCartAbandonment();
            }
        },

        trackCartAbandonment: function() {
            this.setCookie('recovero_cart_abandoned', 'true', 1); // 1 hour
        },

        // Utility Functions
        saveEmailForRecovery: function(email) {
            // Save email for recovery purposes
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'recovero_save_email',
                    email: email,
                    nonce: this.config.nonce
                }
            });
        },

        setCookie: function(name, value, hours) {
            var expires = '';
            if (hours) {
                var date = new Date();
                date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
                expires = '; expires=' + date.toUTCString();
            }
            document.cookie = name + '=' + value + expires + '; path=/';
        },

        getCookie: function(name) {
            var nameEQ = name + '=';
            var ca = document.cookie.split(';');
            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        },

        deleteCookie: function(name) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        }
    };

    // Initialize Recovero
    Recovero.init();

    // Make Recovero available globally
    window.Recovero = Recovero;
});
