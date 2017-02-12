<?php

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
			'server_key',
			'Server key',
			array( $this, 'server_key_callback' ),
			'my-setting-admin',
			'setting_section_id'
		);

		add_settings_field(
			'sender_id', // ID
			'Sender id', // Title
			array( $this, 'sender_id_callback' ), // Callback
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
		if ( isset( $input['sender_id'] ) ) {
			$new_input['sender_id'] = sanitize_text_field( $input['sender_id'] );
		}

		if ( isset( $input['server_key'] ) ) {
			$new_input['server_key'] = sanitize_text_field( $input['server_key'] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		print 'Configure your settings below:';
		$options = get_option( 'fa_options' );

		if ( ! $options ) {
			print '<br/><b>Warning:</b> No configuration found. You need to set the server key and sender id first';

			return;
		}

		$server_key = $options['server_key'];
		$sender_id  = $options['sender_id'];

		if ( ! $server_key || ! $sender_id ) {
			print '<br/><b>Warning:</b> No configuration found. You need to set the server key and sender id first';

			return;
		}

	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function sender_id_callback() {
		printf(
			'<input type="text" id="sender_id" name="fa_options[sender_id]" value="%s" />',
			isset( $this->options['sender_id'] ) ? esc_attr( $this->options['sender_id'] ) : ''
		);
	}


	/**
	 * Get the settings option array and print one of its values
	 */
	public function server_key_callback() {
		printf(
			'<textarea id="server_key" name="fa_options[server_key]">%s</textarea>',
			isset( $this->options['server_key'] ) ? esc_attr( $this->options['server_key'] ) : ''
		);
	}
}

