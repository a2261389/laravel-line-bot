<?php

namespace App\Services\Line;

use Illuminate\Http\Request;
use App\Services\Curl;
use Exception;

class LineBot
{
    private const LINE_REPLY_URL = 'https://api.line.me/v2/bot/message/reply';
    private $secret = '';
    private $access_token = '';
    protected $mode = 'text';
    protected $request;
    protected $replyRequestHeaders;

    public function __construct($secret, $access_token)
    {
        $this->secret = $secret;
        $this->access_token = $access_token;
        $this->replyRequestHeaders = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->access_token,
        ];
    }

    // 選擇監聽模式
    public function on($mode = 'text')
    {
        $this->mode = $mode;
        return $this;
    }

    // 初始化
    public function init(Request $request)
    {
        $signature = $request->header('X-LINE-Signature');
        if (empty($signature)) {
            throw new Exception('[X-LINE-Signature] not found');
        }

        $requestBody = $request->getContent();
        if (!$this->validateSignature($requestBody, $this->secret, $signature)) {
            throw new Exception('[X-LINE-Signature] verification failed');
        }

        $this->request = $request;
        return $this;
    }

    public function getResponse()
    {
        if (!$this->request) {
            throw new Exception('Need init before call function getResponse');
        }

        switch ($this->mode) {
            case 'text':
                return $this->textEventWatcher($this->request);
                break;
            default:
                return [];
                break;
        }
    }

    public function getFirstText()
    {
        if (!$this->request) {
            throw new Exception('Need init before call function getFirstText');
        }
        $strings = $this->textEventWatcher($this->request);
        if (count($strings) > 0) {
            if (isset($strings[0]['message']['text'])) {
                return $strings[0]['message']['text'];
            }
        }
        throw new Exception('Could not found first text');
    }

    public function reply($message)
    {
        if (!$this->request) {
            throw new Exception('Need init before call function reply');
        }
        $responseData = $this->getResponse();

        $replyRequestData = [
            'replyToken' => $responseData[0]['replyToken'],
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message,
                    // 'text' => $responseData[0]['message']['text'],
                ],
            ],
        ];
        $curl = new Curl();
        $replyResponse = $curl->send(self::LINE_REPLY_URL, 'POST', $replyRequestData, $this->replyRequestHeaders);
        return $replyResponse;
    }

    protected function textEventWatcher($request)
    {
        $data = [];
        foreach ($request['events'] as $key => $event) {
            if ($event['mode'] == 'active') {
                if ($event['type'] == 'message') {
                    $data[$key] = $event;
                }
            }
        }
        return array_values($data);
    }

    protected function validateSignature($string, $key, $encrypt)
    {
        $sha256 = hash_hmac('sha256', $string, $key, true);
        $base64 = base64_encode($sha256);
        return $base64 === $encrypt;
    }
}
