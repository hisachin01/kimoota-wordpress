<?php
/**
 * Plugin Name: Custom User Contact Methods
 * Description: ユーザープロフィールにSNS項目を追加します。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'user_contactmethods', function( $methods ) {
	$methods['x_url']        = 'X（旧Twitter）URL';
	$methods['facebook_url'] = 'Facebook URL';
	$methods['bluesky_url']  = 'BlueSky URL';
	$methods['codepen_url']  = 'CodePen URL';
	$methods['website_url']  = 'Webサイト URL';

	return $methods;
} );
