<?php

namespace App\Domain\System\Services;

use App\Domain\System\Models\DependencyAudit;
use Illuminate\Support\Facades\Process;

/**
 * Ejecuta las auditorías de seguridad de dependencias (Composer y npm), parsea
 * su salida JSON a una estructura normalizada y guarda un snapshot en
 * `dependency_audits`. No modifica dependencias: solo lee el estado.
 *
 * La obtención del JSON crudo está aislada en métodos protegidos
 * (`rawComposerAudit` / `rawNpmAudit`) para poder sustituirla en pruebas sin
 * depender de la red ni de las herramientas del sistema.
 */
class DependencyAuditService
{
    /** Severidades que consideramos "con vulnerabilidad" (se ignora 'info'). */
    private const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    /**
     * Corre ambas auditorías, guarda y devuelve el snapshot.
     */
    public function runAndStore(): DependencyAudit
    {
        $result = $this->analyze();

        return DependencyAudit::create([
            'composer_critical' => $result['composer']['counts']['critical'],
            'composer_high' => $result['composer']['counts']['high'],
            'composer_medium' => $result['composer']['counts']['medium'],
            'composer_low' => $result['composer']['counts']['low'],
            'composer_total' => $result['composer']['counts']['total'],
            'npm_critical' => $result['npm']['counts']['critical'],
            'npm_high' => $result['npm']['counts']['high'],
            'npm_medium' => $result['npm']['counts']['medium'],
            'npm_low' => $result['npm']['counts']['low'],
            'npm_total' => $result['npm']['counts']['total'],
            'total_vulnerabilities' => $result['composer']['counts']['total'] + $result['npm']['counts']['total'],
            'has_errors' => $result['composer']['error'] !== null || $result['npm']['error'] !== null,
            'detail' => $result,
        ]);
    }

    /**
     * Analiza ambos ecosistemas sin persistir. Útil para tests y para la vista
     * previa.
     *
     * @return array{composer: array<string, mixed>, npm: array<string, mixed>}
     */
    public function analyze(): array
    {
        return [
            'composer' => $this->parseComposer($this->rawComposerAudit()),
            'npm' => $this->parseNpm($this->rawNpmAudit()),
        ];
    }

    /**
     * Normaliza la salida de `composer audit --format=json`.
     *
     * @return array{counts: array<string, int>, packages: list<array<string, mixed>>, abandoned: list<string>, error: ?string}
     */
    public function parseComposer(?string $json): array
    {
        $counts = $this->emptyCounts();
        $packages = [];
        $abandoned = [];

        if ($json === null) {
            return ['counts' => $counts, 'packages' => $packages, 'abandoned' => $abandoned, 'error' => 'No se pudo ejecutar «composer audit».'];
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return ['counts' => $counts, 'packages' => $packages, 'abandoned' => $abandoned, 'error' => 'Salida de «composer audit» ilegible.'];
        }

        // `advisories` es un objeto {paquete: [avisos]}; vacío llega como [].
        $advisories = $data['advisories'] ?? [];
        if (is_array($advisories)) {
            foreach ($advisories as $package => $items) {
                foreach ((array) $items as $item) {
                    $severity = $this->normalizeSeverity($item['severity'] ?? 'medium');
                    $counts[$severity]++;
                    $counts['total']++;
                    $packages[] = [
                        'package' => is_string($package) ? $package : ($item['packageName'] ?? 'desconocido'),
                        'severity' => $severity,
                        'title' => $item['title'] ?? ($item['cve'] ?? 'Aviso de seguridad'),
                        'cve' => $item['cve'] ?? null,
                        'link' => $item['link'] ?? null,
                        'affected_versions' => $item['affectedVersions'] ?? null,
                    ];
                }
            }
        }

        $abandonedRaw = $data['abandoned'] ?? [];
        if (is_array($abandonedRaw)) {
            foreach ($abandonedRaw as $name => $replacement) {
                $abandoned[] = is_string($name) ? $name : (string) $replacement;
            }
        }

        return ['counts' => $counts, 'packages' => $packages, 'abandoned' => $abandoned, 'error' => null];
    }

    /**
     * Normaliza la salida de `npm audit --json`.
     *
     * @return array{counts: array<string, int>, packages: list<array<string, mixed>>, error: ?string}
     */
    public function parseNpm(?string $json): array
    {
        $counts = $this->emptyCounts();
        $packages = [];

        if ($json === null) {
            return ['counts' => $counts, 'packages' => $packages, 'error' => 'No se pudo ejecutar «npm audit».'];
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return ['counts' => $counts, 'packages' => $packages, 'error' => 'Salida de «npm audit» ilegible.'];
        }

        $vulns = $data['vulnerabilities'] ?? [];
        if (is_array($vulns)) {
            foreach ($vulns as $name => $info) {
                if (! is_array($info)) {
                    continue;
                }
                $severity = $this->normalizeSeverity($info['severity'] ?? 'medium');
                $counts[$severity]++;
                $counts['total']++;

                // El título/enlace del aviso viene en `via` (puede ser string o
                // array de objetos). Tomamos el primer objeto con detalle.
                $title = null;
                $link = null;
                foreach ((array) ($info['via'] ?? []) as $via) {
                    if (is_array($via)) {
                        $title = $via['title'] ?? $title;
                        $link = $via['url'] ?? $link;
                        break;
                    }
                }

                $packages[] = [
                    'package' => is_string($name) ? $name : (string) ($info['name'] ?? 'desconocido'),
                    'severity' => $severity,
                    'title' => $title ?? 'Aviso de seguridad',
                    'link' => $link,
                    'range' => $info['range'] ?? null,
                    'fix_available' => (bool) ($info['fixAvailable'] ?? false),
                    'direct' => (bool) ($info['isDirect'] ?? false),
                ];
            }
        }

        return ['counts' => $counts, 'packages' => $packages, 'error' => null];
    }

    /**
     * Mapea severidades de las herramientas a nuestras 4 categorías. npm usa
     * 'moderate' (lo tratamos como 'medium') e 'info' (lo ignoramos).
     */
    private function normalizeSeverity(string $severity): string
    {
        $severity = strtolower($severity);

        return match ($severity) {
            'critical' => 'critical',
            'high' => 'high',
            'moderate', 'medium' => 'medium',
            default => 'low', // low, info y cualquier otra cosa
        };
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        $counts = ['total' => 0];
        foreach (self::SEVERITIES as $s) {
            $counts[$s] = 0;
        }

        return $counts;
    }

    /**
     * Ejecuta `composer audit` y devuelve su JSON, o null si falla. Protegido
     * para poder sustituirlo en pruebas.
     */
    protected function rawComposerAudit(): ?string
    {
        return $this->runJson(['composer', 'audit', '--format=json', '--no-interaction']);
    }

    /**
     * Ejecuta `npm audit` y devuelve su JSON, o null si falla. Protegido para
     * poder sustituirlo en pruebas.
     */
    protected function rawNpmAudit(): ?string
    {
        return $this->runJson(['npm', 'audit', '--json']);
    }

    /**
     * Corre un comando y devuelve su stdout si parece JSON. Estas herramientas
     * salen con código != 0 cuando ENCUENTRAN vulnerabilidades, así que no se
     * puede confiar en el exit code: se valida que la salida sea JSON.
     *
     * @param  list<string>  $command
     */
    private function runJson(array $command): ?string
    {
        try {
            $output = Process::path(base_path())->timeout(180)->run($command)->output();
        } catch (\Throwable) {
            return null;
        }

        $output = trim($output);

        return str_starts_with($output, '{') || str_starts_with($output, '[') ? $output : null;
    }
}
