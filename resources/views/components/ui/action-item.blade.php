@props([
    'type' => 'link',     // link, button, modal, delete, divider
    'href' => '#',
    'icon' => null,
    'label' => '',
    'target' => null,     // modal target e.g. #editModal
    'action' => null,     // for type="delete", form route URL
    'confirm' => __('Are you sure want to delete?'),
    'method' => 'DELETE',
    'id' => null,
])

@if($type === 'divider')
    <li class="divider my-1" style="border-top: 1px solid var(--neo-border); opacity: 0.7;"></li>
@elseif($type === 'modal')
    <li role="presentation">
        <button type="button" 
                {{ $attributes->merge(['class' => 'btn btn-link zolo-action-item']) }} 
                data-toggle="modal" 
                data-target="{{ $target }}">
            @if($icon) <i class="{{ $icon }}"></i> @endif
            <span>{{ $label ?: $slot }}</span>
        </button>
    </li>
@elseif($type === 'delete')
    <li role="presentation">
        <form action="{{ $action }}" method="POST" class="d-inline zolo-delete-form" onsubmit="return confirm('{{ addslashes($confirm) }}')">
            @csrf
            @method($method)
            @if($id)
                <input type="hidden" name="id" value="{{ $id }}">
            @endif
            <button type="submit" {{ $attributes->merge(['class' => 'btn btn-link text-danger zolo-action-item zolo-action-delete']) }}>
                <i class="{{ $icon ?: 'dripicons-trash' }}"></i>
                <span>{{ $label ?: $slot ?: __('db.delete') }}</span>
            </button>
        </form>
    </li>
@elseif($type === 'button')
    <li role="presentation">
        <button type="button" {{ $attributes->merge(['class' => 'btn btn-link zolo-action-item']) }}>
            @if($icon) <i class="{{ $icon }}"></i> @endif
            <span>{{ $label ?: $slot }}</span>
        </button>
    </li>
@else
    <li role="presentation">
        <a href="{{ $href }}" {{ $attributes->merge(['class' => 'zolo-action-item']) }}>
            @if($icon) <i class="{{ $icon }}"></i> @endif
            <span>{{ $label ?: $slot }}</span>
        </a>
    </li>
@endif
