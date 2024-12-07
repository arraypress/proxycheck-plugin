<?php
/**
 * Plugin Name:         ArrayPress - ProxyCheck Tester
 * Plugin URI:          https://github.com/arraypress/proxycheck-plugin
 * Description:         A plugin to test and demonstrate the ProxyCheck.io API integration.
 * Author:              ArrayPress
 * Author URI:          https://arraypress.com
 * License:             GNU General Public License v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         arraypress-proxycheck
 * Domain Path:         /languages/
 * Requires PHP:        7.4
 * Requires at least:   6.7.1
 * Version:             1.0.0
 */

namespace ArrayPress\ProxyCheck;

defined( 'ABSPATH' ) || exit;

/**
 * Include required files and initialize the Plugin class if available.
 */
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Plugin class to handle all the functionality
 */
class Plugin {

	/**
	 * Instance of ProxyCheck Client
	 *
	 * @var Client|null
	 */
	private ?Client $client = null;

	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		// Initialize client if key is set
		$key = get_option( 'proxycheck_api_key' );
		if ( $key ) {
			$this->client = new Client( $key );
		}

		// Hook into WordPress
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
	}

	/**
	 * Enqueue admin styles
	 */
	public function enqueue_admin_styles( $hook ) {
		if ( 'tools_page_proxycheck-tester' !== $hook ) {
			return;
		}

		// Add inline styles
		wp_add_inline_style( 'wp-admin', '
            .proxycheck-results {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin: 20px 0;
            }
            .proxycheck-results table {
                border-spacing: 0;
                width: 100%;
                clear: both;
            }
            .proxycheck-results th {
                font-weight: 600;
                padding: 8px 10px;
                text-align: left;
                width: 200px;
                background: #f8f9fa;
            }
            .proxycheck-results td {
                padding: 8px 10px;
                vertical-align: top;
            }
            .proxycheck-results tr:not(:last-child) {
                border-bottom: 1px solid #f0f0f1;
            }
            .proxycheck-status {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-weight: 500;
            }
            .proxycheck-status-safe {
                background: #d1e7dd;
                color: #0a3622;
            }
            .proxycheck-status-warning {
                background: #fff3cd;
                color: #664d03;
            }
            .proxycheck-status-danger {
                background: #f8d7da;
                color: #842029;
            }
            .proxycheck-coordinates {
                color: #0073aa;
                text-decoration: none;
            }
            .proxycheck-coordinates:hover {
                color: #0096dd;
            }
            .proxycheck-section {
                margin: 20px 0;
                padding: 15px;
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .proxycheck-section h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
        ' );
	}

	/**
	 * Add admin menu pages
	 */
	public function add_admin_menu() {
		add_management_page(
			'ProxyCheck Tester',
			'ProxyCheck Tester',
			'manage_options',
			'proxycheck-tester',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'proxycheck_settings', 'proxycheck_api_key' );

		add_settings_section(
			'proxycheck_settings_section',
			'API Settings',
			null,
			'proxycheck-tester'
		);

		add_settings_field(
			'proxycheck_api_key',
			'ProxyCheck API Key',
			[ $this, 'render_key_field' ],
			'proxycheck-tester',
			'proxycheck_settings_section'
		);
	}

	/**
	 * Render API key field
	 */
	public function render_key_field() {
		$key = get_option( 'proxycheck_api_key' );
		echo '<input type="text" name="proxycheck_api_key" value="' . esc_attr( $key ) . '" class="regular-text">';
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		// Get test parameters
		$test_type   = isset( $_POST['test_type'] ) ? sanitize_text_field( $_POST['test_type'] ) : 'single_ip';
		$test_ip     = isset( $_POST['test_ip'] ) ? sanitize_text_field( $_POST['test_ip'] ) : '';
		$test_email  = isset( $_POST['test_email'] ) ? sanitize_text_field( $_POST['test_email'] ) : '';
		$batch_ips   = isset( $_POST['batch_ips'] ) ? sanitize_textarea_field( $_POST['batch_ips'] ) : '';
		$query_flags = isset( $_POST['query_flags'] ) ? array_map( 'sanitize_text_field', (array) $_POST['query_flags'] ) : [];

		$results = null;

		// Process form submission
		if ( $this->client && isset( $_POST['submit'] ) ) {
			$options = $this->build_query_options(
				$query_flags,
				isset( $_POST['test_tag'] ) ? sanitize_text_field( $_POST['test_tag'] ) : null
			);

			switch ( $test_type ) {
				case 'single_ip':
					if ( $test_ip ) {
						$results = $this->client->check_ip( $test_ip, $options );
					}
					break;

				case 'batch_ip':
					if ( $batch_ips ) {
						$ips     = array_map( 'trim', explode( "\n", $batch_ips ) );
						$results = $this->client->check_ips( $ips, $options );
					}
					break;

				case 'email':
					if ( $test_email ) {
						$results = $this->client->check_email( $test_email );
					}
					break;
			}
		}

		// Start rendering the page
		?>
        <div class="wrap">
            <h1>ProxyCheck Tester</h1>

            <!-- Settings Form -->
			<?php $this->render_settings_form(); ?>

            <hr>

            <!-- Test Interface -->
			<?php $this->render_test_interface( $test_type, $test_ip, $test_email, $batch_ips, $query_flags ); ?>

            <!-- Results Section -->
			<?php $this->render_results( $results, $test_type ); ?>
        </div>

		<?php $this->render_js(); ?>
		<?php
	}

	/**
	 * Build query options from flags
	 */
	private function build_query_options( array $flags, ?string $tag = null ): array {
		$options       = [];
		$flag_mappings = [
			'vpn'  => 1,
			'asn'  => 1,
			'node' => 1,
			'time' => 1,
			'risk' => 1,
			'port' => 1,
			'seen' => 1
		];

		foreach ( $flag_mappings as $flag => $value ) {
			if ( in_array( $flag, $flags ) ) {
				$options[ $flag ] = $value;
			}
		}

		// Special case for risk=2
		if ( in_array( 'risk_history', $flags ) ) {
			$options['risk'] = 2;
		}

		// Add tag if provided
		if ( ! empty( $tag ) ) {
			$options['tag'] = $tag;
		}

		return $options;
	}

	/**
	 * Render the settings form
	 */
	private function render_settings_form() {
		?>
        <form method="post" action="options.php">
			<?php
			settings_fields( 'proxycheck_settings' );
			do_settings_sections( 'proxycheck-tester' );
			submit_button( 'Save API Key' );
			?>
        </form>
		<?php
	}

	/**
	 * Render the test interface
	 */
	private function render_test_interface( $test_type, $test_ip, $test_email, $batch_ips, $query_flags ) {
		?>
        <h2>Test Options</h2>
        <form method="post">
            <table class="form-table">
                <!-- Test Type Selection -->
                <tr>
                    <th scope="row">Test Type</th>
                    <td>
                        <label>
                            <input type="radio" name="test_type" value="single_ip"
								<?php checked( $test_type, 'single_ip' ); ?>>
                            Single IP Check
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="test_type" value="batch_ip"
								<?php checked( $test_type, 'batch_ip' ); ?>>
                            Batch IP Check
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="test_type" value="email"
								<?php checked( $test_type, 'email' ); ?>>
                            Email Check
                        </label>
                    </td>
                </tr>

                <!-- Single IP Fields -->
                <tr class="single-ip-fields" style="<?php echo $test_type !== 'single_ip' ? 'display:none;' : ''; ?>">
                    <th scope="row"><label for="test_ip">IP Address</label></th>
                    <td>
                        <input type="text" name="test_ip" id="test_ip"
                               value="<?php echo esc_attr( $test_ip ?: $this->get_current_ip() ); ?>"
                               class="regular-text">
                        <p class="description">Enter a single IP address (defaults to your current IP)</p>
                    </td>
                </tr>

                <!-- Batch Processing Fields -->
                <tr class="batch-ip-fields" style="<?php echo $test_type !== 'batch_ip' ? 'display:none;' : ''; ?>">
                    <th scope="row"><label for="batch_ips">IP Addresses</label></th>
                    <td>
                        <textarea name="batch_ips" id="batch_ips" rows="5"
                                  class="large-text code"><?php echo esc_textarea( $batch_ips ); ?></textarea>
                        <p class="description">Enter multiple IP addresses, one per line (max 1000)</p>
                    </td>
                </tr>

                <!-- Email Check Fields -->
                <tr class="email-fields" style="<?php echo $test_type !== 'email' ? 'display:none;' : ''; ?>">
                    <th scope="row"><label for="test_email">Email Address</label></th>
                    <td>
                        <input type="email" name="test_email" id="test_email"
                               value="<?php echo esc_attr( $test_email ); ?>"
                               class="regular-text">
                        <p class="description">Enter an email address to check if it's disposable</p>
                    </td>
                </tr>

                <!-- Query Flags -->
                <tr class="ip-check-fields" style="<?php echo $test_type === 'email' ? 'display:none;' : ''; ?>">
                    <th scope="row">Query Flags</th>
                    <td>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="vpn" <?php checked( in_array( 'vpn', $query_flags ) ); ?>> VPN
                            Detection</label><br>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="asn" <?php checked( in_array( 'asn', $query_flags ) ); ?>> ASN Data</label><br>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="risk" <?php checked( in_array( 'risk', $query_flags ) ); ?>> Risk
                            Score</label><br>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="risk_history" <?php checked( in_array( 'risk_history', $query_flags ) ); ?>>
                            Risk
                            History</label><br>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="port" <?php checked( in_array( 'port', $query_flags ) ); ?>> Port Detection</label><br>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="seen" <?php checked( in_array( 'seen', $query_flags ) ); ?>> Last
                            Seen</label><br>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="node" <?php checked( in_array( 'node', $query_flags ) ); ?>> Node
                            Info</label><br>
                        <label><input type="checkbox" name="query_flags[]"
                                      value="time" <?php checked( in_array( 'time', $query_flags ) ); ?>> Query
                            Time</label>
                    </td>
                </tr>

                <!-- Tag Field (for IP checks) -->
                <tr class="ip-check-fields" style="<?php echo $test_type === 'email' ? 'display:none;' : ''; ?>">
                    <th scope="row"><label for="test_tag">Request Tag</label></th>
                    <td>
                        <input type="text" name="test_tag" id="test_tag"
                               value="<?php echo esc_attr( $_POST['test_tag'] ?? '' ); ?>"
                               class="regular-text">
                        <p class="description">Optional tag to identify this request in your ProxyCheck.io dashboard</p>
                    </td>
                </tr>
            </table>

			<?php submit_button( 'Run Test', 'primary', 'submit', false ); ?>
        </form>
		<?php
	}

	/**
	 * Render the results section
	 */
	private function render_results( $results, $test_type ) {
		if ( ! $results ) {
			return;
		}

		?>
        <h2>Results</h2>
		<?php

		if ( is_wp_error( $results ) ) {
			?>
            <div class="notice notice-error">
                <p><?php echo esc_html( $results->get_error_message() ); ?></p>
            </div>
			<?php
			return;
		}

		if ( $test_type === 'email' ) {
			$this->render_email_result( $results );
		} elseif ( $test_type === 'single_ip' ) {
			$this->render_ip_result( $results );
		} else {
			foreach ( $results as $ip => $info ) {
				?>
                <h3>Results for IP: <?php echo esc_html( $ip ); ?></h3>
				<?php
				$this->render_ip_result( $info );
			}
		}

		// Debug information
		if ( ! is_string( $results ) ) {
			$this->render_debug_info( $results );
		}
	}

	/**
	 * Render a single IP result
	 */
	/**
	 * Render a single IP result
	 */
	/**
	 * Render a single IP result
	 */
	private function render_ip_result( Response $result ) {
		?>
        <div class="proxycheck-results">
            <table class="widefat">
                <tbody>
                <!-- Query Information -->
                <tr>
                    <th scope="row"><?php _e( 'Query Information', 'arraypress-proxycheck' ); ?></th>
                    <td>
                        <strong><?php _e( 'Node:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $result->get_node() ); ?>
                        <br>
                        <strong><?php _e( 'Query Time:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $result->get_query_time() ); ?>
                        s
                    </td>
                </tr>

                <!-- Basic IP Information -->
                <tr>
                    <th scope="row"><?php _e( 'IP Details', 'arraypress-proxycheck' ); ?></th>
                    <td>
                        <strong><?php _e( 'IP:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $result->get_ip() ); ?>
                        <br>
						<?php if ( $hostname = $result->get_hostname() ): ?>
                            <strong><?php _e( 'Hostname:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $hostname ); ?>
                            <br>
						<?php endif; ?>
						<?php if ( $range = $result->get_range() ): ?>
                            <strong><?php _e( 'IP Range:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $range ); ?>
						<?php endif; ?>
                    </td>
                </tr>

                <!-- Proxy/VPN Status -->
                <tr>
                    <th scope="row"><?php _e( 'Proxy/VPN Status', 'arraypress-proxycheck' ); ?></th>
                    <td>
						<?php
						$status_class = $result->is_proxy() ? 'proxycheck-status-danger' : 'proxycheck-status-safe';
						?>
                        <span class="proxycheck-status <?php echo esc_attr( $status_class ); ?>">
                                <?php echo $result->is_proxy() ? esc_html__( 'Proxy/VPN Detected', 'arraypress-proxycheck' ) : esc_html__( 'No Proxy Detected', 'arraypress-proxycheck' ); ?>
                            </span>
						<?php if ( $type = $result->get_type() ): ?>
                            <br>
                            <strong><?php _e( 'Type:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $type ); ?>
						<?php endif; ?>

						<?php
						// Get operator details from raw data
						$raw_data = $result->get_all();
						$ip       = $result->get_ip();
						if ( isset( $raw_data[ $ip ]['operator'] ) ) {
							$operator = $raw_data[ $ip ]['operator'];
							?>
                            <div class="operator-details" style="margin-top: 10px;">
                                <strong><?php _e( 'VPN Provider:', 'arraypress-proxycheck' ); ?></strong>
								<?php echo esc_html( $operator['name'] ); ?>
								<?php if ( isset( $operator['url'] ) ): ?>
                                    (<a href="<?php echo esc_url( $operator['url'] ); ?>" target="_blank"
                                        rel="noopener noreferrer">Website</a>)
								<?php endif; ?>
                                <br>

                                <strong><?php _e( 'Anonymity Level:', 'arraypress-proxycheck' ); ?></strong>
								<?php echo esc_html( ucfirst( $operator['anonymity'] ) ); ?><br>

                                <strong><?php _e( 'Popularity:', 'arraypress-proxycheck' ); ?></strong>
								<?php echo esc_html( ucfirst( $operator['popularity'] ) ); ?><br>

								<?php if ( ! empty( $operator['protocols'] ) ): ?>
                                    <strong><?php _e( 'Protocols:', 'arraypress-proxycheck' ); ?></strong>
									<?php echo esc_html( implode( ', ', $operator['protocols'] ) ); ?><br>
								<?php endif; ?>

								<?php if ( ! empty( $operator['policies'] ) ): ?>
                                    <strong><?php _e( 'Policies:', 'arraypress-proxycheck' ); ?></strong><br>
                                    <ul style="margin-top: 5px; margin-bottom: 0;">
										<?php foreach ( $operator['policies'] as $policy => $value ): ?>
                                            <li><?php echo esc_html( ucwords( str_replace( '_', ' ', $policy ) ) ); ?>:
                                                <span style="color: <?php echo $value === 'yes' ? '#00a32a' : '#d63638'; ?>">
                                                        <?php echo $value === 'yes' ? '✓' : '✗'; ?>
                                                    </span>
                                            </li>
										<?php endforeach; ?>
                                    </ul>
								<?php endif; ?>
                            </div>
						<?php } ?>

                        <!-- Risk Assessment -->
						<?php if ( $risk_score = $result->get_risk_score() ): ?>
                <tr>
                    <th scope="row"><?php _e( 'Risk Assessment', 'arraypress-proxycheck' ); ?></th>
                    <td>
						<?php
						$risk_class = 'proxycheck-status-safe';
						if ( $risk_score > 66 ) {
							$risk_class = 'proxycheck-status-danger';
						} elseif ( $risk_score > 33 ) {
							$risk_class = 'proxycheck-status-warning';
						}
						?>
                        <span class="proxycheck-status <?php echo esc_attr( $risk_class ); ?>">
                                <?php echo esc_html( sprintf( __( 'Risk Score: %d', 'arraypress-proxycheck' ), $risk_score ) ); ?>
                            </span>

						<?php if ( $attack_history = $result->get_attack_history() ): ?>
                            <h4><?php _e( 'Attack History:', 'arraypress-proxycheck' ); ?></h4>
                            <ul>
								<?php foreach ( $attack_history as $type => $count ): ?>
                                    <li><?php echo esc_html( ucwords( str_replace( '_', ' ', $type ) ) ); ?>
                                        : <?php echo esc_html( $count ); ?></li>
								<?php endforeach; ?>
                            </ul>
						<?php endif; ?>
                    </td>
                </tr>
				<?php endif; ?>

                <!-- Network Information -->
                <tr>
                    <th scope="row"><?php _e( 'Network Information', 'arraypress-proxycheck' ); ?></th>
                    <td>
						<?php if ( $operator = $result->get_operator() ): ?>
                            <strong><?php _e( 'Provider:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $operator['name'] ?? '' ); ?>
                            <br>
                            <strong><?php _e( 'ASN:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $operator['asn'] ?? '' ); ?>
                            <br>
						<?php endif; ?>
						<?php if ( $organisation = $result->get_organisation() ): ?>
                            <strong><?php _e( 'Organization:', 'arraypress-proxycheck' ); ?></strong> <?php echo esc_html( $organisation ); ?>
                            <br>
						<?php endif; ?>
						<?php if ( $devices = $result->get_devices() ): ?>
                            <strong><?php _e( 'Devices:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo sprintf( __( 'Address: %d, Subnet: %d', 'arraypress-proxycheck' ),
								$devices['address'],
								$devices['subnet']
							); ?>
						<?php endif; ?>
                    </td>
                </tr>

                <!-- Location Information -->
                <tr>
                    <th scope="row"><?php _e( 'Location', 'arraypress-proxycheck' ); ?></th>
                    <td>
						<?php if ( $continent = $result->get_continent() ): ?>
                            <strong><?php _e( 'Continent:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo esc_html( $continent['name'] ); ?>
                            (<?php echo esc_html( $continent['code'] ); ?>)<br>
						<?php endif; ?>

						<?php if ( $country = $result->get_country() ): ?>
                            <strong><?php _e( 'Country:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo esc_html( $country['name'] ); ?>
                            (<?php echo esc_html( $country['code'] ); ?>)
							<?php if ( $country['is_eu'] ): ?>
                                🇪🇺
							<?php endif; ?><br>
						<?php endif; ?>

						<?php if ( $region = $result->get_region() ): ?>
                            <strong><?php _e( 'Region:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo esc_html( $region['name'] ); ?>
                            (<?php echo esc_html( $region['code'] ); ?>)<br>
						<?php endif; ?>

						<?php if ( $result->get_city() ): ?>
                            <strong><?php _e( 'City:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo esc_html( $result->get_city() ); ?><br>
						<?php endif; ?>

						<?php if ( $result->get_postcode() ): ?>
                            <strong><?php _e( 'Postal Code:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo esc_html( $result->get_postcode() ); ?><br>
						<?php endif; ?>

						<?php if ( $coordinates = $result->get_coordinates() ): ?>
                            <strong><?php _e( 'Coordinates:', 'arraypress-proxycheck' ); ?></strong>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo esc_attr( $coordinates['latitude'] ); ?>,<?php echo esc_attr( $coordinates['longitude'] ); ?>"
                               target="_blank"
                               class="proxycheck-coordinates">
								<?php echo esc_html( $coordinates['latitude'] ); ?>
                                , <?php echo esc_html( $coordinates['longitude'] ); ?>
                            </a><br>
						<?php endif; ?>
                    </td>
                </tr>

                <!-- Time and Currency -->
                <tr>
                    <th scope="row"><?php _e( 'Regional Settings', 'arraypress-proxycheck' ); ?></th>
                    <td>
						<?php if ( $timezone = $result->get_timezone() ): ?>
                            <strong><?php _e( 'Timezone:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo esc_html( $timezone ); ?><br>
						<?php endif; ?>

						<?php if ( $currency = $result->get_currency() ): ?>
                            <strong><?php _e( 'Currency:', 'arraypress-proxycheck' ); ?></strong>
							<?php
							$currency_parts = [];
							if ( $currency['symbol'] ) {
								$currency_parts[] = $currency['symbol'];
							}
							if ( $currency['code'] ) {
								$currency_parts[] = $currency['code'];
							}
							if ( $currency['name'] ) {
								$currency_parts[] = "({$currency['name']})";
							}
							echo esc_html( implode( ' ', $currency_parts ) );
							?>
						<?php endif; ?>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
		<?php
	}

	/**
	 * Render an email check result
	 *
	 * @param DisposableEmailResponse $result The email check result
	 */
	private function render_email_result( DisposableEmailResponse $result ) {
		?>
        <div class="proxycheck-results">
            <table class="widefat">
                <tbody>
                <tr>
                    <th scope="row"><?php _e( 'Email Address', 'arraypress-proxycheck' ); ?></th>
                    <td><?php echo esc_html( $result->get_email() ); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Status', 'arraypress-proxycheck' ); ?></th>
                    <td>
						<?php
						if ( $result->is_disposable() ) {
							echo '<span class="proxycheck-status proxycheck-status-danger">';
							echo esc_html__( '✗ Disposable Email Address', 'arraypress-proxycheck' );
							echo '</span>';
						} else {
							echo '<span class="proxycheck-status proxycheck-status-safe">';
							echo esc_html__( '✓ Valid Email Address', 'arraypress-proxycheck' );
							echo '</span>';
						}
						?>
                    </td>
                </tr>
				<?php if ( $result->get_node() ): ?>
                    <tr>
                        <th scope="row"><?php _e( 'Query Information', 'arraypress-proxycheck' ); ?></th>
                        <td>
                            <strong><?php _e( 'Node:', 'arraypress-proxycheck' ); ?></strong>
							<?php echo esc_html( $result->get_node() ); ?>
							<?php if ( $result->get_query_time() ): ?>
                                <br>
                                <strong><?php _e( 'Query Time:', 'arraypress-proxycheck' ); ?></strong>
								<?php echo esc_html( $result->get_query_time() ); ?>s
							<?php endif; ?>
                        </td>
                    </tr>
				<?php endif; ?>
                </tbody>
            </table>
        </div>
		<?php
	}

	/**
	 * Render debug information
	 */
	private function render_debug_info( $results ) {
		?>
        <div class="debug-info" style="background: #f5f5f5; padding: 15px; margin-top: 20px;">
            <h3>Raw Response Data:</h3>
            <pre style="background: #fff; padding: 10px; overflow: auto;">
                <?php
                if ( is_array( $results ) ) {
	                foreach ( $results as $ip => $info ) {
		                echo esc_html( $ip ) . ":\n";
		                print_r( $info->get_all() );
		                echo "\n";
	                }
                } else {
	                print_r( $results->get_all() );
                }
                ?>
            </pre>

            <h3>API Response Status:</h3>
            <ul>
				<?php
				$result = is_array( $results ) ? current( $results ) : $results;
				?>
                <li>Status: <?php echo esc_html( $result->get_status() ?? 'ok' ); ?></li>
				<?php if ( $message = $result->get_message() ): ?>
                    <li>Message: <?php echo esc_html( $message ); ?></li>
				<?php endif; ?>
            </ul>
        </div>
		<?php
	}

	/**
	 * Render JavaScript for the page
	 */
	private function render_js() {
		?>
        <script>
            jQuery(document).ready(function ($) {
                $('input[name="test_type"]').change(function () {
                    var type = $(this).val();

                    // Hide all fields first
                    $('.single-ip-fields, .batch-ip-fields, .email-fields, .ip-check-fields').hide();

                    // Show relevant fields based on selection
                    switch (type) {
                        case 'single_ip':
                            $('.single-ip-fields, .ip-check-fields').show();
                            break;
                        case 'batch_ip':
                            $('.batch-ip-fields, .ip-check-fields').show();
                            break;
                        case 'email':
                            $('.email-fields').show();
                            break;
                    }
                });

                // Initialize view based on current selection
                $('input[name="test_type"]:checked').change();
            });
        </script>
		<?php
	}

	/**
	 * Get the current user's IP address
	 *
	 * @return string
	 */
	private function get_current_ip(): string {
		return ( $_SERVER["HTTP_CF_CONNECTING_IP"] ?? $_SERVER["REMOTE_ADDR"] );
	}

}

new Plugin();