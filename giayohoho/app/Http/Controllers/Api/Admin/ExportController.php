<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function productsCsv(): StreamedResponse
    {
        return $this->productsExcelExport('products.csv');
    }

    public function ordersCsv(): StreamedResponse
    {
        return $this->streamCsv('orders.csv', ['id', 'account_id', 'total', 'sub_total', 'discount_amount', 'status', 'created_at'], function ($out) {
            OrderDetail::chunk(500, function ($rows) use ($out) {
                foreach ($rows as $order) {
                    fputcsv($out, [
                        $order->id,
                        $order->account_id,
                        $order->total,
                        $order->sub_total,
                        $order->discount_amount,
                        $order->status,
                        $order->created_at,
                    ]);
                }
            });
        });
    }

    public function accountsTemplate(): StreamedResponse
    {
        return $this->streamCsv('accounts-template.csv', $this->accountHeaders(), function ($out) {
            fputcsv($out, ['customer01', 'customer01@example.com', 'Customer', 'One', '0900000001', 'CUSTOMER', 'ACTIVE', 'false', 'Password@123']);
        });
    }

    public function accountsExport(): StreamedResponse
    {
        return $this->streamCsv('accounts-export.csv', $this->accountHeaders(), function ($out) {
            User::withTrashed()
                ->with('roles')
                ->orderBy('id')
                ->chunk(500, function ($users) use ($out) {
                    foreach ($users as $user) {
                        fputcsv($out, [
                            $user->username,
                            $user->email,
                            $user->first_name,
                            $user->last_name,
                            $user->phone_number,
                            $user->roles->pluck('name')->implode('|'),
                            $user->status,
                            $user->locked ? 'true' : 'false',
                            '',
                        ]);
                    }
                });
        });
    }

    public function accountsValidate(Request $request)
    {
        [$rows, $readErrors] = $this->readCsvRows($request);
        $errors = array_merge($readErrors, $this->validateAccountRows($rows));

        return $this->ok([
            'valid' => count($errors) === 0,
            'rowCount' => count($rows),
            'errors' => $errors,
            'format' => 'csv',
        ]);
    }

    public function accountsImport(Request $request)
    {
        [$rows, $readErrors] = $this->readCsvRows($request);
        $errors = array_merge($readErrors, $this->validateAccountRows($rows));
        if ($errors) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid account import file',
                'data' => [
                    'successCount' => 0,
                    'errors' => $errors,
                    'format' => 'csv',
                ],
                'timestamp' => now()->getTimestampMs(),
            ], 422);
        }

        $successCount = DB::transaction(function () use ($rows) {
            $count = 0;

            foreach ($rows as $row) {
                $email = strtolower($this->field($row, 'email'));
                $username = $this->field($row, 'username');
                $firstName = $this->field($row, 'first_name') ?: $username;
                $lastName = $this->field($row, 'last_name') ?: null;
                $status = strtoupper($this->field($row, 'status') ?: 'ACTIVE');
                $locked = $this->booleanField($row, 'locked', $status !== 'ACTIVE');
                $password = $this->field($row, 'password');

                $user = User::withTrashed()
                    ->where('email', $email)
                    ->orWhere('username', $username)
                    ->first();

                $payload = [
                    'username' => $username,
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => trim($firstName.' '.($lastName ?? '')),
                    'phone_number' => $this->field($row, 'phone_number') ?: null,
                    'status' => $status,
                    'locked' => $locked,
                    'email_verified' => true,
                    'email_verified_at' => now(),
                ];
                if ($password !== '') {
                    $payload['password'] = $password;
                } elseif (! $user) {
                    $payload['password'] = Str::random(32);
                }

                if ($user) {
                    $user->fill($payload)->save();
                    if ($user->trashed() && $status !== 'DELETED') {
                        $user->restore();
                    }
                } else {
                    $user = User::create($payload);
                }

                $roleIds = $this->roleIds($this->field($row, 'roles') ?: $this->field($row, 'role') ?: 'CUSTOMER');
                $user->roles()->sync($roleIds);
                $count++;
            }

            return $count;
        });

        return $this->ok([
            'successCount' => $successCount,
            'errors' => [],
            'format' => 'csv',
        ], 'Accounts imported');
    }

    public function productsTemplate(): StreamedResponse
    {
        return $this->streamCsv('products-template.csv', $this->productHeaders(), function ($out) {
            fputcsv($out, ['nike-pegasus-41', 'Nike Pegasus 41', 'Nike', 'UNISEX', 'running', 'Running', '2590000', 'Daily running shoe', 'NIKE-PEGASUS-41-42-BLACK', '42', 'Black', '20', '0']);
        });
    }

    public function productsExcelExport(?string $filename = null): StreamedResponse
    {
        return $this->streamCsv($filename ?: 'products-export.csv', $this->productHeaders(), function ($out) {
            Product::with(['category', 'variants'])
                ->orderBy('id')
                ->chunk(200, function ($products) use ($out) {
                    foreach ($products as $product) {
                        if ($product->variants->isEmpty()) {
                            fputcsv($out, $this->productRow($product));
                            continue;
                        }

                        foreach ($product->variants as $variant) {
                            fputcsv($out, $this->productRow($product, $variant));
                        }
                    }
                });
        });
    }

    public function productsValidate(Request $request)
    {
        [$rows, $readErrors] = $this->readCsvRows($request);
        $errors = array_merge($readErrors, $this->validateProductRows($rows));

        return $this->ok([
            'valid' => count($errors) === 0,
            'rowCount' => count($rows),
            'errors' => $errors,
            'format' => 'csv',
        ]);
    }

    public function productsImport(Request $request)
    {
        [$rows, $readErrors] = $this->readCsvRows($request);
        $errors = array_merge($readErrors, $this->validateProductRows($rows));
        if ($errors) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid product import file',
                'data' => [
                    'successCount' => 0,
                    'errors' => $errors,
                    'format' => 'csv',
                ],
                'timestamp' => now()->getTimestampMs(),
            ], 422);
        }

        $successCount = DB::transaction(function () use ($rows) {
            $count = 0;

            foreach ($rows as $row) {
                $category = $this->categoryFromRow($row);
                $name = $this->field($row, 'name');
                $slug = $this->slug($this->field($row, 'slug') ?: $name, 'product');
                $product = Product::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'category_id' => $category?->id,
                        'name' => $name,
                        'brand' => $this->field($row, 'brand') ?: null,
                        'gender' => strtoupper($this->field($row, 'gender') ?: 'UNISEX'),
                        'base_price' => $this->integerField($row, 'base_price'),
                        'original_price' => $this->field($row, 'original_price') !== '' ? $this->integerField($row, 'original_price') : null,
                        'description' => $this->field($row, 'description') ?: null,
                    ]
                );

                $sku = $this->field($row, 'sku');
                $size = $this->field($row, 'size');
                $color = $this->field($row, 'color');
                if ($sku !== '' || $size !== '' || $color !== '') {
                    ProductVariant::updateOrCreate(
                        ['sku' => $sku ?: $this->sku($slug, $size, $color)],
                        [
                            'product_id' => $product->id,
                            'size' => $size ?: null,
                            'color' => $color ?: null,
                            'stock' => $this->integerField($row, 'stock'),
                            'extra_price' => $this->integerField($row, 'extra_price'),
                        ]
                    );
                }

                $count++;
            }

            return $count;
        });

        return $this->ok([
            'successCount' => $successCount,
            'errors' => [],
            'format' => 'csv',
        ], 'Products imported');
    }

    public function variantsExport(): StreamedResponse
    {
        return $this->streamCsv('variants-export.csv', ['id', 'product_id', 'product_slug', 'product_name', 'sku', 'size', 'color', 'stock', 'extra_price'], function ($out) {
            ProductVariant::with('product')
                ->orderBy('id')
                ->chunk(500, function ($variants) use ($out) {
                    foreach ($variants as $variant) {
                        fputcsv($out, [
                            $variant->id,
                            $variant->product_id,
                            $variant->product?->slug,
                            $variant->product?->name,
                            $variant->sku,
                            $variant->size,
                            $variant->color,
                            $variant->stock,
                            $variant->extra_price,
                        ]);
                    }
                });
        });
    }

    public function dashboardExport(): StreamedResponse
    {
        return $this->streamCsv('dashboard-export.csv', ['metric', 'value'], function ($out) {
            $paidStatuses = ['PAID', 'DELIVERED', 'DONE'];
            $rows = [
                ['generated_at', now()->toDateTimeString()],
                ['total_orders', OrderDetail::count()],
                ['pending_orders', OrderDetail::where('status', 'PENDING')->count()],
                ['paid_orders', OrderDetail::whereIn('status', $paidStatuses)->count()],
                ['gross_revenue', (int) OrderDetail::whereIn('status', $paidStatuses)->sum('total')],
                ['total_accounts', User::withTrashed()->count()],
                ['active_accounts', User::where('status', 'ACTIVE')->count()],
                ['total_products', Product::count()],
                ['total_variants', ProductVariant::count()],
                ['total_stock', (int) ProductVariant::sum('stock')],
            ];

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        });
    }

    private function accountHeaders(): array
    {
        return ['username', 'email', 'first_name', 'last_name', 'phone_number', 'roles', 'status', 'locked', 'password'];
    }

    private function productHeaders(): array
    {
        return ['slug', 'name', 'brand', 'gender', 'category_slug', 'category_name', 'base_price', 'description', 'sku', 'size', 'color', 'stock', 'extra_price'];
    }

    private function productRow(Product $product, ?ProductVariant $variant = null): array
    {
        return [
            $product->slug,
            $product->name,
            $product->brand,
            $product->gender,
            $product->category?->slug,
            $product->category?->name,
            $product->base_price,
            $product->description,
            $variant?->sku,
            $variant?->size,
            $variant?->color,
            $variant?->stock,
            $variant?->extra_price,
        ];
    }

    private function streamCsv(string $filename, array $headers, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($headers, $writer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $writer($out);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function readCsvRows(Request $request): array
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return [[], [['line' => 0, 'message' => 'Cannot read uploaded file']]];
        }

        $headers = null;
        $rows = [];
        $line = 0;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;
            if ($headers === null) {
                $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), $data);
                continue;
            }

            if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = ['_line' => $line];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($data[$index] ?? ''));
                }
            }
            $rows[] = $row;
        }

        fclose($handle);

        if ($headers === null || count(array_filter($headers)) === 0) {
            return [[], [['line' => 0, 'message' => 'Missing CSV header row']]];
        }

        return [$rows, []];
    }

    private function validateAccountRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            $line = $row['_line'] ?? 0;
            if ($this->field($row, 'username') === '') {
                $errors[] = ['line' => $line, 'field' => 'username', 'message' => 'Username is required'];
            }
            if (! filter_var($this->field($row, 'email'), FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $line, 'field' => 'email', 'message' => 'Valid email is required'];
            }
            $status = strtoupper($this->field($row, 'status') ?: 'ACTIVE');
            if (! in_array($status, ['ACTIVE', 'LOCKED', 'DELETED'], true)) {
                $errors[] = ['line' => $line, 'field' => 'status', 'message' => 'Status must be ACTIVE, LOCKED or DELETED'];
            }
        }

        return $errors;
    }

    private function validateProductRows(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            $line = $row['_line'] ?? 0;
            if ($this->field($row, 'name') === '') {
                $errors[] = ['line' => $line, 'field' => 'name', 'message' => 'Product name is required'];
            }
            if (! is_numeric($this->field($row, 'base_price'))) {
                $errors[] = ['line' => $line, 'field' => 'base_price', 'message' => 'Base price must be numeric'];
            }
            foreach (['stock', 'extra_price'] as $field) {
                if ($this->field($row, $field) !== '' && ! is_numeric($this->field($row, $field))) {
                    $errors[] = ['line' => $line, 'field' => $field, 'message' => Str::headline($field).' must be numeric'];
                }
            }
        }

        return $errors;
    }

    private function categoryFromRow(array $row): ?Category
    {
        $categoryName = $this->field($row, 'category_name');
        $categorySlug = $this->field($row, 'category_slug');
        if ($categoryName === '' && $categorySlug === '') {
            return null;
        }

        $slug = $this->slug($categorySlug ?: $categoryName, 'category');

        return Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $categoryName ?: Str::headline(str_replace('-', ' ', $slug))]
        );
    }

    private function roleIds(string $roles): array
    {
        return collect(preg_split('/[|,;]/', $roles) ?: [])
            ->map(fn ($role) => strtoupper(trim($role)))
            ->filter()
            ->map(fn ($role) => Role::firstOrCreate(['name' => $role], ['description' => $role])->id)
            ->values()
            ->all();
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim(ltrim($header, "\xEF\xBB\xBF")));

        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $header), '_');
    }

    private function field(array $row, string $field): string
    {
        return trim((string) ($row[$field] ?? ''));
    }

    private function booleanField(array $row, string $field, bool $default = false): bool
    {
        $value = strtolower($this->field($row, $field));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'true', 'yes', 'y', 'locked'], true);
    }

    private function integerField(array $row, string $field): int
    {
        return max(0, (int) round((float) ($this->field($row, $field) ?: 0)));
    }

    private function slug(string $value, string $fallback): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : $fallback.'-'.Str::lower(Str::random(8));
    }

    private function sku(string $slug, string $size, string $color): string
    {
        $sku = strtoupper(Str::slug($slug.'-'.$size.'-'.$color));

        return $sku !== '' ? $sku : 'SKU-'.strtoupper(Str::random(8));
    }
}
