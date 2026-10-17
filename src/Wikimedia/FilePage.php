<?php

declare(strict_types=1);

namespace AttributionGenerator\Wikimedia;

final class FilePage
{
	/**
	 * @param list<string> $templates
	 */
	public function __construct(
		public readonly string $title,
		public readonly ?string $mediaType,
		public readonly ?string $html,
		public readonly array $templates,
	) {
	}
}
