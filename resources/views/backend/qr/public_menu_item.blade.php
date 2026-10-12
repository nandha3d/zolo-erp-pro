@php $image = trim(explode(',', (string) $product->image)[0] ?? ''); @endphp
<article class="item">
    @if($image !== '' && $image !== 'zummXD2dvAtI.png')<img src="{{ asset('images/product/'.$image) }}" alt="" loading="lazy">@endif
    <div>
        <strong>{{ $product->name }}</strong>
        <p class="price">
            @if($product->promotion && $product->promotion_price)<s>{{ $money($product->price) }}</s>{{ $money($product->promotion_price) }}
            @else{{ $money($product->price) }}@endif
        </p>
    </div>
</article>
