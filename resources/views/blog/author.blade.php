@extends('layouts.app')

@php
    $page = $posts->currentPage();
@endphp

@section('title', $author->name.' – IPO Blog Author'.($page > 1 ? ' – Page '.$page : ''))
@section('description', \Illuminate\Support\Str::limit($author->bio ?: 'Posts by '.$author->name.' on the IPO Darbaar blog.', 160))
@if ($posts->total() === 0)
    @section('robots', 'noindex, follow')
@endif

@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/blog.css') }}?v={{ filemtime(public_path('assets/css/blog.css')) }}">
<x-jsonld :breadcrumbs="[['Home', route('home')], ['Blog', route('blog.index')], [$author->name, $author->url()]]" />
<x-jsonld :data="[
    '@context' => 'https://schema.org',
    '@type' => 'ProfilePage',
    'url' => $author->url(),
    'mainEntity' => [
        '@type' => 'Organization',
        'name' => $author->name,
        'description' => $author->bio,
        'url' => $author->url(),
        'parentOrganization' => ['@type' => 'Organization', 'name' => 'IPO Darbaar', 'url' => route('home')],
    ],
]" />
@endpush

@section('content')
<section class="page-head blog-head">
    <div class="container">
        <nav class="crumbs">
            <a href="{{ route('home') }}">Home</a> <x-icon name="chevron-right" :size="13" />
            <a href="{{ route('blog.index') }}">Blog</a> <x-icon name="chevron-right" :size="13" />
            <span>{{ $author->name }}</span>
        </nav>
        <h1>{{ $author->name }}</h1>
        @if ($author->role)<p class="lead" style="margin-top:4px">{{ $author->role }}</p>@endif
    </div>
</section>

<div class="page-body">
    <div class="container">
        <div class="card card-pad blog-author-box" style="margin-bottom:24px">
            <span class="blog-author-mark"><x-icon name="users" :size="22" /></span>
            <div>
                @if ($author->bio)<p style="margin-top:0">{{ $author->bio }}</p>@endif
                <a class="link" href="{{ route('editorial-policy') }}">How we research and review</a> · <a class="link" href="{{ route('about') }}">About IPO Darbaar</a>
            </div>
        </div>

        @if ($posts->isEmpty())
            <div class="card card-pad blog-empty"><p class="muted" style="margin:0">No posts yet.</p></div>
        @else
            <div class="blog-grid blog-grid-3">
                @foreach ($posts as $post)
                    @include('blog._card', ['post' => $post])
                @endforeach
            </div>
            @if ($posts->hasPages())
                <div style="margin-top:28px">{{ $posts->onEachSide(1)->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
