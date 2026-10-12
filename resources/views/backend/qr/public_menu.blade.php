<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $qr->title }} · {{ $general_setting->site_title ?? 'Menu' }}</title>
    <style>
        :root { --accent: {{ preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $qr->theme_color) ? $qr->theme_color : '#4f46e5' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f8fafc; color: #0f172a; }
        header { padding: 20px 16px; background: var(--accent); color: #fff; }
        header h1 { margin: 0; font-size: 22px; }
        header p { margin: 4px 0 0; opacity: .85; font-size: 13px; }
        nav { position: sticky; top: 0; display: flex; gap: 8px; overflow-x: auto; padding: 10px 16px; background: #fff; border-bottom: 1px solid #e2e8f0; }
        nav a { flex: 0 0 auto; padding: 6px 12px; border-radius: 999px; background: #f1f5f9; color: #0f172a; font-size: 13px; text-decoration: none; }
        main { max-width: 960px; margin: 0 auto; padding: 16px; }
        h2 { margin: 24px 0 10px; font-size: 17px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .item { display: flex; flex-direction: column; overflow: hidden; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; }
        .item img { width: 100%; height: 140px; object-fit: cover; background: #f1f5f9; }
        .item div { padding: 10px 12px; }
        .item strong { display: block; font-size: 14px; }
        .price { margin-top: 6px; color: var(--accent); font-weight: 700; }
        .price s { margin-right: 6px; color: #94a3b8; font-weight: 400; }
        .empty { padding: 40px 0; color: #64748b; text-align: center; }
    </style>
</head>
<body>
@php
    $money = fn ($value) => number_format((float) $value, $general_setting->decimal ?? 2);
    $grouped = $products->groupBy('category_id');
    $named = $categories->filter(fn ($category) => $grouped->has($category->id));
    $other = $grouped->except($categories->pluck('id')->all())->flatten(1);
@endphp
<header>
    <h1>{{ $qr->title }}</h1>
    <p>{{ $general_setting->company_name ?? $general_setting->site_title ?? '' }}</p>
</header>
@if($named->count() > 1)
    <nav aria-label="Categories">
        @foreach($named as $category)<a href="#category-{{ $category->id }}">{{ $category->name }}</a>@endforeach
    </nav>
@endif
<main>
    @forelse($named as $category)
        <section id="category-{{ $category->id }}">
            <h2>{{ $category->name }}</h2>
            <div class="grid">@foreach($grouped[$category->id] as $product)@include('backend.qr.public_menu_item')@endforeach</div>
        </section>
    @empty
        @if($other->isEmpty())<p class="empty">No items are published to the online menu yet.</p>@endif
    @endforelse
    @if($other->isNotEmpty())
        <section>
            <h2>More</h2>
            <div class="grid">@foreach($other as $product)@include('backend.qr.public_menu_item')@endforeach</div>
        </section>
    @endif
</main>
</body>
</html>
