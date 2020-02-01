<?php

namespace App\Http\Controllers;

class ApiController
{
    protected $apiVersion = "1.0";

    protected function responseSuccess($data = [], $code = 200, $header = [])
    {
        $response = [
            'apiVersion' => $this->apiVersion,
            'data' => $data,
        ];
        return response()->json($response, $code, $header);
    }

    protected function responseFail($errorCode, $errorMsg = 'Failed', $errors = [], $header = [])
    {
        $response = [
            'apiVersion' => $this->apiVersion,
            'error' => [
                'code' => $errorCode,
                'message' => $errorMsg,
            ]
        ];
        if (count($errors) > 0) {
            $response['error']['errors'] = $errors;
        }
        return response()->json($response, $errorCode, $header);
    }
}
