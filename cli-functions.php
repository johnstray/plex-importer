<?php
/**
 * cli-functions - A set of functions to make working with PHP on the command line just that little bit easier.
 * Copyright 2014-2017 (c) John Stray - All rights reserved.
 */

date_default_timezone_set("Australia/Darwin");

$matches = array();
preg_match( '/CON.*:(\n[^|]+?){3}(?<cols>\d+)/', `mode`, $matches );
$console_cols = (int) $matches['cols'] - 1;

function print_c ( $text, $alignment = 'center', $ending = "\n", $offset = 0 ) {
	GLOBAL $console_cols;
	$text_len = strlen( $text ) - $offset;
	$width = $console_cols;
	$padding = (int) round( ( $width / 2 ) - ( $text_len / 2 ) );
	switch ( $alignment ) {
		case 'center':
			print str_pad('',$padding,' ',STR_PAD_LEFT).$text.$ending;
			break;
		case 'right':
			print str_pad('', $width - $text_len, ' ', STR_PAD_LEFT).$text.$ending;
			break;
	}
}

function print_a ( $char = '*' ) {
	GLOBAL $console_cols;
	print( str_repeat( $char, $console_cols ) . "\n" );
}

function print_s ( $text, $status ) {
	GLOBAL $console_cols;
	$padding = $console_cols - strlen( $text );

	if ( is_integer( $status ) ) {
		if ( $status == 0 ) {
			printf( "{$text}%{$padding}s\n", "[SUCCESS]" );
		} elseif ( $status == 1 ) {
			printf( "{$text}%{$padding}s\n", "[FAILED]" );
		} elseif ( $status == 2 ) {
			printf( "{$text}%{$padding}s\n", "[WARNING]" );
		} elseif ( $status == 3 ) {
			printf( "{$text}%{$padding}s\n", "[SKIPPING]" );
		} else {
			printf( "{$text}%{$padding}s\n", "[UNKNOWN]" );
		}
	} else {
		printf( "{$text}%{$padding}s\n", "[NONINT]" );
	}
}

function prompt ( $msg ) { 
	echo $msg; 
	$in = trim( fgets( fopen( 'php://stdin', 'r' ) ) ); 
	return $in; 
}

function rrmdir ( $directory ) {
	$items = scandir( $directory );
	foreach ( $items as $item ) {
		if ( $item == '.' || $item == '..' ) { continue; }
		$path = $directory . '/' . $item;
		if ( is_dir( $path ) ) {
			rrmdir( $path );
		} else {
			unlink( $path );
		}
	}
	return rmdir($directory);
}

if ( isset( $cliOptions ) ) {
	if ( array_key_exists( 'v', $cliOptions ) || array_key_exists( 'version', $cliOptions ) ) {
		if ( function_exists( 'print_version' ) ) {
			print_version();
			die();
		} else {
			die( "No version information is available with this program" );
		}
	}
	if ( array_key_exists( 'h', $cliOptions ) || array_key_exists( 'help', $cliOptions ) ) {
		if ( function_exists( 'print_help') ) {
			print_help();
			die();
		} else {
			die( "No help information is available with this program" );
		}
	}
} else {
	die( "Malformed program!\nPlease contact the program developer for assistance." );
}
