<?php

namespace Moosylvania\SEO\Tests;

use SilverStripe\Control\Director;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

class LLMsTestProduct extends DataObject implements TestOnly
{
    private static string $table_name = 'LLMsTestProduct';

    private static string $plural_name = 'Products';

    private static array $db = [
        'Title' => 'Varchar',
    ];

    public function canView($member = null): bool
    {
        return true;
    }

    public function AbsoluteLink(): string
    {
        return Director::absoluteURL('products/' . $this->ID . '/');
    }
}
