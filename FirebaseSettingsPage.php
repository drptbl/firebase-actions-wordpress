<?php
namespace Firebase;

class FirebaseSettingsPage {
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page(
			'Firebase Actions Admin',
			'Firebase Actions',
			'manage_options',
			'my-setting-admin',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		// Set class property
		$this->options = get_option( 'fa_options' );
		?>
        <div class="wrap">
            <h1>Firebase Actions</h1>
            <form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields( 'my_option_group' );
				do_settings_sections( 'my-setting-admin' );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			'my_option_group', // Option group
			'fa_options', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'setting_section_id', // ID
			'Firebase Actions Settings', // Title
			array( $this, 'print_section_info' ), // Callback
			'my-setting-admin' // Page
		);

		add_settings_field(
			'firebase_credentials',
			'Credentials Path (json)',
			array( $this, 'firebase_credentials_callback' ),
			'my-setting-admin',
			'setting_section_id'
		);

		add_settings_field(
			'shared_secret', // ID
			'Secret', // Title
			array( $this, 'shared_secret_callback' ), // Callback
			'my-setting-admin', // Page
			'setting_section_id' // Section
		);

	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 */
	public function sanitize( $input ) {
		$new_input = array();
		if ( isset( $input['shared_secret'] ) ) {
			$new_input['shared_secret'] = sanitize_text_field( $input['shared_secret'] );
		}

		if ( isset( $input['firebase_credentials'] ) ) {
			$new_input['firebase_credentials'] = sanitize_text_field( $input['firebase_credentials'] );
		}

		if ( isset( $new_input['firebase_credentials'] ) ) {
			if ( ! file_exists( $new_input['firebase_credentials'] ) ) {
				add_settings_error( 'firebase_credentials', 'firebase_credentials',
					"The file " . $new_input['firebase_credentials'] . " does not exist." );
			}
		}

		if ( isset( $new_input['shared_secret'] ) ) {
			if ( strlen( $new_input['shared_secret'] ) < 10 ) {
				add_settings_error( 'shared_secret', 'shared_secret', "Please enter a share secret of at least 10 characters" );
			}
		}


		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		print 'Configure your settings below:'
		      . '<br/><ul>
            <li><strong>Credentials path:</strong> The path to a json credential file, saved on your server. This is'
		      . ' a Firebase service account. You can create this file in the '
		      . '<a href="https://console.firebase.google.com/project/_/settings/serviceaccounts/adminsdk">Firebase console</a>.<br/>'
		      . '<i>You should upload this file to a location on your server that is outside of your www-root</i>.</li>'
		      . '<li><strong>Secret:</strong> This secret serves as a password you should enter in the app to authenticate</li>'
		      . '</ul><br/>';
		$options = get_option( 'fa_options' );

		if ( ! $options ) {
			print '<b>Warning:</b> No credentials set. Before using this plugin you need to create a service account.';

			return;
		}

		$credentials_path = $options['firebase_credentials'];
		$shared_secret    = $options['shared_secret'];


		if ( $credentials_path === false ) {
			print '<b>Warning:</b> No credentials set. Before using this plugin you need to create a service account.' . $credentials_path;
		} else if ( ! file_exists( $credentials_path ) ) {
			print '<b>Warning:</b> Credentials path invalid. Check if the file ' . $credentials_path . ' exists.';
		} else {

			if ( isset( $shared_secret ) && strlen( $shared_secret ) >= 10 ) {
				try {

					$firebase = \Firebase::fromServiceAccount( $credentials_path );
					$database = $firebase->getDatabase();

					$database->getReference( 'options/secret' )->set( $shared_secret );
					print '<b>Configuration working:</b> Firebase database: ' . $firebase->getDatabaseUri();
				} catch ( \Firebase\Exception\InvalidArgumentException $e ) {
					print '<b>Error:</b> ' . $e->getMessage();
				}
			} else {
				print '<b>Warning:</b> Secret invalid. Enter a secret of at least 10 characters';
			}
		}
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function shared_secret_callback() {
		printf(
			'<input type="text" id="shared_secret" name="fa_options[shared_secret]" value="%s" />',
			isset( $this->options['shared_secret'] ) ? esc_attr( $this->options['shared_secret'] ) : ''
		);
	}


	/**
	 * Get the settings option array and print one of its values
	 */
	public function firebase_credentials_callback() {
		printf(
			'<input type="text" id="firebase_credentials" name="fa_options[firebase_credentials]" value="%s" />',
			isset( $this->options['firebase_credentials'] ) ? esc_attr( $this->options['firebase_credentials'] ) : ''
		);
	}
}

