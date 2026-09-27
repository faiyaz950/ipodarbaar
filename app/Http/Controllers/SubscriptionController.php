<?php

namespace App\Http\Controllers;

use App\Mail\SubscriptionConfirmMail;
use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Email digest sign-up with double opt-in, plus one-click unsubscribe.
 */
class SubscriptionController extends Controller
{
    /** Don't re-send a confirmation to the same address more often than this. */
    private const RESEND_COOLDOWN_MINUTES = 10;

    private const PENDING_MESSAGE = 'Almost done! Check your inbox and tap the link to confirm.';

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'frequency' => ['nullable', Rule::in(array_keys(Subscriber::FREQUENCIES))],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        // Bots fill the hidden "website" field; they get the normal reply but nothing is stored.
        if ($request->filled('website')) {
            return $this->respond($request, self::PENDING_MESSAGE);
        }

        $subscriber = Subscriber::query()->firstOrNew(['email' => Str::lower(trim($data['email']))]);

        if ($subscriber->exists && $subscriber->isReceiving()) {
            return $this->respond($request, 'You are already subscribed. Look out for the next digest!');
        }

        $recentlySent = $subscriber->exists && $subscriber->updated_at?->gt(now()->subMinutes(self::RESEND_COOLDOWN_MINUTES));

        $subscriber->frequency = $data['frequency'] ?? 'daily';
        $subscriber->forceFill([
            'consent_at' => now(),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'ip_hash' => Subscriber::hashIp((string) $request->ip()),
        ])->save();

        if (! $recentlySent) {
            Mail::to($subscriber->email)->send(new SubscriptionConfirmMail($subscriber));
        }

        return $this->respond($request, self::PENDING_MESSAGE);
    }

    public function confirm(Subscriber $subscriber): View
    {
        if (! $subscriber->isReceiving()) {
            $subscriber->forceFill(['confirmed_at' => now(), 'unsubscribed_at' => null])->save();
        }

        return view('subscribe.confirmed', ['subscriber' => $subscriber]);
    }

    public function showUnsubscribe(Request $request, Subscriber $subscriber): View
    {
        return view('subscribe.unsubscribe', [
            'subscriber' => $subscriber,
            'action' => $request->fullUrl(),
            'done' => $subscriber->unsubscribed_at !== null,
        ]);
    }

    /**
     * Also the target of mail providers' one-click unsubscribe (RFC 8058), which posts
     * without a CSRF token; the signed URL proves the request is genuine.
     */
    public function unsubscribe(Subscriber $subscriber): View
    {
        if ($subscriber->unsubscribed_at === null) {
            $subscriber->forceFill(['unsubscribed_at' => now()])->save();
        }

        return view('subscribe.unsubscribe', [
            'subscriber' => $subscriber,
            'action' => null,
            'done' => true,
        ]);
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('subscribe_status', $message);
    }
}
