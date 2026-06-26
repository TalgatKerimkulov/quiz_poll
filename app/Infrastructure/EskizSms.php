<?php
// app/Infrastructure/EskizSms.php

namespace App\Infrastructure;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EskizSms
{
    protected string $email;
    protected string $password;
    protected string $sender;
    protected string $baseUrl;
    protected int $tokenLifetime;

    public function __construct()
    {
        $this->email = (string) config('services.eskiz.email');
        $this->password = (string) config('services.eskiz.password');
        $this->sender = (string) config('services.eskiz.sender', '4546');
        $this->baseUrl = (string) config('services.eskiz.url', 'https://notify.eskiz.uz/api');
        $this->tokenLifetime = (int) config('services.eskiz.token_lifetime', 2592000);
    }

    /**
     * Token olish (cache bilan)
     */
    protected function getToken(): ?string
    {
        return Cache::remember('eskiz_sms_token', $this->tokenLifetime, function () {
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($response->successful() && isset($response['data']['token'])) {
                return $response['data']['token'];
            }

            Log::error('Eskiz SMS: Token olishda xatolik', [
                'response' => $response->json()
            ]);

            return null;
        });
    }

    /**
     * SMS yuborish
     */
    public function sendMessage(string $phone, string $message): bool
    {
        $phone = preg_replace('/\D+/', '', $phone);
        // $token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJleHAiOjE3NjQxNDI0NzQsImlhdCI6MTc2MTU1MDQ3NCwicm9sZSI6InRlc3QiLCJzaWduIjoiNzUwN2E0OGFmMDk2ZjJkZjQzZDkzMzQzNDM1MjRiYmMwZDVmM2YyMzY5MDBmODRhNjg0MDY0OTVjM2U3MDQzYiIsInN1YiI6IjEzMDE2In0._NhklG0qNVgcY-P6AMpfikuEqzpno15_bCupM6nHD3k";
        $token = $this->getToken();
        if (!$token) {
            Log::error('Eskiz SMS: Token mavjud emas');
            return false;
        }
        // dd($token);
        $response = Http::withToken($token)
            ->asForm()
            ->post("{$this->baseUrl}/message/sms/send", [
                'mobile_phone' => $this->formatPhone($phone),
                'message' => $message,
                'from' => $this->sender,
            ]);
            // dd($response);

        // Agar 401 (Unauthorized) bo'lsa, tokenni yangilash
        if ($response->status() === 401) {
            Cache::forget('eskiz_sms_token');
            return $this->sendMessage($phone, $message); // Rekursiv qayta urinish
        }

        if ($response->successful()) {
            Log::info('SMS yuborildi', [
                'phone' => $phone,
                'message_id' => $response['id'] ?? null
            ]);
            return true;
        }

        Log::error('Eskiz SMS: Yuborishda xatolik', [
            'phone' => $phone,
            'response' => $response->json()
        ]);

        return false;
    }

    /**
     * Balansni tekshirish
     */
    public function getBalance(): ?array
    {
        $token = $this->getToken();

        if (!$token) {
            return null;
        }

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/user/get-limit");

        if ($response->successful()) {
            return [
                'balance' => $response['balance'] ?? 0,
                'is_vip' => $response['is_vip'] ?? false,
            ];
        }

        return null;
    }

    /**
     * Telefon raqamini formatlash
     */
    protected function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Agar + yoki 998 bilan boshlanmasa, qo'shamiz
        if (!str_starts_with($phone, '998')) {
            $phone = '998' . $phone;
        }

        return $phone;
    }

    /**
     * User info olish
     */
    public function getUserInfo(): ?array
    {
        $token = $this->getToken();

        if (!$token) {
            return null;
        }

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/auth/user");

        return $response->successful() ? $response->json() : null;
    }
}
