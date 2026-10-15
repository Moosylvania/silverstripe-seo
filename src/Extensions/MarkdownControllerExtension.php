<?php

namespace Moosylvania\SEO\Extensions;

use League\HTMLToMarkdown\HtmlConverter;
use Moosylvania\SEO\SEO;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\View\Exception\MissingTemplateException;
use SilverStripe\View\SSViewer;

/**
 * Serves a markdown version of each page at {page}/index.md, see https://llmstxt.org/
 *
 * @extends Extension<ContentController>
 */
class MarkdownControllerExtension extends Extension
{
    use Configurable;

    /**
     * Elements dropped before converting the page's $Layout to markdown
     *
     * @config
     */
    private static string $remove_nodes = 'script style noscript form iframe svg nav button';

    /**
     * Points crawlers at the markdown version and llms.txt from normal page responses too
     */
    protected function onAfterInit(): void
    {
        $owner = $this->getOwner();
        $page = $owner->data();
        if ($owner->getRequest()->getExtension() || !$page instanceof SiteTree || !$page->ShowInSearch) {
            return;
        }

        $response = $owner->getResponse();
        $links = array_filter([
            $response->getHeader('Link'),
            sprintf('<%s>; rel="alternate"; type="text/markdown"', SEO::getMarkdownLink($page)),
            sprintf('<%s>; rel="describedby"; type="text/plain"', SEO::getLLMsTxtLink()),
        ]);
        $response->addHeader('Link', implode(', ', $links));
    }

    /**
     * Replaces the index action's HTML with markdown when the request ends in .md
     */
    protected function afterCallActionHandler(HTTPRequest $request, string $action, mixed $result): ?HTTPResponse
    {
        $owner = $this->getOwner();
        $page = $owner->data();
        if ($request->getExtension() !== 'md' || $action !== 'index' || !$page instanceof SiteTree) {
            return null;
        }

        if (!$page->ShowInSearch) {
            $owner->httpError(404);
        }

        return HTTPResponse::create($this->getMarkdown($page))
            ->addHeader('Content-Type', 'text/markdown; charset=utf-8')
            ->addHeader('Link', sprintf('<%s>; rel="canonical"', $page->AbsoluteLink()));
    }

    /**
     * The page's LLMsMarkdown if set, otherwise its meta title, description and $Layout converted to markdown
     *
     * @param SiteTree|PageSeoExtension $page
     */
    protected function getMarkdown(SiteTree $page): string
    {
        if (trim($page->LLMsMarkdown ?? '')) {
            return trim($page->LLMsMarkdown) . "\n";
        }

        $sections = ['# ' . ($page->MetaTitle ?: $page->Title)];
        if ($page->MetaDescription) {
            $sections[] = trim($page->MetaDescription);
        }

        $converter = new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'remove_nodes' => static::config()->get('remove_nodes'),
            'hard_break' => true,
        ]);
        $sections[] = $converter->convert($this->renderLayout($page));

        $markdown = $this->cleanWhitespace(implode("\n\n", array_filter(array_map('trim', $sections))));

        return preg_replace("/\n{3,}/", "\n\n", $markdown) . "\n";
    }

    /**
     * Template indentation survives conversion, and 4+ leading spaces would turn headings and text into code blocks.
     * Strips it everywhere except inside code fences and nested list items.
     */
    protected function cleanWhitespace(string $markdown): string
    {
        $inFence = false;
        $lines = explode("\n", $markdown);
        foreach ($lines as $i => $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = !$inFence;
                $lines[$i] = trim($line);
            } elseif (!$inFence) {
                $lines[$i] = preg_match('/^\s+([-*+]|\d+\.) /', $line) ? rtrim($line) : trim($line);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Renders the same templates/Layout template that $Layout would, falling back to the Content field
     */
    protected function renderLayout(SiteTree $page): string
    {
        $templates = array_merge(
            SSViewer::get_templates_by_class(get_class($page), '', SiteTree::class),
            SSViewer::get_templates_by_class(get_class($this->getOwner()), '', Controller::class)
        );

        try {
            return $this->getOwner()->renderWith(['type' => 'Layout', 'templates' => $templates])->getValue();
        } catch (MissingTemplateException) {
            return (string) $page->Content;
        }
    }
}
