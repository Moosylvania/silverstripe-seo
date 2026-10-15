<?php

namespace Moosylvania\SEO\Extensions;

use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * @property string $GoogleAnalytics
 * @property string $FacebookPixel
 * @property string $TwitterPixel
 * @property string $SnapPixel
 * @property string $TwitterAccountName
 * @property string $TitleTagEnding
 * @property string $LLMsTxt
 * @property string $LLMsFullTxt
 * @property bool $LLMsIncludeSitemap
 * @property int $DefaultShareImageID
 *
 * @method Image DefaultShareImage()
 *
 * @extends Extension<SiteConfig&static>
 */
class SiteConfigSettingsExtension extends Extension
{
    use Configurable;

    private static array $casting = [
        'GoogleAnalytics' => 'HTMLText'
    ];

    private static array $db = [
        'GoogleAnalytics'    => 'Text',
        'FacebookPixel'      => 'Text',
        'TwitterPixel'       => 'Text',
        'SnapPixel'          => 'Text',
        'TwitterAccountName' => 'Varchar(80)',
        'TitleTagEnding'     => 'Varchar(255)',
        'LLMsTxt'            => 'Text',
        'LLMsFullTxt'        => 'Text',
        'LLMsIncludeSitemap' => 'Boolean(1)',
    ];

    private static array $has_one = [
        'DefaultShareImage' => Image::class
    ];

    private static $owns = ['DefaultShareImage'];

    protected function updateCMSFields(FieldList $fields): void
    {
        $fbPixelHelp = 'https://www.facebook.com/business/help/952192354843755';
        $twPixelHelp = 'https://business.x.com/en/help/campaign-measurement-and-analytics/conversion-tracking-for-websites';
        $snPixelHelp = 'https://businesshelp.snapchat.com/en-US/article/snap-pixel';
        $gaHelp = 'https://support.google.com/analytics/answer/1008080?hl=en';
        $llmsHelp = 'https://llmstxt.org/';

        $fields->addFieldsToTab('Root.SEO', [
            TextField::create('TitleTagEnding'),
            UploadField::create('DefaultShareImage'),
            TextField::create('TwitterAccountName'),
            TextareaField::create('GoogleAnalytics', 'Google Analytics')->setRightTitle($this->getHelpLink($gaHelp)),
            ToggleCompositeField::create('PixelsComposite', 'Pixels', [
                TextareaField::create('FacebookPixel', 'Facebook Pixel')
                    ->setRightTitle($this->getHelpLink($fbPixelHelp)),
                TextareaField::create('TwitterPixel', 'Twitter Pixel')->setRightTitle($this->getHelpLink($twPixelHelp)),
                TextareaField::create('SnapPixel', 'Snap Pixel')->setRightTitle($this->getHelpLink($snPixelHelp)),
            ]),
            ToggleCompositeField::create('LLMsComposite', 'LLMs', [
                TextareaField::create('LLMsTxt', 'llms.txt')
                    ->setRows(15)
                    ->setRightTitle(DBHTMLText::create()->setValue(
                        'Markdown served at /llms.txt. If blank, the site title and tagline are used. ' .
                        $this->getHelpLink($llmsHelp)
                    )),
                CheckboxField::create('LLMsIncludeSitemap', 'Append a list of all pages to llms.txt'),
                TextareaField::create('LLMsFullTxt', 'llms-full.txt')
                    ->setRows(15)
                    ->setRightTitle('Markdown served at /llms-full.txt. If blank, llms.txt is served instead.'),
            ]),
        ]);
    }

    private function getHelpLink(string $link): DBHTMLText
    {
        return DBHTMLText::create()->setValue(
            sprintf('Find out more: <a href="%1$s" target="_blank" rel="noopener">%1$s</a>', $link)
        );
    }
}
