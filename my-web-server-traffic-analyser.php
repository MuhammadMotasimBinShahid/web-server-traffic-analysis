<?php

if ($argc < 2) {
    echo "Usage: php my-web-server-traffic-analyser.php <logFile> [<from>] [<to>]\n";
    exit(1);
}

$logFile = $argv[1];
$from = isset($argv[2]) ? strtotime($argv[2]) : strtotime('-1 hour');
$to = isset($argv[3]) ? strtotime($argv[3]) : time();

if (!file_exists($logFile)) {
    echo "Log file not found\n";
    exit(1);
}

/**
 * @throws Exception
 */
function readLogFile($logFile, $from, $to)
{
    $handle = fopen($logFile, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
           yield $line;
        }
        fclose($handle);
    } else {
        throw new Exception("Error reading log file");
    }
}

$requestCounts = [];
$statusCounts = [];

try {
    foreach (readLogFile($logFile, $from, $to) as $log) {
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $log, $matches)) {
            $timestamp = strtotime($matches[1]);
            if ($timestamp && $timestamp >= $from && $timestamp <= $to) {
                $minute = date('Y-m-d H:i', $timestamp);
                if (!isset($requestCounts[$minute])) {
                    $requestCounts[$minute] = 0;
                }
                $requestCounts[$minute]++;

                if (preg_match('/Status: (\d{3})/', $log, $matches)) {
                    $status = $matches[1];
                    if (!isset($statusCounts[$minute])) {
                        $statusCounts[$minute] = [];
                    }
                    if (!isset($statusCounts[$minute][$status])) {
                        $statusCounts[$minute][$status] = 0;
                    }
                    $statusCounts[$minute][$status]++;
                }
            }
        }
    }
} catch (Exception $e) {
    echo "Error reading log file: " . $e->getMessage() . "\n";
    exit(1);
}

$requestPerMinute = array_values($requestCounts);
sort($requestPerMinute);
$totalRequests = array_sum($requestPerMinute);
$countRequests = count($requestPerMinute);
$maxRPM = $countRequests > 0 ? max($requestPerMinute) : 0;
$averageRPM = $countRequests > 0 ? $totalRequests / $countRequests : 0;
$percentile95Index = (int) (0.95 * $countRequests) - 1;
$percentile95RPM = $countRequests > 0 ? $requestPerMinute[$percentile95Index] : 0;

echo "Statistics from " . date('Y-m-d H:i:s', $from) . " to " . date('Y-m-d H:i:s', $to) . "\n";
echo "Maximum RPM: $maxRPM\n";
echo "Average RPM: " . round($averageRPM, 2) . "\n";
echo "95th percentile RPM: $percentile95RPM\n";


echo "HTTP Status Codes Rate per Minute:\n";
if (empty($statusCounts)) {
    echo "No status codes recorded in the given time range.\n";
} else {
    foreach ($statusCounts as $minute => $statuses) {
        echo "$minute\n";
        foreach ($statuses as $status => $count) {
            echo "  $status: $count\n";
        }
    }
}

?>




