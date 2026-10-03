<?php

namespace App\Modules\Squad\Exceptions;

use RuntimeException;

/**
 * Thrown by SquadNumberService::assignNumberForNewPlayer when a new over-23
 * player needs a 1-25 slot, the slots are full of over-23 players, and the
 * 26-99 academy slots are full too — so the usual "bump the youngest U-23
 * to 26+" rescue has nowhere to put the youngster.
 *
 * Thrown BEFORE any number is modified, so no existing player silently
 * loses her dorsal: a null number means "deliberately unenrolled by the
 * user" (see SquadNumberService::reassignNumbers) and must never happen
 * without user action.
 */
class NoSquadNumberAvailableException extends RuntimeException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('game.squad_number_no_slots_available'));
    }
}
