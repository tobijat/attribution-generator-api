<?php

declare(strict_types=1);

namespace AttributionGenerator\Wikimedia;

use AttributionGenerator\Licence\Licence;

final class Asset
{
	public function __construct(
		public readonly string $filename,
		public readonly string $title,
		public readonly string $url,
		public readonly ?Licence $licence,
		public readonly HtmlFragment $authors,
		public readonly ?HtmlFragment $attribution,
	) {
	}
}
