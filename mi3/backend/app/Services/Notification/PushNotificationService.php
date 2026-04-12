<?php

namespace App\Services\Notification;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private ?WebPush $webPush = null;

    /**
     * Get or create the WebPush instance.
     */
    private function getWebPush(): WebPush
    {
        if ($this->webPush === null) {
            $auth = [
                'VAPID' => [
                    'subject' => config('app.url'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ],
            ];

            $this->webPush = new WebPush($auth);
            $this->webPush->setAutomaticPadding(false);
        }

        return $this->webPush;
    }

    /**
     * Send a push notification to all active subscriptions for a worker.
     *
     * @return int Number of notifications sent successfully
     */
    public function enviar(
        int $personalId,
        string $titulo,
        string $cuerpo,
        string $url = '/dashboard',
        string $prioridad = 'normal'
    ): int {
        $subscriptions = PushSubscription::where('personal_id', $personalId)
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload = json_encode([
            'title' => $titulo,
            'body' => $cuerpo,
            'url' => $url,
            'priority' => $prioridad,
            'badgeCount' => 1,
            'icon' => 'https://laruta11-images.s3.amazonaws.com/menu/logo-work.png',
            'badge' => 'https://laruta11-images.s3.amazonaws.com/menu/logo-work.png',
        ]);

        $webPush = $this->getWebPush();
        $sent = 0;

        foreach ($subscriptions as $sub) {
            $subData = $sub->subscription;

            try {
                $subscription = Subscription::create([
                    'endpoint' => $subData['endpoint'],
                    'publicKey' => $subData['keys']['p256dh'] ?? null,
                    'authToken' => $subData['keys']['auth'] ?? null,
                ]);

                $webPush->queueNotification($subscription, $payload);
            } catch (\Throwable $e) {
                // Skip invalid subscriptions
                $sub->update(['is_active' => false]);
            }
        }

        // Flush and check results
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } else {
                // Mark expired/invalid subscriptions as inactive
                $endpoint = $report->getEndpoint();
                PushSubscription::where('personal_id', $personalId)
                    ->where('is_active', true)
                    ->get()
                    ->filter(fn($s) => ($s->subscription['endpoint'] ?? '') === $endpoint)
                    ->each(fn($s) => $s->update(['is_active' => false]));
            }
        }

        return $sent;
    }

    /**
     * Subscribe a worker's device for push notifications.
     * Updates existing subscription if endpoint matches, or creates new one.
     */
    public function suscribir(int $personalId, array $subscription): void
    {
        $endpoint = $subscription['endpoint'] ?? '';

        // Deactivate existing subscription with same endpoint
        PushSubscription::where('personal_id', $personalId)
            ->where('is_active', true)
            ->get()
            ->filter(fn($s) => ($s->subscription['endpoint'] ?? '') === $endpoint)
            ->each(fn($s) => $s->update(['is_active' => false]));

        PushSubscription::create([
            'personal_id' => $personalId,
            'subscription' => $subscription,
            'is_active' => true,
        ]);
    }

    /**
     * Deactivate expired subscriptions (those that failed to send).
     *
     * @return int Number of subscriptions deactivated
     */
    public function desactivarExpiradas(): int
    {
        // Deactivate subscriptions older than 30 days that haven't been refreshed
        return PushSubscription::where('is_active', true)
            ->where('updated_at', '<', now()->subDays(30))
            ->update(['is_active' => false]);
    }
}
