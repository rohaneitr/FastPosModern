<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        if (get_class($this) !== \Tests\Feature\RBACTest::class && get_class($this) !== \Tests\Feature\SubscriptionMiddlewareTest::class) {
            $this->withoutMiddleware([
                \Spatie\Permission\Middlewares\RoleMiddleware::class,
                \Spatie\Permission\Middlewares\PermissionMiddleware::class,
                \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware::class,
                'role',
                'permission',
                'role_or_permission',
            ]);
        }
    }
}
