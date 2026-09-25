@extends('layouts.admin')

@section('title', 'Log in')

@section('content')
<div class="admin-login card card-pad">
    <div class="brand" style="margin-bottom:18px">
        @include('partials.brand-mark')
        <span class="brand-name">IPO <span>Darbaar</span> <small class="admin-tag">Admin</small></span>
    </div>
    <h1>Log in</h1>
    <p class="muted" style="margin:4px 0 20px">Only admin accounts can sign in here.</p>

    <form method="post" action="{{ route('admin.login.attempt') }}" class="admin-form">
        @csrf
        <label class="af">
            <span class="af-label">Email</span>
            <input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<span class="af-error">{{ $message }}</span>@enderror
        </label>
        <label class="af">
            <span class="af-label">Password</span>
            <input class="input" type="password" name="password" required autocomplete="current-password">
            @error('password')<span class="af-error">{{ $message }}</span>@enderror
        </label>
        <label class="af-check">
            <input type="checkbox" name="remember" value="1"> Keep me signed in
        </label>
        <button type="submit" class="btn btn-gold btn-block">Log in</button>
    </form>
</div>
@endsection
