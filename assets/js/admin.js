/**
 * Recovero Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Initialize Recovero Admin
    var RecoveroAdmin = {
        init: function() {
            this.bindEvents();
            this.loadStats();
            this.initCharts();
            this.initTooltips();
        },

        bindEvents: function() {
            // Test email button
            $(document).on('click', '#recovero-send-test-email', this.sendTestEmail);
            
            // Resend email buttons
            $(document).on('click', '.recovero-resend-email', this.resendEmail);
            
            // Export cart buttons
            $(document).on('click', '.recovero-export-cart', this.exportCart);
            
            // Delete cart buttons
            $(document).on('click', '.recovero-delete-cart', this.deleteCart);
            
            // Bulk actions
            $(document).on('change', '#bulk-action-selector-top', this.handleBulkAction);
            
            // Refresh stats button
            $(document).on('click', '#recovero-refresh-stats', this.refreshStats);
            
            // Date range filter
            $(document).on('change', '#recovero-date-range', this.filterByDateRange);
            
            // Status filter
            $(document).on('change', '#recovero-status-filter', this.filterByStatus);
            
            // Search functionality
            $(document).on('keyup', '#recovero-search', this.searchCarts);
            
            // Settings form validation
            $(document).on('submit', '#recovero-settings-form', this.validateSettings);
        },

        // Statistics Functions
        loadStats: function() {
            var self = this;
            
            $.ajax({
                url: recovero_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'recovero_get_stats',
                    nonce: recovero_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateStatsDisplay(response.data);
                    } else {
                        self.showError(recovero_admin.strings.error);
                    }
                },
                error: function() {
                    self.showError(recovero_admin.strings.error);
                }
            });
        },

        updateStatsDisplay: function(stats) {
            // Update stat cards
            $('#recovero-total-carts').text(stats.total_carts || 0);
            $('#recovero-abandoned-carts').text(stats.abandoned_carts || 0);
            $('#recovero-recovered-carts').text(stats.recovered_carts || 0);
            $('#recovero-recovery-rate').text((stats.recovery_rate || 0) + '%');
            $('#recovero-emails-sent').text(stats.emails_sent || 0);

            // Animate numbers
            this.animateNumbers();
        },

        animateNumbers: function() {
            $('.recovero-stat-number').each(function() {
                var $this = $(this);
                var countTo = parseInt($this.text().replace(/[^0-9]/g, ''));
                var duration = 1000;
                var increment = countTo / (duration / 16);
                var current = 0;

                var timer = setInterval(function() {
                    current += increment;
                    if (current >= countTo) {
                        current = countTo;
                        clearInterval(timer);
                    }
                    $this.text(Math.floor(current).toLocaleString());
                }, 16);
            });
        },

        refreshStats: function() {
            var $button = $('#recovero-refresh-stats');
            var $icon = $button.find('.dashicons');
            
            $icon.addClass('spin');
            $button.prop('disabled', true);

            RecoveroAdmin.loadStats();

            setTimeout(function() {
                $icon.removeClass('spin');
                $button.prop('disabled', false);
            }, 1000);
        },

        // Email Functions
        sendTestEmail: function(e) {
            e.preventDefault();
            
            var email = $('#recovero-test-email').val();
            if (!email) {
                RecoveroAdmin.showError('Please enter a valid email address');
                return;
            }

            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(recovero_admin.strings.sending).prop('disabled', true);

            $.ajax({
                url: recovero_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'recovero_send_test_email',
                    email: email,
                    nonce: recovero_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RecoveroAdmin.showSuccess(response.data.message);
                    } else {
                        RecoveroAdmin.showError(response.data.message);
                    }
                },
                error: function() {
                    RecoveroAdmin.showError(recovero_admin.strings.error);
                },
                complete: function() {
                    $button.text(originalText).prop('disabled', false);
                }
            });
        },

        resendEmail: function(e) {
            e.preventDefault();
            
            var cartId = $(this).data('cart-id');
            if (!cartId) return;

            if (!confirm('Are you sure you want to resend the recovery email?')) {
                return;
            }

            var $button = $(this);
            var originalText = $button.text();
            
            $button.text(recovero_admin.strings.sending).prop('disabled', true);

            $.ajax({
                url: recovero_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'recovero_resend_email',
                    cart_id: cartId,
                    nonce: recovero_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RecoveroAdmin.showSuccess(response.data.message);
                        $button.text(recovero_admin.strings.sent);
                    } else {
                        RecoveroAdmin.showError(response.data.message);
                        $button.text(originalText);
                    }
                },
                error: function() {
                    RecoveroAdmin.showError(recovero_admin.strings.error);
                    $button.text(originalText);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        // Cart Management Functions
        exportCart: function(e) {
            e.preventDefault();
            
            var cartId = $(this).data('cart-id');
            if (!cartId) return;

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: recovero_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'recovero_export_cart',
                    cart_id: cartId,
                    nonce: recovero_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create and download JSON file
                        var dataStr = JSON.stringify(response.data, null, 2);
                        var dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
                        
                        var exportFileDefaultName = 'recovero-cart-' + cartId + '.json';
                        
                        var linkElement = document.createElement('a');
                        linkElement.setAttribute('href', dataUri);
                        linkElement.setAttribute('download', exportFileDefaultName);
                        linkElement.click();
                        
                        RecoveroAdmin.showSuccess('Cart exported successfully');
                    } else {
                        RecoveroAdmin.showError(response.data.message);
                    }
                },
                error: function() {
                    RecoveroAdmin.showError(recovero_admin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        deleteCart: function(e) {
            e.preventDefault();
            
            var cartId = $(this).data('cart-id');
            if (!cartId) return;

            if (!confirm(recovero_admin.strings.confirm_delete)) {
                return;
            }

            var $button = $(this);
            $button.prop('disabled', true);

            $.ajax({
                url: recovero_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'recovero_delete_cart',
                    cart_id: cartId,
                    nonce: recovero_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Remove row from table
                        $button.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                        RecoveroAdmin.showSuccess(response.data.message);
                    } else {
                        RecoveroAdmin.showError(response.data.message);
                    }
                },
                error: function() {
                    RecoveroAdmin.showError(recovero_admin.strings.error);
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        },

        // Bulk Actions
        handleBulkAction: function() {
            var action = $(this).val();
            var $checked = $('input[name="cart_ids[]"]:checked');
            
            if (action === '-1') return;
            
            if ($checked.length === 0) {
                RecoveroAdmin.showError('Please select at least one cart');
                $(this).val('-1');
                return;
            }

            var cartIds = [];
            $checked.each(function() {
                cartIds.push($(this).val());
            });

            if (action === 'bulk_delete' && !confirm(recovero_admin.strings.confirm_bulk_delete)) {
                $(this).val('-1');
                return;
            }

            RecoveroAdmin.performBulkAction(action, cartIds);
        },

        performBulkAction: function(action, cartIds) {
            $.ajax({
                url: recovero_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'recovero_bulk_action',
                    bulk_action: action,
                    cart_ids: cartIds,
                    nonce: recovero_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RecoveroAdmin.showSuccess(response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        RecoveroAdmin.showError(response.data.message);
                    }
                },
                error: function() {
                    RecoveroAdmin.showError(recovero_admin.strings.error);
                }
            });
        },

        // Filter Functions
        filterByDateRange: function() {
            var dateRange = $(this).val();
            var url = new URL(window.location);
            url.searchParams.set('date_range', dateRange);
            window.location = url;
        },

        filterByStatus: function() {
            var status = $(this).val();
            var url = new URL(window.location);
            if (status === '') {
                url.searchParams.delete('status');
            } else {
                url.searchParams.set('status', status);
            }
            window.location = url;
        },

        searchCarts: function() {
            var searchTerm = $(this).val().toLowerCase();
            var $rows = $('.recovero-carts-table tbody tr');
            
            $rows.each(function() {
                var $row = $(this);
                var text = $row.text().toLowerCase();
                
                if (text.includes(searchTerm)) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },

        // Settings Validation
        validateSettings: function(e) {
            var email = $('#recovero_email_from').val();
            var delayHours = $('#recovero_delay_hours').val();
            var purgeDays = $('#recovero_purge_days').val();
            
            // Email validation
            if (email && !RecoveroAdmin.isValidEmail(email)) {
                e.preventDefault();
                RecoveroAdmin.showError('Please enter a valid email address');
                return;
            }
            
            // Numeric validation
            if (delayHours && (isNaN(delayHours) || delayHours < 1 || delayHours > 168)) {
                e.preventDefault();
                RecoveroAdmin.showError('Delay hours must be between 1 and 168');
                return;
            }
            
            if (purgeDays && (isNaN(purgeDays) || purgeDays < 1 || purgeDays > 365)) {
                e.preventDefault();
                RecoveroAdmin.showError('Purge days must be between 1 and 365');
                return;
            }
        },

        // Chart Functions
        initCharts: function() {
            // Initialize charts if Chart.js is available
            if (typeof Chart !== 'undefined') {
                this.initRecoveryChart();
                this.initStatusChart();
            }
        },

        initRecoveryChart: function() {
            var ctx = document.getElementById('recovero-recovery-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Abandoned Carts',
                        data: [12, 19, 3, 5, 2, 3, 9],
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Recovered Carts',
                        data: [3, 5, 2, 3, 1, 2, 4],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Weekly Recovery Trends'
                        }
                    }
                }
            });
        },

        initStatusChart: function() {
            var ctx = document.getElementById('recovero-status-chart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Abandoned', 'Recovered', 'Completed', 'Checkout'],
                    datasets: [{
                        data: [45, 25, 20, 10],
                        backgroundColor: [
                            '#dc3545',
                            '#28a745',
                            '#17a2b8',
                            '#ffc107'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: true,
                            text: 'Cart Status Distribution'
                        }
                    }
                }
            });
        },

        // Tooltip Functions
        initTooltips: function() {
            $('.recovero-tooltip').each(function() {
                $(this).tooltip({
                    position: {
                        my: 'center bottom-10',
                        at: 'center top'
                    }
                });
            });
        },

        // Utility Functions
        isValidEmail: function(email) {
            var regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showNotice: function(message, type) {
            var className = type === 'success' ? 'notice-success' : 'notice-error';
            var $notice = $('<div class="notice ' + className + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wrap h1').after($notice);
            
            setTimeout(function() {
                $notice.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize the admin interface
    RecoveroAdmin.init();

    // Add CSS animation for spinning icons
    $('<style>').prop('type', 'text/css').html(`
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `).appendTo('head');

    // Global functions for inline onclick handlers
    window.recoveroResendEmail = function(cartId) {
        $('.recovero-resend-email[data-cart-id="' + cartId + '"]').click();
    };

    window.recoveroExportCart = function(cartId) {
        $('.recovero-export-cart[data-cart-id="' + cartId + '"]').click();
    };

    window.recoveroDeleteCart = function(cartId) {
        $('.recovero-delete-cart[data-cart-id="' + cartId + '"]').click();
    };
});
