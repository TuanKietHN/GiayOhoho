<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class ProductMediaController extends Controller
{
    private const BACKGROUNDS = ['#f8fafc', '#f1f5f9', '#fef3c7', '#ecfeff', '#f0fdf4', '#fdf2f8'];
    private const ACCENTS = ['#0f766e', '#2563eb', '#b45309', '#7c3aed', '#be123c', '#334155'];

    public function show(string $slug, string $view)
    {
        if (! preg_match('/^[a-z0-9-]{1,120}$/', $slug) || ! preg_match('/^[a-z0-9-]{1,32}$/', $view)) {
            abort(404);
        }

        $title = $this->humanize($slug);
        $normalizedView = strtolower($view);
        $background = self::BACKGROUNDS[abs(crc32($slug.':'.$normalizedView)) % count(self::BACKGROUNDS)];
        $accent = self::ACCENTS[abs(crc32($slug)) % count(self::ACCENTS)];
        $subtitle = match ($normalizedView) {
            'side' => 'Side profile',
            'detail' => 'Material detail',
            'sole' => 'Outsole view',
            default => 'Primary view',
        };

        $svg = strtr(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="960" height="720" viewBox="0 0 960 720" role="img" aria-labelledby="title desc">
  <title id="title">{{title}}</title>
  <desc id="desc">{{desc}}</desc>
  <rect width="960" height="720" fill="{{background}}"/>
  <rect x="56" y="56" width="848" height="608" rx="32" fill="#ffffff" opacity="0.92"/>
  <circle cx="780" cy="150" r="82" fill="{{accent}}" opacity="0.16"/>
  <circle cx="190" cy="560" r="118" fill="{{accent}}" opacity="0.10"/>
  <path d="M180 422 C272 356 328 284 412 274 C480 266 540 306 608 312 C682 318 732 288 794 324 C824 342 834 374 814 402 C785 444 702 466 598 468 L306 468 C240 468 195 454 180 422 Z" fill="{{accent}}"/>
  <path d="M304 433 L608 433 C706 433 776 410 806 378 C818 406 788 444 704 466 C662 477 606 484 536 484 L294 484 C230 484 183 462 172 430 C196 439 236 442 304 433 Z" fill="#111827"/>
  <path d="M332 328 C372 300 426 292 484 310 C522 322 564 338 614 340 C562 358 498 368 434 356 C388 348 354 340 332 328 Z" fill="#ffffff" opacity="0.9"/>
  <path d="M356 351 L548 390" stroke="#ffffff" stroke-width="12" stroke-linecap="round" opacity="0.5"/>
  <path d="M386 333 L584 374" stroke="#ffffff" stroke-width="10" stroke-linecap="round" opacity="0.44"/>
  <path d="M302 486 C396 508 616 510 740 484" stroke="#111827" stroke-width="18" stroke-linecap="round" opacity="0.16"/>
  <text x="96" y="118" fill="#111827" font-family="Arial, sans-serif" font-size="28" font-weight="700">{{title}}</text>
  <text x="96" y="156" fill="#475569" font-family="Arial, sans-serif" font-size="18" font-weight="700" letter-spacing="2">{{subtitle}}</text>
  <text x="96" y="612" fill="#475569" font-family="Arial, sans-serif" font-size="20" font-weight="700">{{slug}}</text>
</svg>
SVG, [
            '{{title}}' => e($title),
            '{{desc}}' => e($title.' '.$subtitle),
            '{{background}}' => $background,
            '{{accent}}' => $accent,
            '{{subtitle}}' => e(strtoupper($subtitle)),
            '{{slug}}' => e($slug),
        ]);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=2592000',
        ]);
    }

    private function humanize(string $slug): string
    {
        return str($slug)->replace('-', ' ')->title()->toString();
    }
}
