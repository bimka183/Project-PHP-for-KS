<?php
// server.php
header('Content-Type: application/json; charset=utf-8');

if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
    // 1. Получаем количество ядер
    $num_cpus = (int) shell_exec('powershell -Command "(Get-CimInstance Win32_ComputerSystem).NumberOfLogicalProcessors"');
    if (!$num_cpus) $num_cpus = 1;

    // 2. Команда в одну строку без переносов.
    // Используем Get-CimInstance, так как он быстрее и стабильнее
    $psCommand = 'Get-CimInstance Win32_PerfFormattedData_PerfProc_Process | Where-Object { $_.IDProcess -gt 0 -and $_.Name -notmatch \'\b(Idle|_Total|System)\b\' } | Select-Object @{Name=\'cpu\';Expression={[math]::Round($_.PercentProcessorTime / ' . $num_cpus . ', 2)}}, @{Name=\'pid\';Expression={$_.IDProcess}}, @{Name=\'cmd\';Expression={$_.Name}} | ConvertTo-Json -Compress';

    $json = shell_exec("powershell -Command \"$psCommand\"");

    // Если PowerShell вернул пустую строку, создаем пустой массив
    $rows = [];
    if (!empty($json)) {
        $data = json_decode($json, true);
        // Если вернулся один объект (а не массив), превращаем его в массив
        if (isset($data['pid'])) {
            $rows = [$data];
        } elseif (is_array($data)) {
            $rows = $data;
        }
    }

    // Добавляем заглушку для Owner (в Windows получить владельца процесса быстро — сложно)
    foreach ($rows as &$row) {
        $row['user'] = 'System';
    }
    unset($row);

} else {
    // Linux (оставляем как было, оно обычно работает надежно)
    $cmd = 'ps -eo pcpu,pid,user,comm --no-headers';
    $output = [];
    exec($cmd, $output);
    $rows = [];
    foreach ($output as $line) {
        $parts = preg_split('/\s+/', trim($line), 4);
        if (count($parts) >= 4) {
            $rows[] = [
                'cpu'  => (float)$parts[0],
                'pid'  => (int)$parts[1],
                'user' => $parts[2],
                'cmd'  => $parts[3],
            ];
        }
    }
}

// Итоговый ответ
echo json_encode([
    'time' => time(),
    'processes' => array_values($rows)
], JSON_UNESCAPED_UNICODE);