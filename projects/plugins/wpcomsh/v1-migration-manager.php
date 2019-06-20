<?php

/**
 * V1 Migration Manager.
 * A few helper functions to change the behavior of the site while we migrate to Atomic V2
 *
 */

class V1_Migration_Manager {
	private static $instance = null;

	public $options;

	public $at_options;

	public $migration_activated;

	public function __construct( $at_options ) {
		$this->at_options = $at_options;
		$this->options = wp_parse_args(
			$at_options['v1_migration_options'] ?? [],
			[
				'migration_active'        => false,
				'maintenance_mode'        => false,
				'media_backfill_active'   => true,
				'show_maintenance_notice' => false,
				'migration_window_start'  => date('Y-m-d H:i:s'),
				'migration_window_end'    => date('Y-m-d H:i:s'),
				'show_migration_window'   => false,
				'migration_time_est'      => 'only a few moments',
			]
		);

		$this->migration_activated = get_option( 'wpcom_atomic_migration_lock_timeout', 0 );
		if ( ! $this->migration_activated ) {
			$this->migration_activated = $this->options['migration_active'];
		}

		if ( defined( 'IS_PRESSABLE' ) && IS_PRESSABLE ) {
			add_filter( 'query',         [ $this, 'enable_lock_while_running_migration' ] );
			add_action( 'get_header',    [ $this, 'migration_maintenance_mode' ] );
			add_action( 'wp_footer',     [ $this, 'show_adminbar_notice' ] );
			add_action( 'admin_notices', [ $this, 'display_migration_notice' ] );
			add_action( 'admin_notices', [ $this, 'display_upcoming_migration_notice' ] );
			$this->handle_migration_lock_request();
		}

		$this->handle_checksum_request();
	}

