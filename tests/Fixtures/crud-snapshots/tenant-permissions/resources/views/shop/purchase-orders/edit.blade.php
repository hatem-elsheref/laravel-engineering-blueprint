@extends(config('blueprint.layout', 'layouts.blueprint'))

@section('title', __('Edit :item #:id', ['item' => __('Purchase Order'), 'id' => $purchaseOrder->id]))

@section('content')
    <h1>{{ __('Edit :item #:id', ['item' => __('Purchase Order'), 'id' => $purchaseOrder->id]) }}</h1>

    <form method="POST" action="{{ route('purchase-orders.update', $purchaseOrder) }}">
        @csrf
        @method('PUT')
        @include('shop.purchase-orders._form')
        <button type="submit">{{ __('Save') }}</button>
    </form>

    <p><a href="{{ route('purchase-orders.show', $purchaseOrder) }}">{{ __('Cancel') }}</a></p>
@endsection
