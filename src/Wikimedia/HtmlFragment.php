<?php

declare(strict_types=1);

namespace AttributionGenerator\Wikimedia;

/**
 * A small piece of attribution HTML made of text and links.
 */
final class HtmlFragment
{
	/**
	 * @param list<array{text: string, href: string|null}> $parts
	 */
	private function __construct( private readonly array $parts )
	{
	}

	/**
	 * @param list<array{text: string, href?: string|null}> $parts
	 */
	public static function fromParts( array $parts ): self
	{
		$normalized = [];
		foreach( $parts as $part ) {
			$normalized[] = [
				'text' => (string)$part[ 'text' ],
				'href' => isset( $part[ 'href' ] ) && $part[ 'href' ] !== '' ? (string)$part[ 'href' ] : null,
			];
		}

		return new self( $normalized );
	}

	public static function fromElement( \DOMElement $container ): self
	{
		$parts = [];
		foreach( $container->childNodes as $child ) {
			if( $child instanceof \DOMText ) {
				$parts[] = [ 'text' => $child->textContent, 'href' => null ];
			} elseif( $child instanceof \DOMElement && strtolower( $child->nodeName ) === 'a' ) {
				$href = $child->getAttribute( 'href' );
				$parts[] = [
					'text' => $child->textContent,
					'href' => $href !== '' ? $href : null,
				];
			} elseif( $child instanceof \DOMElement ) {
				$parts[] = [ 'text' => $child->textContent, 'href' => null ];
			}
		}

		return new self( $parts );
	}

	public function isEmpty(): bool
	{
		return $this->text() === '';
	}

	public function text(): string
	{
		$text = '';
		foreach( $this->parts as $part ) {
			$text .= $part[ 'text' ];
		}

		return trim( preg_replace( '/\s+/u', ' ', $text ) ?? $text );
	}

	public function toHtml( bool $targetBlank ): string
	{
		$html = '';
		foreach( $this->parts as $part ) {
			$text = self::escape( $part[ 'text' ] );
			if( $part[ 'href' ] === null ) {
				$html .= $text;
				continue;
			}

			$target = $targetBlank ? ' target="_blank"' : '';
			$html .= '<a href="' . self::escape( $part[ 'href' ] ) . '"' . $target . '>' . $text . '</a>';
		}

		return $html;
	}

	public static function escape( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
