<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panel;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Preferências de notificação — o mesmo catálogo e a mesma regra do starter
 * Livewire (App\Livewire\Notifications\Preferences): catálogo em
 * config/notifications.php, escolha em users.notification_preferences,
 * leitura efetiva por User::notificationPreference(). Toggle `locked`
 * (alerta de segurança) é sempre gravado ligado, venha o que vier.
 */
final class NotificationPreferencesController
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $items = [];

        foreach ((array) config('notifications.preferences', []) as $key => $meta) {
            $items[] = [
                'key' => (string) $key,
                'label' => __("panel.notifications.pref_{$key}"),
                'hint' => __("panel.notifications.pref_{$key}_hint"),
                'locked' => (bool) ($meta['locked'] ?? false),
                'enabled' => $user->notificationPreference((string) $key),
            ];
        }

        return Inertia::render('settings/notifications', ['preferences' => $items]);
    }

    public function update(Request $request): RedirectResponse
    {
        $catalog = (array) config('notifications.preferences', []);
        $input = (array) $request->input('preferences', []);

        $saved = [];

        foreach ($catalog as $key => $meta) {
            $saved[$key] = ($meta['locked'] ?? false) ? true : filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);
        }

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['notification_preferences' => $saved])->save();

        return back()->with('status', __('panel.notifications.saved'));
    }
}
