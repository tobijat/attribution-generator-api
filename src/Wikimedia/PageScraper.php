<?php

declare(strict_types=1);

namespace AttributionGenerator\Wikimedia;

use AttributionGenerator\Licence\LicenceCatalogue;

/**
 * Reads author, attribution and licence information from a parsed file page.
 */
final class PageScraper
{
	public function __construct( private readonly LicenceCatalogue $licences )
	{
	}

	/**
	 * @param list<string> $templates
	 */
	public function scrape( string $prefixedFilename, string $html, array $templates, string $wikiUrl ): Asset
	{
		$filename = preg_replace( '/^[^:]+:/', '', $prefixedFilename ) ?? $prefixedFilename;
		$title = preg_replace( '/\.[^.]+$/', '', $filename ) ?? $filename;
		$title = str_replace( '_', ' ', $title );

		$wikiUrl = rtrim( $wikiUrl, '/' ) . '/';
		if( !str_starts_with( $wikiUrl, 'http' ) ) {
			$wikiUrl = 'https://' . $wikiUrl;
		}

		$authors = HtmlFragment::fromParts( [] );
		$attribution = null;
		if( $html !== '' ) {
			$xpath = $this->load( $html );
			$authors = $this->scrapeAuthors( $xpath );
			$attribution = $this->scrapeAttribution( $xpath );
		}

		return new Asset(
			$prefixedFilename,
			$title,
			$wikiUrl . 'wiki/' . $prefixedFilename,
			$this->licences->detect( $templates ),
			$authors,
			$attribution,
		);
	}

	private function load( string $html ): \DOMXPath
	{
		$document = new \DOMDocument();
		$internal = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );

