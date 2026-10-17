<?php

declare(strict_types=1);

namespace AttributionGenerator\Http;

use AttributionGenerator\Exception\AttributionException;

final class Request
{
	/**
	 * @param array<string, mixed> $query
	 * @param array<string, mixed> $post
	 */
	public function __construct(
		private readonly string $method,
		private readonly ?string $rawBody,
		private readonly array $query,
		private readonly array $post,
		private readonly string $contentType,
	) {
	}

	public static function fromGlobals(): self
	{
		$body = file_get_contents( 'php://input' );

		return new self(
			$_SERVER[ 'REQUEST_METHOD' ] ?? 'GET',
			is_string( $body ) ? $body : null,
			$_GET,
			$_POST,
			$_SERVER[ 'CONTENT_TYPE' ] ?? $_SERVER[ 'HTTP_CONTENT_TYPE' ] ?? '',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function input(): array
	{
		if( strtoupper( $this->method ) === 'GET' ) {
			return $this->query;
		}

		if( str_contains( strtolower( $this->contentType ), 'application/json' ) || $this->looksLikeJson() ) {
			if( $this->rawBody === null || trim( $this->rawBody ) === '' ) {
				throw new AttributionException( 'invalid-input', 'Request body is empty.' );
			}
			$decoded = json_decode( $this->rawBody, true, 512, JSON_THROW_ON_ERROR );
			if( !is_array( $decoded ) ) {
				throw new AttributionException( 'invalid-input', 'JSON body must be an object.' );
			}

			return $decoded;
		}

		if( $this->post !== [] ) {
			return $this->post;
		}

		return $this->query;
	}

	private function looksLikeJson(): bool
	{
		$body = ltrim( (string)$this->rawBody );

		return str_starts_with( $body, '{' );
	}
}
