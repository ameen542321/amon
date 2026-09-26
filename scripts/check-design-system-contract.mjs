import { existsSync, readFileSync } from 'node:fs';

const passed = [], failed = [];
const read = (file) => existsSync(file) ? readFileSync(file, 'utf8') : '';
const check = (condition, message) => (condition ? passed : failed).push(message);
const required = ['config/design_system.php', 'app/Http/Controllers/Api/DesignSystemController.php', 'tests/Unit/DesignSystemApiContractTest.php'];
required.forEach((file) => check(existsSync(file), `required file exists: ${file}`));
const config = read(required[0]);
const controller = read(required[1]);
const css = read('resources/css/app.css');
const manifest = read('public/manifest.webmanifest');
const routes = read('routes/api.php');
const context = read('app/Http/Controllers/Api/MobileContextController.php');

check(routes.includes("Route::get('/design-system'") && routes.includes('ability:design-system:read'), 'design-system endpoint requires its read ability');
check(config.includes("'default_theme' => 'dark'") && config.includes("'supported_themes' => ['dark', 'light']"), 'contract declares dark default and both themes');
check(config.includes("'direction' => 'rtl'") && config.includes("'family' => 'Cairo'"), 'contract preserves Arabic RTL typography');
check(config.includes("'brand' => '#00C4B4'") && css.includes('--ui-brand: #00C4B4;') && manifest.includes('"theme_color": "#00C4B4"'), 'brand color matches config, CSS, and manifest');
check(config.includes("'background' => '#020617'") && css.includes('--ui-bg: #020617;') && manifest.includes('"background_color": "#020617"'), 'dark background matches config, CSS, and manifest');
check(config.includes("'logo' => '/carled.svg'") && existsSync('public/carled.svg'), 'shared logo asset exists');
check(controller.includes("hash('sha256'") && controller.includes("->header('ETag'"), 'contract exposes a deterministic checksum and ETag');
check(controller.includes("'publish' => false") && controller.includes("'rollback' => false"), 'unsafe remote publishing and rollback remain disabled');
check(context.includes("'design_system_contract' => true") && context.includes("'design_system_publish' => false"), 'feature flags advertise the read-only contract');

passed.forEach((message) => console.log(`PASS: ${message}`));
failed.forEach((message) => console.error(`FAIL: ${message}`));
if (failed.length) process.exit(1);
console.log(`Design-system contract readiness passed (${passed.length} checks).`);
