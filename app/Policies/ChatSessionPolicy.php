<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ChatSession;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ChatSessionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ChatSession');
    }

    public function view(AuthUser $authUser, ChatSession $chatSession): bool
    {
        return $authUser->can('View:ChatSession');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ChatSession');
    }

    public function update(AuthUser $authUser, ChatSession $chatSession): bool
    {
        return $authUser->can('Update:ChatSession');
    }

    public function delete(AuthUser $authUser, ChatSession $chatSession): bool
    {
        return $authUser->can('Delete:ChatSession');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ChatSession');
    }

    public function restore(AuthUser $authUser, ChatSession $chatSession): bool
    {
        return $authUser->can('Restore:ChatSession');
    }

    public function forceDelete(AuthUser $authUser, ChatSession $chatSession): bool
    {
        return $authUser->can('ForceDelete:ChatSession');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ChatSession');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ChatSession');
    }

    public function replicate(AuthUser $authUser, ChatSession $chatSession): bool
    {
        return $authUser->can('Replicate:ChatSession');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ChatSession');
    }
}
