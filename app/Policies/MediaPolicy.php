<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Who may open a private file (insurance, licenses, complaint photos — PLAN §9). Public photos have their
 * own URL and are not served here. Anyone else gets a 404, so a file's existence is never revealed.
 * Later phases add their own readers (e.g. the owner of the boat a complaint is about, Phase 7).
 */
class MediaPolicy
{
    public function view(User $user, Media $media): Response
    {
        $allowed = ! $media->isPublic()
            && ($user->can('access-admin') || ($user->isActive() && $media->uploaded_by === $user->getKey()));

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
