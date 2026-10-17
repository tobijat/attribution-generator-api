<?php

declare(strict_types=1);

namespace AttributionGenerator\Evaluation;

use AttributionGenerator\Attribution;
use AttributionGenerator\Exception\AttributionException;
use AttributionGenerator\I18n\Translator;
use AttributionGenerator\Licence\Licence;
use AttributionGenerator\Licence\LicenceCatalogue;
use AttributionGenerator\Questionnaire;
use AttributionGenerator\Wikimedia\Asset;
use AttributionGenerator\Wikimedia\HtmlFragment;

/**
 * Builds the three attribution strings from a file and the questionnaire answers.
 * The wording follows the web application's DialogueEvaluation.
 */
final class Evaluator
{
	private const OPEN_QUOTE = "\u{201E}";
	private const CLOSE_QUOTE = "\u{201C}";

	public function __construct(
		private readonly LicenceCatalogue $licences,
		private readonly Translator $translator,
	) {
	}

	public function evaluate( Asset $asset, Questionnaire $answers ): Attribution
	{
		if( $asset->licence === null ) {
			throw new AttributionException( 'no-licence', 'No supported licence was found on this file page.', 422 );
		}

		$text = $answers->isPrint()
			? $this->printAttribution( $asset, $answers )
			: $this->htmlAttribution( $asset, $answers );

		$plain = ( $answers->isPrint() || !$asset->licence->isInGroup( 'cc4' ) )
			? $this->printAttribution( $asset, $answers )
			: $this->textWithLinks( $asset, $answers );

		$html = $answers->isPrint()
			? $text
			: ( preg_replace( '/\s+target="_blank"/', '', $text ) ?? $text );

		return new Attribution( $text, $plain, $html );
	}

	private function printAttribution( Asset $asset, Questionnaire $answers ): string
	{
		$author = $this->authorAttribution( $asset, $answers, false ) ?? $this->author( $asset, $answers, false );
		$result = $author . ' (' . $asset->url . ')';
		$licence = $asset->licence;
		if( $licence !== null && !$licence->isInGroup( 'cc4' ) ) {
			$result .= ', ' . self::OPEN_QUOTE . $asset->title . self::CLOSE_QUOTE;
		}

		$editing = $this->editing( $answers, false );
		if( $editing !== '' ) {
			$result .= ', ' . $editing;
		}

		$url = $this->attributionLicence( $asset, $answers )->url();
		if( $url ) {
			$result .= ', ';
			if( $editing === '' && $licence !== null && $licence->isPublicDomain() ) {
				$result .= $this->translator->t( 'dialogue.pd-attribution-hint' )
					. ', ' . $this->translator->t( 'dialogue.check-details' )
					. ' Wikimedia Commons: ';
			}
			$result .= $url;
		}

		return $result;
	}

	private function htmlAttribution( Asset $asset, Questionnaire $answers ): string
	{
		$licence = $this->attributionLicence( $asset, $answers );
		$editing = $this->editing( $answers, true );

		if( $editing === '' && $licence->isPublicDomain() ) {
			$attributionLink = ', ' . HtmlFragment::escape( $this->translator->t( 'dialogue.pd-attribution-hint' ) );
			if( $licence->url() ) {
				$attributionLink .= ', ' . HtmlFragment::escape( $this->translator->t( 'dialogue.check-details' ) ) . ' '
					. $this->link( $licence->url(), 'Wikimedia Commons', false );
			}
		} else {
			$attributionLink = ', ' . $this->licenceHtml( $licence );
		}

		$editingSnippet = $editing !== '' ? ', ' . $editing : '';
		$author = $this->authorAttribution( $asset, $answers, true ) ?? $this->author( $asset, $answers, true );

		return $author . ', '
			. $this->link( $asset->url, $asset->title, false )
			. $editingSnippet
			. $attributionLink;
	}

	private function textWithLinks( Asset $asset, Questionnaire $answers ): string
	{
		$author = $this->authorAttribution( $asset, $answers, true ) ?? $this->author( $asset, $answers, false );
		$result = $author . ' (' . $asset->url . '), '
			. self::OPEN_QUOTE . $asset->title . self::CLOSE_QUOTE;

		$editing = $this->editing( $answers, false );
		if( $editing !== '' ) {
			$result .= ', ' . $editing;
		}

		$url = $this->attributionLicence( $asset, $answers )->url();
		if( $url ) {
			$result .= ', ' . $url;
		}

		return $result;
	}

	private function authorAttribution( Asset $asset, Questionnaire $answers, bool $asHtml ): ?string
	{
		if( $asset->attribution === null || $asset->attribution->isEmpty() ) {
			return null;
		}

		if( !$asHtml || $answers->isPrint() ) {
			$text = $asset->attribution->text();

			return $text !== '' ? $text : null;
		}

		$html = $asset->attribution->toHtml( false );

		return $html !== '' ? $html : $asset->attribution->text();
	}

	private function author( Asset $asset, Questionnaire $answers, bool $asHtml ): string
	{
		if( $asset->authors->isEmpty() ) {
			$name = $answers->suppliedAuthor( $this->translator );

			return $asHtml ? HtmlFragment::escape( $name ) : $name;
		}

		return $asHtml ? $asset->authors->toHtml( true ) : $asset->authors->text();
	}

	private function editing( Questionnaire $answers, bool $asHtml ): string
	{
		if( !$answers->edited ) {
			return '';
		}

		$change = $answers->change !== null ? trim( $answers->change ) : '';
		$attribution = $change !== '' ? $change : $this->translator->t( 'evaluation.edited' );
		$creator = $answers->creator !== null ? trim( $answers->creator ) : '';
		if( $creator !== '' ) {
			$attribution .= ' ' . $this->translator->t( 'evaluation.by' ) . ' ' . $creator;
		}

		return $asHtml ? HtmlFragment::escape( $attribution ) : $attribution;
	}

	private function attributionLicence( Asset $asset, Questionnaire $answers ): Licence
	{
		$choice = $answers->licence !== null ? trim( $answers->licence ) : '';
		if( $answers->edited && $choice !== '' && $choice !== 'original' ) {
			$licence = $this->licences->get( $choice );
			if( $licence === null ) {
				throw new AttributionException( 'invalid-licence', 'Unknown licence "' . $choice . '".', 422 );
			}

			return $licence;
		}

		if( $asset->licence === null ) {
			throw new AttributionException( 'no-licence', 'No supported licence was found on this file page.', 422 );
		}

		return $asset->licence;
	}

	private function licenceHtml( Licence $licence ): string
	{
		if( $licence->url() ) {
			return $this->link( $licence->url(), $licence->name(), true );
		}

		return HtmlFragment::escape( $licence->name() );
	}

	private function link( string $href, string $text, bool $licence ): string
	{
		$rel = $licence ? ' rel="license"' : '';

		return '<a href="' . HtmlFragment::escape( $href ) . '"' . $rel . ' target="_blank">'
			. HtmlFragment::escape( $text ) . '</a>';
	}
}
