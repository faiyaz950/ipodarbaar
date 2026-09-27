<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Services\ShareCardService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Only reached when public/og/ipo/{file} doesn't exist yet: renders the share card,
 * after which the web server serves the static file directly.
 */
class OgImageController extends Controller
{
    public function __invoke(string $file, ShareCardService $cards): BinaryFileResponse
    {
        abort_unless(preg_match('/^(?<slug>[a-z0-9-]+)-[0-9a-f]{10}\.png$/', $file, $m), 404);

        $ipo = Ipo::query()->where('slug', $m['slug'])->first();
        abort_if(! $ipo || $cards->filename($ipo) !== $file, 404);

        $path = $cards->ensure($ipo);
        abort_if(! $path, 404);

        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
