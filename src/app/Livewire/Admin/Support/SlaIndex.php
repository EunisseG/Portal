<?php

namespace App\Livewire\Admin\Support;

use App\Domain\Reporting\Services\SatisfactionStats;
use App\Domain\Tickets\Enums\TicketCriticality;
use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Services\SlaResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla de métricas de SLA / soporte: tiempo de primera respuesta (FRT),
 * tiempo de resolución, % de cumplimiento y tickets vencidos, por criticidad,
 * en un rango de fechas (por fecha de creación del ticket). Enlaza el CSAT ya
 * existente.
 */
class SlaIndex extends Component
{
    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    /** Tipo de ticket mostrado: 'client' | 'internal'. */
    #[Url(as: 'type')]
    public string $ticketType = 'client';

    /** Pestaña principal: 'metricas' | 'politicas'. */
    #[Url(as: 'tab')]
    public string $tab = 'metricas';

    public function mount(): void
    {
        // Ver métricas de soporte: mismo permiso que ver tickets.
        Gate::authorize('tickets.view');

        if (! in_array($this->ticketType, ['client', 'internal'], true)) {
            $this->ticketType = 'client';
        }
        if (! in_array($this->tab, ['metricas', 'politicas'], true)) {
            $this->tab = 'metricas';
        }

        if ($this->from === '') {
            $this->from = Carbon::now()->startOfMonth()->subMonths(2)->toDateString();
        }
        if ($this->to === '') {
            $this->to = Carbon::now()->toDateString();
        }
    }

    public function applyFilter(): void
    {
        $this->normalizeRange();
    }

    public function setType(string $type): void
    {
        $this->ticketType = $type === 'internal' ? 'internal' : 'client';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'politicas' ? 'politicas' : 'metricas';
    }

    public function resetFilter(): void
    {
        $this->from = Carbon::now()->startOfMonth()->subMonths(2)->toDateString();
        $this->to = Carbon::now()->toDateString();
    }

    private function fromDate(): Carbon
    {
        return Carbon::parse($this->from)->startOfDay();
    }

    private function toDate(): Carbon
    {
        return Carbon::parse($this->to)->endOfDay();
    }

