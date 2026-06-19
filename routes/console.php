<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// nightly maintenance; staggered to avoid a midnight thundering herd
Schedule::command('pipeline:archive-stale')->daily();

Schedule::command('invoices:advance-dunning')->dailyAt('00:05');

Schedule::command('installments:issue-due')->dailyAt('00:10');

Schedule::command('workorders:materialize')->dailyAt('00:15');

Schedule::command('contracts:expire-stale')->dailyAt('00:20');

// catch missed DocuSign webhooks by polling in-flight envelopes
Schedule::command('contracts:reconcile-signatures')->hourly();

// release expired holds and promote the holds queued behind them
Schedule::command('bookings:expire-holds')->dailyAt('00:25');

Schedule::command('reports:dispatch-scheduled')->hourly();

// AC-2(3)
Schedule::command('users:disable-inactive')->dailyAt('00:30');

Schedule::command('compliance:check-certificates')->dailyAt('00:35');

// expire time-bound role assignments; hourly to keep revocation lag under an hour
Schedule::command('roles:expire')->hourly();

// safety-net reindex; self-heals drift from writes that bypass Eloquent events
Schedule::command('search:reindex')->dailyAt('00:40');
