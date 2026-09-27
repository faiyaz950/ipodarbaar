@extends('layouts.admin')

@section('title', 'Edit news')

@section('content')
<div class="admin-head">
    <div>
        <a class="link-gold" href="{{ route('admin.news.index') }}"><x-icon name="arrow-left" :size="14" /> All news</a>
        <h1>{{ $item['headline'] }}</h1>
        <p class="muted">
            <span class="badge {{ $override->is_hidden ? 'b-closed' : 'b-open' }}">{{ $override->is_hidden ? 'Hidden' : 'Showing' }}</span>
            @foreach ($item['edits'] as $label)<span class="badge b-gold">{{ $label }} edited</span> @endforeach
            API #{{ $item['id'] }} · {{ $item['category']['name'] }}{{ $item['date_label'] ? ' · '.$item['date_label'] : '' }}
        </p>
    </div>
    <div class="admin-actions">
        @unless ($override->is_hidden)
            <a class="btn btn-outline btn-sm" href="{{ $item['url'] }}" target="_blank" rel="noopener"><x-icon name="external" :size="15" /> View story</a>
        @endunless
    </div>
</div>

@if ($errors->any())
    <div class="flash err" role="alert"><x-icon name="alert" :size="17" /> Please fix the highlighted fields.</div>
@endif

<div class="layout">
    <form method="post" action="{{ route('admin.news.update', $item['id']) }}" enctype="multipart/form-data" class="stack">
        @csrf
        @method('PUT')

        <div class="card card-pad">
            <div class="card-title af-section">Headline</div>
            <label class="af">
                <span class="af-label">Headline (English)</span>
                <input class="input" type="text" name="headline" value="{{ old('headline', $form['headline']) }}" required maxlength="300">
                @error('headline')<span class="af-error">{{ $message }}</span>@enderror
            </label>
            <label class="af" style="margin-top:14px">
                <span class="af-label">Headline (Hinglish)</span>
                <input class="input" type="text" name="headline_hinglish" value="{{ old('headline_hinglish', $form['headline_hinglish']) }}" maxlength="300">
                <span class="af-hint">Leave blank to use the English headline.</span>
                @error('headline_hinglish')<span class="af-error">{{ $message }}</span>@enderror
            </label>
            <label class="af" style="margin-top:14px">
                <span class="af-label">Category</span>
                <select class="input" name="category_id">
                    @foreach ($categories as $cat)
                        <option value="{{ $cat['id'] }}" @selected((int) old('category_id', $form['category_id']) === $cat['id'])>{{ $cat['name'] }}</option>
                    @endforeach
                </select>
                @error('category_id')<span class="af-error">{{ $message }}</span>@enderror
            </label>
        </div>

        <div class="card card-pad">
            <div class="card-title af-section">Story text</div>
            <x-rich-editor name="news_detail" label="Text (English)" :value="$form['news_detail']"
                hint="Type like a normal document. Bullet points show as the key points in Shorts." />
            <div style="margin-top:14px">
                <x-rich-editor name="news_detail_hinglish" label="Text (Hinglish)" :value="$form['news_detail_hinglish']"
                    hint="Leave empty to use the English text." />
            </div>
        </div>

        <div class="card card-pad">
            <div class="card-title af-section">Image</div>
            @if ($item['image'])
                <img src="{{ $item['image'] }}" alt="" style="max-width:100%;max-height:220px;border-radius:12px;margin-bottom:12px;object-fit:cover">
            @endif
            <label class="af">
                <span class="af-label">Upload a new image</span>
                <input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <span class="af-hint">JPG, PNG or WebP, up to 3 MB. A wide (16:9) image looks best.</span>
                @error('image')<span class="af-error">{{ $message }}</span>@enderror
            </label>
            @if ($override->image_path)
                <label class="af-check" style="margin-top:10px"><input type="checkbox" name="remove_image" value="1"> Remove my image and use the API image again</label>
            @endif
        </div>

        <div class="card card-pad">
            <label class="af-check"><input type="checkbox" name="is_hidden" value="1" @checked(old('is_hidden', $override->is_hidden))> Hide this story from the site (news, shorts, home page and sitemap)</label>
        </div>

        <div class="admin-save">
            <button type="submit" class="btn btn-gold">Save changes</button>
            <a href="{{ route('admin.news.index') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>

    <aside class="sidebar stack">
        @if ($override->exists)
            <div class="card card-pad">
                <div class="card-title af-section">Undo edits</div>
                <p class="muted" style="font-size:13.5px">Removes every edit (and your uploaded image) so the story shows exactly what the API sends.</p>
                <form method="post" action="{{ route('admin.news.reset', $item['id']) }}" onsubmit="return confirm('Remove all edits for this story?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline btn-sm">Reset to API version</button>
                </form>
            </div>
        @endif

        <div class="card card-pad">
            <div class="card-title af-section">Original from API</div>
            <p style="font-weight:600;margin-bottom:6px">{{ $original['headline'] }}</p>
            <p class="muted" style="font-size:13.5px">{{ $original['summary'] }}</p>
        </div>
    </aside>
</div>
<script src="{{ asset('assets/js/admin-editor.js') }}?v={{ filemtime(public_path('assets/js/admin-editor.js')) }}" defer></script>
@endsection
