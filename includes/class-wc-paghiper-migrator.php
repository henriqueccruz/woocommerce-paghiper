<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database migrations and versioning.
 */
class WC_Paghiper_Migrator {

	const DB_VERSION_OPTION = 'woocommerce_paghiper_db_version';

	/**
	 * List of migrations.
	 * key: version number
	 * value: class name
	 * 
	 * @var array
	 */
	protected $migrations = [
		'1.2.0'	=> 'WC_Paghiper_Migration_1_2_0',
	];

	/**
	 * Initialize the migrator.
	 */
	public function __construct() {
		// Run checks on admin_init to ensure everything is loaded
		add_action( 'admin_init', array( $this, 'check_version' ), 5 );
	}

	/**
	 * Check if database version is out of sync with code version.
	 */
	public function check_version() {
		$current_db_version = get_option( self::DB_VERSION_OPTION, '1.2.0' );

		// If current DB version is lower than the latest plugin version, we might need to migrate.
		if ( version_compare( $current_db_version, WC_Paghiper::VERSION, '<' ) ) {
			$this->migrate( $current_db_version );
		}
	}

	/**
	 * Run necessary migrations in order.
	 * 
	 * @param string $current_db_version The current version stored in the database.
	 */
	private function migrate( $current_db_version ) {
		
		// Ensure migrations are sorted by version (key)
		uksort( $this->migrations, 'version_compare' );

		foreach ( $this->migrations as $version => $class_name ) {
			// Only run migrations that are newer than the current DB version
			if ( version_compare( $version, $current_db_version, '>' ) ) {
				$success = $this->run_migration( $version, $class_name );
				
				// If a step fails, we stop to prevent inconsistent state
				if ( ! $success ) {
					// Optionally log critical failure
					return;
				}

				// Update the DB version after EACH successful migration.
				// This ensures that if 1.3 fails, 1.2 is already saved as applied.
				update_option( self::DB_VERSION_OPTION, $version );
				$current_db_version = $version;
			}
		}

		// Finally, ensure the DB version matches the plugin version 
		// if all eligible migrations ran successfully (or if there were none).
		update_option( self::DB_VERSION_OPTION, WC_Paghiper::VERSION );
	}

	/**
	 * Execute a single migration.
	 * 
	 * @param string $version The version being migrated to.
	 * @param string $class_name The class name handling the migration.
	 * @return bool True on success, False on failure.
	 */
	private function run_migration( $version, $class_name ) {
		$file_name = 'class-wc-paghiper-migration-' . str_replace( '.', '-', $version ) . '.php';
		$file_path = plugin_dir_path( __FILE__ ) . 'migrations/' . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			
			if ( class_exists( $class_name ) ) {
				$migration = new $class_name();
				if ( $migration instanceof WC_Paghiper_Migration_Interface ) {
					try {
						$migration->up();
						// Log success
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "WC PagHiper: Migration to {$version} executed successfully." );
						}
						return true;
					} catch ( Exception $e ) {
						error_log( "WC PagHiper: Migration to {$version} failed. Error: " . $e->getMessage() );
						return false;
					}
				}
			}
		} else {
			error_log( "WC PagHiper: Migration file for {$version} not found at {$file_path}" );
			return false;
		}

		return true;
	}
}
