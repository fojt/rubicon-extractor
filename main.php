<?php
require "vendor/autoload.php";
include_once 'RubiconAnalyticsReport.php';

// read the configuration file
$dataDir = getenv('KBC_DATADIR') . DIRECTORY_SEPARATOR;
$configFile = $dataDir . 'config.json';
$config = json_decode(file_get_contents($configFile), true);

try {
    $rubiconAnalyticsReport = new RubiconAnalyticsReport($config);
    $response = $rubiconAnalyticsReport->call();

    if (isset($response['statusCode'])
        && (intval($response['statusCode']) >= 200 && intval($response['statusCode'] < 400))
    ) {

        $outFile = new \Keboola\Csv\CsvFile(
            $dataDir . 'out' . DIRECTORY_SEPARATOR . 'tables' . DIRECTORY_SEPARATOR . 'destination.csv'
        );

        $data = json_decode($response['result']);
        if (isset($data->data)
            && isset($data->data->items)
            && is_array($data->data->items)
        ) {
            $items = $data->data->items;

            $outFile->writeRow(
                array_keys(get_object_vars($items[0]))
            );

            foreach ($items as $item) {
                $outFile->writeRow(
                    array_values(get_object_vars($item))
                );
            }
        }

    } else {
        if (isset($response['error'])) {
            echo $response['error'];
            exit(2);
        }
    }

} catch (InvalidArgumentException $e) {
    echo $e->getMessage();
    exit(1);
} catch (\Throwable $e) {
    echo $e->getMessage();
    exit(2);
}