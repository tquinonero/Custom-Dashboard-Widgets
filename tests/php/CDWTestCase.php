<?php

namespace CDW\Tests;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

abstract class CDWTestCase extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
}
