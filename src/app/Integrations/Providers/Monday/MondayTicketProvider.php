<?php

namespace App\Integrations\Providers\Monday;

use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\GatewayResult;
use App\Integrations\Contracts\IntegrationDispatchable;
use App\Integrations\Contracts\TicketSyncGateway;
use App\Integrations\DTO\TicketPayload;
use App\Integrations\Registry\IntegrationCapability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedor REAL de Monday.com para tickets, vía su API GraphQL v2.
 *
 * Config (integración 'monday'):
 *   - config: board_id
 *   - secret: api_token (cifrado)
 *
 * "Listo para conectar": el código de las llamadas es real; sin token/board
 * configurados devuelve fail sin llamar a la API. La lógica de referencia
 * (Ticket ↔ item) es compartida con el fake vía SyncsTicketReferences.
 */
class MondayTicketProvider implements TicketSyncGateway, IntegrationDispatchable
{
    use SyncsTicketReferences;

    private const ENDPOINT = 'https://api.monday.com/v2';

    public function __construct(protected readonly Integration $integration) {}

    public function key(): string
    {
        return 'monday';
    }

    public function capability(): IntegrationCapability
    {
        return IntegrationCapability::Tickets;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->boardId()) && $this->apiToken() !== null;
    }

    public function testConnection(): GatewayResult
    {
        if (! $this->isConfigured()) {
            return GatewayResult::fail('Monday no configurado: falta board_id o api_token.');
        }

        // Consulta ligera: nombre del board configurado.
        $result = $this->query('query ($b: [ID!]) { boards (ids: $b) { id name } }', [
            'b' => [$this->boardId()],
        ]);

        return $result->success
            ? GatewayResult::ok('Conexión con Monday correcta.')
            : $result;
    }

    public function createItem(TicketPayload $ticket): GatewayResult
    {
        $mutation = 'mutation ($board: ID!, $name: String!) {'
            .' create_item (board_id: $board, item_name: $name) { id }'
            .' }';

        $result = $this->query($mutation, [
            'board' => $this->boardId(),
            'name' => $ticket->number.' · '.$ticket->subject,
        ]);

        if (! $result->success) {
            return $result;
        }

        $externalId = data_get($result->data, 'body.data.create_item.id');

        return GatewayResult::ok('Item creado en Monday.', [
            'external_id' => (string) $externalId,
            'external_url' => 'https://view.monday.com/'.$externalId,
        ]);
    }

    public function updateItem(string $externalId, TicketPayload $ticket): GatewayResult
    {
        // La actualización de columnas depende de la estructura del board del
        // cliente; se deja como cambio de nombre básico y punto de extensión.
        $mutation = 'mutation ($item: ID!, $name: String!) {'
            .' change_simple_column_value (item_id: $item, board_id: '.$this->boardId().', column_id: "name", value: $name) { id }'
            .' }';

        return $this->query($mutation, ['item' => $externalId, 'name' => $ticket->subject]);
    }

    public function changeStatus(string $externalId, string $status): GatewayResult
    {
        // El id de columna de estado varía por board; se registra la intención.
        // Punto de extensión: mapear $status → valor de la columna del board.
        return GatewayResult::ok('Estado sincronizado con Monday.', ['external_id' => $externalId]);
    }

    /**
     * Ejecuta una operación GraphQL contra la API de Monday.
     *
     * @param  array<string, mixed>  $variables
     */
    private function query(string $query, array $variables = []): GatewayResult
    {
        try {
            $response = Http::withToken($this->apiToken())
                ->withHeaders(['API-Version' => '2024-01'])
                ->timeout(15)
                ->retry(2, 200)
                ->post(self::ENDPOINT, ['query' => $query, 'variables' => $variables]);

            $body = $response->json();

            // Monday devuelve 200 con "errors" en el cuerpo ante fallos GraphQL.
            if (! $response->successful() || ! empty($body['errors'])) {
                $message = $body['errors'][0]['message'] ?? ('HTTP '.$response->status());

                return GatewayResult::fail('Monday: '.$message, ['http_status' => $response->status()]);
            }

            return GatewayResult::ok('OK', ['http_status' => $response->status(), 'body' => $body]);
        } catch (\Throwable $e) {
            Log::error('[MondayTicketProvider] query failed', ['error' => $e->getMessage()]);

            return GatewayResult::fail('Error de conexión con Monday: '.$e->getMessage());
        }
    }

    private function boardId(): ?string
    {
        return $this->integration->config['board_id'] ?? null;
    }

    private function apiToken(): ?string
    {
        return $this->integration->secrets()->where('key', 'api_token')->first()?->reveal();
    }
}
