<?php
require "vendor/autoload.php";
include_once 'RubiconAnalyticsReport.php';

// read the configuration file
$dataDir = getenv('KBC_DATADIR') . DIRECTORY_SEPARATOR;
$configFile = $dataDir . 'config.json';
$config = json_decode(file_get_contents($configFile), true);

$outFile = new \Keboola\Csv\CsvFile(
    $dataDir . 'out' . DIRECTORY_SEPARATOR . 'tables' . DIRECTORY_SEPARATOR . 'output.csv'
);
$isSetOutFileHeader = false;

$outErrors = new \Keboola\Csv\CsvFile(
    $dataDir . 'out' . DIRECTORY_SEPARATOR . 'tables' . DIRECTORY_SEPARATOR . 'api-errors.csv'
);
$outErrors->writeRow(['url', 'error', 'errorData']);


if (!isset($config['parameters']) || !$config['parameters']) {
    echo 'Missing config parameters';
    exit(1);
}

if (!isset($config['parameters']['params'])) {
    echo 'Missing call params';
    exit(1);
}

$dayBack = 1;
if (isset($config['parameters']['dayBack'])) {
    if (intval($config['parameters']['dayBack']) > 0) {
        $dayBack = intval($config['parameters']['dayBack']);
    }
}

$hour = 3600;
$day = 86400 * $dayBack;
$apiTimeShift = -8 - (intval(date('Z')) / $hour);
$zoneDiff = $hour * $apiTimeShift;


for ($h = 0; $h < 24; $h++) {
    if($h < 10) {
        $hh = '0'.$h;
    } else {
        $hh = $h;
    }
    $yesterday = date('Y-m-d', time() - $day);
    $start = date('Y-m-d\TH:i:s-08:00', strtotime($yesterday . 'T' . $hh . ':00:00' . date('P')) + $zoneDiff);
    $end = date('Y-m-d\TH:i:s-08:00', strtotime($yesterday . 'T' . $hh . ':59:00' . date('P')) + $zoneDiff);

    $config['parameters']['params']['start'] = $start;
    $config['parameters']['params']['end'] = $end;

    try {
        $rubiconAnalyticsReport = new RubiconAnalyticsReport($config['parameters']);
        $response = $rubiconAnalyticsReport->call();


        if (isset($response['items']) && $response['items']) {
            $items = $response['items'];

            if (!$isSetOutFileHeader) {
                $outFile->writeRow(array_keys($items[0]));
                $isSetOutFileHeader = true;
            }

            foreach ($items as $item) {
                $item['date'] = $yesterday . ' ' . $hh . ':00:00';
                $outFile->writeRow(array_values($item));
            }
        }


        /* Errors */
        if (isset($response['errors'])) {
            foreach ($response['errors'] as $error) {
                $outFile->writeRow(array_values($error));
            }
        }

    } catch (InvalidArgumentException $e) {
        echo $e->getMessage();
        exit(1);
    } catch (\Throwable $e) {
        echo $e->getMessage();
        exit(2);
    }
}
