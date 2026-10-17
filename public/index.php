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

FrontController::create()->handle()->send();
