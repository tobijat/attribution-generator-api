<?php

declare(strict_types=1);

namespace AttributionGenerator\Wikimedia;

use AttributionGenerator\Exception\AttributionException;
use AttributionGenerator\Http\HttpClient;

final class MediaWikiClient
{
	private const CONTINUATION_LIMIT = 10;

	public function __construct( private readonly HttpClient $http )
	{
	}

	public function fetchFile( string $wikiUrl, string $title ): FilePage
	{
		$page = $this->query( $wikiUrl, [
			'action' => 'query',
			'prop' => 'imageinfo|revisions|templates',
			'titles' => $title,
			'iiprop' => 'mediatype|url',
			'iilimit' => '1',
			'rvprop' => 'content',
			'rvparse' => '1',
			'tlnamespace' => '10',
			'tllimit' => '100',
		], 'tlcontinue' );

		return $this->toFilePage( $page );
	}

	public function titleFromPageId( string $wikiUrl, string $pageId ): string
	{
		$page = $this->query( $wikiUrl, [
			'action' => 'query',
			'pageids' => $pageId,
		], null );

		$title = $page[ 'title' ] ?? null;
		if( !is_string( $title ) || $title === '' ) {
			throw new AttributionException( 'file-not-found', 'No file was found for this link.', 404 );
		}

		return $title;
	}

	/**
	 * @param array<string, scalar|null> $params
	 * @return array<string, mixed>
	 */
	private function query( string $wikiUrl, array $params, ?string $continueParam ): array
	{
		$params[ 'format' ] = 'json';
		$merged = null;
		$count = 0;

		do {
			$data = $this->http->getJson( rtrim( $wikiUrl, '/' ) . '/w/api.php', $params );
			$pages = $data[ 'query' ][ 'pages' ] ?? null;
			if( !is_array( $pages ) ) {
				throw new AttributionException( 'upstream-error', 'Wikimedia returned an unexpected response.', 502 );
			}

			$page = $this->firstPage( $pages );
			$merged = $merged === null ? $page : $this->mergeTemplates( $merged, $page );
			$count++;
			$again = $continueParam !== null
				&& isset( $data[ 'continue' ][ $continueParam ] )
				&& is_scalar( $data[ 'continue' ][ $continueParam ] )
				&& $count < self::CONTINUATION_LIMIT;
			if( $again ) {
				$params[ $continueParam ] = $data[ 'continue' ][ $continueParam ];
			}
		} while( $again );

		return $merged ?? [];
	}

	/**
	 * @param array<mixed> $pages
	 * @return array<string, mixed>
	 */
	private function firstPage( array $pages ): array
	{
		foreach( $pages as $page ) {
			if( !is_array( $page ) ) {
				continue;
			}
			if( isset( $page[ 'invalid' ] ) ) {
				throw new AttributionException( 'invalid-url', 'The image link does not refer to a valid page.', 422 );
			}
			$shared = ( $page[ 'imagerepository' ] ?? '' ) === 'shared';
			if( isset( $page[ 'missing' ] ) && !$shared ) {
				throw new AttributionException( 'file-not-found', 'No file was found for this link.', 404 );
			}

			return $page;
		}

		throw new AttributionException( 'upstream-error', 'Wikimedia returned an empty response.', 502 );
	}

	/**
	 * @param array<string, mixed> $page
	 * @param array<string, mixed> $more
	 * @return array<string, mixed>
	 */
	private function mergeTemplates( array $page, array $more ): array
	{
		if( isset( $more[ 'templates' ] ) && is_array( $more[ 'templates' ] ) ) {
			$existing = is_array( $page[ 'templates' ] ?? null ) ? $page[ 'templates' ] : [];
			$page[ 'templates' ] = array_merge( $existing, $more[ 'templates' ] );
		}

		return $page;
	}

	/**
	 * @param array<string, mixed> $page
	 */
	private function toFilePage( array $page ): FilePage
	{
		$info = $page[ 'imageinfo' ][ 0 ] ?? null;
		$mediaType = is_array( $info ) && isset( $info[ 'mediatype' ] ) ? strtolower( (string)$info[ 'mediatype' ] ) : null;
		$html = $page[ 'revisions' ][ 0 ][ '*' ] ?? null;
		$templates = [];
		foreach( $page[ 'templates' ] ?? [] as $template ) {
			if( !is_array( $template ) || !isset( $template[ 'title' ] ) ) {
				continue;
			}
			$templates[] = preg_replace( '/^[^:]+:/', '', (string)$template[ 'title' ] ) ?? (string)$template[ 'title' ];
		}

		return new FilePage(
			(string)( $page[ 'title' ] ?? '' ),
			$mediaType,
			is_string( $html ) ? $html : null,
			$templates,
		);
	}
}
