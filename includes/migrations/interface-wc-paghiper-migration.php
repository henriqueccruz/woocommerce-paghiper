<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for all migration classes.
 */
interface WC_Paghiper_Migration_Interface {
	/**
	 * Run the migration logic.
	 */
	public function up();
}
