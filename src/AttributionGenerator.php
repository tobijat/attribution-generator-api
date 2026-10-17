<?php

declare(strict_types=1);

namespace AttributionGenerator;

use AttributionGenerator\Evaluation\Evaluator;
use AttributionGenerator\Exception\AttributionException;
use AttributionGenerator\Http\CurlHttpClient;
use AttributionGenerator\Http\HttpClient;
use AttributionGenerator\I18n\Translator;
use AttributionGenerator\Licence\LicenceCatalogue;
use AttributionGenerator\Wikimedia\FileResolver;
use AttributionGenerator\Wikimedia\MediaWikiClient;
use AttributionGenerator\Wikimedia\PageScraper;

/**
 * Builds the text, plain text and HTML attribution for a Wikimedia file.
 *
 * The image argument is a file page or file URL, for example
 * https://commons.wikimedia.org/wiki/File:Example.jpg. The questionnaire
 * carries the same answers as the web form: type of use, and, when the file
 * was edited, the change, the creator and the licence.
 */
final class AttributionGenerator
{
	private const SUPPORTED_MEDIA_TYPES = [ 'bitmap', 'drawing' ];

	public function __construct(
		private readonly FileResolver $files,
		private readonly MediaWikiClient $wiki,
		private readonly PageScraper $scraper,
		private readonly LicenceCatalogue $catalogue,
	) {
	}

	public static function create( ?HttpClient $http = null ): self
	{
		$catalogue = LicenceCatalogue::fromPackage();

		return new self(
			new FileResolver(),
			new MediaWikiClient( $http ?? new CurlHttpClient() ),
			new PageScraper( $catalogue ),
			$catalogue,
		);
	}

	public function generate( string $imageUrl, Questionnaire $answers ): Attribution
	{
		$location = $this->files->resolve( $imageUrl );
		$wikiUrl = $location->wikiUrl;
		$title = $location->title ?? $this->wiki->titleFromPageId( $wikiUrl, (string)$location->pageId );

		try {
			$page = $this->wiki->fetchFile( $wikiUrl, $title );
		} catch( AttributionException $exception ) {
			if( $exception->errorCode() !== 'file-not-found' || $this->isCommons( $wikiUrl ) ) {
				throw $exception;
			}
			$title = $this->commonsTitle( $title );
			$wikiUrl = 'https://commons.wikimedia.org/';
			$page = $this->wiki->fetchFile( $wikiUrl, $title );
		}

		if( !$this->isCommons( $wikiUrl ) && ( $page->html === null || $page->html === '' || $page->templates === [] ) ) {
			try {
				$commons = $this->wiki->fetchFile( 'https://commons.wikimedia.org/', $this->commonsTitle( $page->title ) );
				if( $commons->mediaType !== null ) {
					$page = $commons;
					$wikiUrl = 'https://commons.wikimedia.org/';
				}
			} catch( AttributionException ) {
				// Keep the page from the wiki named in the link.
			}
		}

		if( $page->mediaType === null ) {
			throw new AttributionException(
				'not-a-file',
				'The link does not refer to a Wikimedia file. Pass the file page or the direct file URL.',
				422,
			);
		}

		if( !in_array( $page->mediaType, self::SUPPORTED_MEDIA_TYPES, true ) ) {
			throw new AttributionException( 'unsupported-media', 'Only bitmap and drawing files are supported.', 422 );
		}

		$asset = $this->scraper->scrape( $page->title, $page->html ?? '', $page->templates, $wikiUrl );
		if( $asset->licence === null ) {
			throw new AttributionException( 'no-licence', 'No supported licence was found on this file page.', 422 );
		}
		if( $asset->licence->isInGroup( 'unsupported' ) ) {
			throw new AttributionException( 'unsupported-licence', 'This Creative Commons licence is not supported.', 422 );
		}

		return ( new Evaluator( $this->catalogue, new Translator( $answers->language ) ) )->evaluate( $asset, $answers );
	}

	private function isCommons( string $wikiUrl ): bool
	{
		return str_contains( $wikiUrl, 'commons.wikimedia.org' );
	}

	private function commonsTitle( string $title ): string
	{
		$name = preg_replace( '/^[^:]+:/', '', $title ) ?? $title;

		return 'File:' . $name;
	}
}
