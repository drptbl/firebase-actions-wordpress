<?php
/*
 * Plugin Name:  Firebase messages
 * Plugin URI:   https://www.warmbeer.io
 * Version:      1.0
 * Description:  Get notifications from important events
 * Author:       Nick Martens
 * Author URI:   https://www.warmbeer.io/about/
 */

namespace Firebase;
require "vendor/autoload.php";

require_once( "FirebaseSettingsPage.php" );
require_once( 'Firebase.php' );


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


	$credentialsPath = $options['firebase_credentials'];
	$shared_secret   = $options['shared_secret'];
	if ( ! $credentialsPath ) {
		return false;
	}
	if ( ! file_exists( $credentialsPath ) ) {
		return false;
	}

	if ( ! $shared_secret ) {
		return false;
	}
	if ( strlen( $shared_secret ) < 10 ) {
		return false;
	}

	return true;
}


function _do_post( $refPath, $title, $message, $url ) {
	$options = get_option( 'fa_options' );
	$credentialsPath = $options['firebase_credentials'];
	$expected_secret = $options['shared_secret'];

	try {
		$firebase = \Firebase::fromServiceAccount( $credentialsPath );
		$database = $firebase->getDatabase();

		$secret = $database->getReference('options/secret')->getValue();
		if (strcmp($secret, $expected_secret) != 0) {
			error_log('The secret in firebase does not match with the secret in the config');
			return;
		}

		$newPost = $database->getReference( 'blog/posts/' . $refPath . '/tasks' )->push( [
			'title'         => $title,
			'body'          => $message,
			'url'           => $url,
			'remote_addr'   => getenv( 'REMOTE_ADDR' ),
			'forwarded_for' => getenv( 'HTTP_FORWARDED_FOR' )
		] );
	} catch ( \Firebase\Exception\InvalidArgumentException $e ) {
		error_log( $e->getMessage() );
	}
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


?>