<?php

class RubiconAnalyticsReport {

    private $config;
    private $rubiconApiSpecification;

    public function __construct(array $config) {
        $this->rubiconApiSpecification = $this->getRubiconApiSpecification();
        $this->config = $config;
    }

    private function getRubiconApiSpecification($file_name = __DIR__.'/conversions.json') {
        $array = json_decode(file_get_contents($file_name), true);
        return $array;
    }

    public function call(array $params = []) {
        if (!$params) {
            $params = $this->config['params'];
        }
        return $this->callRubicon([
            'dimensions' => $this->getColumnNamesForType(
                ((isset($params['labelsToReturn'])) ? $params['labelsToReturn'] : []),
                'Dimension'
            ),
            'metrics' => $this->getColumnNamesForType(
                ((isset($params['labelsToReturn'])) ? $params['labelsToReturn'] : []),
                'Metric'
            ),
            'account' => $params['account'],
            'start' => $params['start'],
            'end' => $params['end'],
            'currency' => $params['currency'],
            'limit' => $params['limit']
        ]);
    }

    private function callRubicon(array $params = []) {

        $paramsByKey = [];
        foreach ($params as $key => $val) {
            $paramsByKey[] = $key . '=' . ((is_array($val)) ? implode(',', $val) : $val);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['url'] . '?' . implode('&', $paramsByKey));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); //timeout after 30 seconds
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic " . $this->config['basicAuth']]);
        $result = curl_exec($ch);

        if ($result === false) {
            return ['error' => curl_error($ch)];
        }else {
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            return ['statusCode' => $statusCode, 'result' => $result];
        }

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
}