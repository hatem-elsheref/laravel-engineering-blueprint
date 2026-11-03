@extends(config('blueprint.layout', 'layouts.blueprint'))

@section('title', __('Purchase Orders'))

@section('content')
    <h1>{{ __('Purchase Orders') }}</h1>

    @can('create', \App\Modules\Shop\Models\PurchaseOrder::class)
        <p><a href="{{ route('purchase-orders.create') }}">{{ __('New :item', ['item' => __('Purchase Order')]) }}</a></p>
    @endcan

    @if ($purchaseOrders->isEmpty())
        <p>{{ __('Nothing here yet.') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Quantity') }}</th>
                    <th>{{ __('Price') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th>{{ __('Tags') }}</th>
                    <th>{{ __('Shipped At') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseOrders as $purchaseOrder)
                    <tr>
                        <td>{{ $purchaseOrder->id }}</td>
                        <td>{{ $purchaseOrder->name }}</td>
                        <td>{{ $purchaseOrder->quantity }}</td>
                        <td>{{ $purchaseOrder->price }}</td>
                        <td>{{ $purchaseOrder->active ? __('Yes') : __('No') }}</td>
                        <td>{{ implode(', ', $purchaseOrder->tags ?? []) }}</td>
                        <td>{{ $purchaseOrder->shipped_at?->format('Y-m-d H:i') }}</td>
                        <td><a href="{{ route('purchase-orders.show', $purchaseOrder) }}">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{ $purchaseOrders->links() }}
    @endif
@endsection
