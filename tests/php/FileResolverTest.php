<?php

declare(strict_types=1);

namespace AttributionGenerator\Tests;

use AttributionGenerator\Exception\AttributionException;
use AttributionGenerator\Wikimedia\FileResolver;
use PHPUnit\Framework\TestCase;

final class FileResolverTest extends TestCase
{
	private FileResolver $resolver;

	protected function setUp(): void
	{
		$this->resolver = new FileResolver();
	}

	public function testReadsACommonsFilePage(): void
	{
		$location = $this->resolver->resolve( 'https://commons.wikimedia.org/wiki/File:Helene_Fischer_2010.jpg' );

		self::assertSame( 'https://commons.wikimedia.org/', $location->wikiUrl );
		self::assertSame( 'File:Helene_Fischer_2010.jpg', $location->title );
	}

	public function testReadsADirectUploadUrl(): void
	{
		$location = $this->resolver->resolve( 'https://upload.wikimedia.org/wikipedia/commons/4/4a/Helene_Fischer_2010.jpg' );

		self::assertSame( 'https://commons.wikimedia.org/', $location->wikiUrl );
		self::assertSame( 'File:Helene_Fischer_2010.jpg', $location->title );
	}

	public function testReadsAThumbnailUrl(): void
	{
		$location = $this->resolver->resolve( 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/4a/Helene_Fischer_2010.jpg/120px-Helene_Fischer_2010.jpg' );

		self::assertSame( 'File:Helene_Fischer_2010.jpg', $location->title );
	}

	public function testKeepsTheLocalFileNamespace(): void
	{
		$location = $this->resolver->resolve( 'https://de.wikipedia.org/wiki/Datei:Beispiel.jpg' );

		self::assertSame( 'https://de.wikipedia.org/', $location->wikiUrl );
		self::assertSame( 'Datei:Beispiel.jpg', $location->title );
	}

	public function testReadsAPageId(): void
	{
		$location = $this->resolver->resolve( 'https://commons.wikimedia.org/w/index.php?curid=15382769' );

		self::assertNull( $location->title );
		self::assertSame( '15382769', $location->pageId );
	}

	public function testRejectsANonWikimediaLink(): void
	{
		$this->expectException( AttributionException::class );
		$this->resolver->resolve( 'https://example.com/photo.jpg' );
	}
}
