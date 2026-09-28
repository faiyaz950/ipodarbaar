<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Models\IpoVote;
use App\Support\PageCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Records a visitor's "Will you apply?" vote. Visitors may change their answer until
 * the IPO lists. A random voter cookie identifies the visitor; it is issued with the
 * first vote, because IPO pages are served from the page cache and set no cookies.
 */
class IpoVoteController extends Controller
{
    public function store(Request $request, Ipo $ipo): JsonResponse
    {
        $data = $request->validate([
            'choice' => ['required', Rule::in(array_keys(IpoVote::CHOICES))],
        ]);

        if (! $ipo->pollIsOpen()) {
            return response()->json(['message' => 'Voting has closed because this IPO has listed.'], 422);
        }

        $voterId = (string) $request->cookie(IpoVote::COOKIE);
        if (! Str::isUuid($voterId)) {
            $voterId = (string) Str::uuid();
            Cookie::queue(IpoVote::COOKIE, $voterId, 60 * 24 * 365);
        }

        $voterHash = IpoVote::hash($voterId);
        $ipHash = IpoVote::hash((string) $request->ip());

        $isNewVoter = ! $ipo->votes()->where('voter_hash', $voterHash)->exists();
        if ($isNewVoter && $ipo->votes()->where('ip_hash', $ipHash)->count() >= IpoVote::MAX_PER_IP) {
            return response()->json(['message' => 'Too many votes from your network for this IPO.'], 429);
        }

        IpoVote::query()->upsert([[
            'ipo_id' => $ipo->id,
            'voter_hash' => $voterHash,
            'ip_hash' => $ipHash,
            'choice' => $data['choice'],
        ]], ['ipo_id', 'voter_hash'], ['choice', 'ip_hash']);

        IpoVote::forgetResults($ipo);
        PageCache::forget($ipo->url());

        return response()->json(IpoVote::results($ipo) + ['mine' => $data['choice']]);
    }
}
