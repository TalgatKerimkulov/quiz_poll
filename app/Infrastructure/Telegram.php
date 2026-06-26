<?php

namespace App\Infrastructure;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Telegram
{
    protected string $token;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token = (string) config('services.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     *  text yuborish
     */
    public function sendMessage(int|string $chatId, string $message, array $extra = []): bool
    {
        $payload = array_merge([
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ], $extra);

        $response = Http::post("{$this->baseUrl}/sendMessage", $payload);

        if (!$response->successful()) {
            Log::warning('Telegram sendMessage failed.', [
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
                'chat_id' => $chatId,
            ]);
        }

        return $response->successful();
    }

    public function sendWebAppButton(int|string $chatId, string $message, string $buttonText): bool
    {
        $webAppUrl = (string) config('services.telegram.web_app_url');
        if (!$webAppUrl) {
            Log::warning('Telegram web app URL is not configured.');

            return $this->sendMessage($chatId, 'Приложение пока не настроено.');
        }

        return $this->sendMessage($chatId, $message, [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        [
                            'text' => $buttonText,
                            'web_app' => [
                                'url' => $webAppUrl,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }


    /**
     * Rasm yuborish
     */
    public function sendPhoto(int|string $chatId, string $photoUrl, ?string $caption = null): bool
    {
        $response = Http::post("{$this->baseUrl}/sendPhoto", [
            'chat_id'  => $chatId,
            'photo'    => $photoUrl,
            'caption'  => $caption,
            'parse_mode' => 'HTML',
        ]);

        return $response->successful();
    }

    /**
     * Fayl yuborish
     */

    public function sendDocument(int|string $chatId, string $fileUrl, ?string $caption = null): bool
    {
        $response = Http::post("{$this->baseUrl}/sendDocument", [
            'chat_id'  => $chatId,
            'document' => $fileUrl,
            'caption'  => $caption,
            'parse_mode' => 'HTML',
        ]);

        return $response->successful();
    }

    public function askPhoneNumber(int|string $chatId, string $lang = 'ru'): void
    {
        $text = $lang === 'uz'
            ? "📱 Telefon raqamingizni yuboring:"
            : "📱 Отправьте свой номер телефона:";

        $button = $lang === 'uz'
            ? "📲 Telefonni yuborish"
            : "📲 Отправить номер";

        $this->sendMessage($chatId, $text, [
            'reply_markup' => [
                'keyboard' => [
                    [[
                        'text' => $button,
                        'request_contact' => true
                    ]]
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]
        ]);
    }


    public function askLanguage(int|string $chatId): void
    {
        $this->sendMessage($chatId, "Tilni tanlang / Выберите язык:", [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => '🇺🇿 O‘zbekcha', 'callback_data' => 'uz'],
                        ['text' => '🇷🇺 Русский', 'callback_data' => 'ru'],
                    ]
                ]
            ]
        ]);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false)
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        // if ($text) {
        //     $payload['text'] = $text;       // foydalanuvchiga ko‘rinadigan matn
        //     $payload['show_alert'] = $showAlert; // true bo‘lsa pop-up ochiladi
        // }

        return Http::post("{$this->baseUrl}/answerCallbackQuery", $payload)->json();
    }

    public function setWebhook(string $url, ?string $secret = null): array
    {
        $payload = ['url' => $url];
        if ($secret) {
            $payload['secret_token'] = $secret;
        }

        return Http::post("{$this->baseUrl}/setWebhook", $payload)->json();
    }

    public function deleteWebhook(): array
    {
        return Http::post("{$this->baseUrl}/deleteWebhook")->json();
    }

    public function getWebhookInfo(): array
    {
        return Http::get("{$this->baseUrl}/getWebhookInfo")->json();
    }

    public function setChatOpenAppButton(string $url, string $text, int|string|null $chatId = null): array
    {
        $payload = [
            'menu_button' => [
                'type' => 'web_app',
                'text' => $text,
                'web_app' => [
                    'url' => $url,
                ],
            ],
        ];

        if ($chatId !== null) {
            $payload['chat_id'] = $chatId;
        }

        return Http::post("{$this->baseUrl}/setChatOpenAppButton", $payload)->json();
    }

    public function editMessageReplyMarkup(int|string $chatId,int $messageId,?array $replyMarkup = null): bool {
            $payload = [
                'chat_id'    => $chatId,
                'message_id' => $messageId,
            ];
            if ($replyMarkup !== null) {
                $payload['reply_markup'] = json_encode($replyMarkup);
            }

            $response = Http::post("{$this->baseUrl}/editMessageReplyMarkup", $payload);

            return $response->successful();
    }





}
