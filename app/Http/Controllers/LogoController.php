<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\LogoService;

/**
 * Only reached when public/logos/{file} doesn't exist yet: builds the thumbnail,
 * after which the web server serves the static file directly.
 */
class LogoController extends Controller
{
    public function __invoke(string $file, LogoService $logos)
    {
        abort_unless(preg_match('/^(?<slug>[a-z0-9-]+)-(?<hash>[0-9a-f]{8})\.webp$/', $file, $m), 404);

        $ipo = Ipo::where('slug', $m['slug'])->first();
        abort_if(! $ipo || $logos->filename($ipo) !== $file, 404);

        $path = $logos->ensure($ipo);
        abort_if(! $path, 404);

        return response()->file($path, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
