<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Models\Invoice;
use App\Exports\ClientsExport;
use App\Exports\ReportExport;
use App\Exports\ServicesExport;
use App\Http\Controllers\Controller;
use App\Imports\ClientsImport;
use App\Imports\ServicesImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Maatwebsite\Excel\Facades\Excel;

class DataTransferController extends Controller
{
    public function exportClients(Request $request): BinaryFileResponse
    {
        Gate::authorize('clients.view');

        [$from, $to] = $this->dateRange($request);
        [$ext, $writer] = $this->writer($request);
        $rep = $request->query('rep');
        $rep = is_string($rep) ? $rep : null;

        return Excel::download(
            new ClientsExport($from, $to, $rep),
            'clientes-'.now()->format('Ymd-His').'.'.$ext,
            $writer,
        );
    }

    public function exportEmployees(Request $request): BinaryFileResponse
    {
        Gate::authorize('employees.manage');

        [$from, $to] = $this->dateRange($request);
        [$ext, $writer] = $this->writer($request);
        $area = $request->query('area');
        $area = is_string($area) && ctype_digit($area) ? $area : null;

        return Excel::download(
            new \App\Exports\EmployeesExport($from, $to, $area),
            'empleados-'.now()->format('Ymd-His').'.'.$ext,
            $writer,
        );
    }

    public function exportSalesReps(Request $request): BinaryFileResponse
    {
        Gate::authorize('sales_reps.manage');

        [$from, $to] = $this->dateRange($request);
        [$ext, $writer] = $this->writer($request);
        $status = $request->query('status');
        $status = in_array($status, ['active', 'inactive'], true) ? $status : null;

        return Excel::download(
            new \App\Exports\SalesRepsExport($from, $to, $status),
            'ejecutivos-'.now()->format('Ymd-His').'.'.$ext,
            $writer,
        );
    }

    public function exportServices(Request $request): BinaryFileResponse
    {
        Gate::authorize('services.view');

        $type = $request->query('type') ?: null;
        $clientId = $request->query('client_id') ? (int) $request->query('client_id') : null;
        [$from, $to] = $this->dateRange($request);
        [$ext, $writer] = $this->writer($request);

        return Excel::download(
            new ServicesExport($type, $clientId, $from, $to),
            'pedidos-'.now()->format('Ymd-His').'.'.$ext,
            $writer,
        );
    }

    /**
     * Resuelve el rango de fechas (from/to) desde la petición. Valida formato
     * Y-m-d; devuelve null para los que falten o sean inválidos.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function dateRange(Request $request): array
    {
        $parse = function (?string $value): ?string {
            if (! $value) {
                return null;
            }
            $date = \DateTime::createFromFormat('Y-m-d', $value);

            return ($date && $date->format('Y-m-d') === $value) ? $value : null;
        };

        return [$parse($request->query('from')), $parse($request->query('to'))];
    }

    /**
     * Resuelve [extensión, writerType] según el formato pedido (xlsx por
     * defecto, csv si se solicita).
     *
     * @return array{0: string, 1: string}
     */
    private function writer(Request $request): array
    {
        // Nota: las constantes de writer viven en la clase concreta
        // Maatwebsite\Excel\Excel, no en la fachada. Usamos sus valores string
        // ('Xlsx'/'Csv') directamente para evitar el desajuste con la fachada.
        return $request->query('format') === 'csv'
            ? ['csv', \Maatwebsite\Excel\Excel::CSV]
            : ['xlsx', \Maatwebsite\Excel\Excel::XLSX];
    }

    public function exportReport(Request $request): BinaryFileResponse
    {
        // Mismo permiso que la pantalla de Gráficas (ver facturas).
        Gate::authorize('viewAny', Invoice::class);

        [$from, $to] = $this->dateRange($request);
        // Por defecto: últimos 12 meses (coincide con la pantalla).
        $from ??= now()->startOfMonth()->subMonths(11)->toDateString();
        $to ??= now()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        [$ext, $writer] = $this->writer($request);

        return Excel::download(
            new ReportExport($from, $to),
            'reporte-'.now()->format('Ymd-His').'.'.$ext,
            $writer,
        );
    }

    public function importClients(Request $request): RedirectResponse
    {
        Gate::authorize('clients.manage');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $import = new ClientsImport();
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('admin.clients.index')
            ->with('status', "Importación completada: {$import->created} creados, {$import->updated} actualizados.");
    }

    public function importServices(Request $request): RedirectResponse
    {
        Gate::authorize('services.manage');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $import = new ServicesImport();
        Excel::import($import, $request->file('file'));

        return redirect()
            ->route('admin.services.index')
            ->with('status', "Importación completada: {$import->created} servicios creados, {$import->skipped} omitidos.");
    }
}
