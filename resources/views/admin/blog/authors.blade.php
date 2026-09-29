@extends('layouts.admin')

@section('title', 'Blog authors')

@section('content')
<div class="admin-head">
    <div>
        <a class="link-gold" href="{{ route('admin.blog.index') }}"><x-icon name="arrow-left" :size="14" /> All posts</a>
        <h1>Blog authors</h1>
        <p class="muted">Each author has a public profile page. A clear bio (who writes, what sources are used, who reviews) builds trust with readers and Google.</p>
    </div>
</div>

@if ($errors->any())
    <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> {{ $errors->first() }}</div>
@endif

<div class="stack">
    @foreach ($authors as $item)
        <form method="post" action="{{ route('admin.blog.authors.update', $item) }}" class="card card-pad">
            @csrf
            @method('PUT')
            <div class="card-title af-section" style="display:flex;gap:10px;align-items:center">
                {{ $item->name }}
                <span class="muted" style="font-size:13px;font-weight:500">{{ $item->posts_count }} {{ \Illuminate\Support\Str::plural('post', $item->posts_count) }}</span>
                <a class="link-gold" style="margin-left:auto" href="{{ $item->url() }}" target="_blank" rel="noopener">Profile page <x-icon name="external" :size="13" /></a>
            </div>
            <div class="af-grid">
                <label class="af"><span class="af-label">Name</span><input class="input" type="text" name="name" value="{{ $item->name }}" required maxlength="120"></label>
                <label class="af"><span class="af-label">Role</span><input class="input" type="text" name="role" value="{{ $item->role }}" maxlength="120"></label>
            </div>
            <label class="af" style="margin-top:14px"><span class="af-label">Bio</span><textarea class="input" name="bio" rows="3" maxlength="1000">{{ $item->bio }}</textarea></label>
            <div class="btn-row" style="margin-top:14px"><button type="submit" class="btn btn-outline btn-sm">Save</button></div>
        </form>
    @endforeach

    <form method="post" action="{{ route('admin.blog.authors.store') }}" class="card card-pad">
        @csrf
        <div class="card-title af-section">Add an author</div>
        <div class="af-grid">
            <label class="af"><span class="af-label">Name</span><input class="input" type="text" name="name" value="{{ old('name') }}" required maxlength="120"></label>
            <label class="af"><span class="af-label">Role</span><input class="input" type="text" name="role" value="{{ old('role') }}" maxlength="120" placeholder="e.g. Senior Analyst"></label>
        </div>
        <label class="af" style="margin-top:14px"><span class="af-label">Bio</span><textarea class="input" name="bio" rows="3" maxlength="1000">{{ old('bio') }}</textarea></label>
        <div class="btn-row" style="margin-top:14px"><button type="submit" class="btn btn-gold btn-sm"><x-icon name="plus" :size="14" /> Add author</button></div>
    </form>
</div>
@endsection
