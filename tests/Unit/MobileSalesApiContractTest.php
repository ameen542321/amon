<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
class MobileSalesApiContractTest extends TestCase {
 public function test_sales_api_is_read_only_scoped_and_hides_cost_and_profit(): void {
  $root=dirname(__DIR__,2); $routes=file_get_contents($root.'/routes/api.php'); $controller=file_get_contents($root.'/app/Http/Controllers/Api/SaleController.php'); $resource=file_get_contents($root.'/app/Support/Api/SaleResource.php');
  self::assertStringContainsString("prefix('sales')",$routes); self::assertStringContainsString('ability:sales:read',$routes); self::assertStringNotContainsString("Route::post('/sales",$routes);
  self::assertStringContainsString("attributes->get('api_actor')",$controller); self::assertStringContainsString("whereDate('business_date'",$controller);
  self::assertStringNotContainsString("'profit'",$resource); self::assertStringNotContainsString('cost_price',$resource); self::assertStringContainsString("'remaining_amount'",$resource);
 }
}