		return new \DOMXPath( $document );
	}

	private function scrapeAuthors( \DOMXPath $xpath ): HtmlFragment
	{
		$nodes = $xpath->query( '//*[@id="fileinfotpl_aut"]' );
		if( $nodes === false || $nodes->length === 0 ) {
			return HtmlFragment::fromParts( [] );
		}

		$value = $this->nextElement( $nodes->item( 0 ) );
		if( !$value instanceof \DOMElement ) {
			return HtmlFragment::fromParts( [] );
		}

		return $this->scrapeSummaryField( $value );
	}

	private function scrapeAttribution( \DOMXPath $xpath ): ?HtmlFragment
	{
		$fromTemplate = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' licensetpl_attr ')]" );
		if( $fromTemplate !== false && $fromTemplate->length > 0 && $fromTemplate->item( 0 ) instanceof \DOMElement ) {
			$container = $this->childrenContainer( $fromTemplate->item( 0 ) );
			$this->sanitizeAnchors( $container );
			$this->trimEdgeWhitespace( $container );

			return HtmlFragment::fromElement( $container );
		}

		$cells = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' commons-file-information-table ')]//tr/*[1]" );
		if( $cells === false ) {
			return null;
		}

		foreach( $cells as $cell ) {
			if( !$cell instanceof \DOMElement || !str_contains( $cell->textContent, 'Attribution' ) ) {
				continue;
			}
			$value = $this->nextElement( $cell );
			if( !$value instanceof \DOMElement ) {
				return null;
			}

			return $this->scrapeSummaryField( $value );
		}

		return null;
	}

	private function scrapeSummaryField( \DOMElement $cell ): HtmlFragment
	{
		$container = $this->childrenContainer( $cell );
		$this->sanitizeAnchors( $container );
		$this->flattenVcards( $container );
		$container = $this->unwrapSingleWrapper( $container );
		$this->removeTalkLinks( $container );
		$this->unwrapNonAnchors( $container );
		$container = $this->collapseWhitespace( $container );
		$this->trimEdgeWhitespace( $container );

		return HtmlFragment::fromElement( $container );
	}

	private function childrenContainer( \DOMNode $node ): \DOMElement
	{
		$document = new \DOMDocument();
		$div = $document->createElement( 'div' );
		$document->appendChild( $div );
		foreach( $node->childNodes as $child ) {
			$div->appendChild( $document->importNode( $child, true ) );
		}

		return $div;
	}

	private function containerFromHtml( string $html ): \DOMElement
	{
		$document = new \DOMDocument();
		$internal = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );
		$divs = $document->getElementsByTagName( 'div' );

		return $divs->item( 0 ) instanceof \DOMElement ? $divs->item( 0 ) : $document->documentElement;
	}

	private function sanitizeAnchors( \DOMElement $container ): void
	{
		$anchors = [];
		$this->collectElements( $container, $anchors );
		foreach( $anchors as $anchor ) {
			if( strtolower( $anchor->nodeName ) !== 'a' ) {
				continue;
			}
			$href = $anchor->getAttribute( 'href' );
			if( str_starts_with( $href, '/w/index.php?title=User:' ) ) {
				if( preg_match( '/^\/w\/index\.php\?title=([^&]+).*$/', $href, $matches ) ) {
					$href = 'https://commons.wikimedia.org/wiki/' . $matches[ 1 ];
				}
			} elseif( str_starts_with( $href, '/wiki/User:' ) ) {
				$href = 'https://commons.wikimedia.org' . $href;
			} elseif( str_starts_with( $href, '//' ) ) {
				$href = 'https:' . $href;
			}

			while( $anchor->attributes->length > 0 ) {
				$anchor->removeAttribute( $anchor->attributes->item( 0 )->name );
			}
			$anchor->setAttribute( 'href', $href );
		}
	}

	private function flattenVcards( \DOMElement $container ): void
	{
		$vcards = [];
		$this->collectElements( $container, $vcards );
		foreach( $vcards as $element ) {
			if( strtolower( $element->nodeName ) !== 'div' || !$this->hasClass( $element, 'vcard' ) ) {
				continue;
			}
			$creator = null;
			$candidates = [];
			$this->collectElements( $element, $candidates );
			foreach( $candidates as $candidate ) {
				if( strtolower( $candidate->nodeName ) === 'span' && $candidate->getAttribute( 'id' ) === 'creator' ) {
					$creator = $candidate;
					break;
				}
			}
			while( $element->firstChild ) {
				$element->removeChild( $element->firstChild );
			}
			if( $creator instanceof \DOMElement ) {
				foreach( $creator->childNodes as $child ) {
					$element->appendChild( $child->cloneNode( true ) );
				}
			}
		}
	}

	private function unwrapSingleWrapper( \DOMElement $container ): \DOMElement
	{
		$meaningful = $this->meaningfulChildren( $container );
		if( count( $meaningful ) !== 1 || !$meaningful[ 0 ] instanceof \DOMElement ) {
			return $container;
		}
		if( strtolower( $meaningful[ 0 ]->nodeName ) === 'a' ) {
			return $container;
		}

		return $this->childrenContainer( $meaningful[ 0 ] );
	}

	private function removeTalkLinks( \DOMElement $container ): void
	{
		$children = [];
		foreach( $container->childNodes as $child ) {
			$children[] = $child;
		}
		foreach( $children as $index => $child ) {
			if( !$child instanceof \DOMElement || strtolower( $child->nodeName ) !== 'a' ) {
				continue;
			}
			if( trim( $child->textContent ) !== 'talk' ) {
				continue;
			}
			foreach( [ $children[ $index - 1 ] ?? null, $child, $children[ $index + 1 ] ?? null ] as $node ) {
				if( $node instanceof \DOMNode && $node->parentNode === $container ) {
					$container->removeChild( $node );
				}
			}
		}
	}

	private function unwrapNonAnchors( \DOMElement $container ): void
	{
		$changed = true;
		while( $changed ) {
			$changed = false;
			$elements = [];
			$this->collectElements( $container, $elements );
			foreach( $elements as $element ) {
				if( strtolower( $element->nodeName ) === 'a' || $element->parentNode === null ) {
					continue;
				}
				$parent = $element->parentNode;
				while( $element->firstChild ) {
					$parent->insertBefore( $element->firstChild, $element );
				}
				$parent->removeChild( $element );
				$changed = true;
				break;
			}
		}
	}

	private function collapseWhitespace( \DOMElement $container ): \DOMElement
	{
		$html = $this->innerHtml( $container );
		$html = str_replace( [ "\u{00A0}", '&nbsp;' ], ' ', $html );
		$html = preg_replace( '/\s+/u', ' ', $html ) ?? $html;

		return $this->containerFromHtml( trim( $html ) );
	}

	private function trimEdgeWhitespace( \DOMElement $container ): void
	{
		while( $container->firstChild instanceof \DOMText && trim( $container->firstChild->textContent ) === '' ) {
			$container->removeChild( $container->firstChild );
		}
		while( $container->lastChild instanceof \DOMText && trim( $container->lastChild->textContent ) === '' ) {
			$container->removeChild( $container->lastChild );
		}
	}

	private function innerHtml( \DOMElement $element ): string
	{
		$html = '';
		foreach( $element->childNodes as $child ) {
			$html .= $element->ownerDocument?->saveHTML( $child ) ?? '';
		}

		return $html;
	}

	private function nextElement( ?\DOMNode $node ): ?\DOMElement
	{
		$sibling = $node?->nextSibling;
		while( $sibling !== null && !$sibling instanceof \DOMElement ) {
			$sibling = $sibling->nextSibling;
		}

		return $sibling instanceof \DOMElement ? $sibling : null;
	}

	/**
	 * @return list<\DOMNode>
	 */
	private function meaningfulChildren( \DOMElement $container ): array
	{
		$children = [];
		foreach( $container->childNodes as $child ) {
			if( $child instanceof \DOMText && trim( $child->textContent ) === '' ) {
				continue;
			}
			$children[] = $child;
		}

		return $children;
	}

	/**
	 * @param list<\DOMElement> $elements
	 */
	private function collectElements( \DOMNode $node, array &$elements ): void
	{
		foreach( $node->childNodes as $child ) {
			if( $child instanceof \DOMElement ) {
				$elements[] = $child;
				$this->collectElements( $child, $elements );
			}
		}
	}

	private function hasClass( \DOMElement $element, string $class ): bool
	{
		$classes = preg_split( '/\s+/', $element->getAttribute( 'class' ) ) ?: [];

		return in_array( $class, $classes, true );
	}
}
