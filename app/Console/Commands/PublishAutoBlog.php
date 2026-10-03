<?php

namespace App\Console\Commands;

use App\Services\Blog\AutoBlogPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('blog:auto {kind=daily : daily or weekly} {--date= : The day to write about (weekly: any day in, or the weekend before, the week)} {--force : Rewrite the post if it already exists, even when switched off} {--draft : Save a new post as a draft}')]
#[Description('Write the automatic daily IPO update or weekly IPO calendar blog post from IPO data')]
class PublishAutoBlog extends Command
{
    public function handle(AutoBlogPublisher $publisher): int
    {
        $kind = (string) $this->argument('kind');
        if (! array_key_exists($kind, AutoBlogPublisher::KINDS)) {
            $this->error('Kind must be one of: '.implode(', ', array_keys(AutoBlogPublisher::KINDS)));

            return self::INVALID;
        }

        $result = $publisher->run(
            $kind,
            $this->option('date') ? Carbon::parse((string) $this->option('date')) : null,
            (bool) $this->option('force'),
            $this->option('draft') ? false : null,
        );

        $this->line($result['message']);

        return self::SUCCESS;
    }
}
