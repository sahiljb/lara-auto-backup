<?php

namespace SahilJB\LaraAutoBackup\Listeners;

use Illuminate\Support\Facades\Http;
use SahilJB\LaraAutoBackup\Events\BackupCompleted;
use SahilJB\LaraAutoBackup\Events\BackupFailed;
use Throwable;

class SendWebhookNotification
{
    public function handleFailure(BackupFailed $event): void
    {
        if (! config('auto-backup.notifications.on_failure', true)) {
            return;
        }

        $this->post(sprintf(
            '❌ [%s] Backup of [%s] failed: %s',
            config('app.name'),
            $event->connection,
            $event->exception->getMessage()
        ));
    }

    public function handleSuccess(BackupCompleted $event): void
    {
        if (! config('auto-backup.notifications.on_success', false)) {
            return;
        }

        $this->post(sprintf(
            '✅ [%s] Backup of [%s] uploaded: %s (%s) in %ss',
            config('app.name'),
            $event->result->database,
            $event->result->remotePath,
            $event->result->humanSize(),
            $event->result->duration
        ));
    }

    private function post(string $text): void
    {
        $url = config('auto-backup.notifications.webhook_url');

        if (! $url) {
            return;
        }

        try {
            Http::timeout(10)->post($url, ['text' => $text]);
        } catch (Throwable) {
            // A failing webhook must never mask the backup outcome.
        }
    }
}
