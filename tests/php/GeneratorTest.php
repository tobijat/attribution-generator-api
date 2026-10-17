<?php

declare(strict_types=1);

namespace AttributionGenerator\Tests;

use AttributionGenerator\AttributionGenerator;
use AttributionGenerator\Http\FrontController;
use AttributionGenerator\Http\HttpClient;
use AttributionGenerator\Http\Request;
use AttributionGenerator\Questionnaire;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase
{
	public function testBuildsTheThreeAttributionsFromAFilePage(): void
	{
		$attribution = AttributionGenerator::create( new HeleneFischerClient() )->generate(
			'https://commons.wikimedia.org/wiki/File:Helene_Fischer_2010.jpg',
			Questionnaire::fromArray( [ 'type_of_use' => 'online' ] ),
		);

		self::assertSame(
			'<a href="https://commons.wikimedia.org/wiki/User:Fleyx24" target="_blank">Fleyx24</a>, '
			. '<a href="https://commons.wikimedia.org/wiki/File:Helene Fischer 2010.jpg" target="_blank">Helene Fischer 2010</a>, '
			. '<a href="https://creativecommons.org/licenses/by-sa/3.0/legalcode" rel="license" target="_blank">CC BY-SA 3.0</a>',
			$attribution->text,
		);
		self::assertSame(
			'Fleyx24 (https://commons.wikimedia.org/wiki/File:Helene Fischer 2010.jpg), „Helene Fischer 2010“, https://creativecommons.org/licenses/by-sa/3.0/legalcode',
			$attribution->plainText,
		);
		self::assertSame(
			'<a href="https://commons.wikimedia.org/wiki/User:Fleyx24">Fleyx24</a>, '
			. '<a href="https://commons.wikimedia.org/wiki/File:Helene Fischer 2010.jpg">Helene Fischer 2010</a>, '
			. '<a href="https://creativecommons.org/licenses/by-sa/3.0/legalcode" rel="license">CC BY-SA 3.0</a>',
			$attribution->html,
		);
	}

	public function testHttpApiReturnsTheThreeVersions(): void
	{
		$controller = new FrontController( AttributionGenerator::create( new HeleneFischerClient() ) );
		$response = $controller->handle( new Request(
			'POST',
			json_encode( [
				'image' => 'https://commons.wikimedia.org/wiki/File:Helene_Fischer_2010.jpg',
				'type_of_use' => 'print',
				'edited' => false,
				'compilation' => false,
			], JSON_THROW_ON_ERROR ),
			[],
			[],
			'application/json',
		) );

		self::assertSame( 200, $response->status );
		self::assertSame(
			'Fleyx24 (https://commons.wikimedia.org/wiki/File:Helene Fischer 2010.jpg), „Helene Fischer 2010“, https://creativecommons.org/licenses/by-sa/3.0/legalcode',
			$response->body[ 'text' ],
		);
		self::assertSame( $response->body[ 'text' ], $response->body[ 'plain_text' ] );
		self::assertSame( $response->body[ 'text' ], $response->body[ 'html' ] );
	}

	public function testHttpApiReportsMissingInput(): void
	{
		$controller = new FrontController( AttributionGenerator::create( new HeleneFischerClient() ) );
		$response = $controller->handle( new Request( 'POST', '{}', [], [], 'application/json' ) );

		self::assertSame( 400, $response->status );
		self::assertSame( 'invalid-input', $response->body[ 'error' ][ 'code' ] );
	}
}

final class HeleneFischerClient implements HttpClient
{
	public function getJson( string $url, array $query ): array
	{
		$revision = json_decode( (string)file_get_contents( __DIR__ . '/fixtures/helene-fischer-revision.json' ), true );
		$templates = json_decode( (string)file_get_contents( __DIR__ . '/fixtures/helene-fischer-templates.json' ), true );

		return [
			'query' => [
				'pages' => [
					'15382769' => [
						'pageid' => 15382769,
						'ns' => 6,
						'title' => 'File:Helene Fischer 2010.jpg',
						'imagerepository' => 'local',
						'imageinfo' => [ [
							'mediatype' => 'BITMAP',
							'url' => 'https://upload.wikimedia.org/wikipedia/commons/4/4a/Helene_Fischer_2010.jpg',
						] ],
						'revisions' => $revision[ 'revisions' ],
						'templates' => $templates[ 'templates' ],
					],
				],
			],
		];
	}
}
