<p align="center">
    <img src=".github/header.svg" alt="artemeon/confluence" width="100%">
</p>

<p align="center">
    <a href="https://packagist.org/packages/artemeon/confluence"><img src="https://img.shields.io/packagist/v/artemeon/confluence?style=for-the-badge" alt="Packagist Version"></a>
    <a href="https://packagist.org/packages/artemeon/confluence"><img src="https://img.shields.io/packagist/dependency-v/artemeon/confluence/php?style=for-the-badge" alt="PHP Version"></a>
    <a href="https://github.com/artemeon/confluence/actions/workflows/phpunit.yml"><img src="https://img.shields.io/github/actions/workflow/status/artemeon/confluence/phpunit.yml?branch=main&style=for-the-badge&label=tests" alt="Tests"></a>
    <a href="https://github.com/artemeon/confluence/actions/workflows/phpstan.yml"><img src="https://img.shields.io/github/actions/workflow/status/artemeon/confluence/phpstan.yml?branch=main&style=for-the-badge&label=phpstan" alt="PHPStan"></a>
    <a href="phpstan.neon"><img src="https://img.shields.io/badge/PHPStan-level%208-brightgreen?style=for-the-badge" alt="PHPStan Level 8"></a>
    <a href="LICENSE"><img src="https://img.shields.io/packagist/l/artemeon/confluence?style=for-the-badge" alt="License"></a>
</p>

A small PHP client for the Atlassian Confluence Cloud REST API. It lists the pages of a space, fetches single pages with their labels, and downloads a page as a local `content.html` together with its attachments.

On the way to disk, Confluence storage-format markup (`<ac:…>` macros) is turned into plain HTML by a chain of macro replacers you put together yourself. Panels become `<div>`s, images and videos become `<img>`/`<video>` tags pointing at the downloaded attachments, and everything else is stripped.

## Requirements

