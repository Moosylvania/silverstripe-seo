<?php

namespace Moosylvania\SEO\Tests;

use Page;

class PageSeoExtensionTest extends SEOTestCase
{
    public function testMetaTags(): void
    {
        $tags = $this->objFromFixture(Page::class, 'about')->MetaTags();

        $this->assertStringContainsString('<title>About Test Site</title>', $tags);
        $this->assertMatchesRegularExpression('#<link rel="alternate" type="text/markdown" href="http.+/about-us/index.md"/>#', $tags);
        $this->assertMatchesRegularExpression('#<link rel="describedby" type="text/plain" href="http.+/llms.txt"/>#', $tags);
        $this->assertStringContainsString('<meta property="og:title" content="About Us"/>', $tags);
        $this->assertStringContainsString('<meta property="og:description" content="Who we are"/>', $tags);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary"/>', $tags);
        $this->assertStringNotContainsString('noindex', $tags);
    }

    public function testHiddenPageIsNoIndexWithoutMarkdownLink(): void
    {
        $tags = $this->objFromFixture(Page::class, 'hidden')->MetaTags();

        $this->assertStringContainsString('<meta name="robots" content="noindex"/>', $tags);
        $this->assertStringNotContainsString('text/markdown', $tags);
    }

    public function testLargeTwitterCardWithImage(): void
    {
        $page = $this->objFromFixture(Page::class, 'about');
        $page->CustomShareImage = 'https://example.com/share.jpg';

        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image"/>', $page->MetaTags());
    }
}
