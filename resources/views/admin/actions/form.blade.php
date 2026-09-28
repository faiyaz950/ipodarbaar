@extends('layouts.admin')

@php
    use App\Models\CorporateAction;
    $types = CorporateAction::TYPES;
    $label = $types[$action->type]['label'] ?? 'Offer';
@endphp

@section('title', $action->exists ? 'Edit '.$action->company : 'Add '.$label)

@section('content')
<div class="admin-head">
    <div>
        <h1>{{ $action->exists ? $action->company : 'Add '.strtolower($label) }}</h1>
        <p class="muted">Fields that don't apply to this kind of offer can be left empty.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-outline btn-sm" href="{{ route('admin.actions.index') }}"><x-icon name="arrow-left" :size="14" /> All offers</a>
        @if ($action->exists && $action->is_published)
            <a class="btn btn-outline btn-sm" href="{{ $action->url() }}" target="_blank" rel="noopener"><x-icon name="external" :size="14" /> View page</a>
        @endif
    </div>
</div>

<form method="post" action="{{ $action->exists ? route('admin.actions.update', $action) : route('admin.actions.store') }}" class="stack">
    @csrf
    @if ($action->exists) @method('PUT') @endif

    <div class="card card-pad">
        <div class="card-title af-section">Offer</div>
        <div class="af-grid">
            <label class="af">
                <span class="af-label">Type</span>
                <select class="input" name="type">
                    @foreach ($types as $key => $meta)
                        <option value="{{ $key }}" @selected(old('type', $action->type) === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                @error('type')<span class="af-error">{{ $message }}</span>@enderror
            </label>
            <x-admin-input name="company" label="Company" type="text" :value="$action->company" required maxlength="160" />
            <x-admin-input name="exchange" label="Exchange" type="text" :value="$action->exchange" maxlength="40" placeholder="e.g. NSE, BSE" />
        </div>
        <div class="af-grid" style="margin-top:16px">
            <x-admin-input name="open_date" label="Opens" type="date" :value="$action->open_date" />
            <x-admin-input name="close_date" label="Closes" type="date" :value="$action->close_date" />
            <x-admin-input name="record_date" label="Record date" type="date" :value="$action->record_date" />
        </div>
        <div class="af-grid" style="margin-top:16px">
            <x-admin-input name="price" label="Price (₹): buyback / rights price, or NCD face value" :value="$action->price" min="0" />
            <x-admin-input name="size_cr" label="Size (₹ crore)" :value="$action->size_cr" min="0" />
        </div>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Type-specific details</div>
        <div class="af-grid">
            <x-admin-input name="method" label="Buyback method" type="text" :value="$action->method" maxlength="40" placeholder="Tender offer" />
            <x-admin-input name="ratio" label="Rights ratio (new : held)" type="text" :value="$action->ratio" maxlength="20" placeholder="1:5" />
            <x-admin-input name="coupon" label="NCD coupon" type="text" :value="$action->coupon" maxlength="120" placeholder="9.50% – 10.25%" />
            <x-admin-input name="tenure" label="NCD tenure" type="text" :value="$action->tenure" maxlength="80" placeholder="24 to 60 months" />
            <x-admin-input name="rating" label="NCD credit rating" type="text" :value="$action->rating" maxlength="80" placeholder="CRISIL AA-/Stable" />
        </div>
    </div>

    <div class="card card-pad">
        <div class="card-title af-section">Page content</div>
        <label class="af">
            <span class="af-label">Details (plain text; blank lines start a new paragraph)</span>
            <textarea class="input" name="details" rows="7" maxlength="10000">{{ old('details', $action->details) }}</textarea>
            @error('details')<span class="af-error">{{ $message }}</span>@enderror
        </label>
        <div class="af-grid" style="margin-top:16px">
            <x-admin-input name="source_url" label="Offer letter or exchange announcement (https link)" type="url" :value="$action->source_url" maxlength="500" />
            <label class="af">
                <span class="af-label">Visibility</span>
                <span><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $action->is_published))> Show on the site</span>
            </label>
        </div>
    </div>

    <div class="btn-row">
        <button type="submit" class="btn btn-gold">Save</button>
    </div>
</form>

@if ($action->exists)
    <form method="post" action="{{ route('admin.actions.destroy', $action) }}" style="margin-top:24px" onsubmit="return confirm('Delete this offer? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-outline btn-sm">Delete</button>
    </form>
@endif
@endsection
