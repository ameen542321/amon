<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MobilePeopleApiContractTest extends TestCase
{
    public function test_people_api_is_store_scoped_and_read_only(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/PeopleController.php'));

        self::assertStringContainsString("prefix('people')", $routes);
        self::assertStringContainsString('ability:people:read', $routes);
        self::assertStringNotContainsString("Route::post('/employees", $routes);
        self::assertStringNotContainsString("Route::patch('/employees", $routes);
        self::assertStringNotContainsString("Route::delete('/employees", $routes);
        self::assertStringContainsString('ApiStoreScopeService', $controller);
        self::assertStringContainsString("where('store_id', \$storeId)", $controller);
        self::assertStringContainsString('cursorPaginate', $controller);
    }

    public function test_people_api_minimizes_private_and_financial_data(): void
    {
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/PeopleController.php'));
        $resource = file_get_contents(base_path('app/Support/Api/PeopleResource.php'));
        $context = file_get_contents(base_path('app/Http/Controllers/Api/MobileContextController.php'));

        self::assertStringContainsString('! $actor instanceof Accountant', $controller);
        self::assertStringContainsString('whereKey($actor->getAuthIdentifier())', $controller);
        self::assertStringContainsString('if ($includeContact)', $resource);
        self::assertStringNotContainsString("'salary'", $resource);
        self::assertStringNotContainsString("'suspension_reason'", $resource);
        self::assertStringContainsString("'people_finance_read' => false", $context);
        self::assertStringContainsString("'people_write' => false", $context);
        self::assertStringContainsString("'people_permissions_write' => false", $context);
    }
}
