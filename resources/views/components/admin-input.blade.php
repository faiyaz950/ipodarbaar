@props(['name', 'label', 'value' => null, 'type' => 'number', 'step' => 'any', 'hint' => null])
@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $current = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
@endphp
<label class="af">
    <span class="af-label">{{ $label }}</span>
    <input class="input" type="{{ $type }}" name="{{ $name }}" value="{{ old($key, $current) }}" @if($type === 'number') step="{{ $step }}" @endif {{ $attributes }}>
    @if ($hint)<span class="af-hint">{{ $hint }}</span>@endif
    @error($key)<span class="af-error">{{ $message }}</span>@enderror
</label>
