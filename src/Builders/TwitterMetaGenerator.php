<?php

namespace Moosylvania\SEO\Builders;

use InvalidArgumentException;
use Moosylvania\SEO\Extensions\SiteConfigSettingsExtension;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Builds the Twitter/X card meta tags
 */
class TwitterMetaGenerator
{
    use Injectable, Configurable;

    protected ?string $description = null;

    protected ?string $imageUrl = null;

    protected ?string $title = null;

    public function getDescription(): ?string
    {
        if (!$this->description) {
            return null;
        }

        return DBHTMLText::create()->setValue($this->description)->LimitCharacters(297);
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @return string[]
     */
    public function process(): array
    {
        /** @var SiteConfig|SiteConfigSettingsExtension $siteConfig */
        $siteConfig = SiteConfig::current_site_config();
        $tags = [];

        $tags[] = sprintf(
            '<meta name="twitter:card" content="%s"/>',
            $this->getImageUrl() ? 'summary_large_image' : 'summary'
        );

        if ($this->getTitle()) {
            $tags[] = sprintf('<meta name="twitter:title" content="%s"/>', htmlentities($this->getTitle()));
        }

        if ($this->getDescription()) {
            $tags[] = sprintf('<meta name="twitter:description" content="%s"/>', htmlentities($this->getDescription()));
        }

        if ($this->getImageUrl()) {
            $tags[] = sprintf('<meta name="twitter:image" content="%s"/>', $this->getImageUrl());
        }

        if ($siteConfig->TwitterAccountName) {
            $tags[] = sprintf(
                '<meta name="twitter:site" content="@%s" />',
                htmlentities(ltrim($siteConfig->TwitterAccountName, '@'))
            );
        }

        return $tags;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            throw new InvalidArgumentException(
                'A relative or invalid URL was detected, you must provide the full absolute URL'
            );
        }

        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }
}
