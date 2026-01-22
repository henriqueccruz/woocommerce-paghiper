<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Paghiper_Migration_1_2_0 implements WC_Paghiper_Migration_Interface {
	
	public function up() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$upload_dir   = wp_upload_dir();
		$paghiper_dir = trailingslashit( $upload_dir['basedir'] ) . 'paghiper';
		$target_dir   = trailingslashit( $paghiper_dir ) . 'billets';

		// Check if the source directory exists
		if ( ! $wp_filesystem->is_dir( $paghiper_dir ) ) {
			return; // Nothing to migrate
		}

		// Create target directory if it doesn't exist
		if ( ! $wp_filesystem->is_dir( $target_dir ) ) {
			if ( ! wp_mkdir_p( $target_dir ) ) {
				error_log( 'WC PagHiper Migration 1.2.0: Failed to create directory ' . $target_dir );
				return;
			}
		}

		// Get files in the PagHiper directory
		$files = $wp_filesystem->dirlist( $paghiper_dir );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				// Skip the target directory itself and current/parent pointers just in case
				if ( 'billets' === $file['name'] || '.' === $file['name'] || '..' === $file['name'] ) {
					continue;
				}

				$source_file = trailingslashit( $paghiper_dir ) . $file['name'];
				$destination = trailingslashit( $target_dir ) . $file['name'];

				// Move the file/directory
				if ( ! $wp_filesystem->move( $source_file, $destination ) ) {
					error_log( "WC PagHiper Migration 1.2.0: Failed to move {$source_file} to {$destination}" );
				}
			}
		}

		set_transient( 'woo_paghiper_notice_3_0', true, (5 * 24 * 60 * 60) );

	}
}