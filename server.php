<?php
// server.php
header('Content-Type: application/json; charset=utf-8');

// Проверим ОС
if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
    // Используем PowerShell
    $cmd = 'powershell -Command "Get-Process | Select-Object CPU,Id,ProcessName | ConvertTo-Json"';
} else {
    // Для Linux/Unix оставляем ps
    $cmd = 'ps -eo pcpu,pid,user,args --no-headers';
}

$output = [];
$return_var = 0;
exec($cmd, $output, $return_var);

// PowerShell возвращает JSON сразу, ps — строки
if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
    $json = implode("\n", $output);
    $rows = json_decode($json, true);
} else {
    $rows = [];
    foreach ($output as $line) {
        $parts = preg_split('/\s+/', trim($line), 4);
        if (count($parts) >= 4) {
            $rows[] = [
                'cpu' => $parts[0],
                'pid' => $parts[1],
                'user' => $parts[2],
                'cmd' => $parts[3],
            ];
        }
    }
}

// Вернём JSON
echo json_encode(['time' => time(), 'processes' => $rows], JSON_UNESCAPED_UNICODE);
