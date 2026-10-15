# silverstripe-seo

SEO, OpenGraph and LLM crawler support for Silverstripe CMS 6.

This module replaces `vulcandigital/silverstripe-seo` (the `ss6.0` fork) under the `Moosylvania\SEO` namespace. Every database field keeps its name, so existing SEO data carries over.

## Install

```sh
composer require moosylvania/silverstripe-seo
vendor/bin/sake db:build --flush
```

### Migrating from vulcandigital/silverstripe-seo

1. Run `composer remove vulcandigital/silverstripe-seo`, then `composer require moosylvania/silverstripe-seo`.
2. Replace any `QuinnInteractive\Seo\...` references in your code or YAML with `Moosylvania\SEO\...`.
3. Run a dev/build.

These parts of the old module are gone:

- `TrailingSlashRedirector`. The module sets `Controller.add_trailing_slash: true`, and core's `CanonicalURLMiddleware` does the redirect.
- `MemberExtension`, along with the `twitter:creator` tag and the `enable_creator_tag` config. The `Member.TwitterAccountName` column stays in the database until you drop it.
- The bundled CSS and JS.
- The `jonom/silverstripe-text-target-length` dependency and its character counters on the CMS fields.

## What it outputs

`$MetaTags` on every `Page` adds:

- `<link rel="canonical">`
- OpenGraph tags (`og:title`, `og:description`, `og:type`, `og:url`, `og:image`, image size, `og:locale`, `og:site_name`)
- Twitter/X card tags. The card is `summary_large_image` when the page has a share image and `summary` when it doesn't.
- `article:published_time` and `article:modified_time`
- `<link rel="alternate" type="text/markdown">` pointing at the page's `index.md`
- `<link rel="describedby">` pointing at `/llms.txt`
- `<meta name="robots" content="noindex">` when "Show in search" is unticked
- The site's Google Analytics and pixel snippets, and the page's structured data

The `<title>` uses the page's Meta Title if one is set. Otherwise it appends the site's Title Tag Ending.

Share images come from the first one set, in this order: `CustomShareImage` (a URL property your page class can provide), the page's Facebook or Twitter image, then the site's Default Share Image.

## LLM endpoints

These follow the [llms.txt proposal](https://llmstxt.org/).

### /llms.txt

This returns the "llms.txt" field from Settings > SEO > LLMs. When that field is empty, it returns the site title as an H1 and the tagline as a blockquote.

With "Append a list of all pages" ticked, it adds the links from `sitemap.xml`. The list comes from `wilr/silverstripe-googlesitemaps`, so the sitemap's filters, registered DataObjects and registered routes all apply. Pages link to their `index.md`. Other objects link to their `AbsoluteLink()`.

How the links appear depends on how many sections the sitemap has:

- **One section** (pages only, under `objects_per_sitemap`, which defaults to 1000). The links go inline under `## Pages`, one per line, like `- [Meta title](https://example.com/page/index.md): Meta description`.
- **More than one section** (registered DataObjects, or more pages than `objects_per_sitemap`). `llms.txt` lists the sections under `## Sitemaps`, the same way the `sitemap.xml` index does:

  ```markdown
  ## Sitemaps

  - [Pages](https://example.com/llms.txt/sitemap/SilverStripe-CMS-Model-SiteTree/1/): Updated 2026-10-08
  - [Products](https://example.com/llms.txt/sitemap/App-Model-Product/1/): Updated 2026-10-15
  - [Products, page 2](https://example.com/llms.txt/sitemap/App-Model-Product/2/): Updated 2026-10-15
  ```

  Each section URL returns that slice as a markdown link list. Section titles use the class's plural name. Fluent sites get one section per locale, and the locale is added as a last URL segment, as in `sitemap.xml`. Class names that aren't in the sitemap return 404.

The index and each section are cached separately for an hour in the `MoosylvaniaSEO` core cache namespace, the same interval as the sitemap module's `regenerate_time`. Publishing, unpublishing or archiving a page clears the whole cache. Changes to registered DataObjects show up once the hour is up. Edits to the Site Config text show up straight away because that part isn't cached. To change the lifetime:

```yaml
SilverStripe\Core\Injector\Injector:
  Psr\SimpleCache\CacheInterface.MoosylvaniaSEO:
    constructor:
      defaultLifetime: 86400
```

### /llms-full.txt

This returns the "llms-full.txt" field, or the same output as `/llms.txt` when that field is empty. The module never builds it from every page, because that takes too long on large sites.

### {page}/index.md

Add `index.md` to any page URL to get the page as markdown. `/index.md` returns the home page, and `/about-us.md` works too.

The output is the page's "LLMs markdown" field (under Metadata) if it's filled in. Otherwise the module converts the page to markdown, starting with:

```markdown
# Meta title (or page title)

Meta description

...the page's $Layout template converted to markdown...
```

The conversion renders the page's `templates/Layout` template, so elemental blocks are included. It drops `script`, `style`, `noscript`, `form`, `iframe`, `svg`, `nav` and `button` elements first. You can change that list:

```yaml
Moosylvania\SEO\Extensions\MarkdownControllerExtension:
  remove_nodes: 'script style noscript form iframe svg nav button aside'
```

Markdown responses send a `Link: <page URL>; rel="canonical"` header so search engines index the HTML version. HTML page responses send `Link` headers pointing at the markdown and `/llms.txt`. Pages with "Show in search" unticked return 404 at `index.md`.

## Fields

| Model | Field | Type |
| --- | --- | --- |
| Page | MetaTitle | Varchar(255) |
| Page | StructuredData | HTMLText |
| Page | FacebookPageType | Varchar(50) |
| Page | FacebookPageTitle | Varchar(255) |
| Page | FacebookPageDescription | Text |
| Page | FacebookPageImage | has_one Image |
| Page | TwitterPageTitle | Varchar(255) |
| Page | TwitterPageDescription | Text |
| Page | TwitterPageImage | has_one Image |
| Page | Creator | has_one Member |
| Page | LLMsMarkdown | Text (new) |
| SiteConfig | TitleTagEnding | Varchar(255) |
| SiteConfig | DefaultShareImage | has_one Image |
| SiteConfig | TwitterAccountName | Varchar(80) |
| SiteConfig | GoogleAnalytics | Text |
| SiteConfig | FacebookPixel, TwitterPixel, SnapPixel | Text |
| SiteConfig | LLMsTxt | Text (new) |
| SiteConfig | LLMsFullTxt | Text (new) |
| SiteConfig | LLMsIncludeSitemap | Boolean (new) |

## Tests

```sh
vendor/bin/phpunit vendor/moosylvania/silverstripe-seo/tests
```
