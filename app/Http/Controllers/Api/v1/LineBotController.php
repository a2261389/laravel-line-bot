<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use App\Services\Line\LineBot;
use App\Models\Merchant;
use App\Models\MerchantTag;
use DB;

class LineBotController extends ApiController
{
    const APP_VERSION = 'v1.0';
    protected $lineBot;

    protected $commands = [
        'help'              => '列出所有指令',
        'add'               => '新增店家',
        'cancel'            => '取消目前操作',
        'remove [name]'     => '移除[name]店家',
        'update [name]'     => '更新[name]店家資訊',
        'find [name]'       => '列出[name]店家詳細資訊',
        'list'              => '列出所有店家名稱',
        'start'             => '依據輸入的條件抽出店家',
        'random'            => '隨機抽取所有店家',
    ];

    protected $addCommands = [
        "開始新增店家資訊[注意：操作時間只持續五分鐘] \r\n----------\r\n請輸入店家名稱：",
        "請輸入消費金額範圍(最低消費 最高消費)：\r\n(注意金額中間需有空格，例如：50 100)",
        "請輸入分類標籤：\r\n例如：早餐,午餐,素食(請以逗號分隔)",
    ];

    protected $listCommand = [
        "儲存的店家共有：\r\n",
    ];

    protected $removeCommand = [
        "請輸入欲刪除的店家名稱或者編號：",
    ];

    public function __construct()
    {
        $this->lineBot = new LineBot(env('LINE_BOT_CHANNEL_SECRET'), env('LINE_BOT_CHANNEL_ACCESS_TOKEN'));
    }

    public function getWebHook(Request $request)
    {
        try {
            $text = $this->lineBot->init($request)->on('text')->getFirstText();
            $userId = $this->lineBot->getUserId();
            $command = $this->commandHandler(trim($text), $userId);
            if (!is_null($command)) {
                $this->lineBot->reply($command);
            }

            return $this->responseSuccess();
        } catch (\Throwable $th) {
            info($th->getMessage() . " in Line:" . $th->getLine());
            return $this->responseSuccess();
        }
    }

    protected function commandHandler($message, $userId)
    {
        if ($message == 'cancel') {
            cache()->forget('add-merchant-step_' . $userId);
            cache()->forget('add-merchant-data_' . $userId);
            return '操作已取消';
        }

        if ($message == 'help') {
            $string = "[餐點抽籤] " . self::APP_VERSION . "\r\n";
            $string .= "=====================\r\n";
            foreach ($this->commands as $command => $intro) {
                $string .= "{$command} {$intro} \r\n";
            }
            return $string;
        }

        if ($message == 'list') {
            $text = '';
            $merchants = Merchant::where('line_user_id', $userId)->get();
            if ($merchants->count() < 1) {
                return '目前尚未新增店家...';
            }
            foreach ($merchants as $index => $merchant) {
                $index = $index + 1;
                $text .= "{$index}. {$merchant->name}\r\n";
            }
            return $text;
        }

        if ($message == 'random') {
            $merchant = Merchant::where('line_user_id', $userId)->inRandomOrder()->first();
            if ($merchant) {
                return "抽到的是：" . $merchant->name;
            }
            return "尚未新增店家或無符合條件店家";
        }

        $stepCacheName = 'add-merchant-step_' . $userId;
        $addCacheName = 'add-merchant-data_' . $userId;
        if ($message == 'add' || cache()->has($stepCacheName)) {
            $step = cache()->get($stepCacheName, -1);
            switch ($step) {
                case 0:
                    if (strlen($message) < 1) {
                        return "名稱不可為空!\r\n" . $this->addCommands[0];
                    }
                    $data = cache()->get($addCacheName);
                    $data['name'] = $message;
                    cache()->put($addCacheName, $data, 300);

                    cache()->increment($stepCacheName);
                    return $this->addCommands[1];
                    break;
                case 1:
                    $money = explode(' ', $message);
                    if (
                        count($money) != 2
                        || !(is_numeric($money[0]) && is_numeric($money[1]))
                        || $money[0] > $money[1]
                    ) {
                        return "消費金額格式錯誤!\r\n" . $this->addCommands[1];
                    }
                    $data = cache()->get($addCacheName);
                    $data['range'] = $message;
                    cache()->put($addCacheName, $data, 300);

                    cache()->increment($stepCacheName);
                    return $this->addCommands[2];
                    break;
                case 2:
                    $items = explode(',', trim($message, ','));
                    $items = array_filter($items, function ($item) {
                        return strlen($item) > 0;
                    });
                    if (count($items) < 1) {
                        return "至少需要一組標籤!\r\n" . $this->addCommands[2];
                    }
                    $data = cache()->get($addCacheName);
                    $data['tags'] = $message;

                    $range = explode(' ', $data['range']);

                    DB::beginTransaction();
                    $merchant = Merchant::create([
                        'name' => $data['name'],
                        'line_user_id' => $userId,
                        'minimum_order' => $range[0],
                        'highest_order' => $range[1],
                    ]);
                    if (!$merchant) {
                        DB::rollback();
                        return "儲存失敗，請重新操作。";
                    }
                    foreach (explode(',', $data['tags']) as $tag) {
                        $tag = MerchantTag::create([
                            'tag' => $tag,
                            'merchant_id' => $merchant->id,
                        ]);
                        if (!$tag) {
                            DB::rollback();
                            return "儲存失敗，請重新操作。";
                        }
                    }
                    DB::commit();

                    $successText = "儲存成功！以下是您所輸入的資訊：\r\n";
                    $successText .= "商店編號：{$merchant->id}\r\n";
                    $successText .= "商店名稱：{$data['name']}\r\n";
                    $successText .= "消費金額範圍：{$data['range']}\r\n";
                    $successText .= "標籤：{$data['tags']}\r\n";
                    cache()->forget($addCacheName);
                    cache()->forget($stepCacheName);
                    return $successText;
                    break;
            }
            cache()->put('add-merchant-data_' . $userId, [], 300);
            cache()->put($stepCacheName, 0, 300);
            return $this->addCommands[0];
        }
        return null;
    }
}
