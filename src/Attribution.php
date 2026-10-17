<?php

declare(strict_types=1);

namespace AttributionGenerator;

/**
 * The three attribution variants produced by the generator.
 *
 * For online use, text and html are HTML snippets. text includes target="_blank"
 * on generated links, which is what the "Text" view renders. html is the same
 * markup without those target attributes, which is what the "HTML" view copies.
 * plainText is the "Plain Text" view, with URLs written out.
 * For print use, all three values are the print attribution.
 */
final class Attribution
{
	public function __construct(
		public readonly string $text,
		public readonly string $plainText,
		public readonly string $html,
	) {
	}

	/**
	 * @return array{text: string, plain_text: string, html: string}
	 */
	public function toArray(): array
	{
		return [
			'text' => $this->text,
			'plain_text' => $this->plainText,
			'html' => $this->html,
		];
	}
}
