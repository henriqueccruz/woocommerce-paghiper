<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';

class WC_PagHiper_Upgrader_Skin extends WP_Upgrader_Skin {
    public $feedback = array();

    public function __construct($args = array()) {
        parent::__construct($args);
    }

    public function header() {}
    public function footer() {}
    public function error($errors) {}
    public function feedback($string, ...$args) {
        if ( isset( $this->upgrader->strings[ $string ] ) ) {
            $string = $this->upgrader->strings[ $string ];
        }
        if ( strpos( $string, '%' ) !== false ) {
            if ( $args ) {
                $args = array_map( 'strip_tags', $args );
                $args = array_map( 'esc_html', $args );
                $string = vsprintf( $string, $args );
            }
        }
        if ( empty( $string ) ) {
            return;
        }
        $this->feedback[] = $string;
    }
}