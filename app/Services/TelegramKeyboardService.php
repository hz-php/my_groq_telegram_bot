<?php

namespace App\Services;

use Telegram\Bot\Keyboard\Keyboard;

/**
     * Формируем основное меню с inline-кнопками
     *
     * @return array
     */
class TelegramKeyboardService
{
    public function mainMenu()  
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row([
                Keyboard::inlineButton(['text' => '🧹 Очистить контекст', 'callback_data' => '/clear']),
            ]);

        return $keyboard;   
    }
}
