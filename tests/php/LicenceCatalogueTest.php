<?php

declare(strict_types=1);

namespace AttributionGenerator\Tests;

use AttributionGenerator\Licence\LicenceCatalogue;
use PHPUnit\Framework\TestCase;

final class LicenceCatalogueTest extends TestCase
{
	private LicenceCatalogue $catalogue;

	protected function setUp(): void
	{
		$this->catalogue = LicenceCatalogue::fromPackage();
	}

	public function testDetectsTheFirstMatchingLicence(): void
	{
		$licence = $this->catalogue->detect( [ 'Cc-by-sa-layout', 'Cc-by-sa-3.0', 'Information' ] );

		self::assertNotNull( $licence );
		self::assertSame( 'cc-by-sa-3.0', $licence->id() );
		self::assertSame( 'CC BY-SA 3.0', $licence->name() );
		self::assertSame( 'https://creativecommons.org/licenses/by-sa/3.0/legalcode', $licence->url() );
	}

	public function testBuildsAKnownPortedLicence(): void
	{
		$licence = $this->catalogue->detect( [ 'CC-BY-SA-3.0-fr' ] );

		self::assertNotNull( $licence );
		self::assertSame( 'cc-by-sa-3.0-ported', $licence->id() );
		self::assertSame( 'CC BY-SA 3.0 FR', $licence->name() );
		self::assertSame( 'https://creativecommons.org/licenses/by-sa/3.0/fr/deed.en', $licence->url() );
		self::assertTrue( $licence->isInGroup( 'ported' ) );
	}

	public function testDetectsPublicDomainTemplatesThatContainSpecialCharacters(): void
	{
		$licence = $this->catalogue->detect( [ 'PD-Germany-§134' ] );

		self::assertNotNull( $licence );
		self::assertSame( 'PD-Germany-§134', $licence->id() );
		self::assertTrue( $licence->isPublicDomain() );
	}
}
