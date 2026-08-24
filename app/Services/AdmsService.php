<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\AdmsCredential;
use App\Models\AppConfig;
use App\Models\PunchLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdmsService
{
    /**
     * Set or update key-value in AppConfig table.
     */
    protected function setConfig(string $key, ?string $value): void
    {
        AppConfig::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Logs into the ADMS server using stored credentials,
     * scrapes the employee data array, and upserts to our database.
     *
     * @return array ['success' => bool, 'message' => string, 'count' => int]
     */
    public function syncEmployees(): array
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        if (!$creds || empty($creds->url)) {
            Log::warning('No active ADMS credentials configured. Skipping sync.');
            return ['success' => false, 'message' => 'No active ADMS credentials configured.', 'count' => 0];
        }

        $admsUrl = rtrim($creds->url, '/');
        $cookieJar = new \GuzzleHttp\Cookie\CookieJar();

        try {
            // 1. Get CSRF Token from login page
            $loginPageRes = Http::withOptions(['cookies' => $cookieJar, 'timeout' => 15])
                ->get("{$admsUrl}/iclock/accounts/");

            if (!$loginPageRes->successful()) {
                throw new \Exception("Failed to access ADMS login page: HTTP {$loginPageRes->status()}");
            }

            $csrfToken = $cookieJar->getCookieByName('csrftoken')?->getValue();
            if (!$csrfToken) {
                // Try regex extraction from HTML form if cookie is missing
                if (preg_match('/name=["\']csrfmiddlewaretoken["\']\s+value=["\'](.*?)["\']/', $loginPageRes->body(), $m)) {
                    $csrfToken = $m[1];
                }
            }

            if (!$csrfToken) {
                throw new \Exception('Could not obtain CSRF token from ADMS login page.');
            }

            // 2. Perform Login
            $loginRes = Http::withOptions(['cookies' => $cookieJar, 'timeout' => 15])
                ->withHeaders([
                    'Referer' => "{$admsUrl}/iclock/accounts/",
                ])
                ->asForm()
                ->post("{$admsUrl}/iclock/accounts/", [
                    'username' => $creds->username,
                    'password' => $creds->password,
                    'csrfmiddlewaretoken' => $csrfToken,
                ]);

            if (!$loginRes->successful() && $loginRes->status() !== 302) {
                throw new \Exception("ADMS authentication failed with HTTP {$loginRes->status()}");
            }

            // 3. Fetch full employee data list
            $employeeRes = Http::withOptions(['cookies' => $cookieJar, 'timeout' => 45])
                ->get("{$admsUrl}/iclock/data/employee/?p=1&l=5000");

            if (!$employeeRes->successful()) {
                throw new \Exception("Failed to fetch employee list: HTTP {$employeeRes->status()}");
            }

            $html = $employeeRes->body();

            // 4. Extract data array via regex
            if (!preg_match('/data=\[(.*?)\];/s', $html, $matches)) {
                throw new \Exception('Could not find employee data array in ADMS response.');
            }

            $dataStr = '[' . $matches[1] . ']';
            $dataStr = str_replace('deviceText', '""', $dataStr);
            $dataStr = preg_replace('/,\s*\]/', ']', $dataStr);

            $usersRaw = json_decode($dataStr, true);
            if (!is_array($usersRaw)) {
                throw new \Exception('Failed to parse employee JSON data from ADMS.');
            }

            $syncedCount = 0;
            foreach ($usersRaw as $row) {
                if (!is_array($row) || count($row) < 5) {
                    continue;
                }

                $admsId = (string)$row[0];
                $pin = (string)$row[1];
                $name = trim((string)$row[2]);
                $dept = (string)$row[4];

                $emp = Employee::where('employee_id', $pin)->first();
                if ($emp) {
                    if ($emp->is_deleted) {
                        continue;
                    }
                    if ($emp->employee_type && $emp->employee_type !== 'regular') {
                        continue;
                    }

                    $emp->adms_id = $admsId;
                    if (!empty($name)) {
                        $emp->full_name = $name;
                    } elseif (empty($emp->full_name)) {
                        $emp->full_name = "Employee {$pin}";
                    }
                    $emp->department = $dept;
                    $emp->last_synced = Carbon::now();
                    $emp->save();
                } else {
                    Employee::create([
                        'adms_id' => $admsId,
                        'employee_id' => $pin,
                        'full_name' => !empty($name) ? $name : "Employee {$pin}",
                        'department' => $dept,
                        'is_active' => true,
                        'is_deleted' => false,
                        'employee_type' => 'regular',
                        'last_synced' => Carbon::now(),
                    ]);
                }

                $syncedCount++;
            }

            // Record sync metadata
            $nowStr = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
            $this->setConfig('last_adms_sync_time', $nowStr);
            $this->setConfig('last_adms_sync_count', (string)$syncedCount);
            $this->setConfig('last_adms_sync_status', 'success');

            Log::info("Successfully synced {$syncedCount} employees from ADMS.");

            return [
                'success' => true,
                'message' => "Successfully synced {$syncedCount} employees from ADMS.",
                'count' => $syncedCount,
            ];
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("ADMS Sync Error: {$errorMsg}");
            $this->setConfig('last_adms_sync_status', "failed: {$errorMsg}");

            return [
                'success' => false,
                'message' => "ADMS sync failed: {$errorMsg}",
                'count' => 0,
            ];
        }
    }

    /**
     * Push a single punch log to ADMS server and mark uploaded.
     */
    public function pushPunchLog(PunchLog $punch): bool
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        $admsUrl = $creds && !empty($creds->url) ? rtrim($creds->url, '/') : 'https://adms.hartonomotor-group.com';

        $state = strtolower($punch->punch_type) === 'out' ? 1 : 0;
        $timeStr = $punch->timestamp->format('Y-m-d H:i:s');
        $line = "{$punch->employee_id}\t{$timeStr}\t{$state}\t0\t\t\t\t\t\t\t\t\n";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'iClock Proxy/1.0',
                'Content-Type' => 'text/plain',
            ])->timeout(10)->withBody($line, 'text/plain')
              ->post("{$admsUrl}/iclock/cdata?SN=VIRTUAL_MOBILE_01&table=ATTLOG");

            if ($response->successful() || str_contains($response->body(), 'OK')) {
                $punch->update([
                    'adms_status' => 'uploaded',
                    'synced_at' => Carbon::now(),
                ]);
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning("Immediate ADMS push failed for punch {$punch->id}: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Push all pending punch records to ADMS server via iClock protocol.
     */
    public function syncPendingPunches(): array
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        $admsUrl = $creds && !empty($creds->url) ? rtrim($creds->url, '/') : 'https://adms.hartonomotor-group.com';

        $pendingPunches = PunchLog::where('adms_status', 'pending')
            ->orWhereNull('adms_status')
            ->orderBy('timestamp', 'asc')
            ->limit(200)
            ->get();

        if ($pendingPunches->isEmpty()) {
            return ['success' => true, 'message' => 'No pending punches to sync.', 'count' => 0];
        }

        $lines = [];
        foreach ($pendingPunches as $punch) {
            $state = strtolower($punch->punch_type) === 'out' ? 1 : 0;
            $timeStr = $punch->timestamp->format('Y-m-d H:i:s');
            $lines[] = "{$punch->employee_id}\t{$timeStr}\t{$state}\t0\t\t\t\t\t\t\t\t";
        }

        $body = implode("\n", $lines) . "\n";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'iClock Proxy/1.0',
                'Content-Type' => 'text/plain',
            ])->timeout(20)->withBody($body, 'text/plain')
              ->post("{$admsUrl}/iclock/cdata?SN=VIRTUAL_MOBILE_01&table=ATTLOG");

            if ($response->successful() || str_contains($response->body(), 'OK')) {
                $ids = $pendingPunches->pluck('id')->toArray();
                PunchLog::whereIn('id', $ids)->update([
                    'adms_status' => 'uploaded',
                    'synced_at' => Carbon::now(),
                ]);

                return [
                    'success' => true,
                    'message' => "Successfully pushed {$pendingPunches->count()} punch records to ADMS.",
                    'count' => $pendingPunches->count(),
                ];
            } else {
                throw new \Exception("ADMS returned HTTP {$response->status()}: {$response->body()}");
            }
        } catch (\Throwable $e) {
            Log::error("Failed to push punches to ADMS: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Failed to push punches to ADMS: " . $e->getMessage(),
                'count' => 0,
            ];
        }
    }

    /**
     * Send heartbeat (handshake & getrequest poll) to ADMS server
     * to keep virtual device status as 'Online'.
     *
     * @param string $sn
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendHeartbeat(string $sn = 'VIRTUAL_MOBILE_01'): array
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        $admsUrl = $creds && !empty($creds->url) ? rtrim($creds->url, '/') : 'https://adms.hartonomotor-group.com';

        try {
            // 1. Handshake request
            $handshakeRes = Http::withHeaders([
                'User-Agent' => 'iClock Proxy/1.0',
            ])->timeout(10)->get("{$admsUrl}/iclock/cdata", [
                'SN' => $sn,
                'DeviceName' => 'Mobile Gateway',
                'options' => 'all',
                'language' => '69',
                'pushver' => '2.4.1',
                'PushOptionsFlag' => '1',
            ]);

            // 2. Poll for commands (/iclock/getrequest)
            $pollRes = Http::withHeaders([
                'User-Agent' => 'iClock Proxy/1.0',
            ])->timeout(10)->get("{$admsUrl}/iclock/getrequest", [
                'SN' => $sn,
            ]);

            // 3. Acknowledge commands if any returned (Format: C:{id}:{command})
            if ($pollRes->successful()) {
                $body = trim($pollRes->body());
                if (!empty($body)) {
                    foreach (explode("\n", $body) as $line) {
                        $line = trim($line);
                        if (preg_match('/^C:(\d+):(.+)$/', $line, $matches)) {
                            $cmdId = $matches[1];
                            $cmdName = $matches[2];
                            Http::withHeaders([
                                'User-Agent' => 'iClock Proxy/1.0',
                                'Content-Type' => 'text/plain',
                            ])->timeout(5)->withBody("ID={$cmdId}&Return=0&CMD={$cmdName}\r\n", 'text/plain')
                              ->post("{$admsUrl}/iclock/devicecmd?SN={$sn}");
                        }
                    }
                }
            }

            $nowStr = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
            $this->setConfig('last_adms_heartbeat_time', $nowStr);
            $this->setConfig('last_adms_heartbeat_status', 'online');

            return [
                'success' => true,
                'message' => "Heartbeat sent successfully for SN: {$sn}",
                'timestamp' => $nowStr,
            ];
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::warning("ADMS Heartbeat error for SN {$sn}: {$errorMsg}");
            $this->setConfig('last_adms_heartbeat_status', "failed: {$errorMsg}");

            return [
                'success' => false,
                'message' => "ADMS Heartbeat failed: {$errorMsg}",
            ];
        }
    }
}
