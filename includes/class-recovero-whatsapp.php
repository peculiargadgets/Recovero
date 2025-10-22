<?php
if (! defined( 'ABSPATH' ) ) exit;

class Recovero_WhatsApp {
    private $access_token;
    private $phone_number_id;
    private $api_version = 'v17.0'; // adjust if needed

    public function __construct() {
        // read from options (add fields in admin settings)
        $this->access_token = get_option( 'recovero_whatsapp_access_token', '' );
        $this->phone_number_id = get_option( 'recovero_whatsapp_phone_number_id', '' );
    }

    /**
     * Send a plain text message via WhatsApp Cloud API
     * @param string $to E.164 phone number, e.g. "88017xxxxxxx" or "15551234567"
     * @param string $message plain text
     * @return array|WP_Error
     */
    public function send_text( $to, $message ) {
        if ( empty( $this->access_token ) || empty( $this->phone_number_id ) ) {
            return new WP_Error( 'wa_not_configured', 'WhatsApp not configured' );
        }

        // ensure phone format (very basic) - no plus sign
        $to = preg_replace('/[^0-9]/', '', $to);

        $endpoint = "https://graph.facebook.com/{$this->api_version}/{$this->phone_number_id}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message,
                'preview_url' => false
            ]
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode( $body ),
            'timeout' => 20
        ];

        $resp = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $resp ) ) {
            return $resp;
        }

        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $json = json_decode( $body, true );

        if ( $code >= 200 && $code < 300 ) {
            // success
            return $json;
        }

        // Graph API errors
        return new WP_Error( 'wa_api_error', $body );
    }

    /**
     * Send a template message (if you have pre-approved template)
     * This uses message templates. Provide $template_name and $language (e.g., en_US) and components array.
     * @param string $to
     * @param string $template_name
     * @param string $language
     * @param array $components
     */
    public function send_template( $to, $template_name, $language = 'en_US', $components = [] ) {
        if ( empty( $this->access_token ) || empty( $this->phone_number_id ) ) {
            return new WP_Error( 'wa_not_configured', 'WhatsApp not configured' );
        }

        $to = preg_replace('/[^0-9]/', '', $to);
        $endpoint = "https://graph.facebook.com/{$this->api_version}/{$this->phone_number_id}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template_name,
                'language' => ['code' => $language],
            ]
        ];

        if ( ! empty( $components ) && is_array( $components ) ) {
            $body['template']['components'] = $components;
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode( $body ),
            'timeout' => 20
        ];

        $resp = wp_remote_post( $endpoint, $args );

        if ( is_wp_error( $resp ) ) return $resp;

        $code = wp_remote_retrieve_response_code( $resp );
        $body = wp_remote_retrieve_body( $resp );
        $json = json_decode( $body, true );

        if ( $code >= 200 && $code < 300 ) return $json;

        return new WP_Error( 'wa_api_error', $body );
    }
}
