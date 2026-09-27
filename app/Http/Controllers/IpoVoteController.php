<?php

namespace App\Http\Controllers;

use App\Models\Ipo;
use App\Models\IpoVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Records a visitor's "Will you apply?" vote. Visitors may change their answer until
 * the IPO lists; the random voter cookie is issued when the IPO page is viewed.
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
            return response()->json(['message' => 'Please reload the page and vote again.'], 422);
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

        return response()->json(IpoVote::results($ipo) + ['mine' => $data['choice']]);
    }
}
