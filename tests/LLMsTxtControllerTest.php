<?php

namespace Moosylvania\SEO\Tests;

use Moosylvania\SEO\Controllers\LLMsTxtController;
use Page;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\SiteConfig\SiteConfig;
use Wilr\GoogleSitemaps\GoogleSitemap;

class LLMsTxtControllerTest extends SEOTestCase
{
    protected static $extra_dataobjects = [
        LLMsTestProduct::class,
    ];

    protected function tearDown(): void
    {
        GoogleSitemap::clear_registered_dataobjects();
        parent::tearDown();
    }

    public function testDefaultHeaderAndPageList(): void
    {
        $response = $this->get('llms.txt');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/plain', $response->getHeader('Content-Type'));
        $body = $response->getBody();
        $this->assertStringStartsWith("# Test Site\n\n> A site for testing\n\n## Pages\n", $body);
        $this->assertStringContainsString('- [About Test Site](', $body);
        $this->assertStringContainsString('/about-us/index.md): Who we are', $body);
        $this->assertStringNotContainsString('/hidden/', $body);
    }

    public function testCustomTextWithoutPageList(): void
    {
        $this->updateSiteConfig(['LLMsTxt' => "# Custom\n\n> Hand written", 'LLMsIncludeSitemap' => false]);

        $this->assertSame("# Custom\n\n> Hand written\n", $this->get('llms.txt')->getBody());
    }

    public function testFullFallsBackToLLMsTxt(): void
    {
        $this->assertSame($this->get('llms.txt')->getBody(), $this->get('llms-full.txt')->getBody());
    }

    public function testCustomFullText(): void
    {
        $this->updateSiteConfig(['LLMsFullTxt' => 'Everything']);

        $this->assertSame('Everything', $this->get('llms-full.txt')->getBody());
    }

    public function testPageListIsCachedUntilAPageIsPublished(): void
    {
        $this->get('llms.txt');
        // Change the live title behind the ORM's back so no publish hook clears the cache
        SQLUpdate::create('"SiteTree_Live"', ['"Title"' => 'Renamed'], ['"URLSegment"' => 'custom'])->execute();

        $this->assertStringNotContainsString('[Renamed]', $this->get('llms.txt')->getBody());

        $page = Page::create(['Title' => 'New Page', 'URLSegment' => 'new-page']);
        $page->write();
        $page->publishRecursive();
        $body = $this->get('llms.txt')->getBody();

        $this->assertStringContainsString('[New Page]', $body);
        $this->assertStringContainsString('[Renamed]', $body);
    }

    public function testLargeSitemapLinksToSections(): void
    {
        // 3 searchable fixture pages, 2 per section
        Config::modify()->set(GoogleSitemap::class, 'objects_per_sitemap', 2);
        LLMsTxtController::clearCache();

        $body = $this->get('llms.txt')->getBody();

        $this->assertStringContainsString("## Sitemaps\n\n- [Pages](", $body);
        $this->assertMatchesRegularExpression('#\[Pages, page 2\]\(http.+/llms\.txt/sitemap/SilverStripe-CMS-Model-SiteTree/2/?\): Updated #', $body);
        $this->assertStringNotContainsString('index.md', $body);

        $section = $this->get('llms.txt/sitemap/SilverStripe-CMS-Model-SiteTree/2/');

        $this->assertSame(200, $section->getStatusCode());
        $this->assertStringStartsWith('text/plain', $section->getHeader('Content-Type'));
        $this->assertMatchesRegularExpression("#^\# Pages, page 2\n\n- \[.+\]\(http.+/index\.md\)#", $section->getBody());
    }

    public function testRegisteredDataObjectGetsItsOwnSection(): void
    {
        LLMsTestProduct::create(['Title' => 'Robusto'])->write();
        GoogleSitemap::register_dataobject(LLMsTestProduct::class);
        LLMsTxtController::clearCache();

        $this->assertStringContainsString(
            '- [Products](http://localhost/llms.txt/sitemap/Moosylvania-SEO-Tests-LLMsTestProduct/1/)',
            $this->get('llms.txt')->getBody()
        );
        $this->assertMatchesRegularExpression(
            '#^\# Products\n\n- \[Robusto\]\(http://localhost/products/\d+/\)\n$#',
            $this->get('llms.txt/sitemap/Moosylvania-SEO-Tests-LLMsTestProduct/1/')->getBody()
        );
    }

    public function testUnknownSectionIs404(): void
    {
        $this->assertSame(404, $this->get('llms.txt/sitemap/SilverStripe-Security-Member/1/')->getStatusCode());
        $this->assertSame(404, $this->get('llms.txt/sitemap/SilverStripe-CMS-Model-SiteTree/0/')->getStatusCode());
    }

    private function updateSiteConfig(array $data): void
    {
        SiteConfig::current_site_config()->update($data)->write();
    }
}
