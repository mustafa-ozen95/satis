<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ConversationPolicy@view IDOR korumasının testi - README standartlar
 * dokümanında "kritik" olarak işaretlenen test: bir kullanıcı
 * başka bir kullanıcının konuşmasının ID'sini tahmin ederek görememeli.
 */
class ConversationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function makeConversation(User $owner): Conversation
    {
        $conversation = $owner->conversations()->create(['ilk_talep' => 'Test talebi']);
        $conversation->messages()->create(['role' => 'user', 'content' => 'Test talebi']);

        return $conversation;
    }

    public function test_owner_can_view_own_conversation(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);

        $response = $this->actingAs($owner)->get(route('quote.show', $conversation));

        $response->assertOk();
    }

    public function test_other_user_cannot_view_someone_elses_conversation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->makeConversation($owner);

        $response = $this->actingAs($other)->get(route('quote.show', $conversation));

        $response->assertRedirect(route('unauthorized'));
    }

    public function test_admin_can_view_any_conversation(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $conversation = $this->makeConversation($owner);

        $response = $this->actingAs($admin)->get(route('quote.show', $conversation));

        $response->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $owner = User::factory()->create();
        $conversation = $this->makeConversation($owner);

        $response = $this->get(route('quote.show', $conversation));

        $response->assertRedirect(route('login'));
    }
}
