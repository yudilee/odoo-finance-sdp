<?php

namespace App\Services;

use App\Models\Setting;

class OdooService
{
    protected string $url;
    protected string $db;
    protected string $user;
    protected string $password;
    protected ?int $uid = null;

    public function __construct()
    {
        $config = Setting::getOdooConfig();
        $this->url = rtrim($config['url'] ?? '', '/');
        $this->db = $config['db'] ?? '';
        $this->user = $config['user'] ?? '';
        $this->password = $config['password'] ?? '';
    }

    /**
     * Test connection to Odoo
     */
    public function testConnection(): array
    {
        try {
            if (empty($this->url) || empty($this->db) || empty($this->user) || empty($this->password)) {
                return ['success' => false, 'message' => 'Missing configuration. Please fill all fields.'];
            }

            $uid = $this->authenticate();
            
            if ($uid && is_numeric($uid)) {
                return ['success' => true, 'message' => "Connection successful! User ID: {$uid}"];
            }
            
            return ['success' => false, 'message' => 'Authentication failed. Check credentials.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Fetch journal entries from Odoo using export_data
     * 
     * @param string $dateFrom  Start date (Y-m-d)
     * @param string $dateTo    End date (Y-m-d)
     * @param array  $accountCodes  Account codes to filter (e.g. ['111002', '112003'])
     */
    public function fetchJournalEntries(string $dateFrom, string $dateTo, array $accountCodes = []): array
    {
        try {
            // Build domain
            $domain = [
                ['state', '=', 'posted'],
                ['date', '>=', $dateFrom],
                ['date', '<=', $dateTo],
            ];

            // If account codes specified, we need to filter by line_ids.account_id
            // First get IDs of account.move matching date + state
            $moveIds = $this->execute('account.move', 'search', [$domain]);

            if (empty($moveIds)) {
                return ['success' => true, 'data' => [], 'count' => 0, 'message' => 'No journal entries found for the given criteria.'];
            }

            // Export fields matching the Excel columns
            $exportFields = [
                'name',                           // 0: Move name (e.g. KJKT/2026/00427)
                'date',                            // 1: Date
                'journal_id/display_name',         // 2: Journal name (e.g. Kas Jakarta)
                'partner_id/display_name',         // 3: Partner
                'ref',                             // 4: Reference
                'amount_total_signed',             // 5: Total amount
                'line_ids/account_id/display_name',// 6: Account (e.g. "111002 Kas Jakarta")
                'line_ids/display_name',           // 7: Line description
                'line_ids/ref',                    // 8: Line reference
                'line_ids/debit',                  // 9: Debit
                'line_ids/credit',                 // 10: Credit
            ];

            $result = $this->execute('account.move', 'export_data', [$moveIds, $exportFields]);

            if (!isset($result['datas'])) {
                return ['success' => false, 'message' => 'Unexpected response format', 'data' => []];
            }

            // Process rows - collect all entries with all their lines first
            $entries = [];
            $currentEntry = null;

            foreach ($result['datas'] as $row) {
                $moveName = $row[0] ?? '';
                $accountDisplay = $row[6] ?? '';
                
                // Extract account code from display name
                $accountCode = '';
                $accountName = '';
                if (!empty($accountDisplay)) {
                    $parts = explode(' ', trim($accountDisplay), 2);
                    $accountCode = $parts[0] ?? '';
                    $accountName = $parts[1] ?? '';
                }

                // If move_name is non-empty, this is a new entry header row
                if (!empty($moveName)) {
                    if ($currentEntry !== null) {
                        $entries[] = $currentEntry;
                    }
                    $currentEntry = [
                        'move_name' => $moveName,
                        'date' => $row[1] ?? '',
                        'journal_name' => $row[2] ?? '',
                        'partner_name' => $row[3] ?? '',
                        'ref' => $row[4] ?? '',
                        'amount_total_signed' => (float)($row[5] ?? 0),
                        'lines' => [],
                    ];
                }

                // Add line item
                if ($currentEntry !== null) {
                    $currentEntry['lines'][] = [
                        'account_code' => $accountCode,
                        'account_name' => $accountName,
                        'display_name' => $row[7] ?? '',
                        'ref' => $row[8] ?? '',
                        'debit' => (float)($row[9] ?? 0),
                        'credit' => (float)($row[10] ?? 0),
                    ];
                }
            }

            // Don't forget the last entry
            if ($currentEntry !== null) {
                $entries[] = $currentEntry;
            }

            // Filter ENTRIES (not lines) by account codes:
            // Keep an entry if ANY of its lines match the selected account codes
            if (!empty($accountCodes)) {
                $entries = array_values(array_filter($entries, function ($entry) use ($accountCodes) {
                    foreach ($entry['lines'] as $line) {
                        if (in_array($line['account_code'], $accountCodes)) {
                            return true;
                        }
                    }
                    return false;
                }));
            }

            return [
                'success' => true,
                'data' => $entries,
                'count' => count($entries),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Fetch failed: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Authenticate with Odoo and return user ID
     */
    protected function authenticate(): ?int
    {
        $commonUrl = $this->url . '/xmlrpc/2/common';
        
        $request = $this->xmlrpcEncode('authenticate', [
            $this->db,
            $this->user,
            $this->password,
            []
        ]);

        $response = $this->sendRequest($commonUrl, $request);
        $result = $this->xmlrpcDecode($response);
        
        if (is_array($result) && isset($result['faultCode'])) {
            throw new \Exception($result['faultString'] ?? 'Unknown XML-RPC error');
        }

        $this->uid = is_numeric($result) ? (int)$result : null;
        return $this->uid;
    }

    /**
     * Execute a method on an Odoo model
     */
    public function execute(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        if (!$this->uid) {
            throw new \Exception('Not authenticated');
        }

        $objectUrl = $this->url . '/xmlrpc/2/object';
        
        $request = $this->xmlrpcEncode('execute_kw', [
            $this->db,
            $this->uid,
            $this->password,
            $model,
            $method,
            $args,
            $kwargs
        ]);

        $response = $this->sendRequest($objectUrl, $request);
        $result = $this->xmlrpcDecode($response);

        if (is_array($result) && isset($result['faultCode'])) {
            throw new \Exception($result['faultString'] ?? 'Unknown XML-RPC error');
        }

        return $result;
    }

    // ─── XML-RPC Encoding/Decoding ───

    protected function xmlrpcEncode(string $method, array $params): string
    {
        $xml = '<?xml version="1.0"?>';
        $xml .= '<methodCall>';
        $xml .= '<methodName>' . htmlspecialchars($method) . '</methodName>';
        $xml .= '<params>';
        foreach ($params as $param) {
            $xml .= '<param>' . $this->encodeValue($param) . '</param>';
        }
        $xml .= '</params>';
        $xml .= '</methodCall>';
        return $xml;
    }

    protected function encodeValue($value): string
    {
        if (is_null($value))  return '<value><nil/></value>';
        if (is_bool($value))  return '<value><boolean>' . ($value ? '1' : '0') . '</boolean></value>';
        if (is_int($value))   return '<value><int>' . $value . '</int></value>';
        if (is_float($value)) return '<value><double>' . $value . '</double></value>';
        
        if (is_string($value)) {
            return '<value><string>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</string></value>';
        }
        
        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                $xml = '<value><struct>';
                foreach ($value as $k => $v) {
                    $xml .= '<member><name>' . htmlspecialchars($k) . '</name>' . $this->encodeValue($v) . '</member>';
                }
                $xml .= '</struct></value>';
                return $xml;
            } else {
                $xml = '<value><array><data>';
                foreach ($value as $v) {
                    $xml .= $this->encodeValue($v);
                }
                $xml .= '</data></array></value>';
                return $xml;
            }
        }
        
        return '<value><string>' . htmlspecialchars((string)$value) . '</string></value>';
    }

    protected function isAssoc(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function xmlrpcDecode(string $xml): mixed
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        
        if ($doc === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new \Exception('Failed to parse XML response: ' . ($errors[0]->message ?? 'Unknown error'));
        }

        if (isset($doc->fault)) {
            $fault = $this->decodeValue($doc->fault->value);
            return ['faultCode' => $fault['faultCode'] ?? 0, 'faultString' => $fault['faultString'] ?? 'Unknown fault'];
        }

        if (isset($doc->params->param->value)) {
            return $this->decodeValue($doc->params->param->value);
        }

        return null;
    }

    protected function decodeValue($valueNode): mixed
    {
        if (isset($valueNode->int) || isset($valueNode->i4))
            return (int)($valueNode->int ?? $valueNode->i4);
        if (isset($valueNode->boolean))
            return (string)$valueNode->boolean === '1';
        if (isset($valueNode->string))
            return (string)$valueNode->string;
        if (isset($valueNode->double))
            return (float)$valueNode->double;
        if (isset($valueNode->nil))
            return null;
        
        if (isset($valueNode->array)) {
            $result = [];
            if (isset($valueNode->array->data->value)) {
                foreach ($valueNode->array->data->value as $val) {
                    $result[] = $this->decodeValue($val);
                }
            }
            return $result;
        }
        
        if (isset($valueNode->struct)) {
            $result = [];
            if (isset($valueNode->struct->member)) {
                foreach ($valueNode->struct->member as $member) {
                    $name = (string)$member->name;
                    $result[$name] = $this->decodeValue($member->value);
                }
            }
            return $result;
        }

        return (string)$valueNode;
    }

    protected function sendRequest(string $url, string $body): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("cURL error: {$error}");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("HTTP error {$httpCode}");
        }
        
        return $response;
    }
}
