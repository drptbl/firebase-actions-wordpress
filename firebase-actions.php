<?php
/*
 * Plugin Name:  Firebase messages
 * Plugin URI:   https://www.warmbeer.io
 * Version:      1.0
 * Description:  Get notifications from important events
 * Author:       Nick Martens
 * Author URI:   https://www.warmbeer.io/about/
 */


require_once( "FirebaseSettingsPage.php" );


if ( is_admin() ) {
	$my_settings_page = new FirebaseSettingsPage();
}
if ( is_configured() ) {
	add_action( 'wp_login', __NAMESPACE__ . '\\fa_init_wp_login', 10, 2 );
	add_action( 'wp_authenticate', __NAMESPACE__ . '\\fa_init_wp_authenticate' );
	add_action( 'save_post', __NAMESPACE__ . '\\fa_init_save_post' );
	add_action( 'publish_post', __NAMESPACE__ . '\\fa_init_publish_post', 10, 2 );
	add_action( 'publish_page', __NAMESPACE__ . '\\fa_init_publish_page', 10, 2 );
}

function is_configured() {
	$options = get_option( 'fa_options' );
	if ( ! $options ) {
		return false;
	}


	$credentialsPath = $options['server_key'];
	$sender_id       = $options['sender_id'];
	if ( ! $credentialsPath ) {
		return false;
	}

	if ( ! $sender_id ) {
		return false;
	}

	return true;
}


function _do_post( $refPath, $title, $message, $url ) {
	$options         = get_option( 'fa_options' );
	$server_key = $options['server_key'];

	$data = [
		'title'         => print_r( $title, true ),
		'body'          => print_r( $message, true ),
		'url'           => print_r( $url, true ),
		'request_time'  => print_r( $_SERVER['REQUEST_TIME'], true ),
		'remote_addr'   => print_r( getenv( 'REMOTE_ADDR' ), true ),
		'forwarded_for' => print_r( getenv( 'HTTP_FORWARDED_FOR' ), true )
	];

	$topic = '/topics/' . $refPath;

	$body = [
		'data' => $data,
		'to' => $topic
	];

	$headers = array
	(
		'Authorization: key=' . $server_key,
		'Content-Type: application/json'
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
	$result = curl_exec($ch);
	curl_close($ch);
	error_log("fcm result: " . $result);

}

function fa_init_save_post( $post_id ) {
	// If this is just a revision, do nothing.
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	$post_title = get_the_title( $post_id );
	$post_url   = get_permalink( $post_id );
	$subject    = 'A post has been updated';

	$message = "A post has been updated on your website:\n\n";
	$message .= $post_title . ": " . $post_url;

	_do_post( 'save_post', $subject, $message, $post_url );
}

function fa_init_wp_login( $user_login, $user ) {
	_do_post( "login", 'User: ' . $user_login . ' logged in', $user_login, null );
}

function fa_init_wp_authenticate( $user_name ) {
	_do_post( "authenticate", "A user tried to login", $user_name, null );
}

function fa_init_publish_post( $ID, $post ) {
	$author    = $post->post_author; /* Post author ID. */
	$name      = get_the_author_meta( 'display_name', $author );
	$title     = $post->post_title;
	$permalink = get_permalink( $ID );
	$subject   = sprintf( 'New article published: %s', $title );
	$message   = sprintf( '%s published a new article: “%s”.', $name, $title );
	$message .= sprintf( 'View: %s', $permalink );
	_do_post( 'new_article', $subject, $message, $permalink );
}

function fa_init_publish_page( $ID, $post ) {
	$author    = $post->post_author; /* Post author ID. */
	$name      = get_the_author_meta( 'display_name', $author );
	$title     = $post->post_title;
	$permalink = get_permalink( $ID );
	$subject   = sprintf( 'New page published: %s', $title );
	$message   = sprintf( '%s published a new page: “%s”.', $name, $title );
	$message .= sprintf( 'View: %s', $permalink );
	_do_post( 'new_page', $subject, $message, $permalink );
}

