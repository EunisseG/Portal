<?php

namespace Tests\Feature\Admin;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Internal\Models\InternalArea;
use App\Domain\Sales\Models\SalesRep;
use App\Domain\Services\Models\Service;
use App\Exports\EmployeesExport;
use App\Exports\SalesRepsExport;
use App\Models\User;
use Database\Seeders\InternalAreasSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PeopleExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(InternalAreasSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    private function employee(InternalArea $area): User
    {
        $user = User::factory()->create([
            'employee_number' => 'E-'.fake()->unique()->numberBetween(1000, 9999),
            'internal_area_id' => $area->id,
        ]);
        $user->assignRole(RoleName::Employee->value);

        return $user;
    }

    public function test_admin_can_export_employees(): void
    {
        $this->freezeTime();
        Excel::fake();

        $this->employee(InternalArea::first());

        $this->actingAs($this->admin())
            ->get(route('admin.employees.export'))
            ->assertOk();

        Excel::assertDownloaded('empleados-'.now()->format('Ymd-His').'.xlsx');
    }

    public function test_employees_export_supports_csv(): void
    {
        $this->freezeTime();
        Excel::fake();

        $this->actingAs($this->admin())
            ->get(route('admin.employees.export', ['format' => 'csv']))
            ->assertOk();

        Excel::assertDownloaded('empleados-'.now()->format('Ymd-His').'.csv');
    }

    public function test_employees_export_only_includes_employees_and_filters_by_area(): void
    {
        $areas = InternalArea::orderBy('id')->take(2)->get();
        $mine = $this->employee($areas[0]);
        $other = $this->employee($areas[1]);
        $admin = $this->admin(); // no es empleado: no debe salir

        $all = (new EmployeesExport)->query()->pluck('id')->all();
        $this->assertContains($mine->id, $all);
        $this->assertContains($other->id, $all);
        $this->assertNotContains($admin->id, $all);

        $filtered = (new EmployeesExport(null, null, (string) $areas[0]->id))->query()->pluck('id')->all();
        $this->assertContains($mine->id, $filtered);
        $this->assertNotContains($other->id, $filtered);
    }

    public function test_support_cannot_export_employees(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value); // sin employees.manage

        $this->actingAs($support)
            ->get(route('admin.employees.export'))
            ->assertForbidden();
    }

    public function test_admin_can_export_sales_reps(): void
    {
        $this->freezeTime();
        Excel::fake();

        SalesRep::factory()->create();

        $this->actingAs($this->admin())
            ->get(route('admin.sales-reps.export'))
            ->assertOk();

        Excel::assertDownloaded('ejecutivos-'.now()->format('Ymd-His').'.xlsx');
    }

    public function test_sales_reps_export_filters_by_status_and_counts_services(): void
    {
        $active = SalesRep::factory()->create(['is_active' => true]);
        $inactive = SalesRep::factory()->create(['is_active' => false]);
        Service::factory()->create(['sales_rep_id' => $active->id]);

        $actives = (new SalesRepsExport(null, null, 'active'))->query()->get();
        $this->assertTrue($actives->contains('id', $active->id));
        $this->assertFalse($actives->contains('id', $inactive->id));
        $this->assertSame(1, $actives->firstWhere('id', $active->id)->services_count);

        $inactives = (new SalesRepsExport(null, null, 'inactive'))->query()->pluck('id')->all();
        $this->assertContains($inactive->id, $inactives);
        $this->assertNotContains($active->id, $inactives);
    }

    public function test_support_cannot_export_sales_reps(): void
    {
        $support = User::factory()->create();
        $support->assignRole(RoleName::Support->value); // sin sales_reps.manage

        $this->actingAs($support)
            ->get(route('admin.sales-reps.export'))
            ->assertForbidden();
    }
}
