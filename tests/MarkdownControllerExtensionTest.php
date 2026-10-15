<?php

namespace Moosylvania\SEO\Tests;

class MarkdownControllerExtensionTest extends SEOTestCase
{
    public function testConvertsPageToMarkdown(): void
    {
        $response = $this->get('about-us/index.md');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('text/markdown', $response->getHeader('Content-Type'));
        $this->assertMatchesRegularExpression('#<http.+/about-us/>; rel="canonical"#', $response->getHeader('Link'));
        $body = $response->getBody();
        $this->assertStringStartsWith("# About Test Site\n\nWho we are\n\n", $body);
        $this->assertStringContainsString('## Our story', $body);
        $this->assertStringContainsString("\nWe **build** things.", $body);
        $this->assertStringContainsString("```\n  indented code\n```", $body);
        $this->assertStringNotContainsString('alert', $body);
    }

    public function testUsesLLMsMarkdownOverride(): void
    {
        $this->assertSame("# Custom markdown\n\nWritten by hand.\n", $this->get('custom/index.md')->getBody());
    }

    public function testHomePage(): void
    {
        $response = $this->get('index.md');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith("# Home\n", $response->getBody());
        $this->assertStringContainsString('Welcome home', $response->getBody());
    }

    public function testHiddenPageIs404(): void
    {
        $this->assertSame(404, $this->get('hidden/index.md')->getStatusCode());
    }

    public function testHtmlPageAdvertisesMarkdown(): void
    {
        $response = $this->get('about-us/');

        $this->assertStringStartsWith('text/html', $response->getHeader('Content-Type'));
        $this->assertStringContainsString('/about-us/index.md>; rel="alternate"; type="text/markdown"', $response->getHeader('Link'));
        $this->assertStringContainsString('/llms.txt>; rel="describedby"', $response->getHeader('Link'));
    }
}
