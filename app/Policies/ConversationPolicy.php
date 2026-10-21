<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Sadece konuşmanın sahibi ya da admin görebilir/mesaj gönderebilir.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->isAdmin() || $conversation->user_id === $user->id;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
