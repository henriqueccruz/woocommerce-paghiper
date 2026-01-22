<?php
/* * PagHiper Admin Class
 *
 * @package PagHiper for WooCommerce
 */

// For the WP team: error_log() is used only on emergency type of errors.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PagHiper\PagHiper;

/**
 * Admin Class
 */
class WC_Paghiper_Admin {

	private $timezone;
	private $log;

	/**
	 * Initialize the admin.
	 */
	public function __construct() {

		// Define our default offset
		$this->timezone = new DateTimeZone('America/Sao_Paulo');

		// Initialize logging
		$this->log = wc_paghiper_initialize_log('yes');

		// Enqueue styles and assets
		add_action( 'admin_enqueue_scripts', array( $this, 'load_plugin_assets' ) );

		// Hook for the admin notice
		add_action( 'admin_notices', array( $this, 'check_long_pix_expiration_notice' ) );

		// Hook for the AJAX handler
		add_action( 'wp_ajax_paghiper_handle_long_expiration_notice', array( $this, 'ajax_handle_long_expiration_notice' ) );

		// AJAX handler for copying credentials
		add_action( 'wp_ajax_paghiper_copy_credentials', array( $this, 'ajax_copy_credentials' ) );

		// AJAX handler for testing credentials
		add_action( 'wp_ajax_paghiper_test_credentials', array( $this, 'ajax_test_credentials' ) );

		// AJAX handler for downloading timer assets
		add_action( 'wp_ajax_paghiper_download_timer_assets', array( $this, 'ajax_download_timer_assets' ) );

		// AJAX handler for checking timer assets status
		add_action( 'wp_ajax_paghiper_get_timer_asset_status', array( $this, 'ajax_get_timer_asset_status' ) );

		// AJAX handler for installing beta versions from GitHub
		add_action( 'wp_ajax_paghiper_install_beta_version', array( $this, 'ajax_install_beta_version' ) );
	}

	/**
	 * Register and enqueue assets
	 */

	    public function load_plugin_assets() {
	
	        wp_register_style( 
	            'wc-paghiper-admin', 
	            wc_paghiper_assets_url( '/css/admin.min.css' ), [], '1.0.0' );
	
	        wp_register_script( 
	            'wc-paghiper-admin', 
	            wc_paghiper_assets_url( '/js/admin.min.js' ), ['jquery'], '1.0.0', true );
	
	        wp_register_script( 
	            'wc-paghiper-notices', 
	            wc_paghiper_assets_url( '/js/notices.min.js' ), ['jquery'], '1.0.0', true );
	
	        if(is_admin()) {
	            if(is_array($_GET) && array_key_exists('page', $_GET) && array_key_exists('section', $_GET)) {
	
	                if($_GET['page'] === 'wc-settings' && in_array($_GET['section'], ['paghiper_billet', 'wc_paghiper_billet_gateway', 'paghiper_pix', 'wc_paghiper_pix_gateway'])) {
	                    
	                    $gateway_id = sanitize_text_field($_GET['section']);
	                    $settings_key = "woocommerce_{$gateway_id}_settings";
	                    $gateway_settings = get_option($settings_key);
	
	                    $is_pix = in_array($gateway_id, ['paghiper_pix', 'wc_paghiper_pix_gateway']);
	                    $default_mode = $is_pix ? 'minutes' : 'days';
	                    $default_value = $is_pix ? 30 : 3;
	
	                    $due_date_mode = !empty($gateway_settings['due_date_mode']) ? $gateway_settings['due_date_mode'] : $default_mode;
	                    $due_date_value = !empty($gateway_settings['due_date_value']) ? $gateway_settings['due_date_value'] : $default_value;
	
	                    $settings_to_pass = array(
	                        'due_date_mode'  => $due_date_mode,
	                        'due_date_value' => $due_date_value,
	                        'is_pix'         => $is_pix,
	                        'nonce'          => wp_create_nonce('paghiper-admin-ajax-nonce'),
                            'gateway_id'     => $gateway_id,
	                    );
	
	                    wp_localize_script('wc-paghiper-admin', 'paghiper_settings', $settings_to_pass);
	                    
	                    wp_enqueue_style( 'wc-paghiper-admin' );
	                    wp_enqueue_script( 'wc-paghiper-admin' );

	                    // Enqueue scripts and styles for jQuery UI Dialog
	                    wp_enqueue_script('jquery-ui-dialog');
	                    wp_enqueue_style('wp-admin-dialog');
	                }
	
	            }
				
	            wp_localize_script('wc-paghiper-notices', 'notice_params', ['ajaxurl' => get_admin_url() . 'admin-ajax.php']);
				wp_enqueue_script( 'wc-paghiper-notices' );

	        }
	    }
	
