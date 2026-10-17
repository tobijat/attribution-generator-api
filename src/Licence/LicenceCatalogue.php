<?php

declare(strict_types=1);

namespace AttributionGenerator\Licence;

use AttributionGenerator\Package;

final class LicenceCatalogue
{
	/**
	 * @param list<Licence> $licences
	 * @param array<string, Licence> $byId
	 * @param array<string, string> $portedLicences
	 */
	private function __construct(
		private readonly array $licences,
		private readonly array $byId,
		private readonly array $portedLicences,
	) {
	}

	public static function fromPackage(): self
	{
		$licences = json_decode( (string)file_get_contents( Package::path( 'data/licences.json' ) ), true );
		$ported = json_decode( (string)file_get_contents( Package::path( 'data/ported-licences.json' ) ), true );

		if( !is_array( $licences ) || !is_array( $ported ) ) {
			throw new \RuntimeException( 'Licence catalogue could not be read.' );
		}

		$list = [];
		$byId = [];
		foreach( $licences as $row ) {
			if( !is_array( $row ) ) {
				continue;
			}
			$licence = Licence::fromArray( $row );
			$list[] = $licence;
			$byId[ $licence->id() ] ??= $licence;
		}

		/** @var array<string, string> $ported */
		return new self( $list, $byId, $ported );
	}

	public function get( string $id ): ?Licence
	{
		return $this->byId[ $id ] ?? null;
	}

	/**
	 * @param list<string> $templates
	 */
	public function detect( array $templates ): ?Licence
	{
		$detected = [];

		foreach( $templates as $template ) {
			foreach( $this->licences as $licence ) {
				if( !$licence->matches( $template ) ) {
					continue;
				}

				$url = $licence->isInGroup( 'ported' ) ? $this->portedUrl( $template ) : null;
				$detected[] = $url !== null ? $licence->asKnownPorted( $template, $url ) : $licence;
			}
		}

		if( $detected === [] ) {
			return null;
		}

		if( count( $detected ) === 1 ) {
			return $detected[ 0 ];
		}

		foreach( $this->licences as $catalogueLicence ) {
			foreach( $detected as $found ) {
				if( $catalogueLicence->id() === $found->id() ) {
					return $found;
				}
			}
		}

		return null;
	}

	private function portedUrl( string $template ): ?string
	{
		$url = $this->portedLicences[ strtolower( $template ) ] ?? null;

		return is_string( $url ) && $url !== '' ? $url : null;
	}
}
