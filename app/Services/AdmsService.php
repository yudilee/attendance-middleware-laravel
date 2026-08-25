<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\AdmsCredential;
use App\Models\AppConfig;
use App\Models\PunchLog;
use App\Models\AdmsRegisteredEmployee;
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
            $skippedCount = 0;
            $firstRowLogged = false;
            $now = Carbon::now();
            foreach ($usersRaw as $row) {
                if (!is_array($row)) {
                    Log::warning('ADMS row is not an array, skipping', ['row' => $row]);
                    continue;
                }

                // ── Debug: Log structure of the first row ──
                if (!$firstRowLogged) {
                    Log::info('ADMS row structure', [
                        'count' => count($row),
                        'keys' => array_keys($row),
                    ]);
                    $firstRowLogged = true;
                }

                // ── Debug: Log raw row data for every row ──
                Log::debug('ADMS employee row', ['row' => $row]);

                // The ADMS array format is: [pin, name, dept, None, None, None, None]
                // Guard: require at least 3 elements for safe positional access
                if (count($row) < 3) {
                    Log::warning('ADMS row has fewer than 3 elements, skipping', ['count' => count($row), 'row' => $row]);
                    $skippedCount++;
                    continue;
                }

                $pin = trim((string)($row[0] ?? ''));       // Index 0 = employee_id (PIN), e.g. "000016376"
                $name = trim((string)($row[1] ?? ''));      // Index 1 = full_name, e.g. "Agus Apri"
                $dept = trim((string)($row[2] ?? ''));      // Index 2 = department, e.g. "1 Default Dept"

                // ── Validate PIN & Name — skip empty/whitespace PINs and department header/summary rows ──
                if (
                    empty($pin) ||
                    in_array(strtolower($pin), ['none', 'null', '-', '--', 'undefined', 'n/a']) ||
                    !preg_match('/^[0-9A-Za-z_-]+$/', $pin) ||
                    $name === '1 Default Dept' && (empty($pin) || $dept === 'None')
                ) {
                    Log::warning('Skipping invalid ADMS row (empty/invalid PIN or header row)', ['pin' => $pin, 'name' => $name, 'dept' => $dept, 'row' => $row]);
                    $skippedCount++;
                    continue;
                }

                // ── Use updateOrCreate to avoid select-then-insert race condition ──
                Employee::updateOrCreate(
                    ['employee_id' => $pin],  // Match on PIN (index 0)
                    [
                        'adms_id' => $pin,     // adms_id is also the PIN (no separate ADMS ID in this format)
                        'full_name' => !empty($name) ? $name : "Employee {$pin}",
                        'department' => $dept,
                        'is_active' => true,
                        'is_deleted' => false,
                        'employee_type' => 'regular',
                        'last_synced' => $now,
                    ]
                );

                // ── Reset is_deleted if employee was previously soft-deleted ──
                // (updateOrCreate with is_deleted=false already handles this)

                // Upsert AdmsRegisteredEmployee record for this synced employee
                AdmsRegisteredEmployee::updateOrCreate(
                    ['employee_id' => $pin],
                    [
                        'employee_name' => !empty($name) ? $name : "Employee {$pin}",
                        'sync_status' => 'registered',
                        'last_synced_at' => $now,
                        'registered_at' => $now,
                    ]
                );

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
     * Pull biometric punch records (ATTLOG) from ADMS server and store them locally.
     * Records Device SN, Machine Name, and maps to the appropriate Branch.
     */
    public function syncPunchesFromAdms(?string $startDate = null, ?string $endDate = null): array
    {
        try {
            $creds = AdmsCredential::where('is_active', true)->first();
            if (!$creds || empty($creds->url) || empty($creds->username) || empty($creds->password)) {
                throw new \Exception('ADMS credentials not configured or inactive.');
            }

            $admsUrl = rtrim($creds->url, '/');
            $cookieJar = new \GuzzleHttp\Cookie\CookieJar();

            // 1. Get CSRF Token
            $loginPageRes = Http::withOptions(['cookies' => $cookieJar, 'timeout' => 20])
                ->get("{$admsUrl}/iclock/accounts/login/");

            $csrfToken = null;
            if (preg_match('/name=["\']csrfmiddlewaretoken["\']\s+value=["\']([^"\']+)["\']/', $loginPageRes->body(), $matches)) {
                $csrfToken = $matches[1];
            } else {
                foreach ($cookieJar->toArray() as $c) {
                    if ($c['Name'] === 'csrftoken') {
                        $csrfToken = $c['Value'];
                        break;
                    }
                }
            }

            if (!$csrfToken) {
                throw new \Exception('Failed to obtain CSRF token from ADMS login page.');
            }

            // 2. Perform Login
            $loginRes = Http::asForm()
                ->withOptions(['cookies' => $cookieJar, 'timeout' => 25, 'allow_redirects' => true])
                ->withHeaders(['Referer' => "{$admsUrl}/iclock/accounts/login/"])
                ->post("{$admsUrl}/iclock/accounts/login/", [
                    'username' => $creds->username,
                    'password' => $creds->password,
                    'csrfmiddlewaretoken' => $csrfToken,
                ]);

            if (!$loginRes->successful() && $loginRes->status() !== 302) {
                throw new \Exception("ADMS authentication failed with HTTP {$loginRes->status()}");
            }

            // 3. Fetch transaction (punch) data
            $url = "{$admsUrl}/iclock/data/transaction/?p=1&l=5000";
            if ($startDate) {
                $url .= "&StartTime=" . urlencode($startDate);
            }
            if ($endDate) {
                $url .= "&EndTime=" . urlencode($endDate);
            }

            $attlogRes = Http::withOptions(['cookies' => $cookieJar, 'timeout' => 45])
                ->get($url);

            if (!$attlogRes->successful()) {
                throw new \Exception("Failed to fetch transactions from ADMS: HTTP {$attlogRes->status()}");
            }

            $html = $attlogRes->body();

            // 4. Extract data array
            if (!preg_match('/data=\[(.*?)\];/s', $html, $matches)) {
                return [
                    'success' => true,
                    'message' => 'No punch log data array found in ADMS transaction response.',
                    'synced' => 0,
                ];
            }

            $dataStr = '[' . $matches[1] . ']';
            $dataStr = str_replace('deviceText', '""', $dataStr);
            $dataStr = preg_replace('/,\s*\]/', ']', $dataStr);

            $rows = json_decode($dataStr, true);
            if (!is_array($rows)) {
                throw new \Exception('Failed to parse transaction JSON data from ADMS.');
            }

            // Cache known branches
            $branches = \App\Models\Branch::where('is_active', true)->get();

            $syncedCount = 0;
            $now = Carbon::now();

            foreach ($rows as $row) {
                if (!is_array($row) || count($row) < 4) {
                    continue;
                }

                // ADMS transaction row structure:
                // [id, pin, name, "DD/MM/YY HH:MM:SS", "Check in", "Fingerprint", "0", "0", "SN(IP)", ""]
                $pin = trim((string)($row[1] ?? ''));
                $name = trim((string)($row[2] ?? ''));
                $timeRaw = trim((string)($row[3] ?? ''));
                $typeStr = trim((string)($row[4] ?? 'Check in'));
                $verifyStr = trim((string)($row[5] ?? 'Fingerprint'));
                $deviceRaw = trim((string)($row[8] ?? ''));

                if (empty($pin) || empty($timeRaw)) {
                    continue;
                }

                // Parse Timestamp (support DD/MM/YY HH:MM:SS and YYYY-MM-DD HH:MM:SS)
                $timestamp = null;
                if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s+(\d{1,2}:\d{2}(?::\d{2})?)$/', $timeRaw, $tm)) {
                    $day = str_pad($tm[1], 2, '0', STR_PAD_LEFT);
                    $month = str_pad($tm[2], 2, '0', STR_PAD_LEFT);
                    $year = strlen($tm[3]) === 2 ? '20' . $tm[3] : $tm[3];
                    $time = $tm[4];
                    $timestamp = Carbon::parse("{$year}-{$month}-{$day} {$time}");
                } else {
                    try {
                        $timestamp = Carbon::parse($timeRaw);
                    } catch (\Throwable) {
                        continue;
                    }
                }

                // Guard: validate PIN and timestamp
                if (
                    in_array(strtolower($pin), ['none', 'null', '-', '--', 'undefined', 'n/a']) ||
                    !preg_match('/^[0-9A-Za-z_-]+$/', $pin) ||
                    !$timestamp
                ) {
                    continue;
                }

                // Extract Device SN and Device IP
                $deviceSn = null;
                $deviceIp = null;
                if (!empty($deviceRaw)) {
                    if (preg_match('/^([^\(]+)(?:\((.*?)\))?$/', $deviceRaw, $dm)) {
                        $deviceSn = trim($dm[1]);
                        $deviceIp = trim($dm[2] ?? '');
                    } else {
                        $deviceSn = $deviceRaw;
                    }
                }

                // Resolve branch from device SN or IP or Name
                $branchId = null;
                if ($deviceSn || $deviceIp) {
                    $searchTarget = strtolower(($deviceSn ?? '') . ' ' . ($deviceIp ?? ''));
                    foreach ($branches as $branch) {
                        if (str_contains($searchTarget, strtolower($branch->name))) {
                            $branchId = $branch->id;
                            break;
                        }
                    }
                }

                // Fallback to employee's assigned branch if device didn't match
                if (!$branchId) {
                    $emp = \App\Models\Employee::with('group.branch')->where('employee_id', $pin)->first();
                    $branchId = $emp?->group?->branch_id ?? $branches->first()?->id;
                }

                $punchType = str_contains(strtolower($typeStr), 'out') ? 'Out' : 'In';
                $isBiometric = !str_contains(strtolower($verifyStr), 'password');

                // Idempotent upsert matching employee_id and exact timestamp
                PunchLog::updateOrCreate(
                    [
                        'employee_id' => $pin,
                        'timestamp' => $timestamp,
                    ],
                    [
                        'punch_type' => $punchType,
                        'punch_source' => 'adms_fingerprint',
                        'device_sn' => $deviceSn ?? 'ADMS_DEVICE',
                        'device_name' => $deviceIp ? "Fingerprint Terminal ({$deviceIp})" : ($deviceSn ?? 'Fingerprint Terminal'),
                        'branch_id' => $branchId,
                        'biometric_verified' => $isBiometric,
                        'adms_status' => 'uploaded',
                        'gps_time_validated' => true,
                        'tz_offset_minutes' => 420,
                    ]
                );

                $syncedCount++;
            }

            Log::info("Synced {$syncedCount} punch logs from ADMS.");

            return [
                'success' => true,
                'message' => "Successfully synced {$syncedCount} punch logs from ADMS.",
                'synced' => $syncedCount,
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error("Failed to sync punches from ADMS: {$msg}");
            return [
                'success' => false,
                'message' => "Failed to sync punches from ADMS: {$msg}",
                'synced' => 0,
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
     * Push all active regular employee names to ADMS server via OPERLOG USER records.
     *
     * Sends employee data (PIN + Name) in batches of 50 to register/update users
     * on the ADMS / ZKTeco iClock server using the OPERLOG protocol.
     *
     * @return array ['success' => bool, 'message' => string, 'total' => int, 'success_count' => int, 'failed_count' => int]
     */
    public function syncAllEmployeesToAdms(): array
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        $admsUrl = $creds && !empty($creds->url) ? rtrim($creds->url, '/') : 'https://adms.hartonomotor-group.com';

        $employees = Employee::where('is_active', true)
            ->where('is_deleted', false)
            ->where(function ($q) {
                $q->where('employee_type', 'regular')
                  ->orWhereNull('employee_type');
            })
            ->get(['employee_id', 'full_name']);

        if ($employees->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No active regular employees to sync.',
                'total' => 0,
                'success_count' => 0,
                'failed_count' => 0,
            ];
        }

        $total = $employees->count();
        $successCount = 0;
        $failedCount = 0;
        $chunkSize = 50;
        $now = Carbon::now();

        // The TZ string is a 48-character field of timezone overrides (all zeros = use device default)
        $tzDefault = str_repeat('0', 48);

        try {
            foreach ($employees->chunk($chunkSize) as $chunk) {
                $lines = [];
                foreach ($chunk as $emp) {
                    $pin = $emp->employee_id;
                    $name = trim($emp->full_name ?? "Employee {$pin}");
                    // Escape tabs and newlines in name to avoid protocol corruption
                    $name = str_replace(["\t", "\n", "\r"], ' ', $name);
                    $lines[] = "OPERLOG: PIN={$pin}\tName={$name}\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ={$tzDefault}";
                }

                $body = implode("\n", $lines) . "\n";

                $response = Http::withHeaders([
                    'User-Agent' => 'iClock Proxy/1.0',
                    'Content-Type' => 'text/plain',
                ])->timeout(30)->withBody($body, 'text/plain')
                  ->post("{$admsUrl}/iclock/cdata?SN=VIRTUAL_MOBILE_01&table=OPERLOG");

                if ($response->successful() || str_contains($response->body(), 'OK')) {
                    $successCount += $chunk->count();

                    // Update AdmsRegisteredEmployee for each successfully pushed employee
                    foreach ($chunk as $emp) {
                        AdmsRegisteredEmployee::updateOrCreate(
                            ['employee_id' => $emp->employee_id],
                            [
                                'employee_name' => trim($emp->full_name ?? "Employee {$emp->employee_id}"),
                                'sync_status' => 'registered',
                                'last_synced_at' => $now,
                            ]
                        );
                    }
                } else {
                    $failedCount += $chunk->count();

                    // Mark failed employees in AdmsRegisteredEmployee
                    foreach ($chunk as $emp) {
                        AdmsRegisteredEmployee::updateOrCreate(
                            ['employee_id' => $emp->employee_id],
                            [
                                'employee_name' => trim($emp->full_name ?? "Employee {$emp->employee_id}"),
                                'sync_status' => 'failed',
                                'error_message' => "ADMS OPERLOG returned HTTP {$response->status()}",
                                'last_synced_at' => $now,
                            ]
                        );
                    }

                    Log::warning("ADMS OPERLOG chunk failed for {$chunk->count()} employees: HTTP {$response->status()}");
                }
            }

            $nowStr = Carbon::now('Asia/Jakarta')->format('Y-m-d H:i:s');
            $this->setConfig('last_adms_push_names_time', $nowStr);
            $this->setConfig('last_adms_push_names_count', (string)$successCount);
            $this->setConfig('last_adms_push_names_status', $failedCount > 0 ? 'partial' : 'success');

            Log::info("Pushed {$successCount} employee names to ADMS ({$failedCount} failed) out of {$total} total.");

            return [
                'success' => $failedCount === 0,
                'message' => "Synced {$total} employee names to ADMS ({$successCount} success, {$failedCount} failed).",
                'total' => $total,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ];
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("ADMS Push Names Error: {$errorMsg}");
            $this->setConfig('last_adms_push_names_status', "failed: {$errorMsg}");

            // Mark all as failed
            foreach ($employees as $emp) {
                AdmsRegisteredEmployee::updateOrCreate(
                    ['employee_id' => $emp->employee_id],
                    [
                        'employee_name' => trim($emp->full_name ?? "Employee {$emp->employee_id}"),
                        'sync_status' => 'failed',
                        'error_message' => $errorMsg,
                        'last_synced_at' => Carbon::now(),
                    ]
                );
            }

            return [
                'success' => false,
                'message' => "ADMS push names failed: {$errorMsg}",
                'total' => $total,
                'success_count' => $successCount,
                'failed_count' => $total - $successCount,
            ];
        }
    }

    /**
     * Delete an employee from ADMS server via OPERLOG protocol.
     *
     * Logs into ADMS using CSRF auth and sends:
     *   DATA DELETE USERINFO PIN={employeeId}
     *
     * @param string $employeeId The employee PIN to delete from ADMS
     * @return array ['success' => bool, 'message' => string]
     */
    public function deleteEmployeeFromAdms(string $employeeId): array
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        $admsUrl = $creds && !empty($creds->url) ? rtrim($creds->url, '/') : 'https://adms.hartonomotor-group.com';

        // Build the OPERLOG delete command
        $body = "DATA DELETE USERINFO PIN={$employeeId}\n";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'iClock Proxy/1.0',
                'Content-Type' => 'text/plain',
            ])->timeout(15)->withBody($body, 'text/plain')
              ->post("{$admsUrl}/iclock/cdata?SN=VIRTUAL_MOBILE_01&table=OPERLOG");

            if ($response->successful() || str_contains($response->body(), 'OK') || str_contains($response->body(), 'DELETE')) {
                Log::info("Successfully deleted employee PIN={$employeeId} from ADMS.");

                return [
                    'success' => true,
                    'message' => "Employee PIN={$employeeId} deleted from ADMS successfully.",
                ];
            } else {
                throw new \Exception("ADMS returned HTTP {$response->status()}: {$response->body()}");
            }
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("Failed to delete employee PIN={$employeeId} from ADMS: {$errorMsg}");

            return [
                'success' => false,
                'message' => "Failed to delete employee from ADMS: {$errorMsg}",
            ];
        }
    }

    /**
     * Register or update a single employee on ADMS via OPERLOG USER record.
     *
     * Sends a single OPERLOG USER record to ADMS for this specific employee,
     * updating their name or creating them on the ADMS server.
     *
     * @param Employee $employee The employee to register/update on ADMS
     * @return array ['success' => bool, 'message' => string]
     */
    public function registerEmployeeOnAdms(Employee $employee): array
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        $admsUrl = $creds && !empty($creds->url) ? rtrim($creds->url, '/') : 'https://adms.hartonomotor-group.com';

        $pin = $employee->employee_id;
        $name = trim($employee->full_name ?? "Employee {$pin}");
        // Escape tabs and newlines in name to avoid protocol corruption
        $name = str_replace(["\t", "\n", "\r"], ' ', $name);
        $tzDefault = str_repeat('0', 48);

        $line = "OPERLOG: PIN={$pin}\tName={$name}\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ={$tzDefault}";
        $body = $line . "\n";

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'iClock Proxy/1.0',
                'Content-Type' => 'text/plain',
            ])->timeout(15)->withBody($body, 'text/plain')
              ->post("{$admsUrl}/iclock/cdata?SN=VIRTUAL_MOBILE_01&table=OPERLOG");

            if ($response->successful() || str_contains($response->body(), 'OK')) {
                // Update AdmsRegisteredEmployee record
                AdmsRegisteredEmployee::updateOrCreate(
                    ['employee_id' => $pin],
                    [
                        'employee_name' => $name,
                        'sync_status' => 'registered',
                        'registered_at' => Carbon::now(),
                        'last_synced_at' => Carbon::now(),
                    ]
                );

                Log::info("Employee PIN={$pin} registered on ADMS successfully.");

                return [
                    'success' => true,
                    'message' => "Employee {$name} (PIN: {$pin}) registered on ADMS successfully.",
                ];
            } else {
                throw new \Exception("ADMS returned HTTP {$response->status()}: {$response->body()}");
            }
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::error("Failed to register employee PIN={$pin} on ADMS: {$errorMsg}");

            // Mark as failed in AdmsRegisteredEmployee
            AdmsRegisteredEmployee::updateOrCreate(
                ['employee_id' => $pin],
                [
                    'employee_name' => $name,
                    'sync_status' => 'failed',
                    'error_message' => $errorMsg,
                    'last_synced_at' => Carbon::now(),
                ]
            );

            return [
                'success' => false,
                'message' => "Failed to register employee on ADMS: {$errorMsg}",
            ];
        }
    }

    /**
     * Send a heartbeat/ping to the ADMS iClock server to maintain Online status.
     *
     * Makes a GET request to the ADMS iClock cdata endpoint with device info,
     * simulating a device heartbeat so the ADMS server sees the gateway as online.
     *
     * @param string $sn Device Serial Number (default: VIRTUAL_MOBILE_01)
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendHeartbeat(string $sn = 'VIRTUAL_MOBILE_01'): array
    {
        $creds = AdmsCredential::where('is_active', true)->first();
        $admsUrl = $creds && !empty($creds->url) ? rtrim($creds->url, '/') : 'https://adms.hartonomotor-group.com';

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'iClock Proxy/1.0',
            ])->timeout(15)->get("{$admsUrl}/iclock/cdata", [
                'SN' => $sn,
                'DeviceName' => 'Mobile Gateway',
                'options' => 'all',
                'language' => '69',
                'pushver' => '2.4.1',
                'PushOptionsFlag' => '1',
            ]);

            if ($response->successful()) {
                Log::info("ADMS heartbeat successful for SN: {$sn}");

                return [
                    'success' => true,
                    'message' => "Heartbeat sent successfully for SN: {$sn}.",
                ];
            }

            throw new \Exception("ADMS heartbeat returned HTTP {$response->status()}: {$response->body()}");
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            Log::warning("ADMS Heartbeat error for SN {$sn}: {$errorMsg}");

            return [
                'success' => false,
                'message' => "ADMS heartbeat failed for SN {$sn}: {$errorMsg}",
            ];
        }
    }
}
