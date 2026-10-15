<?php

namespace Moosylvania\SEO\Controllers;

use Moosylvania\SEO\Extensions\SiteConfigSettingsExtension;
use Moosylvania\SEO\SEO;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\i18n\i18n;
use SilverStripe\Model\ModelData;
use SilverStripe\SiteConfig\SiteConfig;
use Wilr\GoogleSitemaps\GoogleSitemap;

/**
 * Serves /llms.txt and /llms-full.txt, see https://llmstxt.org/
 *
 * The page list mirrors sitemap.xml: one section per sitemap class and slice, served at
 * /llms.txt/sitemap/{Class}/{Page}/{Locale}, so large sites never list every link in one file.
 */
class LLMsTxtController extends Controller
{
    public const string CACHE_SERVICE = CacheInterface::class . '.MoosylvaniaSEO';

    private const string ROUTE_CLASS = 'GoogleSitemapRoute';

    private static array $url_handlers = [
        'sitemap/$ClassName/$Page/$Locale' => 'sitemap',
    ];

    private static array $allowed_actions = [
        'sitemap',
    ];

    /**
     * Drops every cached sitemap section, called whenever a page is published or unpublished
     */
    public static function clearCache(): void
    {
        Injector::inst()->get(static::CACHE_SERVICE)->clear();
    }

    public function index(HTTPRequest $request): HTTPResponse
    {
        /** @var SiteConfig|SiteConfigSettingsExtension $siteConfig */
        $siteConfig = SiteConfig::current_site_config();
        $isFull = pathinfo($request->getURL(), PATHINFO_FILENAME) === 'llms-full';

        return $this->textResponse(
            $isFull && $siteConfig->LLMsFullTxt ? $siteConfig->LLMsFullTxt : $this->getLLMsTxt($siteConfig)
        );
    }

    /**
     * One slice of the sitemap as a markdown link list
     */
    public function sitemap(HTTPRequest $request): HTTPResponse
    {
        $class = $this->unsanitiseClassName((string) $request->param('ClassName'));
        $page = (int) $request->param('Page');
        $locale = $request->param('Locale') ?: null;

        if ($page < 1 || !$this->isSitemapClass($class)) {
            $this->httpError(404);
        }

        $body = $this->cached(
            "section_{$class}_{$page}_{$locale}",
            fn (): string => sprintf(
                "# %s\n\n%s\n",
                $this->getSectionTitle($class, $page, $locale),
                $this->getSectionLinks($class, $page, $locale)
            )
        );

        return $this->textResponse($body);
    }

    /**
     * @param SiteConfig|SiteConfigSettingsExtension $siteConfig
     */
    protected function getLLMsTxt(SiteConfig $siteConfig): string
    {
        $sections = [trim($siteConfig->LLMsTxt ?: $this->getDefaultHeader($siteConfig))];
        if ($siteConfig->LLMsIncludeSitemap) {
            $sections[] = $this->cached('index_' . i18n::get_locale(), fn (): string => $this->getSitemapIndex());
        }

        return implode("\n\n", $sections) . "\n";
    }

    protected function getDefaultHeader(SiteConfig $siteConfig): string
    {
        $header = '# ' . $siteConfig->Title;
        if ($siteConfig->Tagline) {
            $header .= "\n\n> " . $siteConfig->Tagline;
        }

        return $header;
    }

    /**
     * Lists the links inline when the sitemap fits in one section, otherwise links to each section
     */
    protected function getSitemapIndex(): string
    {
        $sitemaps = GoogleSitemap::inst()->getSitemaps();

        if ($sitemaps->count() === 1) {
            $only = $sitemaps->first();
            $class = $this->unsanitiseClassName($only->ClassName);

            return sprintf(
                "## %s\n\n%s",
                $this->getSectionTitle($class, $only->Page, $only->Locale),
                $this->getSectionLinks($class, $only->Page, $only->Locale)
            );
        }

        $links = [];
        foreach ($sitemaps as $sitemap) {
            $link = sprintf(
                '- [%s](%s)',
                $this->getSectionTitle($this->unsanitiseClassName($sitemap->ClassName), $sitemap->Page, $sitemap->Locale),
                Controller::join_links(
                    Director::absoluteBaseURL(),
                    'llms.txt/sitemap',
                    $sitemap->ClassName,
                    $sitemap->Page,
                    $sitemap->Locale
                )
            );
            if ($sitemap->LastModified) {
                $link .= ': Updated ' . $sitemap->LastModified;
            }
            $links[] = $link;
        }

        return "## Sitemaps\n\n" . implode("\n", $links);
    }

    protected function getSectionTitle(string $class, int $page, ?string $locale): string
    {
        $title = match ($class) {
            SiteTree::class => 'Pages',
            static::ROUTE_CLASS => 'Other pages',
            default => singleton($class)->i18n_plural_name(),
        };
        if ($page > 1) {
            $title .= ', page ' . $page;
        }
        if ($locale) {
            $title .= " ($locale)";
        }

        return $title;
    }

    /**
     * One markdown list item per sitemap item; pages link to their index.md
     */
    protected function getSectionLinks(string $class, int $page, ?string $locale): string
    {
        $links = [];
        foreach (GoogleSitemap::inst()->getItems($class, $page, $locale) as $item) {
            $links[] = $this->formatLink($item);
        }

        return implode("\n", $links);
    }

    protected function formatLink(ModelData $item): string
    {
        // Registered routes are ArrayData with only an AbsoluteLink field
        $url = $item->hasMethod('AbsoluteLink') ? $item->AbsoluteLink() : $item->AbsoluteLink;
        if ($item instanceof SiteTree) {
            $url = SEO::getMarkdownLink($item);
        }

        $link = sprintf('- [%s](%s)', $this->escapeLinkText($item->MetaTitle ?: $item->Title ?: $url), $url);
        if ($item->MetaDescription) {
            $link .= ': ' . preg_replace('/\s+/', ' ', trim($item->MetaDescription));
        }

        return $link;
    }

    /**
     * Same classes sitemap.xml serves; anything else in the URL is a 404
     */
    protected function isSitemapClass(string $class): bool
    {
        return $class === SiteTree::class || $class === static::ROUTE_CLASS || GoogleSitemap::is_registered($class);
    }

    private function cached(string $key, callable $build): string
    {
        /** @var CacheInterface $cache */
        $cache = Injector::inst()->get(static::CACHE_SERVICE);
        // PSR-16 reserves characters found in class names and locales
        $key = md5($key);
        $value = $cache->get($key);
        if ($value === null) {
            $value = $build();
            $cache->set($key, $value);
        }

        return $value;
    }

    private function unsanitiseClassName(string $class): string
    {
        return str_replace('-', '\\', $class);
    }

    private function escapeLinkText(string $text): string
    {
        return str_replace(['[', ']'], ['\[', '\]'], $text);
    }

    private function textResponse(string $body): HTTPResponse
    {
        return HTTPResponse::create($body)->addHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
