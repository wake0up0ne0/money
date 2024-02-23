<?php

namespace App\Listeners;

use App\Events\RateProcessed;

class ClearRateCache
{
    /**
     * Create the event listener.
     */
    public function __construct(RateProcessed $event)
    {
    }

    /**
     * Handle the event.
     */
    public function handle(RateProcessed $event): void
    {
        cache()->tags('currency_rates')->flush();
        logger('Rate cache cleared', ['id' => $event->rate->id, 'currency_to_id' => $event->rate->currencyTo->id]);
    }
}