    private function normalizeRange(): void
    {
        if ($this->from !== '' && $this->to !== '' && $this->from > $this->to) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }
    }

    /**
     * Formatea minutos como "Xh Ym" (o "—" si no hay dato).
     */
    private function fmt(?float $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        $m = (int) round($minutes);
        $h = intdiv($m, 60);
        $rem = $m % 60;

        return $h > 0 ? ($h.'h '.$rem.'m') : ($rem.'m');
    }

    public function render(): View
    {
        $this->normalizeRange();

        $resolver = app(SlaResolver::class);

        // Tickets del rango (por fecha de creación). Se necesita client_id para
        // resolver el SLA aplicable (override del cliente o global).
        $tickets = Ticket::query()
            ->where('type', $this->ticketType)
            ->whereDate('created_at', '>=', $this->fromDate()->toDateString())
            ->whereDate('created_at', '<=', $this->toDate()->toDateString())
            ->get(['id', 'type', 'client_id', 'criticality', 'created_at', 'first_response_at', 'resolved_at']);

        // Acumuladores globales y por criticidad.
        $criticalities = [
            TicketCriticality::Critical,
            TicketCriticality::High,
            TicketCriticality::Medium,
            TicketCriticality::Low,
        ];

        $byCrit = [];
        foreach ($criticalities as $c) {
            $byCrit[$c->value] = [
                'label' => $c->label(),
                'total' => 0,
                'frt_sum' => 0.0, 'frt_n' => 0,
                'res_sum' => 0.0, 'res_n' => 0,
                'frt_met' => 0, 'frt_breached' => 0,
                'res_met' => 0, 'res_breached' => 0,
            ];
        }

        $globalFrtSum = 0.0; $globalFrtN = 0;
        $globalResSum = 0.0; $globalResN = 0;
        $globalFrtMet = 0; $globalFrtBreach = 0;
        $globalResMet = 0; $globalResBreach = 0;

        foreach ($tickets as $t) {
            $key = $t->criticality?->value;
            if ($key === null || ! isset($byCrit[$key])) {
                continue;
            }
            $byCrit[$key]['total']++;

            // SLA aplicable a ESTE ticket (override del cliente o global).
            $target = $resolver->forTicket($t);
            $frtTarget = $target['first_response_minutes'];
            $resTarget = $target['resolution_minutes'];

            // Primera respuesta.
            if ($t->first_response_at) {
                $mins = $t->created_at->diffInMinutes($t->first_response_at);
                $byCrit[$key]['frt_sum'] += $mins;
                $byCrit[$key]['frt_n']++;
                $globalFrtSum += $mins; $globalFrtN++;
                if ($frtTarget !== null) {
                    if ($mins <= $frtTarget) { $byCrit[$key]['frt_met']++; $globalFrtMet++; }
                    else { $byCrit[$key]['frt_breached']++; $globalFrtBreach++; }
                }
            }

            // Resolución.
            if ($t->resolved_at) {
                $mins = $t->created_at->diffInMinutes($t->resolved_at);
                $byCrit[$key]['res_sum'] += $mins;
                $byCrit[$key]['res_n']++;
                $globalResSum += $mins; $globalResN++;
                if ($resTarget !== null) {
                    if ($mins <= $resTarget) { $byCrit[$key]['res_met']++; $globalResMet++; }
                    else { $byCrit[$key]['res_breached']++; $globalResBreach++; }
                }
            }
        }

        // Arma filas de la tabla por criticidad.
        $rows = [];
        foreach ($byCrit as $critValue => $d) {
            $frtAvg = $d['frt_n'] > 0 ? $d['frt_sum'] / $d['frt_n'] : null;
            $resAvg = $d['res_n'] > 0 ? $d['res_sum'] / $d['res_n'] : null;
            $frtEval = $d['frt_met'] + $d['frt_breached'];
            $resEval = $d['res_met'] + $d['res_breached'];

            $rows[] = [
                'criticality' => $critValue,
                'label' => $d['label'],
                'total' => $d['total'],
                'frt_avg' => $this->fmt($frtAvg),
                'res_avg' => $this->fmt($resAvg),
                'frt_compliance' => $frtEval > 0 ? (int) round($d['frt_met'] / $frtEval * 100) : null,
                'res_compliance' => $resEval > 0 ? (int) round($d['res_met'] / $resEval * 100) : null,
                'breached' => $d['frt_breached'] + $d['res_breached'],
            ];
        }

        // KPIs globales.
        $frtEvalG = $globalFrtMet + $globalFrtBreach;
        $resEvalG = $globalResMet + $globalResBreach;
        $kpis = [
            'total' => $tickets->count(),
            'frt_avg' => $this->fmt($globalFrtN > 0 ? $globalFrtSum / $globalFrtN : null),
            'res_avg' => $this->fmt($globalResN > 0 ? $globalResSum / $globalResN : null),
            'frt_compliance' => $frtEvalG > 0 ? (int) round($globalFrtMet / $frtEvalG * 100) : null,
            'res_compliance' => $resEvalG > 0 ? (int) round($globalResMet / $resEvalG * 100) : null,
            'breached' => $globalFrtBreach + $globalResBreach,
        ];

        // Gráfica: % de cumplimiento de resolución por criticidad.
        $chart = [
            'labels' => array_map(fn ($r) => $r['label'], $rows),
            'data' => array_map(fn ($r) => $r['res_compliance'] ?? 0, $rows),
            'total' => array_sum(array_map(fn ($r) => $r['total'], $rows)),
        ];

        // CSAT del tipo activo (reutiliza el servicio existente).
        $csat = app(SatisfactionStats::class);
        $csatType = \App\Domain\Tickets\Enums\TicketType::from($this->ticketType);

        return view('livewire.admin.support.sla-index', [
            'rows' => $rows,
            'kpis' => $kpis,
            'chart' => $chart,
            'ticketType' => $this->ticketType,
            'csatAverage' => $csat->average($csatType),
            'csatAnswered' => $csat->answered($csatType),
            'canManagePolicies' => Gate::allows('sla.manage'),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}
