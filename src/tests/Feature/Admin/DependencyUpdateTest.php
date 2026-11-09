<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\System\Enums\DependencyUpdateStatus;
use App\Domain\System\Enums\DependencyUpdateType;
use App\Domain\System\Models\DependencyUpdate;
use App\Domain\System\Services\DependencyAuditService;
use App\Domain\System\Services\DependencyUpdateService;
use App\Livewire\Admin\System\DependencyAudit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Servicio de actualización con todos los comandos externos y pasos de disco
 * simulados: ni ejecuta composer/npm/mysqldump ni toca vendor. Permite probar
 * la orquestación (respaldo, actualización, reversión) de forma determinista.
 */
class FakeDependencyUpdateService extends DependencyUpdateService
{
    public bool $failUpdate = false;
    public bool $failBackup = false;

    protected function artifactIsValid(?string $path): bool
    {
        return ! $this->failBackup; // en fallo de respaldo, el artefacto "no sirve"
    }

    protected function exec(\App\Domain\System\Models\DependencyUpdate $operation, array $command, int $timeout, bool $mustSucceed = true, array $env = [], bool $logCommand = true): \Illuminate\Contracts\Process\ProcessResult
    {
        // Simula el fallo de la actualización de Composer sin tocar el sistema.
        if ($this->failUpdate && ($command[0] ?? null) === 'composer' && ($command[1] ?? null) === 'update') {
            throw new \RuntimeException('composer update falló (simulado).');
        }

        return parent::exec($operation, $command, $timeout, $mustSucceed, $env, $logCommand);
    }

    protected function freeBytes(): ?int
    {
        return PHP_INT_MAX; // siempre hay espacio en las pruebas
    }

    protected function databaseConfig(): array
    {
        // En pruebas la base es SQLite; se simula una MySQL (los comandos
        // mysqldump/mysql están fakeados y no se conectan).
        return [
            'host' => '127.0.0.1', 'port' => '3306', 'database' => 'test',
            'username' => 'test', 'password' => 'secret',
        ];
    }
}

class DependencyUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Process::fake(); // ningún comando externo corre de verdad
        config(['dependencies.backup_path' => storage_path('app/test-backups')]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-backups'));
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    /** Doble de auditoría que reporta un paquete de Composer vulnerable. */
    private function bindAuditWithVulnerability(): void
    {
        $this->app->bind(DependencyAuditService::class, fn () => new class extends DependencyAuditService {
            protected function rawComposerAudit(): ?string
            {
                return json_encode([
                    'advisories' => [
                        'vendor/paquete' => [['severity' => 'high', 'title' => 'Fallo', 'cve' => 'CVE-1']],
                    ],
                    'abandoned' => [],
                ]);
            }

            protected function rawNpmAudit(): ?string
            {
                return json_encode(['vulnerabilities' => []]);
            }
        });
    }

    private function bindAuditClean(): void
    {
        $this->app->bind(DependencyAuditService::class, fn () => new class extends DependencyAuditService {
            protected function rawComposerAudit(): ?string
            {
                return json_encode(['advisories' => [], 'abandoned' => []]);
            }

            protected function rawNpmAudit(): ?string
            {
                return json_encode(['vulnerabilities' => []]);
            }
        });
    }

    // ------------------------------------------------------------------
    // Permisos y acceso
    // ------------------------------------------------------------------

    public function test_staff_cannot_access_panel(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(DependencyAudit::class)
            ->assertForbidden();
    }

    public function test_update_button_hidden_without_vulnerabilities(): void
    {
        $this->bindAuditClean();
        app(DependencyAuditService::class)->runAndStore();

        Livewire::actingAs($this->admin())
            ->test(DependencyAudit::class)
            ->assertDontSee('Actualizar vulnerables');
    }

    public function test_update_button_shown_with_vulnerabilities(): void
    {
        $this->bindAuditWithVulnerability();
        app(DependencyAuditService::class)->runAndStore();

        Livewire::actingAs($this->admin())
            ->test(DependencyAudit::class)
            ->assertSee('Actualizar vulnerables');
    }

    // ------------------------------------------------------------------
    // Encolar
    // ------------------------------------------------------------------

    public function test_update_now_queues_a_job(): void
    {
        Queue::fake();
        $this->bindAuditWithVulnerability();
        app(DependencyAuditService::class)->runAndStore();

        Livewire::actingAs($this->admin())
            ->test(DependencyAudit::class)
            ->call('updateNow');

        Queue::assertPushed(\App\Domain\System\Jobs\RunDependencyOperation::class);
        $this->assertDatabaseHas('dependency_updates', [
            'type' => 'update',
            'status' => 'queued',
        ]);
    }

    public function test_only_one_operation_at_a_time(): void
    {
        DependencyUpdate::create(['type' => 'update', 'status' => 'running']);

        $this->expectException(\RuntimeException::class);
        app(DependencyUpdateService::class)->queueUpdate($this->admin());
    }

    // ------------------------------------------------------------------
    // Ejecución (orquestación)
    // ------------------------------------------------------------------

    public function test_successful_update_backs_up_updates_and_leaves_app_up(): void
    {
        $this->bindAuditWithVulnerability();
        $service = new FakeDependencyUpdateService(app(DependencyAuditService::class));
        $operation = DependencyUpdate::create([
            'type' => 'update', 'status' => 'queued', 'bypass_secret' => 'x',
        ]);

        $service->perform($operation->id);

        $operation->refresh();
        $this->assertSame(DependencyUpdateStatus::Succeeded, $operation->status);
        $this->assertNotNull($operation->backup_path);
        $this->assertNotNull($operation->audit_before_id);
        $this->assertNotNull($operation->audit_after_id);

        // Entró y salió de mantenimiento, y recompiló/actualizó.
        Process::assertRan(fn ($p) => $this->cmd($p) === 'php artisan down --retry=60 --secret=x');
        Process::assertRan(fn ($p) => $this->cmd($p) === 'php artisan up');
        Process::assertRan(fn ($p) => str_contains($this->cmd($p), 'composer update'));
        Process::assertRan(fn ($p) => str_contains($this->cmd($p), 'mysqldump'));
    }

    public function test_clean_audit_skips_update(): void
    {
        $this->bindAuditClean();
        $service = new FakeDependencyUpdateService(app(DependencyAuditService::class));
        $operation = DependencyUpdate::create(['type' => 'update', 'status' => 'queued']);

        $service->perform($operation->id);

        $operation->refresh();
        $this->assertSame(DependencyUpdateStatus::Succeeded, $operation->status);
        $this->assertNull($operation->backup_path);
        Process::assertNotRan(fn ($p) => str_contains($this->cmd($p), 'composer update'));
    }

    public function test_failed_backup_aborts_without_updating(): void
    {
        $this->bindAuditWithVulnerability();
        $service = new FakeDependencyUpdateService(app(DependencyAuditService::class));
        $service->failBackup = true;
        $operation = DependencyUpdate::create(['type' => 'update', 'status' => 'queued']);

        $service->perform($operation->id);

        $operation->refresh();
        $this->assertSame(DependencyUpdateStatus::Failed, $operation->status);
        // No se intentó actualizar nada y la app volvió a estar arriba.
        Process::assertNotRan(fn ($p) => str_contains($this->cmd($p), 'composer update'));
        Process::assertRan(fn ($p) => $this->cmd($p) === 'php artisan up');
    }

    public function test_failed_update_restores_code_and_keeps_database_untouched(): void
    {
        $this->bindAuditWithVulnerability();
        $service = new FakeDependencyUpdateService(app(DependencyAuditService::class));
        $operation = DependencyUpdate::create(['type' => 'update', 'status' => 'queued']);

        $service->failUpdate = true; // composer update falla; lo demás tiene éxito
        $service->perform($operation->id);

        $operation->refresh();
        $this->assertSame(DependencyUpdateStatus::Failed, $operation->status);
        $this->assertStringContainsString('restaur', mb_strtolower($operation->summary));
        // Se restauró el código (se extrajo el vendor del respaldo) pero NO se
        // restauró la base de datos (no se corrió mysql de restauración).
        Process::assertRan(fn ($p) => str_contains($this->cmd($p), 'tar -xzf') && str_contains($this->cmd($p), 'vendor.tar.gz'));
        Process::assertNotRan(fn ($p) => str_contains($this->cmd($p), 'gunzip -c') && str_contains($this->cmd($p), 'mysql'));
        Process::assertRan(fn ($p) => $this->cmd($p) === 'php artisan up');
    }

    // ------------------------------------------------------------------
    // Respaldos: se conservan solo N
    // ------------------------------------------------------------------

    public function test_prune_keeps_only_configured_number_of_backups(): void
    {
        config(['dependencies.keep_backups' => 3]);

        // 5 respaldos con carpeta en disco.
        $dirs = [];
        for ($i = 1; $i <= 5; $i++) {
            $dir = storage_path('app/test-backups/'.$i);
            File::ensureDirectoryExists($dir);
            File::put($dir.'/database.sql.gz', 'x');
            $dirs[$i] = $dir;
            DependencyUpdate::create([
                'type' => 'update', 'status' => 'succeeded', 'backup_path' => $dir, 'backup_size_bytes' => 1,
            ]);
        }

        app(DependencyUpdateService::class)->pruneBackups();

        // Los 2 más antiguos se borraron del disco y quedaron marcados.
        $this->assertDirectoryDoesNotExist($dirs[1]);
        $this->assertDirectoryDoesNotExist($dirs[2]);
        $this->assertDirectoryExists($dirs[3]);
        $this->assertSame(2, DependencyUpdate::whereNotNull('backup_pruned_at')->count());
    }

    // ------------------------------------------------------------------
    // Restauración de base de datos: confirmación obligatoria
    // ------------------------------------------------------------------

    public function test_restore_database_requires_typed_confirmation(): void
    {
        $backup = DependencyUpdate::create([
            'type' => 'update', 'status' => 'succeeded',
            'backup_path' => $this->makeBackupDir(), 'backup_size_bytes' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(DependencyAudit::class)
            ->call('startRestoreDatabase', $backup->id)
            ->set('restoreDbConfirm', 'cualquier cosa')
            ->call('confirmRestoreDatabase')
            ->assertHasErrors('restoreDbConfirm');

        $this->assertDatabaseMissing('dependency_updates', ['type' => 'restore_database']);
    }

    public function test_restore_database_queues_with_correct_confirmation(): void
    {
        Queue::fake();
        $backup = DependencyUpdate::create([
            'type' => 'update', 'status' => 'succeeded',
            'backup_path' => $this->makeBackupDir(), 'backup_size_bytes' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(DependencyAudit::class)
            ->call('startRestoreDatabase', $backup->id)
            ->set('restoreDbConfirm', 'RESTAURAR')
            ->call('confirmRestoreDatabase')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('dependency_updates', [
            'type' => 'restore_database',
            'source_update_id' => $backup->id,
        ]);
    }

    public function test_restore_code_can_be_queued_from_panel(): void
    {
        Queue::fake();
        $backup = DependencyUpdate::create([
            'type' => 'update', 'status' => 'succeeded',
            'backup_path' => $this->makeBackupDir(), 'backup_size_bytes' => 1,
        ]);

        Livewire::actingAs($this->admin())
            ->test(DependencyAudit::class)
            ->call('restoreCode', $backup->id);

        $this->assertDatabaseHas('dependency_updates', [
            'type' => 'restore_code',
            'source_update_id' => $backup->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Interrupción de la cola
    // ------------------------------------------------------------------

    public function test_interrupted_operation_is_marked_failed_and_warns_about_maintenance(): void
    {
        $operation = DependencyUpdate::create(['type' => 'update', 'status' => 'running']);

        app(DependencyUpdateService::class)->markInterrupted($operation->id, 'tiempo agotado');

        $operation->refresh();
        $this->assertSame(DependencyUpdateStatus::Failed, $operation->status);
        $this->assertTrue($operation->maintenance_left_on);
        $this->assertStringContainsString('mantenimiento', mb_strtolower($operation->summary));
    }

    private function makeBackupDir(): string
    {
        $dir = storage_path('app/test-backups/src-'.uniqid());
        File::ensureDirectoryExists($dir);
        File::put($dir.'/database.sql.gz', 'x');
        File::put($dir.'/vendor.tar.gz', 'x');

        return $dir;
    }

    private function cmd($process): string
    {
        return is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
    }
}
