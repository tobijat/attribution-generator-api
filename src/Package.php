<?php

declare(strict_types=1);

namespace AttributionGenerator;

final class Package
{
	public static function path( string $relative ): string
	{
		return dirname( __DIR__ ) . '/' . ltrim( $relative, '/' );
	}
}
