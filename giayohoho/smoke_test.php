<?php
// Smoke test script - kiểm tra các route sau khi nâng cấp
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$tests = [
    // Core & health
    ['GET',  '/api/health',               [200],        'API Health Check'],
    // Auth routes (unauthenticated → expect 200 or 401/422)
    ['POST', '/api/auth/login',           [200,422],    'Auth: Login endpoint'],
    ['POST', '/api/auth/register',        [200,422],    'Auth: Register endpoint'],
    ['POST', '/api/auth/refresh',         [200,401,422],'Auth: Refresh token'],
    ['GET',  '/api/auth/sessions',        [200,401],    'Auth: Sessions list'],
    // Public catalog
    ['GET',  '/api/products',             [200],        'Catalog: Products list'],
    ['GET',  '/api/categories',           [200],        'Catalog: Categories'],
    ['GET',  '/api/surfaces',             [200],        'Catalog: Surfaces'],
    ['GET',  '/api/tags',                 [200],        'Catalog: Tags'],
    ['GET',  '/api/shipping/ghn/provinces',[200],       'Shipping: Provinces (GHN fallback)'],
    ['GET',  '/api/policies',             [200],        'Support: Policies page'],
    // Protected routes (no token → must 401)
    ['GET',  '/api/cart',                 [200,401],    'Cart: Get cart (auth)'],
    ['GET',  '/api/orders',               [200,401],    'Orders: List orders (auth)'],
    ['GET',  '/api/profile',              [200,401],    'Profile: Get profile (auth)'],
    ['GET',  '/api/wishlist',             [200,401],    'Wishlist: List (auth)'],
    // Admin routes (no token → must 401/403)
    ['GET',  '/api/admin/dashboard',      [200,401,403],'Admin: Dashboard'],
    ['GET',  '/api/admin/products',       [200,401,403],'Admin: Products'],
    ['GET',  '/api/admin/orders',         [200,401,403],'Admin: Orders'],
    ['GET',  '/api/admin/accounts',       [200,401,403],'Admin: Accounts'],
    ['GET',  '/api/admin/coupons',        [200,401,403],'Admin: Coupons'],
    // Frontend SPA routes (Laravel serves the Blade shell)
    ['GET',  '/',                         [200],        'Frontend: Home page (SPA shell)'],
    ['GET',  '/login',                    [200],        'Frontend: Login page'],
    ['GET',  '/products',                 [200],        'Frontend: Products page'],
    ['GET',  '/cart',                     [200],        'Frontend: Cart page'],
    ['GET',  '/admin',                    [200],        'Frontend: Admin page'],
];

$pass = 0;
$fail = 0;
$results = [];

foreach ($tests as [$method, $uri, $expectedStatuses, $label]) {
    $request  = Illuminate\Http\Request::create($uri, $method);
    if (str_starts_with($uri, '/api/')) {
        $request->headers->set('Accept', 'application/json');
    }
    $response = $kernel->handle($request);
    $actual   = $response->getStatusCode();
    $ok       = in_array($actual, $expectedStatuses);

    if ($ok) {
        $pass++;
        $symbol = 'PASS';
    } else {
        $fail++;
        $symbol = 'FAIL';
    }

    $results[] = compact('symbol', 'actual', 'method', 'uri', 'label');
}

// Output
echo PHP_EOL;
echo '=== GiayOhoho Smoke Test (post-upgrade) ===' . PHP_EOL;
echo PHP_EOL;

foreach ($results as $r) {
    $line = sprintf(
        '  [%s] HTTP %-3d  %-6s %s  (%s)',
        $r['symbol'],
        $r['actual'],
        $r['method'],
        $r['uri'],
        $r['label']
    );
    echo $line . PHP_EOL;
}

echo PHP_EOL;
echo sprintf('Result: %d PASSED, %d FAILED out of %d tests', $pass, $fail, count($tests)) . PHP_EOL;
echo PHP_EOL;
exit($fail > 0 ? 1 : 0);
