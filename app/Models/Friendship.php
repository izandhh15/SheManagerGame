<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A friend request / friendship between two users. Directional: user_id is
 * the sender, friend_id the recipient. Status: pending | accepted. Removing
 * a friendship deletes the row.
 *
 * Federated friendships (is_federated): the other side lives on the peer
 * instance, so friend_id is NULL and the remote party is described by the
 * remote_* columns. user_id is always the LOCAL user involved; when the
 * remote side sent the request, is_remote_sender is true.
 */
class Friendship extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';

    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
        'is_federated',
        'is_remote_sender',
        'remote_user_id',
        'remote_username',
        'remote_club',
        'remote_instance',
        'remote_request_uuid',
    ];

    protected $casts = [
        'is_federated' => 'boolean',
        'is_remote_sender' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isFederated(): bool
    {
        return (bool) $this->is_federated;
    }

    /**
     * Human label for the peer instance (Wasmer / Vercel / host).
     */
    public function peerLabel(): ?string
    {
        if (! $this->isFederated() || ! $this->remote_instance) {
            return null;
        }

        $host = (string) parse_url($this->remote_instance, PHP_URL_HOST);

        if (str_contains($host, 'wasmer')) {
            return 'Wasmer';
        }

        if (str_contains($host, 'vercel')) {
            return 'Vercel';
        }

        return $host !== '' ? $host : null;
    }
}
