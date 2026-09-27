@extends('layouts.admin')

@section('title', 'Edit '.$ipo->name)

@section('content')
<div class="admin-head">
    <div>
        <a class="link-gold" href="{{ route('admin.ipos.index') }}"><x-icon name="arrow-left" :size="14" /> All IPOs</a>
        <h1>{{ $ipo->name }}</h1>
        <p class="muted">
            <span class="badge b-{{ $ipo->status() }}">{{ $ipo->statusLabel() }}</span>
            <span class="badge b-{{ $ipo->type }}">{{ $ipo->typeLabel() }}</span>
            API #{{ $ipo->api_id }} · updated {{ $ipo->updated_at?->diffForHumans() }}
        </p>
    </div>
    <div class="admin-actions">
        <a class="btn btn-outline btn-sm" href="{{ $ipo->url() }}" target="_blank" rel="noopener"><x-icon name="external" :size="15" /> View page</a>
    </div>
</div>

@include('admin.ipos.tabs', ['ipo' => $ipo])

@if ($errors->any())
    <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> Please fix the highlighted fields.</div>
@endif

{{-- Unlock buttons inside the main form submit these (forms can't be nested). --}}
@foreach ($ipo->locked_fields ?? [] as $field)
    <form id="unlock-{{ $field }}" method="post" action="{{ route('admin.ipos.unlock', $ipo) }}" hidden>
        @csrf
        <input type="hidden" name="field" value="{{ $field }}">
    </form>
@endforeach

<div class="layout">
    <form method="post" action="{{ route('admin.ipos.update', $ipo) }}" class="stack">
        @csrf
        @method('PUT')

        <div class="card card-pad">
            <div class="card-title af-section">Basics</div>
            <div class="af-grid">
                <x-admin-field :ipo="$ipo" name="name" label="Company name" type="text" required maxlength="255" />
                <label class="af {{ $ipo->isLocked('type') ? 'is-locked' : '' }}">
                    <span class="af-label">Type
                        @if ($ipo->isLocked('type'))<span class="badge b-gold">Locked</span><button type="submit" form="unlock-type" class="af-unlock">Unlock</button>@endif
                    </span>
                    <select class="input" name="type">
                        @foreach (['mainboard' => 'Mainboard', 'sme' => 'SME'] as $val => $lbl)
                            <option value="{{ $val }}" @selected(old('type', $ipo->type) === $val)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @error('type')<span class="af-error">{{ $message }}</span>@enderror
                </label>
                <x-admin-field :ipo="$ipo" name="exchange" label="Exchange" type="text" maxlength="40" placeholder="e.g. BSE, NSE or NSE SME" />
                <label class="af">
                    <span class="af-label">Registrar</span>
                    <select class="input" name="registrar">
                        <option value="">— Not set —</option>
                        @foreach ($registrars as $r)
                            <option value="{{ $r['name'] }}" @selected(old('registrar', $ipo->registrar) === $r['name'])>{{ $r['name'] }}</option>
                        @endforeach
                        @if ($ipo->registrar && ! collect($registrars)->contains('name', $ipo->registrar))
                            <option value="{{ $ipo->registrar }}" selected>{{ $ipo->registrar }}</option>
                        @endif
                    </select>
                    <span class="af-hint">Shows an allotment-status link on the IPO page.</span>
                    @error('registrar')<span class="af-error">{{ $message }}</span>@enderror
                </label>
            </div>
        </div>

        <div class="card card-pad">
            <div class="card-title af-section">Price & size</div>
            <div class="af-grid">
                <x-admin-field :ipo="$ipo" name="price_min" label="Price band – lower (₹)" min="0" />
                <x-admin-field :ipo="$ipo" name="price" label="Price band – upper (₹)" min="0" />
                <x-admin-field :ipo="$ipo" name="lot_size" label="Lot size (shares)" step="1" min="1" />
                <x-admin-field :ipo="$ipo" name="issue_size" label="Issue size (₹ crore)" min="0" />
                <x-admin-field :ipo="$ipo" name="gmp" label="GMP (₹)" hint="Saving a new GMP also records today’s value in the trend." />
            </div>
        </div>

        <div class="card card-pad">
            <div class="card-title af-section">Dates</div>
            <div class="af-grid">
                <x-admin-field :ipo="$ipo" name="open_date" label="Open date" type="date" />
                <x-admin-field :ipo="$ipo" name="close_date" label="Close date" type="date" />
                <x-admin-field :ipo="$ipo" name="listing_date" label="Listing date" type="date" />
            </div>
        </div>

        <div class="card card-pad">
            <div class="card-title af-section">Subscription (times)</div>
            <div class="af-grid">
                <x-admin-field :ipo="$ipo" name="subscription_retail" label="Retail (RII)" min="0" />
                <x-admin-field :ipo="$ipo" name="subscription_nii" label="NII / HNI" min="0" />
                <x-admin-field :ipo="$ipo" name="subscription_qib" label="QIB" min="0" />
                <x-admin-field :ipo="$ipo" name="subscription_total" label="Total" min="0" />
            </div>
            @if ($ipo->subscription_updated_at)
                <p class="af-hint" style="margin-top:10px">Last updated {{ $ipo->subscription_updated_at->diffForHumans() }}.</p>
            @endif
        </div>

        <div class="card card-pad">
            <div class="card-title af-section">Listing & editorial</div>
            <div class="af-grid">
                <x-admin-field :ipo="$ipo" name="listing_price" label="Actual listing price (₹)" min="0" hint="Shows listing gain on the IPO page." />
            </div>
            <label class="af" style="margin-top:14px">
                <span class="af-label">About the company</span>
                <textarea class="input" name="about" rows="7" maxlength="10000" placeholder="Business overview, financials, strengths & risks. Blank lines start new paragraphs.">{{ old('about', $ipo->about) }}</textarea>
                @error('about')<span class="af-error">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="admin-save">
            <button type="submit" class="btn btn-gold">Save changes</button>
            <a href="{{ route('admin.ipos.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>

    <aside class="sidebar stack">
        <div class="card">
            <div class="card-head"><div class="card-title">GMP history</div></div>
            @forelse ($history as $h)
                <div class="admin-row">
                    <span>{{ $h->date->format('D, j M') }}</span>
                    <b class="{{ $h->gmp > 0 ? 'up' : ($h->gmp < 0 ? 'down' : 'flat') }}">₹{{ \App\Models\Ipo::num($h->gmp) }}</b>
                </div>
            @empty
                <div class="table-empty">No GMP recorded yet.</div>
            @endforelse
        </div>

        @if ($ipo->description)
        <div class="card card-pad">
            <div class="card-title af-section">Source description</div>
            <p class="muted" style="font-size:13.5px">{{ $ipo->description }}</p>
        </div>
        @endif
    </aside>
</div>
@endsection
