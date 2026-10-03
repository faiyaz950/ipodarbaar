<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Blog\AutoBlogPublisher;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switches for the automatic daily and weekly blog posts, and buttons to write one now.
 */
class BlogAutomationController extends Controller
{
    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $settings->set([
            'blog.auto.daily' => $request->boolean('daily'),
            'blog.auto.weekly' => $request->boolean('weekly'),
            'blog.auto.publish' => $request->boolean('publish'),
        ]);

        return redirect()->route('admin.blog.index')->with('status', 'Automatic post settings saved.');
    }

    public function run(Request $request, AutoBlogPublisher $publisher): RedirectResponse
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(AutoBlogPublisher::KINDS))]])['kind'];

        $result = $publisher->run($kind, force: true);

        return redirect()->route('admin.blog.index')->with($result['post'] ? 'status' : 'error', $result['message']);
    }
}
