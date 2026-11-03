<div>
    <label for="name">{{ __('Name') }}</label>
    <input id="name" name="name" type="text" value="{{ old('name', $order->name) }}">
    @error('name') <p role="alert">{{ $message }}</p> @enderror
</div>

<div>
    <label for="quantity">{{ __('Quantity') }}</label>
    <input id="quantity" name="quantity" type="number" step="1" value="{{ old('quantity', $order->quantity) }}">
    @error('quantity') <p role="alert">{{ $message }}</p> @enderror
</div>

<div>
    <label for="note">{{ __('Note') }}</label>
    <input id="note" name="note" type="text" value="{{ old('note', $order->note) }}">
    @error('note') <p role="alert">{{ $message }}</p> @enderror
</div>
