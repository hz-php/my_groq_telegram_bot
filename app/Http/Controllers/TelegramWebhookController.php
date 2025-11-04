<?php

namespace App\Http\Controllers;

use App\Actions\Telegram\HandleIncomingMessageAction;
use App\Services\TelegramService;
use Illuminate\Http\Request;



class TelegramWebhookController extends Controller
{
    protected TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    public function handleWebhook(Request $request)
    {
        $update = $request->all();
        $this->telegramService->handleIncomingMessage($update);

        return response('OK', 200);
    }
}
