<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

// Preferências de notificação: o catálogo e a regra do Livewire.

it('mostra o catálogo com os rótulos traduzidos', function () {
    $this->actingAs(User::factory()->create())->get('/notifications')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/notifications')
            ->has('preferences', count(config('notifications.preferences')))
            ->where('preferences.0.label', __('panel.notifications.pref_payment_confirmed')));
});

it('grava as escolhas e mantém o alerta de segurança sempre ligado', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/notifications')->put('/notifications', [
        'preferences' => ['payment_confirmed' => '1', 'security_alerts' => '0'],
    ])->assertRedirect('/notifications')->assertSessionHas('status', __('panel.notifications.saved'));

    $user->refresh();

    expect($user->notificationPreference('payment_confirmed'))->toBeTrue()
        ->and($user->notificationPreference('final_customer_receipt'))->toBeFalse()
        ->and($user->notificationPreference('api_key_events'))->toBeFalse()
        ->and($user->notificationPreference('security_alerts'))->toBeTrue();
});
