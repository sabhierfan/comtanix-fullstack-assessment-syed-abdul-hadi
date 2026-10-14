<?php

use Illuminate\Support\Facades\Schedule;

// Every day, delete login tokens that expired more than 24 hours ago,
// so the personal_access_tokens table does not keep growing forever.
// Runs when the server has `php artisan schedule:run` in cron (or `schedule:work` locally).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
