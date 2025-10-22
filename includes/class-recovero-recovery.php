<?php
if (! defined( 'ABSPATH' ) ) exit;

class Recovero_Recovery {
    protected $db;

    public function __construct( $db = null ) {
        $this->db = $db ? $db : new Recovero_DB();

        // Hook: when order is completed/thankyou, mark recovered if match
        add_action( 'woocommerce_thankyou', [ $this, 'handle_order_created' ], 10, 1 );
    }

    /**
     * Create & return recovery link token and persist log entry
     */
    public function create_and_send( $cart_row ) {
        $token = recovero_generate_token(32);
        // insert a recovery log
        $this->db->add_recovery_log([
            'cart_id' => $cart_row->id,
            'action'  => 'generate_token',
            'channel' => 'email',
            'payload' => $token,
            'created_at' => current_time('mysql')
        ]);

        // send email
        $this->send_email( $cart_row, $token );

        // If Pro & phone present, send WhatsApp as well
        $phone = isset( $cart_row->phone ) ? $cart_row->phone : '';
        if ( ! empty( $phone ) && get_option( 'recovero_whatsapp_enable', false ) ) {
            $this->send_whatsapp( $cart_row, $token );
        }
    }

    /**
     * Send recovery email (HTML) using template from views/email-template.php
     * @param object|array $cart_row
     * @param string $token
     */
    public function send_email( $cart_row, $token = '' ) {
        $to = isset( $cart_row->email ) ? $cart_row->email : '';
        if ( empty( $to ) ) return false;

        $from = sanitize_email( get_option( 'recovero_email_from', get_option('admin_email') ) );
        $subject = apply_filters( 'recovero_email_subject', __( 'You left items in your cart', 'recovero' ) );

        $recovery_link = add_query_arg( 'recovero_token', $token, home_url( '/' ) );

        // load view template (prefer child override in plugin dir)
        $template_file = RECOVERO_PATH . 'assets/views/email-template.php';
        ob_start();
        if ( file_exists( $template_file ) ) {
            // provide variables for template
            $cart_items = maybe_unserialize( $cart_row->cart_data );
            $subtotal = isset( $cart_row->subtotal ) ? $cart_row->subtotal : '';
            $customer_name = '';
            if ( isset( $cart_row->user_id ) && ! empty( $cart_row->user_id ) ) {
                $user = get_userdata( $cart_row->user_id );
                $customer_name = $user ? $user->display_name : '';
            }
            include $template_file;
        } else {
            // fallback
            echo '<p>' . esc_html__( 'You left items in your cart. Click the link to recover:', 'recovero' ) . '</p>';
            echo '<p><a href="' . esc_url( $recovery_link ) . '">' . esc_html__( 'Recover my cart', 'recovero' ) . '</a></p>';
        }
        $message = ob_get_clean();

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        if ( ! empty( $from ) ) {
            $headers[] = 'From: ' . wp_specialchars_decode( get_bloginfo( 'name' ) ) . ' <' . $from . '>';
        }

        // send
        $sent = wp_mail( $to, $subject, $message, $headers );

        // log
        $this->db->add_recovery_log([
            'cart_id' => $cart_row->id,
            'action'  => 'email_sent',
            'channel' => 'email',
            'payload' => json_encode( [ 'to' => $to, 'token' => $token, 'sent' => $sent ] ),
            'created_at' => current_time('mysql')
        ]);

        return $sent;
    }

    /**
     * Send WhatsApp (text) message using Recovero_WhatsApp
     */
    public function send_whatsapp( $cart_row, $token = '' ) {
        if ( ! class_exists( 'Recovero_WhatsApp' ) ) {
            require_once RECOVERO_PATH . 'includes/class-recovero-whatsapp.php';
        }
        $wa = new Recovero_WhatsApp();

        $to = isset( $cart_row->phone ) ? $cart_row->phone : '';
        if ( empty( $to ) ) return new WP_Error( 'no_phone', 'No phone number' );

        // build message
        $store = get_bloginfo( 'name' );
        $customer = '';
        if ( ! empty( $cart_row->user_id ) ) {
            $user = get_userdata( $cart_row->user_id );
            $customer = $user ? $user->display_name : '';
        }

        // basic items list
        $items = maybe_unserialize( $cart_row->cart_data );
        $list = [];
        if ( is_array( $items ) ) {
            foreach ( $items as $it ) {
                // item may be serialized product object or array
                if ( isset( $it['data'] ) && is_object( $it['data'] ) ) {
                    $name = $it['data']->get_name();
                    $qty = isset( $it['quantity'] ) ? intval( $it['quantity'] ) : 1;
                } else {
                    $name = isset( $it['name'] ) ? $it['name'] : ( isset( $it['product_id'] ) ? 'Product ' . $it['product_id'] : 'Item' );
                    $qty = isset( $it['qty'] ) ? intval( $it['qty'] ) : ( isset( $it['quantity'] ) ? intval( $it['quantity'] ) : 1 );
                }
                $list[] = $name . ' x ' . $qty;
            }
        }
        $items_text = implode( ', ', array_slice( $list, 0, 10 ) );

        $recovery_link = add_query_arg( 'recovero_token', $token, home_url( '/' ) );

        // Compose message - you can customize or use template messages
        $message = sprintf( "Hi %s,\nYou left items in your cart at %s: %s\nComplete your order: %s", 
            ! empty( $customer ) ? $customer : 'there',
            $store,
            $items_text,
            $recovery_link
        );

        $res = $wa->send_text( $to, $message );

        // log
        $this->db->add_recovery_log([
            'cart_id' => $cart_row->id,
            'action'  => 'whatsapp_sent',
            'channel' => 'whatsapp',
            'payload' => is_wp_error( $res ) ? $res->get_error_message() : json_encode( $res ),
            'created_at' => current_time('mysql')
        ]);

        return $res;
    }

    /**
     * When an order is created (thankyou page), try to find matching abandoned cart and mark as recovered
     * @param int $order_id
     */
    public function handle_order_created( $order_id ) {
        if ( ! $order_id ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $email = $order->get_billing_email();
        $user_id = $order->get_user_id();
        $phone = $order->get_billing_phone();

        global $wpdb;
        $table = $wpdb->prefix . 'recovero_abandoned_carts';

        // Try match by user_id, then email, then session (if stored in order meta)
        $cart = null;
        if ( $user_id ) {
            $cart = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id ) );
        }
        if ( ! $cart && $email ) {
            $cart = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY created_at DESC LIMIT 1", $email ) );
        }
        if ( ! $cart && $phone ) {
            $cart = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone = %s ORDER BY created_at DESC LIMIT 1", $phone ) );
        }

        if ( $cart ) {
            // mark recovered
            $wpdb->update( $table, [
                'status' => 'recovered',
                'recovered_at' => current_time('mysql')
            ], [ 'id' => $cart->id ], [ '%s', '%s' ], [ '%d' ] );

            // log recovery
            $wpdb->insert( $wpdb->prefix . 'recovero_recovery_logs', [
                'cart_id' => $cart->id,
                'action' => 'order_created',
                'channel' => 'order',
                'payload' => json_encode( [ 'order_id' => $order_id ] ),
                'created_at' => current_time('mysql')
            ] );
        }
    }
}
