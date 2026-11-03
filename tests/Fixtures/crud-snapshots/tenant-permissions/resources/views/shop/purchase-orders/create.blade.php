@extends(config('blueprint.layout', 'layouts.blueprint'))

@section('title', __('New :item', ['item' => __('Purchase Order')]))

@section('content')
    <h1>{{ __('New :item', ['item' => __('Purchase Order')]) }}</h1>

    <form method="POST" action="{{ route('purchase-orders.store') }}">
        @csrf
        @include('shop.purchase-orders._form')
        <button type="submit">{{ __('Create') }}</button>
    </form>

    <p><a href="{{ route('purchase-orders.index') }}">{{ __('Back to list') }}</a></p>
@endsection