		public function check_long_pix_expiration_notice() {
		    if(!(is_admin() && isset($_GET['page']) && $_GET['page'] === 'wc-settings' && isset($_GET['section']) && $_GET['section'] === 'paghiper_pix')) {
		        return;
		    }
	
		    if (get_transient('paghiper_long_expiration_notice_dismissed')) {
		        return;
		    }
	
		                $settings = get_option('woocommerce_paghiper_pix_settings');
		    		    $due_date_mode = isset($settings['due_date_mode']) ? $settings['due_date_mode'] : 'minutes';
		    		    $due_date_value = isset($settings['due_date_value']) ? intval($settings['due_date_value']) : 0;
		                $disable_email_gif = isset($settings['disable_email_gif']) ? $settings['disable_email_gif'] : 'no';

		    		    // 24h 00m 00s em minutos é ~1440. Checamos por valores maiores que isso.
		    		    if ($due_date_mode === 'minutes' && $due_date_value > 1440 && $disable_email_gif !== 'yes') {
					        include_once 'views/notices/html-notice-long-pix-expiration.php';
					    }
		}
	
		public function ajax_handle_long_expiration_notice() {
		    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'paghiper_long_expiration_notice')) {
		        wp_send_json_error('Invalid nonce');
		    }
	
		    $action = isset($_POST['user_action']) ? sanitize_text_field($_POST['user_action']) : '';
		    $settings = get_option('woocommerce_paghiper_pix_settings');
	
		    if ($action === 'disable_gif') {
		        $settings['disable_email_gif'] = 'yes';
		        update_option('woocommerce_paghiper_pix_settings', $settings);
		    } elseif ($action === 'change_to_days') {
		        $settings['due_date_mode'] = 'days';
		        $settings['due_date_value'] = 1; // Sugere 1 dia como padrão
		        update_option('woocommerce_paghiper_pix_settings', $settings);
		    }
	
		    set_transient('paghiper_long_expiration_notice_dismissed', true, YEAR_IN_SECONDS);
		    wp_send_json_success();
		}

		public function ajax_copy_credentials() {
			check_ajax_referer( 'paghiper-admin-ajax-nonce', 'nonce' );

			$to_gateway = isset( $_POST['to'] ) ? sanitize_text_field( $_POST['to'] ) : '';
			$from_gateway = ( $to_gateway === 'paghiper_pix' ) ? 'paghiper_billet' : 'paghiper_pix';

			$from_settings = get_option( 'woocommerce_' . $from_gateway . '_settings' );
			$to_settings   = get_option( 'woocommerce_' . $to_gateway . '_settings' );

			if(!is_array($from_settings)) {
				wp_send_json_error( array( 'message' => 'Configurações do gateway de origem inválidas.' ) );
			}

			if(!is_array($to_settings)) {
				$to_settings = [];
			}

			if ( ! empty( $from_settings['api_key'] ) && ! empty( $from_settings['token'] ) ) {
				$to_settings['api_key'] = $from_settings['api_key'];
				$to_settings['token']   = $from_settings['token'];

				update_option( 'woocommerce_' . $to_gateway . '_settings', $to_settings );

				wp_send_json_success();
			} else {
				wp_send_json_error( array( 'message' => 'O gateway de origem não possui credenciais para copiar.' ) );
			}
		}

		public function ajax_test_credentials() {
			check_ajax_referer( 'paghiper-admin-ajax-nonce', 'nonce' );

			$api_key = isset( $_POST['apiKey'] ) ? sanitize_text_field( $_POST['apiKey'] ) : '';
			$token   = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

			if ( empty( $api_key ) || empty( $token ) ) {
				wp_send_json_error( array( 'message' => 'API Key e Token são obrigatórios.' ) );
			}


			try {
				
				wc_paghiper_initialize_sdk();
				$PagHiperAPI = new PagHiper($api_key, $token);
				$response = $PagHiperAPI->transaction()->status('0000000000000000');

			} catch(Exception $e) {

				$errors = [];

				if (str_contains($e->getMessage(), 'token')) {
					$errors[] = 'Token';
				}
				if (str_contains($e->getMessage(), 'apiKey')) {
					$errors[] = 'API Key';
				}

				if (!empty($errors)) {

					// Log error for debugging
					wc_paghiper_add_log(
						$this->log,
						'Teste de credenciais falhou: ' . implode(' e ', $errors) . ' incorreto(s).',
						['api_key' => $api_key, 'token' => $token, $response ?? 'No response', $e->getMessage()],
						WC_Log_Levels::ERROR
					);


					$error_message = 'Credenciais inválidas: ' . implode(' e ', $errors) . ' incorreto(s).';
					wp_send_json_error( array( 'message' => $error_message ) );
				} else {
					wp_send_json_success( array( 'message' => 'Credenciais válidas!' ) );
				}
			}
			
			wp_send_json_success( array( 'message' => 'Credenciais válidas!' ) );

		}

		private function setup_timer_directory() {
			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}
		
			$upload_dir = wp_upload_dir();
			$timers_dir = $upload_dir['basedir'] . '/paghiper/gif-timers';
		
					if ( ! $wp_filesystem->exists( $timers_dir ) ) {
						if ( ! wp_mkdir_p( $timers_dir ) ) {
							return new WP_Error('dir_creation_failed', 'Não foi possível criar o diretório para os cronômetros.');
						}
					}		
			return $timers_dir;
		}

		public function ajax_download_timer_assets() {
			check_ajax_referer( 'paghiper-admin-ajax-nonce', 'nonce' );
		
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Permissão insuficiente.' ), 403 );
			}
		
			$bundle_number = isset( $_POST['bundle'] ) ? intval( $_POST['bundle'] ) : 0;
		
			if ( $bundle_number < 1 || $bundle_number > 24 ) {
				wp_send_json_error( array( 'message' => 'Número do pacote inválido.' ), 400 );
			}
		
			$timers_dir = $this->setup_timer_directory();
			if ( is_wp_error( $timers_dir ) ) {
				wp_send_json_error( array( 'message' => $timers_dir->get_error_message() ) );
			}
		
			// Os pacotes são de 1 a 24, mas os diretórios serão de 0 a 23
			$destination_dir = $timers_dir . '/' . ( $bundle_number - 1 );

					// Garante que o diretório de destino específico do pacote exista
					global $wp_filesystem;
					if ( ! $wp_filesystem->exists( $destination_dir ) ) {
						if ( ! wp_mkdir_p( $destination_dir ) ) {
							wp_send_json_error( array( 'message' => 'Falha ao criar o sub-diretório para o pacote ' . $bundle_number . '. Verifique as permissões de escrita.' ) );
						}
					}		
			// URL do pacote
			$package_url = 'https://paghiper.henriquecruz.com.br/chrono-gif-pack/' . $bundle_number . '.zip';
		
			// Inclui os arquivos necessários para download e extração
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		
			// Baixa o arquivo para um local temporário
			$temp_file = download_url( $package_url );

			wc_paghiper_add_log( $this->log, sprintf('Tentativa de download do pacote %d. URL: %s', $bundle_number, $package_url), ['resultado' => $temp_file] );
		
			if ( is_wp_error( $temp_file ) ) {
				wc_paghiper_add_log( $this->log, 'Falha detectada no download (is_wp_error is true). Retornando erro JSON.', ['erro' => $temp_file->get_error_message()] );
				wp_send_json_error( array( 'message' => 'Falha no download do pacote ' . $bundle_number . ': ' . $temp_file->get_error_message() ) );
			}
		
			// Extrai o ZIP
			$unzip_result = unzip_file( $temp_file, $destination_dir );
			unlink( $temp_file ); // Deleta o arquivo temporário

			wc_paghiper_add_log( $this->log, sprintf('Tentativa de extrair o pacote %d.', $bundle_number), ['resultado' => $unzip_result] );
		
			if ( is_wp_error( $unzip_result ) ) {
				wc_paghiper_add_log( $this->log, 'Falha detectada na extração (is_wp_error is true). Retornando erro JSON.', ['erro' => $unzip_result->get_error_message()] );
				wp_send_json_error( array( 'message' => 'Falha ao extrair o pacote ' . $bundle_number . ': ' . $unzip_result->get_error_message() ) );
			}
		
			wp_send_json_success( array( 'message' => 'Pacote ' . $bundle_number . ' instalado com sucesso.' ) );
		}

		public function ajax_get_timer_asset_status() {
			check_ajax_referer( 'paghiper-admin-ajax-nonce', 'nonce' );
		
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => 'Permissão insuficiente.' ), 403 );
			}
		
			$bundles_needed = isset( $_POST['bundles_needed'] ) ? absint( $_POST['bundles_needed'] ) : 0;
			if ( $bundles_needed < 1 || $bundles_needed > 24 ) {
				wp_send_json_error( array( 'message' => 'Número de pacotes necessários inválido.' ), 400 );
			}
		
			$timers_dir = $this->setup_timer_directory();
			if ( is_wp_error( $timers_dir ) ) {
				wp_send_json_error( array( 'message' => $timers_dir->get_error_message() ) );
			}
		
			$missing_bundles = array();
			for ( $i = 1; $i <= $bundles_needed; $i++ ) {
				$bundle_dir = $timers_dir . '/' . ( $i - 1 );
				if ( ! is_dir( $bundle_dir ) ) {
					$missing_bundles[] = $i;
				}
			}
		
			if ( ! empty( $missing_bundles ) ) {
				$size_map_mb = [
					1 => 335, 2 => 395, 3 => 395, 4 => 395, 5 => 395, 6 => 395, 7 => 395, 8 => 395, 9 => 395, 10 => 395, 11 => 395,
					12 => 390, 13 => 390, 14 => 390, 15 => 390, 16 => 390, 17 => 390, 18 => 390, 19 => 390, 20 => 390,
					21 => 395, 22 => 390, 23 => 390, 24 => 390
				];

				$required_space = 0;
				foreach($missing_bundles as $bundle_num) {
					if(isset($size_map_mb[$bundle_num])) {
						$required_space += $size_map_mb[$bundle_num] * 1024 * 1024;
					}
				}

				$free_space = @disk_free_space( $timers_dir );
		
				if ( $free_space === false || $free_space < $required_space ) {
					$required_space_gb = round($required_space / (1024 * 1024 * 1024), 2);
					$free_space_gb = round($free_space / (1024 * 1024 * 1024), 2);
					wp_send_json_error( array( 'message' => sprintf('Espaço em disco insuficiente. É necessário ~%s GB, mas apenas %s GB estão disponíveis.', $required_space_gb, $free_space_gb) ) );
				}
			}
		
					wp_send_json_success( array( 'missing_bundles' => $missing_bundles ) );
				}
			
				public function ajax_install_beta_version() {
					check_ajax_referer( 'paghiper-admin-ajax-nonce', 'nonce' );
			
					if ( ! current_user_can( 'update_plugins' ) ) {
						wp_send_json_error( array( 'message' => 'Permissão insuficiente para atualizar plugins.' ), 403 );
					}
			
					$version_to_install = isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '';
					if ( empty( $version_to_install ) ) {
						wp_send_json_error( array( 'message' => 'Nenhuma versão especificada para instalação.' ), 400 );
					}
			
					// Re-fetch release data from cache to find the download URL
					$releases = get_transient( 'paghiper_github_releases' );
					if ( false === $releases || ! is_array( $releases ) ) {
						// If cache is expired, we can't proceed. User should refresh.
						wp_send_json_error( array( 'message' => 'O cache de versões do GitHub expirou. Por favor, recarregue a página e tente novamente.' ) );
					}
			
					$download_url = '';
					foreach ( $releases as $release ) {
						if ( ! empty( $release['tag_name'] ) && ltrim( $release['tag_name'], 'v' ) === $version_to_install ) {
							$download_url = $release['zipball_url'];
							break;
						}
					}
			
					if ( empty( $download_url ) ) {
						wp_send_json_error( array( 'message' => 'Não foi possível encontrar a URL de download para a versão ' . esc_html( $version_to_install ) . '.' ) );
					}
			
					include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
					
					// Use a custom skin to capture output
					require_once WC_Paghiper::get_plugin_path() . 'includes/class-wc-paghiper-upgrader-skin.php';
			
					$skin     = new WC_PagHiper_Upgrader_Skin();
					$upgrader = new Plugin_Upgrader( $skin );
					
					// The 'install' method handles upgrades if the plugin is already installed.
					$result = $upgrader->install( $download_url );
			
					if ( is_wp_error( $result ) ) {
						wp_send_json_error( array( 'message' => $result->get_error_message() ) );
					}
			
					if ( $result === null ) {
						// This can happen if the upgrader doesn't think an update is needed
						// or if there's an issue with the zip file not containing the plugin folder correctly.
						wp_send_json_error( array( 'message' => 'A atualização falhou. O arquivo do plugin pode estar mal formatado ou a versão já está instalada.' ) );
					}
			
							// Reactivate the plugin
							$plugin_slug = plugin_basename( WC_PAGHIPER_PLUGIN_FILE );					$activation_result = activate_plugin( $plugin_slug );
			
					if ( is_wp_error( $activation_result ) ) {
						wp_send_json_error( array( 'message' => 'Falha ao reativar o plugin após a atualização: ' . $activation_result->get_error_message() ) );
					}
			
					wp_send_json_success( array( 'message' => 'Plugin atualizado com sucesso para a versão ' . esc_html( $version_to_install ) . '. A página será recarregada.' ) );
				}}

new WC_Paghiper_Admin();