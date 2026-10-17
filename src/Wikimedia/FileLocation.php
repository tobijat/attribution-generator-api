<?php

declare(strict_types=1);

namespace AttributionGenerator\Wikimedia;

final class FileLocation
{
	public function __construct(
		public readonly string $wikiUrl,
		public readonly ?string $title,
		public readonly ?string $pageId = null,
	) {
	}
}
