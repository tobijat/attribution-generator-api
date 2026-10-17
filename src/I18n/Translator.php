<?php

declare(strict_types=1);

namespace AttributionGenerator\I18n;

final class Translator
{
	/**
	 * Phrases that are inserted into the attribution line. Keys match the web app.
	 *
	 * @var array<string, array<string, string>>
	 */
	private const PHRASES = [
		'de' => [
			'evaluation.anonymous' => 'anonym',
			'evaluation.edited' => 'bearbeitet',
			'evaluation.by' => 'von',
			'dialogue.pd-attribution-hint' => 'als gemeinfrei gekennzeichnet',
			'dialogue.check-details' => 'Details auf',
		],
		'en' => [
			'evaluation.anonymous' => 'anonymous',
			'evaluation.edited' => 'modified',
			'evaluation.by' => 'by',
			'dialogue.pd-attribution-hint' => 'marked as public domain',
			'dialogue.check-details' => 'more details on',
		],
		'es' => [
			'evaluation.anonymous' => 'anónimo',
			'evaluation.edited' => 'modificado',
			'evaluation.by' => 'por',
			'dialogue.pd-attribution-hint' => 'marcado como dominio público',
			'dialogue.check-details' => 'para más detalles véase',
		],
		'pl' => [
			'evaluation.anonymous' => 'anonimowy',
			'evaluation.edited' => 'edytowany',
			'evaluation.by' => 'przez',
			'dialogue.pd-attribution-hint' => 'oznaczony jako w domenie publicznej',
			'dialogue.check-details' => 'więcej szczegółów na',
		],
		'pt' => [
			'evaluation.anonymous' => 'anónimo',
			'evaluation.edited' => 'modificado',
			'evaluation.by' => 'por',
			'dialogue.pd-attribution-hint' => 'marcado como domínio público',
			'dialogue.check-details' => 'para mais detalhes, veja',
		],
		'uk' => [
			'evaluation.anonymous' => 'анонім',
			'evaluation.edited' => 'модифіковано',
			'evaluation.by' => 'автор',
			'dialogue.pd-attribution-hint' => 'позначено як суспільне надбання',
			'dialogue.check-details' => 'більше деталей на',
		],
	];

	public function __construct( private readonly string $language = 'de' )
	{
	}

	public static function normalize( ?string $language ): string
	{
		$language = strtolower( str_replace( '_', '-', (string)$language ) );
		$primary = explode( '-', $language )[ 0 ];

		return isset( self::PHRASES[ $primary ] ) ? $primary : 'de';
	}

	public function t( string $key ): string
	{
		$phrases = self::PHRASES[ $this->language ] ?? self::PHRASES[ 'de' ];

		return $phrases[ $key ] ?? self::PHRASES[ 'de' ][ $key ] ?? $key;
	}
}
