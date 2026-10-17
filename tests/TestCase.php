<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run database traits (migrate:fresh, truncation) against anything but a *_test database,
     * so a misconfigured environment can never wipe the development database.
     */
    protected function setUpTraits()
    {
        $database = config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with((string) $database, '_test')) {
            throw new RuntimeException("Tests must run against a *_test database, [{$database}] given. Check phpunit.xml.");
        }

        return parent::setUpTraits();
    }
}
