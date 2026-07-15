<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\Outbox\RelayOutboxMessages;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(new RelayOutboxMessages())->everyMinute()->name('relay-outbox-messages')->withoutOverlapping();
