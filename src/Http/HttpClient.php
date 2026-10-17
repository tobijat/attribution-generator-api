<?php

declare(strict_types=1);

namespace AttributionGenerator\Http;

interface HttpClient
{
	/**
	 * @param array<string, scalar|null> $query
	 * @return array<string, mixed>
	 */
	public function getJson( string $url, array $query ): array;
}
