<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de la carta de libertad a plazos.
 *
 * @property string $id
 * @property string $game_id
 * @property string|null $game_player_id
 * @property string $player_name
 * @property int $total_amount
 * @property int $amount_paid
 * @property int $monthly_amount
 * @property int $months_total
 * @property int $months_paid
 * @property string $status
 * @property \Illuminate\Support\Carbon $next_due_date
 * @property-read \App\Models\Game $game
 * @property-read int $remaining_amount
 * @property-read string $formatted_remaining_amount
 * @property-read string $formatted_monthly_amount
 * @property-read string $formatted_total_amount
 */
class SeverancePaymentPlan extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'game_id',
        'game_player_id',
        'player_name',
        'total_amount',
        'amount_paid',
        'monthly_amount',
        'months_total',
        'months_paid',
        'status',
        'next_due_date',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'amount_paid' => 'integer',
        'monthly_amount' => 'integer',
        'months_total' => 'integer',
        'months_paid' => 'integer',
        'next_due_date' => 'date',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getRemainingAmountAttribute(): int
    {
        return max(0, $this->total_amount - $this->amount_paid);
    }

    public function getFormattedRemainingAmountAttribute(): string
    {
        return Money::format($this->remaining_amount);
    }

    public function getFormattedMonthlyAmountAttribute(): string
    {
        return Money::format($this->monthly_amount);
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return Money::format($this->total_amount);
    }
}
