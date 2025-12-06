<?php
// server.php

header('Content-Type: application/json; charset=utf-8');

if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
    // Получаем количество логических процессоров
    $num_cpus = (int) shell_exec('powershell -Command "(Get-CimInstance Win32_ComputerSystem).NumberOfLogicalProcessors"');
    
    // Команда для получения данных о процессах
    $cmd = 'powershell -Command "Get-CimInstance Win32_PerfFormattedData_PerfProc_Process | Where-Object { $_.IDProcess -gt 0 -and $_.Name -notmatch \'^(Idle|_Total|System)$\' } | Select-Object @{Name=\'CPU\';Expression={[math]::Round([decimal]($_.PercentProcessorTime / ' . $num_cpus . '), 2)}}, @{Name=\'Id\';Expression={$_.IDProcess}}, @{Name=\'Name\';Expression={$_.Name}} | ConvertTo-Json -Compress"';

    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    
    $json = implode("\n", $output);
    $rows = json_decode($json, true);
    
    if (is_array($rows)) {
        // Переименовываем ключи для единообразия с Linux
        foreach ($rows as &$row) {
            $row = [
                'cpu' => (float)$row['CPU'],
                'pid' => (int)$row['Id'],
                'user' => 'N/A', // Для Windows нужно отдельно получать владельца
                'cmd' => $row['Name'],
            ];
        }
        unset($row);
    } else {
        $rows = [];
    }
    
} else {
    // Linux код остается без изменений
    $cmd = 'ps -eo pcpu,pid,user,args --no-headers';
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    
    $rows = [];
    $num_cpus = (int) shell_exec('nproc');
    foreach ($output as $line) {
        $parts = preg_split('/\s+/', trim($line), 4);
        $raw_cpu = (float)$parts[0];
        $cpu_percent = round($raw_cpu / $num_cpus, 2);
        if (count($parts) >= 4) {
            $rows[] = [
                'cpu' => $cpu_percent,
                'pid' => $parts[1],
                'user' => $parts[2],
                'cmd' => $parts[3],
            ];
        }
    }
}

// Фильтруем процессы с нулевой нагрузкой (опционально)
$filtered_rows = array_filter($rows, function($process) {
    return $process['cpu'] >= 0;
});

echo json_encode(
    [
        'time' => time(),
        'num_cpus' => $num_cpus ?? 1,
        'processes' => array_values($filtered_rows) // сбрасываем ключи
    ],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
);