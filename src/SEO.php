<?php

namespace Moosylvania\SEO;

use Moosylvania\SEO\Builders\FacebookMetaGenerator;
use Moosylvania\SEO\Builders\TwitterMetaGenerator;
use Moosylvania\SEO\Extensions\PageSeoExtension;
use Moosylvania\SEO\Extensions\SiteConfigSettingsExtension;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Builds the SEO, social and LLM tags merged into SiteTree::MetaTags()
 */
class SEO
{
    use Injectable, Configurable;

    /**
     * Creates article:published_time and article:modified_time tags
     *
     * @return string[]
     */
    public static function getArticleTags(SiteTree $owner): array
    {
        /** @var DBDatetime $published */
        $published = $owner->dbObject('Created');

        /** @var DBDatetime $modified */
        $modified = $owner->dbObject('LastEdited');

        return [
            sprintf('<meta property="article:published_time" content="%s" />', $published->Rfc3339()),
            sprintf('<meta property="article:modified_time" content="%s" />', $modified->Rfc3339()),
        ];
    }

    /**
     * Creates the canonical url link
     *
     * @return string[]
     */
    public static function getCanonicalUrlLink(SiteTree $owner): array
    {
        return [
            sprintf('<link rel="canonical" href="%s"/>', $owner->AbsoluteLink())
        ];
    }

    /**
     * Absolute URL of the markdown version of a page, see https://llmstxt.org/
     */
    public static function getMarkdownLink(SiteTree $owner): string
    {
        return Controller::join_links($owner->AbsoluteLink(), 'index.md');
    }

    public static function getLLMsTxtLink(): string
    {
        return Controller::join_links(Director::absoluteBaseURL(), 'llms.txt');
    }

    /**
     * Creates the links pointing LLM crawlers at the page's markdown and the site's llms.txt
     *
     * @return string[]
     */
    public static function getLLMsLinks(SiteTree $owner): array
    {
        $links = [sprintf('<link rel="describedby" type="text/plain" href="%s"/>', static::getLLMsTxtLink())];
        // index.md 404s for pages hidden from search
        if ($owner->ShowInSearch) {
            array_unshift(
                $links,
                sprintf('<link rel="alternate" type="text/markdown" href="%s"/>', static::getMarkdownLink($owner))
            );
        }

        return $links;
    }

    /**
     * Pages hidden from search are hidden from search engines too
     *
     * @return string[]
     */
    public static function getRobotsTags(SiteTree $owner): array
    {
        return $owner->ShowInSearch ? [] : ['<meta name="robots" content="noindex"/>'];
    }

    /**
     * Creates the twitter meta tags
     *
     * @param SiteTree|PageSeoExtension $owner
     * @return string[]
     */
    public static function getTwitterMetaTags(SiteTree $owner): array
    {
        $generator = TwitterMetaGenerator::create();
        $generator->setTitle($owner->TwitterPageTitle ?: $owner->Title);
        $generator->setDescription($owner->TwitterPageDescription ?: $owner->MetaDescription ?: $owner->Content);
        $generator->setImageUrl(static::getShareImageUrl($owner, [$owner->TwitterPageImage(), $owner->FacebookPageImage()]));

        return $generator->process();
    }

    /**
     * Creates the Facebook/OpenGraph meta tags
     *
     * @param SiteTree|PageSeoExtension $owner
     * @return string[]
     */
    public static function getFacebookMetaTags(SiteTree $owner): array
    {
        $image = $owner->FacebookPageImage();

        $generator = FacebookMetaGenerator::create();
        $generator->setTitle($owner->FacebookPageTitle ?: $owner->Title);
        $generator->setDescription($owner->FacebookPageDescription ?: $owner->MetaDescription ?: $owner->Content);
        $generator->setImageUrl(static::getShareImageUrl($owner, [$image, $owner->TwitterPageImage()]));
        $generator->setImageDimensions(
            $image->exists() ? $image->getWidth() : null,
            $image->exists() ? $image->getHeight() : null
        );
        $generator->setType($owner->FacebookPageType ?: 'website');
        $generator->setUrl($owner->CustomShareURL ?: $owner->AbsoluteLink());

        return $generator->process();
    }

    /**
     * @return string[]
     */
    public static function getGoogleAnalytics(): array
    {
        /** @var SiteConfig|SiteConfigSettingsExtension $siteConfig */
        $siteConfig = SiteConfig::current_site_config();

        return $siteConfig->GoogleAnalytics ? [$siteConfig->GoogleAnalytics] : [];
    }

    /**
     * Get all Pixel fields
     *
     * @return string[]
     */
    public static function getPixels(): array
    {
        $output = [];
        $siteConfig = SiteConfig::current_site_config();
        $ours = array_keys(SiteConfigSettingsExtension::config()->get('db'));
        foreach ($ours as $field) {
            if (str_contains($field, 'Pixel') && !is_null($siteConfig->{$field})) {
                $output[] = $siteConfig->{$field};
            }
        }

        return $output;
    }

    /**
     * First of: the page's CustomShareImage URL, the given page images, the site's default share image
     *
     * @param Image[] $images in order of preference
     */
    private static function getShareImageUrl(SiteTree $owner, array $images): ?string
    {
        if ($owner->CustomShareImage) {
            return $owner->CustomShareImage;
        }

        $images[] = SiteConfig::current_site_config()->DefaultShareImage();
        foreach ($images as $image) {
            if ($image->exists()) {
                return $image->AbsoluteLink();
            }
        }

        return null;
    }
}
