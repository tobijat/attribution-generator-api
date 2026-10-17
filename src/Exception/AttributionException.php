<?php

declare(strict_types=1);

namespace AttributionGenerator\Exception;

final class AttributionException extends \RuntimeException
{
	public function __construct(
		private readonly string $errorCode,
		string $message,
		private readonly int $statusCode = 400,
	) {
		parent::__construct( $message );
	}

	public function errorCode(): string
	{
		return $this->errorCode;
	}

	public function statusCode(): int
	{
		return $this->statusCode;
	}
}
