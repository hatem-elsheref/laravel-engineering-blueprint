<div>
    <label for="name">{{ __('Name') }}</label>
    <input id="name" name="name" type="text" value="{{ old('name', $purchaseOrder->name) }}">
    @error('name') <p role="alert">{{ $message }}</p> @enderror
</div>

<div>
    <label for="quantity">{{ __('Quantity') }}</label>
    <input id="quantity" name="quantity" type="number" step="1" value="{{ old('quantity', $purchaseOrder->quantity) }}">
    @error('quantity') <p role="alert">{{ $message }}</p> @enderror
</div>

<div>
    <label for="price">{{ __('Price') }}</label>
    <input id="price" name="price" type="number" step="any" value="{{ old('price', $purchaseOrder->price) }}">
    @error('price') <p role="alert">{{ $message }}</p> @enderror
</div>

<div>
    <label for="active">{{ __('Active') }}</label>
    <input type="hidden" name="active" value="0">
    <input id="active" name="active" type="checkbox" value="1" @checked(old('active', $purchaseOrder->active))>
    @error('active') <p role="alert">{{ $message }}</p> @enderror
</div>

<div>
    <label>{{ __('Tags') }}</label>
    @foreach ([...(array) old('tags', $purchaseOrder->tags ?? []), ''] as $value)
        <input name="tags[]" type="text" value="{{ $value }}" aria-label="{{ __('Tags') }}">
    @endforeach
    @error('tags') <p role="alert">{{ $message }}</p> @enderror
</div>

<div>
    <label for="shipped_at">{{ __('Shipped At') }}</label>
    <input id="shipped_at" name="shipped_at" type="datetime-local" value="{{ old('shipped_at', $purchaseOrder->shipped_at?->format('Y-m-d\TH:i')) }}">
    @error('shipped_at') <p role="alert">{{ $message }}</p> @enderror
</div>
