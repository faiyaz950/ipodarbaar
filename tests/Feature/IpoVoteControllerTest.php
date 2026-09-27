<?php

namespace Tests\Feature;

use App\Models\Ipo;
use App\Models\IpoVote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class IpoVoteControllerTest extends TestCase
{
    use RefreshDatabase;

    private const VOTER = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-24 10:00:00', 'Asia/Kolkata'));
    }

    public function test_vote_is_recorded_once_per_visitor_and_can_be_changed(): void
    {
        $ipo = Ipo::factory()->open()->create();

        $this->withCredentials()->withCookie(IpoVote::COOKIE, self::VOTER)
            ->postJson(route('ipos.vote', $ipo), ['choice' => 'apply'])
            ->assertOk()
            ->assertJson(['counts' => ['apply' => 1, 'skip' => 0], 'total' => 1, 'mine' => 'apply']);

        $this->withCredentials()->withCookie(IpoVote::COOKIE, self::VOTER)
            ->postJson(route('ipos.vote', $ipo), ['choice' => 'skip'])
            ->assertOk()
            ->assertJson(['counts' => ['apply' => 0, 'skip' => 1], 'total' => 1, 'mine' => 'skip']);

        $this->assertDatabaseCount('ipo_votes', 1);
        $this->assertDatabaseMissing('ipo_votes', ['voter_hash' => self::VOTER]);
    }

    public function test_vote_is_rejected_after_the_ipo_lists(): void
    {
        $ipo = Ipo::factory()->listed()->create();

        $this->withCredentials()->withCookie(IpoVote::COOKIE, self::VOTER)
            ->postJson(route('ipos.vote', $ipo), ['choice' => 'apply'])
            ->assertStatus(422);

        $this->assertDatabaseCount('ipo_votes', 0);
    }

    public function test_vote_without_the_voter_cookie_is_rejected(): void
    {
        $ipo = Ipo::factory()->open()->create();

        $this->postJson(route('ipos.vote', $ipo), ['choice' => 'apply'])->assertStatus(422);

        $this->assertDatabaseCount('ipo_votes', 0);
    }

    public function test_vote_rejects_an_unknown_choice(): void
    {
        $ipo = Ipo::factory()->open()->create();

        $this->withCredentials()->withCookie(IpoVote::COOKIE, self::VOTER)
            ->postJson(route('ipos.vote', $ipo), ['choice' => 'buy-now'])
            ->assertJsonValidationErrors('choice');
    }

    public function test_new_voters_from_one_ip_are_capped_with_429(): void
    {
        $ipo = Ipo::factory()->open()->create();
        IpoVote::factory()->count(IpoVote::MAX_PER_IP)->for($ipo)->create(['ip_hash' => IpoVote::hash('127.0.0.1')]);

        $this->withCredentials()->withCookie(IpoVote::COOKIE, (string) Str::uuid())
            ->postJson(route('ipos.vote', $ipo), ['choice' => 'apply'])
            ->assertStatus(429);

        $this->assertDatabaseCount('ipo_votes', IpoVote::MAX_PER_IP);
    }
}
