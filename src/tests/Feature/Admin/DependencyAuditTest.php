<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\System\Jobs\SendDependencyAuditNotification;
use App\Domain\System\Models\DependencyAudit as DependencyAuditModel;
use App\Domain\System\Services\DependencyAuditService;
use App\Livewire\Admin\System\DependencyAudit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Doble del servicio de auditoría que devuelve JSON fijo en vez de ejecutar
 * composer/npm (sin red, determinista).
 */
class FakeDependencyAuditService extends DependencyAuditService
{
    public function __construct(
        private readonly ?string $composerJson,
        private readonly ?string $npmJson,
    ) {}

    protected function rawComposerAudit(): ?string
    {
        return $this->composerJson;
    }

    protected function rawNpmAudit(): ?string
    {
        return $this->npmJson;
    }
}

class DependencyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    private function npmJson(int $critical = 0, int $high = 0, int $moderate = 0): string
    {
        $vulns = [];
        for ($i = 0; $i < $critical; $i++) {
            $vulns["crit-$i"] = ['name' => "crit-$i", 'severity' => 'critical', 'isDirect' => true, 'via' => [['title' => 'Boom', 'url' => 'https://example.test/a']], 'range' => '<1.0', 'fixAvailable' => true];
        }
        for ($i = 0; $i < $high; $i++) {
            $vulns["high-$i"] = ['name' => "high-$i", 'severity' => 'high', 'via' => ['other-pkg'], 'fixAvailable' => false];
        }
        for ($i = 0; $i < $moderate; $i++) {
            $vulns["mod-$i"] = ['name' => "mod-$i", 'severity' => 'moderate', 'via' => [], 'fixAvailable' => true];
        }

        return json_encode(['vulnerabilities' => $vulns, 'metadata' => ['vulnerabilities' => ['total' => $critical + $high + $moderate]]]);
    }

    private function bindFake(?string $composerJson, ?string $npmJson): void
    {
        $this->app->bind(
            DependencyAuditService::class,
            fn () => new FakeDependencyAuditService($composerJson, $npmJson),
        );
    }

    public function test_parses_npm_severities_mapping_moderate_to_medium(): void
    {
        $service = new FakeDependencyAuditService('{"advisories":[],"abandoned":[]}', $this->npmJson(critical: 2, high: 1, moderate: 1));

        $result = $service->analyze();

        $this->assertSame(2, $result['npm']['counts']['critical']);
        $this->assertSame(1, $result['npm']['counts']['high']);
        $this->assertSame(1, $result['npm']['counts']['medium']); // moderate -> medium
        $this->assertSame(4, $result['npm']['counts']['total']);
        $this->assertNull($result['npm']['error']);
        $this->assertSame(0, $result['composer']['counts']['total']);
    }

    public function test_parses_composer_advisories(): void
    {
        $composer = json_encode(['advisories' => [
            'vendor/pkg' => [
                ['title' => 'RCE', 'cve' => 'CVE-2026-1', 'link' => 'https://example.test/cve', 'severity' => 'high', 'affectedVersions' => '<2.0'],
            ],
        ], 'abandoned' => ['old/lib' => 'new/lib']]);

        $service = new FakeDependencyAuditService($composer, '{"vulnerabilities":{},"metadata":{}}');
        $result = $service->analyze();

        $this->assertSame(1, $result['composer']['counts']['high']);
        $this->assertSame(1, $result['composer']['counts']['total']);
        $this->assertSame('vendor/pkg', $result['composer']['packages'][0]['package']);
        $this->assertSame(['old/lib'], $result['composer']['abandoned']);
    }

    public function test_null_output_is_recorded_as_error(): void
    {
        $service = new FakeDependencyAuditService(null, null);
        $result = $service->analyze();

        $this->assertNotNull($result['composer']['error']);
        $this->assertNotNull($result['npm']['error']);
    }

    public function test_run_and_store_persists_snapshot(): void
    {
        $service = new FakeDependencyAuditService('{"advisories":[],"abandoned":[]}', $this->npmJson(critical: 1, high: 1));
        $audit = $service->runAndStore();

        $this->assertDatabaseHas('dependency_audits', [
            'id' => $audit->id,
            'npm_critical' => 1,
            'npm_high' => 1,
            'npm_total' => 2,
            'composer_total' => 0,
            'total_vulnerabilities' => 2,
            'has_errors' => false,
        ]);
        $this->assertSame(2, $audit->urgentCount());
    }

    public function test_admin_can_open_screen_and_run_now(): void
    {
        $this->bindFake('{"advisories":[],"abandoned":[]}', $this->npmJson(critical: 1));

        Livewire::actingAs($this->admin())
            ->test(DependencyAudit::class)
            ->assertOk()
            ->assertSee('Dependencias / Seguridad')
            ->call('runNow')
            ->assertHasNoErrors();

        $this->assertSame(1, DependencyAuditModel::count());
        $this->assertSame(1, DependencyAuditModel::first()->npm_critical);
    }

    public function test_non_admin_cannot_open_screen(): void
    {
        Livewire::actingAs($this->staff())
            ->test(DependencyAudit::class)
            ->assertForbidden();
    }

    public function test_command_alerts_when_urgent_vulnerabilities_increase(): void
    {
        $this->bindFake('{"advisories":[],"abandoned":[]}', $this->npmJson(critical: 2, high: 1));

        $this->artisan('dependencies:check')->assertExitCode(0);

        $this->assertSame(1, DependencyAuditModel::count());
        $this->assertDatabaseHas('integration_logs', ['event' => 'dependencies.audit']);
        Queue::assertPushed(SendDependencyAuditNotification::class, 1);
    }

    public function test_command_does_not_alert_when_clean(): void
    {
        $this->bindFake('{"advisories":[],"abandoned":[]}', '{"vulnerabilities":{},"metadata":{}}');

        $this->artisan('dependencies:check')->assertExitCode(0);

        $this->assertDatabaseHas('integration_logs', ['event' => 'dependencies.audit']);
        Queue::assertNotPushed(SendDependencyAuditNotification::class);
    }

    public function test_command_does_not_realert_when_urgent_count_is_stable(): void
    {
        // Snapshot previo ya con 3 urgentes.
        DependencyAuditModel::create([
            'composer_critical' => 0, 'composer_high' => 0, 'composer_medium' => 0, 'composer_low' => 0, 'composer_total' => 0,
            'npm_critical' => 2, 'npm_high' => 1, 'npm_medium' => 0, 'npm_low' => 0, 'npm_total' => 3,
            'total_vulnerabilities' => 3, 'has_errors' => false, 'detail' => [],
        ]);

        // Nueva corrida con los mismos 3 urgentes: no debe re-alertar.
        $this->bindFake('{"advisories":[],"abandoned":[]}', $this->npmJson(critical: 2, high: 1));

        $this->artisan('dependencies:check')->assertExitCode(0);

        Queue::assertNotPushed(SendDependencyAuditNotification::class);
    }
}
