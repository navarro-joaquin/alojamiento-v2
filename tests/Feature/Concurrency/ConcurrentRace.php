<?php

namespace Tests\Feature\Concurrency;

use Closure;
use Illuminate\Console\Application;
use Illuminate\Process\InvokedProcessPool;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Laravel\SerializableClosure\SerializableClosure;
use RuntimeException;
use Throwable;

/**
 * Runs operations in separate PHP processes, each with its own MySQL
 * connection, so they really compete for the same rows.
 *
 * The test process locks the disputed row first and releases it only once
 * every operation is waiting on a lock. All of them are then inside their
 * transactions at the same time, and the outcome depends only on the locks
 * taken by the services. If a service did not lock, its process would never
 * wait and the race would report it.
 */
final class ConcurrentRace
{
    private const SECONDS_TO_WAIT_FOR_LOCKS = 20;

    /**
     * @param  array<string, Closure(): mixed>  $operations  Static closures: they are serialized into the child processes.
     * @return array{todasEsperaron: bool, resultados: array<string, array{resultado?: mixed, error?: class-string<Throwable>, mensaje?: string}>}
     */
    public static function ejecutar(string $table, int $id, array $operations): array
    {
        DB::beginTransaction();

        try {
            DB::table($table)->where('id', $id)->lockForUpdate()->first();

            $pool = self::iniciarProcesos($operations);
            $everyOperationWaited = self::esperarBloqueos($pool, count($operations));
        } finally {
            DB::commit();
        }

        return [
            'todasEsperaron' => $everyOperationWaited,
            'resultados' => self::recogerResultados($pool),
        ];
    }

    /**
     * @param  array<string, Closure(): mixed>  $operations
     */
    private static function iniciarProcesos(array $operations): InvokedProcessPool
    {
        $command = Application::formatCommandString('invoke-serialized-closure');

        return Process::pool(function (Pool $pool) use ($operations, $command): void {
            foreach ($operations as $key => $operation) {
                // Pest test classes do not exist in the child process: drop the closure scope.
                $operation = Closure::bind($operation, null, null);

                $capturingOperation = static function () use ($operation): array {
                    try {
                        return ['resultado' => $operation()];
                    } catch (Throwable $exception) {
                        return ['error' => $exception::class, 'mensaje' => $exception->getMessage()];
                    }
                };

                $pool->as($key)
                    ->path(base_path())
                    ->timeout(60)
                    ->env(['LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new SerializableClosure($capturingOperation)))])
                    ->command($command);
            }
        })->start();
    }

    /**
     * Waits until the given number of other connections of this database are
     * blocked on a locking read, seen twice in a row to skip reads that were
     * merely passing through. An operation that finishes first never waited.
     */
    private static function esperarBloqueos(InvokedProcessPool $pool, int $expectedWaits): bool
    {
        $ownConnectionId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $deadline = microtime(true) + self::SECONDS_TO_WAIT_FOR_LOCKS;
        $consecutiveMatches = 0;

        while (microtime(true) < $deadline && $pool->running()->count() === $expectedWaits) {
            $waitingConnections = DB::table('information_schema.processlist')
                ->where('db', DB::getDatabaseName())
                ->where('id', '!=', $ownConnectionId)
                ->where('info', 'like', '%for update%')
                ->count();

            $consecutiveMatches = $waitingConnections >= $expectedWaits ? $consecutiveMatches + 1 : 0;

            if ($consecutiveMatches === 2) {
                return true;
            }

            usleep(50_000);
        }

        return false;
    }

    /**
     * @return array<string, array{resultado?: mixed, error?: class-string<Throwable>, mensaje?: string}>
     */
    private static function recogerResultados(InvokedProcessPool $pool): array
    {
        return $pool->wait()->collect()->map(function ($result, string $key): array {
            if ($result->failed()) {
                throw new RuntimeException("La operación [{$key}] terminó con el código {$result->exitCode()}: {$result->errorOutput()}");
            }

            $payload = json_decode($result->output(), true);

            if (! ($payload['successful'] ?? false)) {
                throw new RuntimeException("La operación [{$key}] no devolvió un resultado: {$result->output()}");
            }

            return unserialize($payload['result']);
        })->all();
    }
}
