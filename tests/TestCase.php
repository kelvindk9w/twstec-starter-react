<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Twstec\Kit\Foundation\Kit;

abstract class TestCase extends BaseTestCase
{
    /*
     * MÓDULOS OPCIONAIS (twstec/kit-accounts, twstec/kit-uploads,
     * twstec/kit-admin — escolhidos no `php artisan tws:install`): o teste
     * que exercita um deles fica no grupo com o NOME DO MÓDULO (`accounts`,
     * `uploads`, `admin`). Sem o módulo instalado (Kit::has), o teste PULA,
     * com o motivo — o mesmo arranjo do starter Livewire.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (Kit::OPTIONAL as $module) {
            if (in_array($module, $this->groups(), true) && ! Kit::has($module)) {
                $this->markTestSkipped(sprintf('Módulo opcional %s (%s) não instalado.', $module, Kit::package($module)));
            }
        }
    }

    /**
     * Módulo fingido ausente (Kit::pretendAbsent) não vaza para o próximo
     * teste.
     */
    protected function tearDown(): void
    {
        Kit::flushFakes();

        parent::tearDown();
    }

    /**
     * Trava de segurança: a suíte APAGA o banco (RefreshDatabase roda
     * `migrate:fresh`). Em SQLite ela usa memória; em qualquer outro banco,
     * só aceita um banco cujo nome termine em `_test`.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");
        $database = (string) config("database.connections.{$connection}.database");

        if ($driver !== 'sqlite' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Suíte recusada: o banco "%s" (%s) não é de teste. Use um banco cujo nome termine em _test (ver phpunit.pgsql.xml).',
                $database,
                $driver,
            ));
        }

        return parent::setUpTraits();
    }
}
