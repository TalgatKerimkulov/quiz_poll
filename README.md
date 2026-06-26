<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Production Setup

### ENV

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_DRY_RUN=false
TELEGRAM_DEFAULT_TIMEZONE=Asia/Tashkent
TELEGRAM_POLLS_PER_DAY=20
TELEGRAM_POLL_START_TIME=09:00
TELEGRAM_POLL_END_TIME=23:00
TELEGRAM_POLL_OPEN_PERIOD=1800
TELEGRAM_DEFAULT_DIRECTION=en_ru
TELEGRAM_HTTP_TIMEOUT=10
TELEGRAM_HTTP_RETRY_TIMES=2
TELEGRAM_HTTP_RETRY_SLEEP_MS=500
```

### Deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
```

### Cron

Laravel scheduler must run every minute:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:

```bash
php artisan telegram:send-word-quiz
php artisan telegram:close-expired-polls
```

### Queue Worker

Webhook processing is currently synchronous but isolated in services so it can be moved to jobs later. If queue jobs are added, run:

```bash
php artisan queue:work --tries=3
```

### Webhook

```bash
php artisan telegram:set-webhook https://domain.com/api/telegram/webhook
php artisan telegram:bot-info
php artisan telegram:set-commands
```

### Telegram Group Setup

1. Add the bot to a group.
2. Promote the bot to administrator.
3. Give the bot permission to send messages and polls.
4. Run `/connect` in the group.
5. Check `/settings`.
6. Run `/send_now` for a manual quiz.

### Manual QA

1. Fill `.env`.
2. Run migrations and seeders.
3. Check `telegram:bot-info`.
4. Set webhook.
5. Add bot to a group as admin.
6. Run `/connect`.
7. Check `/settings`.
8. Run `/send_now`.
9. Answer the poll from several users.
10. Check `/stats`, `/top`, `/my`.
11. Check `/pause` and `/resume`.
12. Verify scheduler sends polls over time.
