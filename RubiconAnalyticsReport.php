<?php

class RubiconAnalyticsReport {

    private $config;
    private $rubiconApiSpecification;
    private $result = [];
    private $callParams;

    public function __construct(array $config) {
        $this->rubiconApiSpecification = $this->getRubiconApiSpecification();
        $this->config = $config;
        $params = $config['params'];
        $this->callParams = [
            'dimensions' => $this->getColumnNamesForType(
                ((isset($params['labelsToReturn'])) ? $params['labelsToReturn'] : []),
                'Dimension'
            ),
            'metrics' => $this->getColumnNamesForType(
                ((isset($params['labelsToReturn'])) ? $params['labelsToReturn'] : []),
                'Metric'
            ),
            'filters' => ((isset($params['filters'])) ? $params['filters'] : ''),
            'account' => $params['account'],
            'start' => $params['start'],
            'end' => $params['end'],
            'currency' => $params['currency'],
            'limit' => $params['limit']
        ];

        $this->result['items'] = [];
        $this->result['errors'] = [];
        $this->result['messages'] = [];
    }

    private function getRubiconApiSpecification($file_name = __DIR__ . '/conversions.json') {
        return json_decode(file_get_contents($file_name), true);
    }

    private function getColumnNamesForType(array $names, $forType) {
        $buffer = [];
        if ($names) {
            foreach ($names as $name) {
                foreach ($this->rubiconApiSpecification as $spec) {
                    if (
                        ($spec['Label'] == $name || $spec['API Column Key'] == $name)
                        && $spec['Type'] == $forType
                    ) {
                        $buffer[] = $spec['API Column Key'];
                    }
                }
            }
        }
        return $buffer;
    }

    public function call() {
        if (isset($this->config['params']['dimensionsFilter']) && count($this->config['params']['dimensionsFilter']) > 0) {
            $this->callGranularRubicon(-1, '');
        } else {
            $this->makeFinalRubiconCall($this->callParams);
        }
        return $this->result;
    }

    private function makeFinalRubiconCall($params) {

        $result = $this->callRubicon($params);

        if (isset($result['result'])) {
            $items = json_decode($result['result'], true);
            if (isset($items['data']) && isset($items['data']['items']) && $items['data']['items']) {
                $this->result['items'] = array_merge($this->result['items'], $items['data']['items']);

            } elseif (isset($items['errorMessage'])) {
                $this->result['errors'][] = [
                    'url' => $result['url'],
                    'error' => $items['errorMessage'],
                    'errorData' => (isset($items['errorDetails'])) ? $items['errorDetails'] : ''
                ];
            }

            $this->result['messages'][] = ($items['message'])? $items['message'] : '';

        } elseif (isset($result['error'])) {
            $this->result['errors'][] = [
                'url' => $result['url'],
                'error' => $result['error'],
                'errorData' => ''
            ];
        }
    }

    private function callGranularRubicon($index, $filters) {
        $params = $this->config['params'];
        $newIndex = $index + 1;
        $newCallParams = $this->callParams;

        if (($newIndex + 1) > count($params['dimensionsFilter'])) {
            $newCallParams['filters'] = $filters;
            $this->makeFinalRubiconCall($newCallParams);

        } else {
            $newDimension = $this->getColumnNamesForType([$params['dimensionsFilter'][$newIndex]], 'Dimension');
            $newMetric = $this->getColumnNamesForType([$params['metricFilter'][0]], 'Metric');
            $newCallParams['dimensions'] = $newDimension[0];
            $newCallParams['metrics'] = $newMetric[0];
            $newCallParams['filters'] = $filters;
            $result = $this->callRubicon($newCallParams);

            if (isset($result['result'])) {
                $items = json_decode($result['result'], true);
                if (isset($items['data']) && isset($items['data']['items']) && $items['data']['items']) {
                    foreach ($items['data']['items'] as $key => $item) {
                        if (isset($item[$newMetric[0]]) && $item[$newMetric[0]] != 0.0) {
                            $value = $item[$newDimension[0]];
                            $newFilters = $filters . 'dimension:' . $newDimension[0] . '==' . $value . ';';
                            $this->callGranularRubicon($newIndex, $newFilters);

                        }
                    }
                } elseif (isset($items['errorMessage'])) {
                    $this->result['errors'][] = [
                        'url' => $result['url'],
                        'error' => $items['errorMessage'],
                        'errorData' => (isset($items['errorDetails'])) ? $items['errorDetails'] : ''
                    ];
                }
            } elseif (isset($result['error'])) {
                $this->result['errors'][] = [
                    'url' => $result['url'],
                    'error' => $result['error'],
                    'errorData' => ''
                ];
            }
        }
    }

    private function callRubicon($params) {
        $statusCode = -1;
        $paramsByKey = [];
        foreach ($params as $key => $val) {
            $paramsByKey[] = $key . '=' . ((is_array($val)) ? implode(',', $val) : $val);
        }
        $url = $this->config['url'] . '?' . implode('&', $paramsByKey);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $this->config['basicAuth']]);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        try {
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } catch (Exception $exception) {
            $error .= ' - ' . $exception;
        }
        curl_close($ch);

        return ['statusCode' => $statusCode,
            'error' => $error,
            'url' => $url,
            'result' => ($result) ? $result : ''
        ];
    }
}