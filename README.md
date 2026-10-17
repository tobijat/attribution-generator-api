# Attribution Generator

PHP API that builds attribution lines for files from Wikipedia and Wikimedia Commons. Pass a file link and the questionnaire answers; get the text, plain text and HTML attribution back.

## Install

```bash
composer require tobijat/attribution-generator-api
```

If the package is not on Packagist, point Composer at this repository:

```json
{
	"repositories": [
		{ "type": "vcs", "url": "https://github.com/tobijat/Lizenzhinweisgenerator.git" }
	],
	"require": {
		"tobijat/attribution-generator-api": "dev-master"
	},
	"minimum-stability": "dev"
}
```

## Library

```php
use AttributionGenerator\AttributionGenerator;
use AttributionGenerator\Questionnaire;

$attribution = AttributionGenerator::create()->generate(
	'https://commons.wikimedia.org/wiki/File:Helene_Fischer_2010.jpg',
	Questionnaire::fromArray( [
		'type_of_use' => 'online', // or "print"
		'author' => null,          // only when the file page has no author
		'author_unknown' => false,
		'edited' => true,
		'change' => 'cropped',
		'creator' => 'Jane Doe',
		'licence' => 'cc-by-sa-4.0', // or "original"
		'language' => 'de',          // de, en, es, pl, pt, uk
	] ),
);

$attribution->text;
$attribution->plainText;
$attribution->html;
```

`type_of_use` is required. `author` or `author_unknown` is required only when the file page does not name an author. `change`, `creator` and `licence` apply when `edited` is true. `licence` is a licence id, or `original` to keep the file's licence.

For online use, `text` is the linked attribution (with `target="_blank"`), `plainText` writes the URLs out, and `html` is the same markup as `text` without `target="_blank"`. For print use, all three are the print attribution.

Supported licences live in `data/licences.json`.

## HTTP

`public/index.php` exposes the same call as JSON. After `composer install`:

```bash
php -S localhost:8080 -t public
```

When the package is installed in another project, point the web server at `vendor/tobijat/attribution-generator-api/public`.

```bash
curl -s -X POST http://localhost:8080 \
	-H 'Content-Type: application/json' \
	-d '{"image":"https://commons.wikimedia.org/wiki/File:Helene_Fischer_2010.jpg","type_of_use":"online"}'
```

```json
{
	"text": "<a href=\"...\">...</a>",
	"plain_text": "...",
	"html": "<a href=\"...\">...</a>"
}
```

The same fields can be sent as a form body or as a query string on GET. Errors are JSON objects with `error.code` and `error.message`.

## Development

```bash
composer install
vendor/bin/phpunit
```
