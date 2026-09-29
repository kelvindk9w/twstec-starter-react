<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

/**
 * PreventRequestForgery com a verificação ligada também na suíte (o
 * framework a desliga em testes).
 */
class EnforcedCsrfToken extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
