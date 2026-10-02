<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
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

        if ($driver === 'pgsql') {
            self::lockSuiteDatabase((array) config("database.connections.{$connection}"));
        }

        return parent::setUpTraits();
    }

    /**
     * Conexão que segura a trava da suíte no banco de teste, aberta uma vez
     * por processo e mantida até ele acabar (estática: sobrevive à aplicação
     * que cada teste recria).
     */
    private static ?PDO $suiteLock = null;

    /**
     * UMA SUÍTE POR VEZ NO BANCO DE TESTE (PostgreSQL). Duas execuções
     * simultâneas contra o mesmo `<banco>_test` se atropelam: o
     * `migrate:fresh` de uma apaga as tabelas no meio da outra, e o que um
     * teste grava com commit (as conexões próprias dos testes de concorrência
     * e do gatilho das contas demo) aparece na contagem de outro — falhas que
     * vão e vêm conforme o horário. A primeira execução pega uma trava
     * consultiva (`pg_try_advisory_lock`) numa conexão própria e a segura até
     * acabar; a segunda para logo, com o motivo, em vez de falhar ao acaso.
     *
     * @param  array<string, mixed>  $config
     */
    private static function lockSuiteDatabase(array $config): void
    {
        if (self::$suiteLock !== null) {
            return;
        }

        $database = (string) ($config['database'] ?? '');
        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? 5432, $database),
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $statement = $pdo->prepare('SELECT pg_try_advisory_lock(hashtext(?))');
        $statement->execute(['tws-kit-test-suite:'.$database]);

        if ($statement->fetchColumn() !== true) {
            throw new RuntimeException(sprintf(
                'Suíte recusada: outra execução está usando o banco de teste "%s" agora. Duas suítes ao mesmo tempo no mesmo banco se atropelam (migrate:fresh, linhas com commit) e dão falhas ao acaso — espere a outra terminar.',
                $database,
            ));
        }

        self::$suiteLock = $pdo;
    }
}
