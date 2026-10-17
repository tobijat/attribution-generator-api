<?php

declare(strict_types=1);

namespace AttributionGenerator\Http;

final class Response
{
	/**
	 * @param array<string, mixed> $body
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $body,
	) {
	}

	/**
	 * @param array<string, mixed> $body
	 */
	public static function json( int $status, array $body ): self
	{
		return new self( $status, $body );
	}

	public function send(): void
	{
		http_response_code( $this->status );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo json_encode( $this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
	}
}
