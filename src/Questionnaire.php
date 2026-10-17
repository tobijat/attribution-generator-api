<?php

declare(strict_types=1);

namespace AttributionGenerator;

use AttributionGenerator\Exception\AttributionException;
use AttributionGenerator\I18n\Translator;

/**
 * Answers to the attribution questionnaire.
 *
 * typeOfUse is "online" or "print".
 * author and authorUnknown are used only when the file page has no author.
 * When edited is true, change, creator and licence describe the adaptation.
 * licence is a licence id such as "cc-by-sa-4.0", or "original" to keep the file's licence.
 */
final class Questionnaire
{
	public function __construct(
		public readonly string $typeOfUse,
		public readonly ?string $author,
		public readonly bool $authorUnknown,
		public readonly bool $edited,
		public readonly ?string $change,
		public readonly ?string $creator,
		public readonly ?string $licence,
		public readonly string $language = 'de',
	) {
		if( !in_array( $typeOfUse, [ 'online', 'print' ], true ) ) {
			throw new AttributionException( 'invalid-input', 'type_of_use must be "online" or "print".' );
		}
	}

	/**
	 * @param array<string, mixed> $input
	 */
	public static function fromArray( array $input ): self
	{
		$type = $input[ 'type_of_use' ] ?? $input[ 'typeOfUse' ] ?? $input[ 'type' ] ?? null;
		if( !is_string( $type ) || $type === '' ) {
			throw new AttributionException( 'invalid-input', 'type_of_use is required and must be "online" or "print".' );
		}

		return new self(
			$type,
			self::stringOrNull( $input[ 'author' ] ?? null ),
			self::boolish( $input[ 'author_unknown' ] ?? $input[ 'authorUnknown' ] ?? $input[ 'no_author' ] ?? $input[ 'no-author' ] ?? false ),
			self::boolish( $input[ 'edited' ] ?? false ),
			self::stringOrNull( $input[ 'change' ] ?? null ),
			self::stringOrNull( $input[ 'creator' ] ?? null ),
			self::stringOrNull( $input[ 'licence' ] ?? $input[ 'license' ] ?? null ),
			Translator::normalize( is_string( $input[ 'language' ] ?? null ) ? $input[ 'language' ] : null ),
		);
	}

	public function isPrint(): bool
	{
		return $this->typeOfUse === 'print';
	}

	public function suppliedAuthor( Translator $translator ): string
	{
		if( $this->author !== null && trim( $this->author ) !== '' ) {
			return trim( $this->author );
		}

		if( $this->authorUnknown ) {
			return $translator->t( 'evaluation.anonymous' );
		}

		throw new AttributionException(
			'author-required',
			'This file has no author. Provide author, or set author_unknown to true.',
			422,
		);
	}

	private static function stringOrNull( mixed $value ): ?string
	{
		return is_string( $value ) ? $value : null;
	}

	private static function boolish( mixed $value ): bool
	{
		if( is_bool( $value ) ) {
			return $value;
		}

		if( is_int( $value ) ) {
			return $value === 1;
		}

		if( !is_string( $value ) ) {
			return false;
		}

		return in_array( strtolower( $value ), [ '1', 'true', 'yes', 'y' ], true );
	}
}
