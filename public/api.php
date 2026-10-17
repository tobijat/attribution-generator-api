<?php

declare(strict_types=1);

use AttributionGenerator\Http\FrontController;

$autoload = null;
foreach( [
	dirname( __DIR__ ) . '/vendor/autoload.php',
	dirname( __DIR__, 3 ) . '/autoload.php',
] as $candidate ) {
	if( is_file( $candidate ) ) {
		$autoload = $candidate;
		break;
	}
}

if( $autoload === null ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo json_encode( [
		'error' => [
			'code' => 'not-installed',
			'message' => 'Run composer install before using the API.',
		],
	] );
	exit( 1 );
}

require $autoload;

header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
header( 'Access-Control-Allow-Headers: Content-Type' );

if( ( $_SERVER[ 'REQUEST_METHOD' ] ?? 'GET' ) === 'OPTIONS' ) {
	http_response_code( 204 );
	exit;
}

FrontController::create()->handle()->send();
