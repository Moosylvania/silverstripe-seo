<?php

namespace Moosylvania\SEO\Tests;

use Page;
use SilverStripe\Dev\FunctionalTest;

abstract class SEOTestCase extends FunctionalTest
{
    protected static $fixture_file = 'SEOTest.yml';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (Page::get() as $page) {
            $page->publishRecursive();
        }
    }
}
