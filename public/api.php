<?php

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
header( 'Access-Control-Allow-Headers: Content-Type' );

if( ( $_SERVER[ 'REQUEST_METHOD' ] ?? 'GET' ) === 'OPTIONS' ) {
	http_response_code( 204 );
	exit;
}

if( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
	http_response_code( 500 );
	echo json_encode( array(
		'error' => array(
			'code' => 'php-version',
			'message' => 'This API needs PHP 8.1 or newer. This server runs PHP ' . PHP_VERSION . '.',
		),
	) );
	exit( 1 );
}

$autoload = null;
$candidates = array(
	dirname( __DIR__ ) . '/vendor/autoload.php',
	dirname( dirname( dirname( __DIR__ ) ) ) . '/autoload.php',
);
foreach( $candidates as $candidate ) {
	if( is_file( $candidate ) ) {
		$autoload = $candidate;
		break;
	}
}

if( $autoload === null ) {
	http_response_code( 500 );
	echo json_encode( array(
		'error' => array(
			'code' => 'not-installed',
			'message' => 'Run composer install before using the API.',
		),
	) );
	exit( 1 );
}

require $autoload;

\AttributionGenerator\Http\FrontController::create()->handle()->send();
