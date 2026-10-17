<?php

declare(strict_types=1);

namespace AttributionGenerator\Licence;

final class Licence
{
	/**
	 * @param list<string> $groups
	 * @param list<string> $compatible
	 */
	public function __construct(
		private readonly string $id,
		private readonly array $groups,
		private readonly array $compatible,
		private readonly ?string $name,
		private readonly string $pattern,
		private readonly bool $caseInsensitive,
		private readonly ?string $url,
	) {
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public static function fromArray( array $data ): self
	{
		return new self(
			(string)$data[ 'id' ],
			array_values( $data[ 'groups' ] ),
			array_values( $data[ 'compatible' ] ),
			isset( $data[ 'name' ] ) ? (string)$data[ 'name' ] : null,
			(string)$data[ 'pattern' ],
			(bool)$data[ 'caseInsensitive' ],
			isset( $data[ 'url' ] ) && $data[ 'url' ] !== '' ? (string)$data[ 'url' ] : null,
		);
	}

	public function id(): string
	{
		return $this->id;
	}

	public function name(): string
	{
		return $this->name ?? '';
	}

	public function url(): ?string
	{
		return $this->url;
	}

	/**
	 * @return list<string>
	 */
	public function groups(): array
	{
		return $this->groups;
	}

	public function isInGroup( string $group ): bool
	{
		return in_array( $group, $this->groups, true );
	}

	public function isPublicDomain(): bool
	{
		return $this->isInGroup( 'pd' );
	}

	public function matches( string $value ): bool
	{
		$pattern = str_replace( '~', '\~', $this->pattern );
		$modifiers = $this->caseInsensitive ? 'iu' : 'u';
		$result = @preg_match( '~' . $pattern . '~' . $modifiers, $value );

		if( $result === false ) {
			$modifiers = $this->caseInsensitive ? 'i' : '';
			$result = @preg_match( '~' . $pattern . '~' . $modifiers, $value );
		}

		return $result === 1;
	}

	public function asKnownPorted( string $template, string $url ): self
	{
		$name = str_replace( '-', ' ', strtoupper( $template ) );
		$name = preg_replace( '/BY SA/', 'BY-SA', $name, 1 ) ?? $name;

		return new self(
			$this->id,
			array_merge( $this->groups, [ 'knownPorted' ] ),
			[],
			$name,
			$this->pattern,
			$this->caseInsensitive,
			$url,
		);
	}
}
