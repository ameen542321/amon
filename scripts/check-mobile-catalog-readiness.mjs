import { existsSync, readFileSync } from 'node:fs';

const failures = [];
const passes = [];
const source = (path) => existsSync(path) ? readFileSync(path, 'utf8') : '';
const check = (condition, message) => (condition ? passes : failures).push(message);

const required = [
    'app/Http/Controllers/Api/CatalogController.php',
    'app/Services/ApiStoreScopeService.php',
    'app/Support/Api/CatalogResource.php',
    'database/migrations/2026_09_25_000005_add_mobile_catalog_indexes.php',
    'tests/Unit/MobileCatalogApiContractTest.php',
];
for (const file of required) check(existsSync(file), `required file exists: ${file}`);

const routes = source('routes/api.php');
const controller = source(required[0]);
const scope = source(required[1]);
const resource = source(required[2]);
const migration = source(required[3]);
const auth = source('app/Http/Controllers/Api/AuthTokenController.php');
const context = source('app/Http/Controllers/Api/MobileContextController.php');
const schema = source('database/testing/sqlite-schema.sql');

check(routes.includes("prefix('catalog')") && routes.includes('ability:catalog:read'), 'catalog routes require the catalog read ability');
check(!routes.includes("Route::post('/products") && !routes.includes("Route::patch('/products"), 'mobile catalog surface is read only');
check(scope.includes('instanceof Accountant') && scope.includes("where('user_id'"), 'store scope isolates accountant and owner access');
check(controller.includes('cursorPaginate') && controller.includes("'limit' => ['nullable', 'integer', 'min:1', 'max:50']"), 'catalog listing uses bounded cursor pagination');
check(controller.includes("where('barcode'") && controller.includes("where('name', 'like'"), 'catalog supports exact barcode and text search');
check(controller.includes('FULL_SYNC_REQUIRED') && controller.includes("'watermark' =>"), 'delta sync is bounded and watermark based');
check(controller.includes('withTrashed()') && resource.includes("'deleted_at'"), 'delta sync exposes deletion tombstones');
check(!resource.includes("'cost_price'") && resource.includes("'price' => self::decimal"), 'catalog omits product cost and emits stable price strings');
check(resource.includes("'fraction_options'") && controller.includes("with('fractions:id,product_id,option_label,deduction_value,price')"), 'fraction options are serialized without N+1 queries');
check(auth.includes("'catalog:read'") && auth.includes('defaultAbilities()'), 'new and refreshed sessions receive catalog ability');
check(context.includes("'catalog_read' => true") && context.includes("'catalog_delta_sync' => true"), 'app config advertises catalog capabilities');
check(migration.includes('products_store_barcode_index') && migration.includes('products_store_updated_sync_index'), 'production catalog lookup and sync indexes exist');
check(schema.includes('products_store_barcode_index') && schema.includes('categories_store_updated_sync_index'), 'SQLite test schema mirrors catalog indexes');

for (const message of passes) console.log(`PASS: ${message}`);
for (const message of failures) console.error(`FAIL: ${message}`);
if (failures.length) {
    console.error(`Mobile catalog readiness failed with ${failures.length} issue(s).`);
    process.exit(1);
}
console.log(`Mobile catalog readiness passed (${passes.length} checks).`);