	/**
	 * Helper to test if the migration is active.
	 *
	 * @returns bool
	 */
	public function is_migration_active() {
		if ( ! empty( $_SERVER['HTTP_X_MIGRATION_VALIDATION'] ) ) {
			return false;
		}

		if ( $this->migration_activated && time() < intval( $this->migration_activated ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Alias for is_migration_active
	 */
	public function migration_lock_is_active() {
		return $this->is_migration_active();
	}

	/**
	 * Helper to check if we use the maintenance mode.
	 *
	 * @returns true
	 */
	public function use_migration_maintenance_mode() {
		return $this->options['maintenance_mode'];
	}

	/**
	* Disable _most_ writes to the database.
	* It only allows the writes to wp_options when trying to update at_options => v1_migration_options.
	* Allowing this one "write" let's use toggle the read lock.
	*
	* @returns bool
	*/
	public function is_query_ok_while_migrating( $query ) {
		$q = ltrim($query, "\r\n\t (");

		// check for the migration lock header. If set let the query continue
		if ( ! empty( $_SERVER[ 'HTTP_X_WPCOMSH_MIGRATION_LOCK' ] ) &&
			'wsNDGmsDpw8gFXEsZNRVkrArCnqbyUgZFVwuGbRMFGsBJoYmEhsA78ncobiqb9cU' === $_SERVER[ 'HTTP_X_WPCOMSH_MIGRATION_LOCK' ]
		) {
			return true;
		}

		// Allow writes to wp_options when updating at_options.
		if ( 1 === preg_match( '/at_options/', $q ) ) {
			return true;
		}

		// Quick and dirty: only SELECT statements are considered read-only.
		// Stolen directly from hyperdb
		return 1 === preg_match('/^(?:SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\s/i', $q );

	}
	/**
	 * Hook into the query filter and return a no-op query if
	 * the migration is active and the query is a blocked while migrating
	 *
	 * @returns string
	 */
	public function enable_lock_while_running_migration( $query ) {
		if ( ! $this->is_migration_active() || $this->is_query_ok_while_migrating( $query ) ) {
			return $query;
		}

		// Else do nothing.
		return "DO 0;";
	}

	/**
	 * Adds a basic migration maintenance mode page.
	 */
	public function migration_maintenance_mode() {
		$show_maintenance_page =
			$this->is_migration_active() &&
			$this->use_migration_maintenance_mode() &&
			! current_user_can( 'administrator' );

		if ( $show_maintenance_page ) {
			$msg = '<h1 style="color:red">Website Under Maintenance</h1><p>We are currently performing maintenance. We will be back online shortly!</p>';
			wp_die($msg,'',200);
		}
	}


	/**
	 * Adds a banner below the adminbar to show notify Admins that the site is in maintenance mode.
	 */
	public function show_adminbar_notice() {
		$show_maintenance_banner =
			$this->is_migration_active() &&
			$this->use_migration_maintenance_mode() &&
			current_user_can( 'administrator' );

		if ( $show_maintenance_banner ) {
			$styles = "width: 100%;background-color: #cc2d2d;position: fixed;top: 0;text-align: center;color: #270808; z-index:99999";
			echo "<div style='$styles' id='v1_migration_adminbar_notice'><h1>Maintenance Mode</h1><p>You are seeing this page as an Adminstrator. Non Adminstrators will be shown a maintenance notice while we perform server updates on your site.</p></div>";
		}
	}

	/**
	 * Adds a non-dismissible admin notice that the site is being migrated.
	 *
	 * @returns string
	 */
	public function display_migration_notice() {
		if ( ! $this->is_migration_active() ) {
			return '';
		}

		?>
		<div class="notice notice-warning">
			<h1>Website Under Maintenance</h1>
			<p>We are currently performing maintenance. We will be back online shortly!<p>
		</div>
		<?php
	}

	/**
	 * Display an upcoming maintenance notice with info about when the site will be updated.
	 * Uses v1_migration_options to display various migration schedule times
	 *
	 * @returns string
	 */
	public function display_upcoming_migration_notice() {
		if ( $this->is_migration_active() ) {
			return;
		}

		$v1_migration_options = $this->options;

		if ( ! $v1_migration_options['show_maintenance_notice'] ) {
			return;
		}

		$migration_window_start = $v1_migration_options['migration_window_start'];
		$migration_window_end   = $v1_migration_options['migration_window_end'] ?? $migration_window_start;
		$show_migration_window  = $v1_migration_options['show_migration_window'] ?? false;
		$migration_time_est     = $v1_migration_options['migration_time_est'] ?? 'a few moments';

		// set up timestamps if being used.
		$migration_day      = get_date_from_gmt(  $migration_window_start, get_option( 'date_format' ) );
		$time_format        = get_option( 'time_format' );
		$migration_start_at = get_date_from_gmt(  $migration_window_start, $time_format );
		$migration_end_by   = get_date_from_gmt(  $migration_window_end, $time_format );

		?>
		<div class="notice notice-warning">
			<h2>Scheduled Maintenance</h2>
			<p>
				We will be performing scheduled maintenance on <strong><?php echo $migration_day ?></strong> sometime between 9pm and 5am in the
<a href="https://en.support.wordpress.com/settings/time-settings/#change-timezone" >timezone</a> of your site.<br />
				<?php if ( $show_migration_window ): ?>
				The update will begin
				<?php if ( $migration_window_start >= $migration_window_end ) :?>
				sometime after <?php echo $migration_start_at;  ?>
				<?php else: ?>
				sometime between <?php echo $migration_start_at; ?> and <?php echo $migration_end_by; ?>
				<?php endif; ?>
				<?php endif; ?>
			</p>
			<p>
				During this time you will not be able to make any changes to your site.
				<?php if ( $this->use_migration_maintenance_mode() ): ?>
				Any non-logged in Adminstrators will see a maintenance notice when visiting your site.
				<?php endif; ?>
				The process should take <?php echo $migration_time_est; ?>.
			</p>
		</div>
	<?php
	}

	/**
	 * Returns a checksum of posts data on /wp-json/wp/v2/posts-checksum requests
	 */
	public function handle_checksum_request() {
		global $wpdb;
		if ( !empty( $_SERVER[ 'HTTP_X_WPCOMSH_MIGRATION_CHECKSUM' ] ) ) {
			switch( $_SERVER[ 'REQUEST_URI' ] ) {
				case '/wp-json/wp/v2/posts-checksum':
					nocache_headers();
					header( 'Content-Type: text/plain' );
					echo sha1( implode( ',', $wpdb->get_col(
							"SELECT MD5( CONCAT_WS( ',', `ID`, `post_title`, `guid`, `post_content` ) ) as `checksum` FROM $wpdb->posts ORDER BY `ID` DESC"
					) ) );
					die();
			}
		}
	}

	/**
	 * Toggles migration lock
	 */
	public function handle_migration_lock_request() {
		$route = '/wp-json/wp/v2/av1-migration-lock';
    $uri = $_SERVER[ 'REQUEST_URI' ];
    if ( substr( $uri, 0, strlen( $route ) ) === $route ) {
      $action = '';
      if ( isset( $_GET['action'] ) ) {
        $action = $_GET['action'];
			}

			$has_valid_lock_header = isset( $_SERVER[ 'HTTP_X_WPCOMSH_MIGRATION_LOCK' ] ) &&
				'wsNDGmsDpw8gFXEsZNRVkrArCnqbyUgZFVwuGbRMFGsBJoYmEhsA78ncobiqb9cU' === $_SERVER[ 'HTTP_X_WPCOMSH_MIGRATION_LOCK' ];

			if ( ! $has_valid_lock_header ) {
				// Pretend the route doesn't exist.
				echo json_encode ( [
					"code" => "rest_no_route",
					"data" => [
						"status" => 404,
					],
					"message" =>  "No route was found matching the URL and request method"
				] );
				die();
			}

			switch ($action) {

				case 'get':
					echo $this->build_migration_lock_response();
					break;

				case 'enable':
					$timeout = strtotime( '+2 hours' );
					if ( isset( $_GET['timeout'] ) ) {
						$timeout = $_GET['timeout'];
					}

					$lock_option_updated = update_option( 'wpcom_atomic_migration_lock_timeout', $timeout );
					echo $this->build_migration_lock_response( [ "enable_result" => $lock_option_updated ] );
					break;

				case 'disable';
					$lock_options_deleted = delete_option( 'wpcom_atomic_migration_lock_timeout' );
					echo $this->build_migration_lock_response( [ "disable_result" => $lock_option_deleted ] );
					break;
			}
			die();
		}
	}

	private function build_migration_lock_response( $action_detail = [] ) {
		$this->migration_activated = get_option( 'wpcom_atomic_migration_lock_timeout', 0 );
		$lock_active = $this->migration_lock_is_active();

		$response_data = wp_parse_args( $action_detail, [
			"lock_active"  => $lock_active,
			"lock_timeout" => $this->migration_activated,
		] );

		return json_encode( $response_data );
	}

	/**
	 * Factory method to initiate the migration manager.
	 *
	 * @return bool|V1_Migration_Manager  returns false if the manager cannot be initialized.
	 */
	static function init() {
		if ( ! function_exists( 'wpcomsh_get_at_options' ) ) {
			return;
		}
		$at_options = wpcomsh_get_at_options();
		if ( ! self::$instance ) {
			self::$instance = new V1_Migration_Manager( $at_options );
		}
		return self::$instance;
	}
}

V1_Migration_Manager::init();
