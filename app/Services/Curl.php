<?php

namespace App\Services;

class Curl
{

    public function send($url, $method = 'GET', $data = [], $headers = [], $options = [])
    {
        $method = strtoupper($method);
        $defaultOptions = [];
        $ch = curl_init();

        if ($method == 'GET') {
            $url = $url . '?' . http_build_query($data);
        } else if ($method == 'POST') {
            $defaultOptions[CURLOPT_POST] = true;
            if (in_array('Content-Type: multipart/form-data', $headers)) {
                $defaultOptions[CURLOPT_POSTFIELDS] = $data;
            } else if (in_array('Content-Type: application/json', $headers)) {
                $defaultOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            } else {
                $defaultOptions[CURLOPT_POSTFIELDS] = http_build_query($data);
            }
        }
        $defaultOptions[CURLOPT_URL] = $url;
        $defaultOptions[CURLOPT_HTTPHEADER] = $headers;
        $defaultOptions[CURLOPT_CUSTOMREQUEST] = $method;
        // 不直接回傳數值
        $defaultOptions[CURLOPT_RETURNTRANSFER] = true;
        // 取得headers
        $defaultOptions[CURLOPT_HEADER] = true;
        $defaultOptions[CURLOPT_SSL_VERIFYPEER] = false;
        $defaultOptions[CURLOPT_SSL_VERIFYHOST] = false;

        if (count($options) > 0) {
            foreach ($options as $key => $option) {
                $defaultOptions[$key] = $option;
            }
        }
        curl_setopt_array($ch, $defaultOptions);
        $response = [];
        $responseData = curl_exec($ch);
        $response['details'] = curl_getinfo($ch);

        // 進行header size 切割
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response['headers'] = explode("\n", trim(substr($responseData, 0, $headerSize)));
        $response['data'] = substr($responseData, $headerSize);

        if (curl_error($ch)) {
            $response['error'] = curl_error($ch);
        }
        curl_close($ch);
        return $response;
    }
}
