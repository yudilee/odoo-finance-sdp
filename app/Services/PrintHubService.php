<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrintHubService
{
    protected string $url;
    protected string $apiKey;
    protected int $timeout;

    public function __construct(?string $url = null, ?string $apiKey = null)
    {
        $this->url = rtrim($url ?? Setting::get('print_hub_url', ''), '/');
        $this->apiKey = $apiKey ?? Setting::get('print_hub_api_key', '');
        $this->timeout = (int) Setting::get('print_hub_timeout', 15);
    }

    /**
     * Get list of printers from the hub
     */
    public function getPrinters(): array
    {
        if (empty($this->url)) {
            return ['success' => false, 'message' => 'Print Hub URL not configured.'];
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->get("{$this->url}/api/v1/agents/online");

            if ($response->successful()) {
                $agents = $response->json('agents', []);
                $printers = [];
                foreach ($agents as $agent) {
                    foreach ($agent['printers'] as $printer) {
                        $printers[] = [
                            'name' => $printer,
                            'agent' => $agent['name']
                        ];
                    }
                }
                return [
                    'success' => true,
                    'printers' => $printers
                ];
            }

            return [
                'success' => false,
                'message' => 'Hub returned error: ' . ($response->json('error') ?? $response->status())
            ];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (getPrinters): " . $e->getMessage());
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send a PDF to the hub for printing
     */
    public function printPdf(string $printerName, string $pdfBase64, array $options = [], ?string $profile = null): array
    {
        if (empty($this->url)) {
            return ['success' => false, 'message' => 'Print Hub URL not configured.'];
        }

        try {
            // Use provided profile or fall back to default setting
            $targetProfile = $profile ?: Setting::get('print_hub_default_profile');

            $payload = [
                'printer' => $printerName,
                'profile' => $targetProfile,
                'document_base64' => $pdfBase64,
                'type' => 'pdf',
                'options' => $options
            ];

            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->post("{$this->url}/api/v1/print", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'job_id' => $response->json('job_id'),
                    'message' => 'Print job sent successfully.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Hub Error: ' . ($response->json('message') ?? $response->status())
            ];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (printPdf): " . $e->getMessage());
            return ['success' => false, 'message' => 'Printing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send raw data (ZPL/ESC/POS) to the hub
     */
    public function printRaw(string $printerName, string $data, array $options = []): array
    {
        if (empty($this->url)) {
            return ['success' => false, 'message' => 'Print Hub URL not configured.'];
        }

        try {
            $payload = [
                'printer' => $printerName,
                'document_base64' => base64_encode($data),
                'type' => 'raw',
                'options' => $options
            ];

            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->post("{$this->url}/api/v1/print", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'job_id' => $response->json('job_id'),
                    'message' => 'Raw print job sent successfully.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Hub Error: ' . ($response->json('message') ?? $response->status())
            ];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (printRaw): " . $e->getMessage());
            return ['success' => false, 'message' => 'Raw printing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Register a data schema with the hub
     */
    public function registerSchema(string $name, array $data): array
    {
        if (empty($this->url)) {
            return ['success' => false, 'message' => 'Print Hub URL not configured.'];
        }

        try {
            $payload = array_merge(['schema_name' => $name], $data);
            
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->post("{$this->url}/api/v1/schema", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => "Schema '{$name}' registered successfully."
                ];
            }

            return [
                'success' => false,
                'message' => 'Hub Error: ' . ($response->json('message') ?? $response->status())
            ];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (registerSchema): " . $e->getMessage());
            return ['success' => false, 'message' => 'Schema registration failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test connection to the hub
     */
    public function testConnection(): array
    {
        if (empty($this->url)) {
            return ['success' => false, 'message' => 'Print Hub URL not configured.'];
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->get("{$this->url}/api/v1/test");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'app_name' => $response->json('app_name'),
                    'agents' => $response->json('agents'),
                    'message' => $response->json('message')
                ];
            }

            return [
                'success' => false,
                'message' => 'Hub returned error: ' . ($response->json('error') ?? $response->status())
            ];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (testConnection): " . $e->getMessage());
            return ['success' => false, 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Print a PDF via a named queue, with optional agent+printer override.
     */
    public function printQueue(
        string  $queue,
        string  $pdfBase64,
        ?int    $agentId  = null,
        ?string $printer  = null,
        array   $extra    = []
    ): array {
        if (empty($this->url)) {
            return ['success' => false, 'message' => 'Print Hub URL not configured.'];
        }

        try {
            $payload = array_merge([
                'queue'           => $queue,
                'document_base64' => $pdfBase64,
                'type'            => 'pdf',
            ], array_filter([
                'agent_id' => $agentId,
                'printer'  => $printer,
            ], fn($v) => $v !== null), $extra);

            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->post("{$this->url}/api/v1/print", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'job_id' => $response->json('job_id'),
                    'message' => 'Print job sent successfully via queue.'
                ];
            }

            return [
                'success' => false,
                'message' => 'Hub Error: ' . ($response->json('error') ?? $response->status())
            ];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (printQueue): " . $e->getMessage());
            return ['success' => false, 'message' => 'Printing failed: ' . $e->getMessage()];
        }
    }

    /**
     * Get list of queues from the hub
     */
    public function getQueues(): array
    {
        if (empty($this->url)) {
            return ['success' => false, 'queues' => []];
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->get("{$this->url}/api/v1/queues");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'queues' => $response->json('queues', [])
                ];
            }

            return ['success' => false, 'queues' => []];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (getQueues): " . $e->getMessage());
            return ['success' => false, 'queues' => []];
        }
    }

    /**
     * Get list of online agents from the hub
     */
    public function getOnlineAgents(): array
    {
        if (empty($this->url)) {
            return ['success' => false, 'agents' => []];
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->timeout($this->timeout)->get("{$this->url}/api/v1/agents/online");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'agents' => $response->json('agents', [])
                ];
            }

            return ['success' => false, 'agents' => []];
        } catch (\Exception $e) {
            Log::error("PrintHub Error (getOnlineAgents): " . $e->getMessage());
            return ['success' => false, 'agents' => []];
        }
    }
}
