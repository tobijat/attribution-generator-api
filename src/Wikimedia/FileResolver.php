<?php

declare(strict_types=1);

namespace AttributionGenerator\Wikimedia;

use AttributionGenerator\Exception\AttributionException;

/**
 * Turns a Wikimedia file link into a wiki API base URL and a page title.
 */
final class FileResolver
{
	public function resolve( string $input ): FileLocation
	{
		$input = trim( $input );
		if( $input === '' ) {
			throw new AttributionException( 'invalid-input', 'An image link is required.' );
		}

		if( !preg_match( '#^https?://#i', $input ) ) {
			return new FileLocation( 'https://commons.wikimedia.org/', $this->prefixFile( rawurldecode( $input ) ) );
		}

		if( !$this->isWikiUrl( $input ) ) {
			throw new AttributionException(
				'invalid-url',
				'The image link must point to a Wikimedia file page or file URL.',
				422,
			);
		}

		return $this->splitUrl( $input );
	}

	private function isWikiUrl( string $url ): bool
	{
		return str_contains( $url, '.wikipedia.org/w' )
			|| ( str_contains( $url, 'upload.wikimedia.org/wikipedia/' ) && !str_contains( $url, '/wikipedia/commons/' ) )
			|| str_contains( $url, 'wikimedia.org/' );
	}

	private function splitUrl( string $url ): FileLocation
	{
		$wikiUrl = $this->wikiBase( $url );
		if( preg_match( '/curid=([^&=]+)/i', $url, $matches ) ) {
			return new FileLocation( $wikiUrl, null, $matches[ 1 ] );
		}

		$title = $this->extractTitle( $url, !str_contains( $wikiUrl, '.wikipedia.org/' ) );
		if( $title === null || $title === '' || $title === 'File:' ) {
			throw new AttributionException( 'invalid-url', 'The image link does not contain a file name.', 422 );
		}

		return new FileLocation( $wikiUrl, $title );
	}

	private function wikiBase( string $url ): string
	{
		if( str_contains( $url, 'commons.wikimedia.org/' ) || str_contains( $url, 'commons.m.wikimedia.org/' ) ) {
			return 'https://commons.wikimedia.org/';
		}

		if( preg_match( '#upload\.wikimedia\.org/wikipedia/([-a-z]{2,})/#i', $url, $matches ) ) {
			$domain = $matches[ 1 ] === 'commons' ? 'wikimedia' : 'wikipedia';

			return 'https://' . $matches[ 1 ] . '.' . $domain . '.org/';
		}

		if( preg_match( '#([-a-z]{2,})(\.m)?\.wikipedia\.org/#i', $url, $matches ) ) {
			return 'https://' . $matches[ 1 ] . '.wikipedia.org/';
		}

		if( preg_match( '#/wikipedia/([^/]+)/#', $url, $matches ) ) {
			return 'https://' . $matches[ 1 ] . '.wikipedia.org/';
		}

		throw new AttributionException( 'invalid-url', 'The image link could not be read as a Wikimedia file.', 422 );
	}

	private function extractTitle( string $url, bool $forcePrefix ): ?string
	{
		if( preg_match( '/[?&]title=([^&]+)/i', $url, $matches ) ) {
			return $this->normalizeTitle( rawurldecode( $matches[ 1 ] ), $forcePrefix );
		}

		foreach( [ '#mediaviewer/', '#/media/' ] as $key ) {
			$position = strpos( $url, $key );
			if( $position !== false ) {
				return $this->normalizeTitle( rawurldecode( substr( $url, $position + strlen( $key ) ) ), $forcePrefix );
			}
		}

		$path = preg_replace( '/[?#].*$/', '', $url ) ?? $url;
		if( preg_match( '#/wiki/Special:(?:FilePath|Redirect/file)/(.+)$#i', $path, $matches ) ) {
			return $this->normalizeTitle( rawurldecode( $matches[ 1 ] ), true );
		}

		$key = 'wiki/';
		$position = strpos( $path, $key );
		if( $position !== false ) {
			return $this->normalizeTitle( rawurldecode( substr( $path, $position + strlen( $key ) ) ), $forcePrefix );
		}

		$segments = array_values( array_filter( explode( '/', $path ), static fn( string $segment ): bool => $segment !== '' ) );
		$thumb = array_search( 'thumb', $segments, true );
		$name = $thumb !== false ? $segments[ count( $segments ) - 2 ] : $segments[ count( $segments ) - 1 ];

		return $this->normalizeTitle( rawurldecode( $name ), $forcePrefix );
	}

	private function normalizeTitle( string $title, bool $forcePrefix ): string
	{
		$title = preg_replace( '/[?#].*$/', '', $title ) ?? $title;

		return $forcePrefix ? $this->prefixFile( $title ) : $title;
	}

	private function prefixFile( string $title ): string
	{
		if( !str_contains( $title, ':' ) ) {
			return 'File:' . $title;
		}

		return $title;
	}
}
