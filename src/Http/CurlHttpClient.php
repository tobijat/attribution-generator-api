<?php

declare(strict_types=1);

namespace AttributionGenerator\Http;

use AttributionGenerator\Exception\AttributionException;

final class CurlHttpClient implements HttpClient
{
	private const USER_AGENT = 'AttributionGenerator/1.0 (https://lizenzhinweisgenerator.de; PHP attribution API)';

	public function getJson( string $url, array $query ): array
	{
		$separator = str_contains( $url, '?' ) ? '&' : '?';
		$body = $this->request( $url . $separator . http_build_query( $query ) );
		$decoded = json_decode( $body, true );
		if( !is_array( $decoded ) ) {
			throw new AttributionException( 'upstream-error', 'Wikimedia returned invalid JSON.', 502 );
		}

		if( isset( $decoded[ 'error' ] ) ) {
			$info = is_array( $decoded[ 'error' ] ) ? (string)( $decoded[ 'error' ][ 'info' ] ?? 'Wikimedia request failed.' ) : 'Wikimedia request failed.';
			throw new AttributionException( 'upstream-error', $info, 502 );
		}

		return $decoded;
	}

	private function request( string $url ): string
	{
		if( function_exists( 'curl_init' ) ) {
			return $this->curl( $url );
		}

		$context = stream_context_create( [
			'http' => [
				'timeout' => 20,
				'header' => 'User-Agent: ' . self::USER_AGENT . "\r\nAccept: application/json\r\n",
				'ignore_errors' => true,
			],
		] );
		$body = @file_get_contents( $url, false, $context );
		if( !is_string( $body ) || $body === '' ) {
			throw new AttributionException( 'upstream-error', 'Could not reach Wikimedia.', 502 );
		}

		return $body;
	}

	private function curl( string $url ): string
	{
		$handle = curl_init( $url );
		if( $handle === false ) {
			throw new AttributionException( 'upstream-error', 'Could not reach Wikimedia.', 502 );
		}

		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_USERAGENT => self::USER_AGENT,
			CURLOPT_HTTPHEADER => [ 'Accept: application/json' ],
		];
		if( defined( 'CURLSSLOPT_NATIVE_CA' ) ) {
			$options[ CURLOPT_SSL_OPTIONS ] = CURLSSLOPT_NATIVE_CA;
		}
		curl_setopt_array( $handle, $options );
		$body = curl_exec( $handle );
		$status = (int)curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
		$error = curl_error( $handle );
		curl_close( $handle );

		if( !is_string( $body ) || $body === '' || $status >= 400 ) {
			throw new AttributionException(
				'upstream-error',
				'Could not reach Wikimedia' . ( $error !== '' ? ': ' . $error : '.' ),
				502,
			);
		}

		return $body;
	}
}
