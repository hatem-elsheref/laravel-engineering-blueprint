@extends(config('blueprint.layout', 'layouts.blueprint'))

@section('title', __(':item #:id', ['item' => __('Purchase Order'), 'id' => $purchaseOrder->id]))

@section('content')
    <h1>{{ __(':item #:id', ['item' => __('Purchase Order'), 'id' => $purchaseOrder->id]) }}</h1>

    <dl>
            <dt>{{ __('Name') }}</dt>
            <dd>{{ $purchaseOrder->name }}</dd>
            <dt>{{ __('Quantity') }}</dt>
            <dd>{{ $purchaseOrder->quantity }}</dd>
            <dt>{{ __('Price') }}</dt>
            <dd>{{ $purchaseOrder->price }}</dd>
            <dt>{{ __('Active') }}</dt>
            <dd>{{ $purchaseOrder->active ? __('Yes') : __('No') }}</dd>
            <dt>{{ __('Tags') }}</dt>
            <dd>{{ implode(', ', $purchaseOrder->tags ?? []) }}</dd>
            <dt>{{ __('Shipped At') }}</dt>
            <dd>{{ $purchaseOrder->shipped_at?->format('Y-m-d H:i') }}</dd>
    </dl>

    <p>
        @can('update', $purchaseOrder)
            <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}">{{ __('Edit') }}</a>
        @endcan
        <a href="{{ route('purchase-orders.index') }}">{{ __('Back to list') }}</a>
    </p>

    @can('delete', $purchaseOrder)
        <form method="POST" action="{{ route('purchase-orders.destroy', $purchaseOrder) }}">
            @csrf
            @method('DELETE')
            <button type="submit">{{ __('Delete') }}</button>
        </form>
    @endcan
@endsection