- PHP 8.2 or higher
- `ext-json` and `ext-mbstring`
- A Confluence Cloud site, plus an Atlassian account email and [API token](https://id.atlassian.com/manage-profile/security/api-tokens)

## Installation

```shell
composer require artemeon/confluence
```

## Quick Start

```php
use Artemeon\Confluence\ConfluencePageContentDownloader;
use Artemeon\Confluence\Endpoint\Auth;
use Artemeon\Confluence\Endpoint\Content;
use Artemeon\Confluence\Endpoint\Download;
use Artemeon\Confluence\MacroReplacer\AdfPanelReplacer;
use Artemeon\Confluence\MacroReplacer\GenericPanelReplacer;
use Artemeon\Confluence\MacroReplacer\ImageAndVideoMacroReplacer;
use Artemeon\Confluence\MacroReplacer\LayoutSectionMacroReplacer;
use Artemeon\Confluence\MacroReplacer\LinkMacroReplacer;
use Artemeon\Confluence\MacroReplacer\OtherMacroRemover;
use Artemeon\Confluence\MacroReplacer\StructuredMacroReplacer;
use GuzzleHttp\Client;

// The base URI must end with a slash: all endpoint paths are relative ("wiki/rest/api/...").
$client = new Client(['base_uri' => 'https://your-site.atlassian.net/']);
$auth = new Auth('you@example.com', 'your-api-token');

$content = new Content($client, $auth);
$page = $content->findPageContent('123456789');

$downloader = new ConfluencePageContentDownloader(
    $content,
    new Download($client, $auth, __DIR__ . '/export/' . $page->getId()),
    [
        new StructuredMacroReplacer(),
        new AdfPanelReplacer(),
        new GenericPanelReplacer(),
        new LayoutSectionMacroReplacer(),
        new ImageAndVideoMacroReplacer(),
        new LinkMacroReplacer(),
        new OtherMacroRemover(), // always last
    ],
);

$downloader->downloadPageContent($page);
// export/123456789/content.html + every attachment of the page
```

## Authentication

`Auth` holds the credentials and sends them as HTTP Basic auth with every request:

```php
$auth = new Auth('you@example.com', 'your-api-token');
```

The same `Auth` instance is passed to both endpoints. Attachments are downloaded through the REST API (`/wiki/rest/api/content/{id}/child/attachment/{attachmentId}/download`), because the legacy `/wiki/download` servlet rejects API-token auth.

## Reading Content

`Content` wraps the Confluence Content API.

### All pages of a space

```php
$pages = $content->findPagesInSpace('DOCS');

foreach ($pages as $id => $page) {
    echo $page->getTitle(), ' (last updated ', $page->getLastUpdated()?->format('Y-m-d'), ")\n";
}
```

Pages are fetched in batches of 200 until the space is exhausted or `$limit` (default `2000`) is reached. The result is keyed by page ID, and every page already includes its storage-format body, labels and last-updated date. Pass `$offset` to start further into the space:

```php
$pages = $content->findPagesInSpace('DOCS', limit: 500, offset: 200);
```

### A single page

```php
$page = $content->findPageContent('123456789');
```

This expands the body, version, space and labels.

### Attachments of a page

```php
foreach ($content->findChildAttachments('123456789') as $attachment) {
    echo $attachment->getTitle(), ' – ', $attachment->getLastUpdated()?->format(DATE_ATOM), "\n";
}
```

### The page object

`ConfluencePage` exposes the raw API data through typed getters:

| Method              | Returns                                                          |
|---------------------|------------------------------------------------------------------|
| `getId()`           | `?string`, the content ID                                        |
| `getTitle()`        | `?string`                                                        |
| `getType()`         | `?string`, e.g. `page`                                           |
| `getStatus()`       | `?string`, e.g. `current`                                        |
| `getContent()`      | `string`, the storage-format body (or whatever `setContent()` set) |
| `getLabels()`       | `list<ConfluenceLabel>` with `getId()`, `getName()`, `getPrefix()`, `getLabel()` |
| `getLastUpdated()`  | `?DateTime` (filled by `findPagesInSpace()`)                     |
| `getSpace()`, `getVersion()`, `getBody()`, `getMetadata()` | the matching raw arrays or `null` |
| `getRawData()`      | the full decoded API response                                    |

## Downloading Pages

`ConfluencePageContentDownloader::downloadPageContent()` does three things:

1. Runs the page content through every macro replacer, in the order you passed them.
2. Repairs the resulting markup with `DOMDocument` (no network access, `LIBXML_NONET`) and keeps only the inner HTML of `<body>`.
3. Writes it to `content.html` in the download folder and, unless `$withAttachments` is `false`, downloads all attachments next to it.

```php
$downloader->downloadPageContent($page);        // HTML + attachments
$downloader->downloadPageContent($page, false); // HTML only
```

A few things to know:

- **One folder per page.** `Download` writes into the folder given to its constructor, and every page lands as `content.html`. To export several pages, create a `Download` (and downloader) per page folder. Otherwise each page overwrites the previous one.
- **The folder is created on demand** (recursively, mode `0755`).
- **Attachments are only re-downloaded when they changed.** A file is skipped if a local copy exists and its modification time is not older than the attachment's last-updated date in Confluence.
- **Errors are logged, not thrown.** Any exception during the download is caught and passed to the PSR-3 logger given as the fourth constructor argument (default: `NullLogger`):

```php
$downloader = new ConfluencePageContentDownloader($content, $download, $replacers, $logger);
```

## Macro Replacers

Every replacer implements `MacroReplacerInterface` and turns one kind of storage-format markup into HTML:

| Replacer                       | Input                                                     | Output                                                                 |
|--------------------------------|-----------------------------------------------------------|------------------------------------------------------------------------|
| `StructuredMacroReplacer`      | `info`, `note`, `warning`, `error`, `success` macros      | `<div class="documentation-panel-{name}"><div>…</div></div>`           |
| `AdfPanelReplacer`             | `<ac:adf-node type="panel">` (new editor panels)          | `<div class="documentation-panel-{panel-type}"><div>…</div></div>`; also unwraps `<ac:adf-extension>` |
| `GenericPanelReplacer`         | `panel` macro (custom panels)                             | `<div class="documentation-panel-custom" panel-color="…" panel-icon="…">` |
| `LayoutSectionMacroReplacer`   | `<ac:layout-section>` / `<ac:layout-cell>`                | `<div data-macro-type="{type}">` with one `<div>` per cell             |
| `ImageAndVideoMacroReplacer`   | `<ac:image>` referencing an attachment                    | `<img>` (in a `<figure>` when captioned) for jpg/jpeg/png/gif, `<video>` for mp4/avi/mkv/mov, a plain link otherwise |
| `LinkMacroReplacer`            | `<ac:link>`                                               | The link text only (link body, plain-text body, or the target's title) |
| `OtherMacroRemover`            | Remaining `<ac:…>…</ac:…>` elements and stray closing tags | Removed, including their content (self-closing macros like `<ac:structured-macro … />` are left as is) |

The output classes (`documentation-panel-*`) are meant to be styled by whatever renders the exported HTML.

### Order matters

Replacers run in array order, each on the result of the previous one:

- `OtherMacroRemover` deletes **every** remaining macro together with its content, so it must come last.
- `LinkMacroReplacer` must run before `OtherMacroRemover`, otherwise links vanish along with their text.

### Media paths

`ImageAndVideoMacroReplacer` builds `src` attributes from the attachment file name. Pass a folder prefix if the HTML will be served from somewhere other than the attachment folder:

```php
new ImageAndVideoMacroReplacer('/media/123456789'); // <img src="/media/123456789/diagram.png">
```

Without an argument, the file name is used as is, which matches the default layout where attachments sit next to `content.html`.

### Writing your own

```php
use Artemeon\Confluence\MacroReplacer\MacroReplacerInterface;

final class CodeBlockReplacer implements MacroReplacerInterface
{
    public function replace(string $haystack): string
    {
        return preg_replace_callback(
            '/<ac:structured-macro\s+ac:name="code"[^>]*>.*?<ac:plain-text-body><!\[CDATA\[(.*?)]]><\/ac:plain-text-body>.*?<\/ac:structured-macro>/is',
            fn (array $match) => '<pre><code>' . htmlspecialchars($match[1]) . '</code></pre>',
            $haystack,
        ) ?? $haystack;
    }
}
```

Add it to the replacer array before `OtherMacroRemover`.

## Development

```shell
composer install
composer test     # PHPUnit
composer phpstan  # PHPStan, level 8
```

CI runs PHPUnit against PHP 8.2, 8.3, 8.4 and 8.5, plus PHPStan, on every pull request.

## License

`artemeon/confluence` is open-source software licensed under the [MIT license](LICENSE).
