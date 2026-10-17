<?php

declare(strict_types=1);

namespace AttributionGenerator\Http;

use AttributionGenerator\AttributionGenerator;
use AttributionGenerator\Exception\AttributionException;
use AttributionGenerator\Questionnaire;

/**
 * JSON endpoint for the attribution generator.
 *
 * POST a JSON object with image, type_of_use and the optional questionnaire
 * fields. The response contains text, plain_text and html.
 */
final class FrontController
{
	public function __construct( private readonly AttributionGenerator $generator )
	{
	}

	public static function create(): self
	{
		return new self( AttributionGenerator::create() );
	}

	public function handle( ?Request $request = null ): Response
	{
		$request ??= Request::fromGlobals();

		try {
			$input = $request->input();
			$image = $input[ 'image' ] ?? $input[ 'url' ] ?? $input[ 'link' ] ?? null;
			if( !is_string( $image ) || trim( $image ) === '' ) {
				throw new AttributionException( 'invalid-input', 'image is required and must be a Wikimedia file link.' );
			}
			unset( $input[ 'image' ], $input[ 'url' ], $input[ 'link' ] );

			return Response::json( 200, $this->generator->generate( $image, Questionnaire::fromArray( $input ) )->toArray() );
		} catch( AttributionException $exception ) {
			return Response::json( $exception->statusCode(), [
				'error' => [
					'code' => $exception->errorCode(),
					'message' => $exception->getMessage(),
				],
			] );
		} catch( \JsonException ) {
			return Response::json( 400, [
				'error' => [
					'code' => 'invalid-json',
					'message' => 'Request body is not valid JSON.',
				],
			] );
		}
	}
}
