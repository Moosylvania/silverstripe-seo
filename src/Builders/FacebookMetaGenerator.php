<?php

namespace Moosylvania\SEO\Builders;

use InvalidArgumentException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\i18n\i18n;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Builds the Facebook/OpenGraph meta tags
 */
class FacebookMetaGenerator
{
    use Injectable, Configurable;

    protected ?string $description = null;

    protected ?int $imageHeight = null;

    protected ?string $imageUrl = null;

    protected ?int $imageWidth = null;

    protected ?string $title = null;

    protected string $type = 'website';

    protected ?string $url = null;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return string[]
     */
    public function process(): array
    {
        $tags = [];

        if ($this->getTitle()) {
            $tags[] = sprintf('<meta property="og:title" content="%s"/>', htmlentities($this->getTitle()));
        }

        if ($this->getDescription()) {
            $tags[] = sprintf('<meta property="og:description" content="%s"/>', htmlentities($this->getDescription()));
        }

        if ($this->getType()) {
            $tags[] = sprintf('<meta property="og:type" content="%s"/>', $this->getType());
        }

        if ($this->getUrl()) {
            $tags[] = sprintf('<meta property="og:url" content="%s"/>', $this->getUrl());
        }

        if ($this->getImageUrl()) {
            $tags[] = sprintf('<meta property="og:image" content="%s"/>', $this->getImageUrl());
        }

        if ($this->imageWidth && $this->imageHeight) {
            $tags[] = sprintf('<meta property="og:image:width" content="%s" />', $this->imageWidth);
            $tags[] = sprintf('<meta property="og:image:height" content="%s" />', $this->imageHeight);
        }

        $tags[] = sprintf('<meta property="og:locale" content="%s" />', i18n::get_locale());
        $tags[] = sprintf(
            '<meta property="og:site_name" content="%s" />',
            htmlentities(SiteConfig::current_site_config()->Title ?? '')
        );

        return $tags;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function setImageDimensions(?int $width, ?int $height): static
    {
        $this->setImageWidth($width);
        $this->setImageHeight($height);

        return $this;
    }

    public function setImageHeight(?int $height): static
    {
        $this->imageHeight = $height;
        return $this;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            throw new InvalidArgumentException(
                'A relative or invalid URL was detected; you must provide the full absolute URL'
            );
        }

        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function setImageWidth(?int $width): static
    {
        $this->imageWidth = $width;
        return $this;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function setType(string $type): static
    {
        if (!array_key_exists($type, static::getValidTypes())) {
            throw new InvalidArgumentException(sprintf(
                'That type [%s] is not a valid type; please see: %s',
                $type,
                'https://developers.facebook.com/docs/reference/opengraph/'
            ));
        }

        $this->type = $type;
        return $this;
    }

    public function setUrl(?string $url): static
    {
        if ($url && !str_starts_with($url, 'http')) {
            throw new InvalidArgumentException(
                'A relative URL was detected; you must provide the full absolute URL instead'
            );
        }

        $this->url = $url;
        return $this;
    }

    /**
     * Valid types supported by Open Graph
     *
     * @return array<string, string>
     */
    public static function getValidTypes(): array
    {
        return [
            'website'                   => 'website',
            'apps.saves'                => 'apps.saves',
            'books.quotes'              => 'books.quotes',
            'books.rates'               => 'books.rates',
            'books.reads'               => 'books.reads',
            'books.wants_to_read'       => 'books.wants_to_read',
            'fitness.bikes'             => 'fitness.bikes',
            'fitness.runs'              => 'fitness.runs',
            'fitness.walks'             => 'fitness.walks',
            'games.achieves'            => 'games.achieves',
            'games.celebrate'           => 'games.celebrate',
            'games.plays'               => 'games.plays',
            'games.saves'               => 'games.saves',
            'music.listens'             => 'music.listens',
            'music.playlists'           => 'music.playlists',
            'news.publishes'            => 'news.publishes',
            'news.reads'                => 'news.reads',
            'og.follows'                => 'og.follows',
            'og.likes'                  => 'og.likes',
            'pages.saves'               => 'pages.saves',
            'restaurant.visited'        => 'restaurant.visited',
            'restaurant.wants_to_visit' => 'restaurant.wants_to_visit',
            'sellers.rates'             => 'sellers.rates',
            'video.rates'               => 'video.rates',
            'video.wants_to_watch'      => 'video.wants_to_watch',
            'video.watches'             => 'video.watches',
            'article'                   => 'article',
            'book'                      => 'book',
            'books.author'              => 'books.author',
            'books.book'                => 'books.book',
            'books.genre'               => 'books.genre',
            'business.business'         => 'business.business',
            'fitness.course'            => 'fitness.course',
            'game.achievement'          => 'game.achievement',
            'music.album'               => 'music.album',
            'music.playlist'            => 'music.playlist',
            'music.radio_station'       => 'music.radio_station',
            'music.song'                => 'music.song',
            'place'                     => 'place',
            'product'                   => 'product',
            'product.group'             => 'product.group',
            'product.item'              => 'product.item',
            'profile'                   => 'profile',
            'restaurant.menu'           => 'restaurant.menu',
            'restaurant.menu_item'      => 'restaurant.menu_item',
            'restaurant.menu_section'   => 'restaurant.menu_section',
            'restaurant.restaurant'     => 'restaurant.restaurant',
            'video.episode'             => 'video.episode',
            'video.movie'               => 'video.movie',
            'video.other'               => 'video.other',
            'video.tv_show'             => 'video.tv_show',
        ];
    }
}
