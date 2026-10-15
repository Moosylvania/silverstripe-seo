<?php

namespace Moosylvania\SEO\Extensions;

use Moosylvania\SEO\Builders\FacebookMetaGenerator;
use Moosylvania\SEO\Controllers\LLMsTxtController;
use Moosylvania\SEO\SEO;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\VersionedAdmin\Controllers\HistoryViewerController;

/**
 * @property string $FacebookPageType
 * @property string $FacebookPageTitle
 * @property string $FacebookPageDescription
 * @property string $TwitterPageTitle
 * @property string $TwitterPageDescription
 * @property string $StructuredData
 * @property string $MetaTitle
 * @property string $LLMsMarkdown
 * @property int $FacebookPageImageID
 * @property int $TwitterPageImageID
 * @property int $CreatorID
 *
 * @method Image FacebookPageImage()
 * @method Image TwitterPageImage()
 * @method Member Creator()
 *
 * @extends Extension<SiteTree&static>
 */
class PageSeoExtension extends Extension
{
    private static array $cascade_deletes = [
        'FacebookPageImage',
        'TwitterPageImage'
    ];

    private static array $db = [
        'FacebookPageType'        => 'Varchar(50)',
        'FacebookPageTitle'       => 'Varchar(255)',
        'FacebookPageDescription' => 'Text',
        'TwitterPageTitle'        => 'Varchar(255)',
        'TwitterPageDescription'  => 'Text',
        'StructuredData'          => 'HTMLText',
        'MetaTitle'               => 'Varchar(255)',
        'LLMsMarkdown'            => 'Text',
    ];

    private static array $has_one = [
        'FacebookPageImage' => Image::class,
        'TwitterPageImage'  => Image::class,
        'Creator'           => Member::class
    ];

    private static array $owns = [
        'FacebookPageImage',
        'TwitterPageImage'
    ];

    /**
     * Extension point for SiteTree to merge all tags with the standard meta tags
     */
    protected function updateMetaTags(string &$tags): void
    {
        $owner = $this->getOwner();
        $siteConfig = SiteConfig::current_site_config();
        $lines = explode(PHP_EOL, $tags);
        foreach ($lines as $i => $line) {
            $pos = strpos($line, '</title>');
            if ($pos === false) {
                continue;
            }
            if ($owner->MetaTitle) {
                $start = strpos($line, '<title>') + 7;
                $lines[$i] = substr_replace($line, htmlspecialchars($owner->MetaTitle), $start, $pos - $start);
            } else {
                $lines[$i] = substr_replace($line, ' ' . $siteConfig->TitleTagEnding, $pos, 0);
            }
            break;
        }

        $tags = implode(PHP_EOL, array_merge(
            $lines,
            SEO::getCanonicalUrlLink($owner),
            SEO::getRobotsTags($owner),
            SEO::getLLMsLinks($owner),
            SEO::getFacebookMetaTags($owner),
            SEO::getTwitterMetaTags($owner),
            SEO::getArticleTags($owner),
            SEO::getGoogleAnalytics(),
            SEO::getPixels(),
            [$owner->StructuredData]
        ));
    }

    protected function onBeforeWrite(): void
    {
        $owner = $this->getOwner();
        if (!$owner->ID && !$owner->Creator()->exists() && $member = Security::getCurrentUser()) {
            $owner->CreatorID = $member->ID;
        }
    }

    protected function onAfterPublish(): void
    {
        LLMsTxtController::clearCache();
    }

    // Archiving unpublishes too
    protected function onAfterUnpublish(): void
    {
        LLMsTxtController::clearCache();
    }

    protected function updateCMSFields(FieldList $fields): void
    {
        $owner = $this->getOwner();
        // avoid cluttering the history comparison UI
        $suppressMessaging = Controller::curr() instanceof HistoryViewerController;
        $descriptionPlaceholder = $owner->MetaDescription ?: $owner->dbObject('Content')->LimitCharacters(297);
        $descriptionHelp = $suppressMessaging
            ? ''
            : 'If blank, inherits meta description if it exists or gets the first 297 characters from content';
        $titleHelp = $suppressMessaging ? '' : 'If blank, inherits default page title';

        $fields->addFieldToTab('Root.Main', TextField::create('MetaTitle'), 'MetaDescription');
        $fields->addFieldToTab('Root.Main', TextareaField::create('StructuredData'), 'ExtraMeta');
        $fields->addFieldToTab(
            'Root.Main',
            TextareaField::create('LLMsMarkdown', 'LLMs markdown')
                ->setRows(10)
                ->setRightTitle($suppressMessaging
                    ? ''
                    : 'Markdown served to AI crawlers at this page\'s index.md. ' .
                    'If blank, the page content is converted to markdown automatically.'),
            'ExtraMeta'
        );
        $fields->removeByName([
            'FacebookPageType',
            'FacebookPageTitle',
            'FacebookPageImage',
            'FacebookPageDescription',
            'TwitterPageTitle',
            'TwitterPageImage',
            'TwitterPageDescription',
            'Creator',
            'CreatorID',
        ]);
        $fields->insertAfter(
            'Metadata',
            ToggleCompositeField::create('FacebookSeoComposite', 'Facebook SEO', [
                DropdownField::create('FacebookPageType', 'Type', FacebookMetaGenerator::getValidTypes()),
                TextField::create('FacebookPageTitle', 'Title')
                    ->setAttribute('placeholder', $owner->Title)
                    ->setRightTitle($titleHelp),
                UploadField::create('FacebookPageImage', 'Image')
                    ->setRightTitle($suppressMessaging
                        ? ''
                        : 'Facebook recommends images to be 1200 x 630 pixels. ' .
                        'If no image is provided, Facebook will choose the first image that appears on the page, ' .
                        'which usually has bad results')
                    ->setFolderName('seo'),
                TextareaField::create('FacebookPageDescription', 'Description')
                    ->setAttribute('placeholder', $descriptionPlaceholder)
                    ->setRightTitle($descriptionHelp),
            ])
        );
        $fields->insertAfter(
            'Metadata',
            ToggleCompositeField::create('TwitterSeoComposite', 'Twitter SEO', [
                TextField::create('TwitterPageTitle', 'Title')
                    ->setAttribute('placeholder', $owner->Title)
                    ->setRightTitle($titleHelp),
                UploadField::create('TwitterPageImage', 'Image')
                    ->setRightTitle($suppressMessaging ? '' : 'Recommended 1200 x 628 pixels, at least 300 x 157')
                    ->setFolderName('seo'),
                TextareaField::create('TwitterPageDescription', 'Description')
                    ->setAttribute('placeholder', $descriptionPlaceholder)
                    ->setRightTitle($descriptionHelp),
            ])
        );
    }
}
