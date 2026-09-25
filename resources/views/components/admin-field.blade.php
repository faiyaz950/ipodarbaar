@props(['ipo', 'name', 'label', 'type' => 'number', 'step' => 'any', 'hint' => null])

@php
    $current = $ipo->{$name};
    $value = old($name, $current instanceof \DateTimeInterface ? $current->format('Y-m-d') : $current);
    $locked = $ipo->isLocked($name);
@endphp

<label class="af {{ $locked ? 'is-locked' : '' }}">
    <span class="af-label">
        {{ $label }}
        @if ($locked)
            <span class="badge b-gold" title="The sync keeps this value">Locked</span>
            <button type="submit" form="unlock-{{ $name }}" class="af-unlock">Unlock</button>
        @endif
    </span>
    <input class="input" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @if($type === 'number') step="{{ $step }}" @endif {{ $attributes }}>
    @if ($hint)<span class="af-hint">{{ $hint }}</span>@endif
    @error($name)<span class="af-error">{{ $message }}</span>@enderror
</label>
