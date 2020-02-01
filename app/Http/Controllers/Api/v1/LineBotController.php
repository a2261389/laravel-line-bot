<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;

class LineBotController extends ApiController
{
    public function getWebHook(Request $request)
    {
        info($request->all());
        return $this->responseSuccess(['message' => 'Hello']);
    }
}
