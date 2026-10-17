<?php

declare(strict_types=1);

namespace AttributionGenerator\Tests;

use AttributionGenerator\Evaluation\Evaluator;
use AttributionGenerator\I18n\Translator;
use AttributionGenerator\Licence\LicenceCatalogue;
use AttributionGenerator\Questionnaire;
use AttributionGenerator\Wikimedia\Asset;
use AttributionGenerator\Wikimedia\HtmlFragment;
use PHPUnit\Framework\TestCase;

final class EvaluatorTest extends TestCase
{
	private LicenceCatalogue $catalogue;

	protected function setUp(): void
	{
		$this->catalogue = LicenceCatalogue::fromPackage();
	}

	public function testPrintAttributionOmitsTheTitleForCc4(): void
	{
		$attribution = $this->evaluate( 'cc-by-4.0', [ 'type_of_use' => 'print' ] );

		self::assertStringNotContainsString( 'A painting', $attribution->text );
		self::assertStringContainsString( 'Ann (https://commons.wikimedia.org/wiki/File:Example.jpg)', $attribution->text );
		self::assertSame( $attribution->text, $attribution->plainText );
		self::assertSame( $attribution->text, $attribution->html );
	}

	public function testOnlineAttributionLinksTheTitleEvenForCc4(): void
	{
		$attribution = $this->evaluate( 'cc-by-4.0', [ 'type_of_use' => 'online' ] );

		self::assertStringContainsString(
			'<a href="https://commons.wikimedia.org/wiki/File:Example.jpg" target="_blank">A painting</a>',
			$attribution->text,
		);
		self::assertStringContainsString( '„A painting“', $attribution->plainText );
		self::assertStringNotContainsString( 'target="_blank"', $attribution->html );
		self::assertStringContainsString( 'rel="license"', $attribution->html );
	}

	public function testEditedWorkUsesTheChosenLicenceAndNamesTheChange(): void
	{
		$attribution = $this->evaluate( 'cc-by-3.0', [
			'type_of_use' => 'print',
			'edited' => true,
			'change' => 'zugeschnitten',
			'creator' => 'Meh',
			'licence' => 'cc-by-3.0-de',
		] );

		self::assertStringContainsString(
			'zugeschnitten von Meh, https://creativecommons.org/licenses/by/3.0/de/legalcode',
			$attribution->text,
		);
	}

	public function testEditedWorkWithoutADescriptionUsesTheDefaultWording(): void
	{
		$attribution = $this->evaluate( 'cc-by-3.0', [
			'type_of_use' => 'online',
			'edited' => true,
			'creator' => 'Meh',
			'language' => 'en',
		] );

		self::assertStringContainsString( 'modified by Meh', $attribution->text );
		self::assertStringNotContainsString( 'bearbeitet', $attribution->text );
	}

	public function testMissingAuthorCanBeMarkedAnonymous(): void
	{
		$asset = new Asset(
			'File:Example.jpg',
			'A painting',
			'https://commons.wikimedia.org/wiki/File:Example.jpg',
			$this->catalogue->get( 'cc-by-sa-4.0' ),
			HtmlFragment::fromParts( [] ),
			null,
		);
		$attribution = ( new Evaluator( $this->catalogue, new Translator( 'de' ) ) )->evaluate(
			$asset,
			Questionnaire::fromArray( [ 'type_of_use' => 'online', 'author_unknown' => true ] ),
		);

		self::assertStringStartsWith( 'anonym, ', $attribution->text );
	}

	public function testCustomAttributionReplacesTheAuthor(): void
	{
		$asset = new Asset(
			'File:Example.jpg',
			'A painting',
			'https://commons.wikimedia.org/wiki/File:Example.jpg',
			$this->catalogue->get( 'cc-by-3.0' ),
			HtmlFragment::fromParts( [ [ 'text' => 'Ann', 'href' => null ] ] ),
			HtmlFragment::fromParts( [ [ 'text' => 'Museum catalogue', 'href' => null ] ] ),
		);
		$attribution = ( new Evaluator( $this->catalogue, new Translator( 'de' ) ) )->evaluate(
			$asset,
			Questionnaire::fromArray( [ 'type_of_use' => 'online' ] ),
		);

		self::assertStringStartsWith( 'Museum catalogue, ', $attribution->text );
		self::assertStringNotContainsString( 'Ann', $attribution->text );
	}

	/**
	 * @param array<string, mixed> $answers
	 */
	private function evaluate( string $licenceId, array $answers ): \AttributionGenerator\Attribution
	{
		$asset = new Asset(
			'File:Example.jpg',
			'A painting',
			'https://commons.wikimedia.org/wiki/File:Example.jpg',
			$this->catalogue->get( $licenceId ),
			HtmlFragment::fromParts( [ [
				'text' => 'Ann',
				'href' => 'https://commons.wikimedia.org/wiki/User:Ann',
			] ] ),
			null,
		);
		$questionnaire = Questionnaire::fromArray( $answers );

		return ( new Evaluator( $this->catalogue, new Translator( $questionnaire->language ) ) )->evaluate( $asset, $questionnaire );
	}
}
