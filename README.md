# Quiz Poll

Telegram-бот для изучения английской лексики с помощью регулярных quiz-опросов в группах.

Проект хранит слова, переводы и примеры отдельно, поддерживает уровни CEFR A1–C2, русский и
узбекский языки перевода, собирает статистику ответов и чаще повторяет слова, в которых
пользователи ошибаются. Недостающие русские переводы можно создавать пакетно через локальную
Ollama или OpenAI API.

## Основные возможности

- автоматическая отправка Telegram quiz polls по расписанию;
- направления `forward` (английский → перевод), `reverse` и `mixed`;
- фильтрация слов по уровням A1, A2, B1, B2, C1 и C2;
- отдельные настройки для каждой Telegram-группы;
- статистика пользователя, группы и недельный рейтинг;
- повторение слов с учётом предыдущих ошибок;
- мультиязычные переводы со статусами `imported`, `machine` и `reviewed`;
- импорт исходных CSV English Profile;
- ИИ-перевод на русский через Ollama или OpenAI;
- фоновые очереди, защита webhook секретом и дедупликация Telegram updates;
- dry-run режим Telegram для безопасной проверки без реальной отправки.

## Как устроен словарь

Основные таблицы:

- `words` — английская лексема, язык оригинала, уровень, часть речи, guideword, тема и источник;
- `word_translations` — перевод на конкретный язык, источник, AI-провайдер, модель, confidence и
  замечания для проверки;
- `word_examples` — примеры употребления и их переводы;
- `telegram_chats` и `telegram_chat_settings` — подключённые группы и настройки;
- `telegram_polls`, `telegram_poll_answers`, `telegram_user_word_stats` — история опросов и
  статистика;
- `jobs` и `failed_jobs` — задания фоновой очереди.

Для одного значения слова хранится не более одного перевода на каждый язык. AI-переводы получают
статус `machine`; их можно отличить от импортированных и проверенных вручную данных.

## Технологии и зависимости

### Серверная часть

- PHP `8.3+` (Docker-образ использует PHP 8.4);
- Laravel `13.8+`;
- Laravel Tinker `3.x`;
- PostgreSQL 15;
- Redis 7 — cache, sessions и основная очередь Docker-приложения;
- Nginx;
- Supervisor — PHP-FPM и стандартный Laravel worker;
- Docker Compose — рекомендуемый способ запуска.

PHP-расширения Docker-образа: `pdo_pgsql`, `pgsql`, `mbstring`, `pcntl`, `bcmath`, `zip`, `gd`,
`exif`, `opcache` и `redis`.

### ИИ-перевод

- Ollama с моделью `qwen2.5:3b` по умолчанию — локально и без платного API;
- OpenAI Responses API с моделью `gpt-4o-mini` по умолчанию — альтернативный провайдер.

Провайдер выбирается через `AI_TRANSLATION_DRIVER`. Оба адаптера реализуют общий
`TranslationProvider`, поэтому можно добавить другой LLM без изменения jobs и команд импорта.

### Клиентская часть и разработка

- Vite 8;
- Tailwind CSS 4;
- PHPUnit 12.5;
- Laravel Pint;
- Mockery, Faker, Collision, Pail и Pao.

Frontend используется только для стандартной web-части Laravel; основная функциональность проекта
работает через Telegram webhook и Artisan-команды.

## Быстрый запуск через Docker

### 1. Подготовить окружение

```bash
cp .env.example .env
docker compose build
docker compose run --rm vasit-app composer install
docker compose run --rm vasit-app php artisan key:generate
```

Заполните как минимум `TELEGRAM_BOT_TOKEN`. Для реального webhook также задайте
`TELEGRAM_WEBHOOK_SECRET`.

### 2. Запустить контейнеры

```bash
docker compose up -d
docker compose ps
```

Будут запущены:

- `quiz_poll_app` — PHP-FPM, Supervisor и worker очереди `default`;
- `quiz_poll_nginx` — HTTP на `http://localhost:8080`;
- `quiz_poll_postgres` — PostgreSQL на локальном порту `25432`;
- `quiz_poll_redis` — Redis на порту `6379`.

Entrypoint автоматически запускает миграции. Явный запуск:

```bash
docker compose exec -T vasit-app php artisan migrate --force
```

Логи:

```bash
docker compose logs -f vasit-app
tail -f storage/logs/laravel.log
tail -f storage/logs/queue-worker.log
```

Остановка проекта:

```bash
docker compose down
```

Данные PostgreSQL и Redis сохраняются в Docker volumes.

## Основные переменные окружения

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=25432
DB_DATABASE=quiz_poll
DB_USERNAME=quiz_poll
DB_PASSWORD=quiz_poll

CACHE_STORE=database
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=900

TELEGRAM_BOT_TOKEN=
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_DRY_RUN=false
TELEGRAM_DEFAULT_TIMEZONE=Asia/Tashkent
TELEGRAM_POLLS_PER_DAY=20
TELEGRAM_POLL_START_TIME=09:00
TELEGRAM_POLL_END_TIME=23:00
TELEGRAM_POLL_OPEN_PERIOD=1800
TELEGRAM_DEFAULT_LEVEL=
TELEGRAM_SOURCE_LOCALE=en
TELEGRAM_TARGET_LOCALE=ru
TELEGRAM_DEFAULT_DIRECTION=forward
TELEGRAM_REPEAT_MISTAKES_ENABLED=true
```

Допустимые направления:

- `forward` — показать английское слово и выбрать перевод;
- `reverse` — показать перевод и выбрать английское слово;
- `mixed` — случайно выбирать направление.

В Docker Compose значения подключения к PostgreSQL, Redis, cache и queue переопределяются для
контейнера `vasit-app`. Локальные Artisan-команды используют значения из `.env`.

## Импорт English Profile CSV

CSV находятся в `public` и используют разделитель `;`. Импортёр:

- понимает отличающийся формат A1;
- преобразует Windows-1252 в UTF-8;
- пропускает технические пустые строки;
- сохраняет уровень, часть речи, guideword, тему и примеры;
- безопасно запускается повторно без создания дублей;
- при импорте директории по умолчанию исключает агрегатный файл `All`.

Сначала проверить файлы без записи в БД:

```bash
php artisan words:import public --dry-run
```

Ожидаемый результат для текущего набора:

```text
Files: 6
Rows: 16855
Words: 12272
Translations: 1591
Examples: 39
Skipped: 4583
```

Выполнить импорт:

```bash
php artisan words:import public
```

Импортировать конкретный файл:

```bash
php artisan words:import "public/English Profile(A2-1254).csv"
```

Использовать `--include-all` вместе с отдельными файлами обычно не следует: агрегатный файл
пересекается с ними и содержит меньше заполненных переводов.

> Файлы в `public` доступны для скачивания через web-сервер. Если это нежелательно, перенесите их в
> закрытую директорию и передайте новый путь команде `words:import`.

## ИИ-перевод на русский

Команда выбирает английские слова без перевода на целевой язык, разбивает их на пакеты и ставит
`TranslateWordsJob` в отдельную очередь `translation`.

```bash
php artisan words:translate --target=ru --limit=1000
```

Полезные параметры:

```text
--source=en       язык оригинала
--target=ru       язык перевода
--level=B1        ограничить уровень
--limit=1000      максимальное количество слов
--batch=20        слов в одном запросе к модели
--sync            выполнить сразу, без очереди
--overwrite       перезаписать существующие переводы
```

Без `--overwrite` существующие русские переводы не изменяются.

### Локальный перевод через Ollama

Установите Ollama и загрузите модель:

```bash
ollama pull qwen2.5:3b
ollama ps
```

Настройка `.env`:

```env
AI_TRANSLATION_DRIVER=ollama
AI_TRANSLATION_BATCH_SIZE=20
OLLAMA_BASE_URL=http://localhost:11434
OLLAMA_TRANSLATION_MODEL=qwen2.5:3b
OLLAMA_TIMEOUT=300
OLLAMA_NUM_THREAD=2

QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=900
```

`DB_QUEUE_RETRY_AFTER` должен быть больше `--timeout` worker. Иначе длительное задание может быть
выдано второму worker повторно.

Для текущей архитектуры команды постановки ИИ-заданий и Ollama worker запускаются на хосте из
корня `quiz_poll`. Это важно: внутри Docker приложение использует Redis, а локальный worker —
database queue. Не ставьте задания внутри контейнера, если обрабатывать их будет host-worker.

```bash
php artisan config:clear
php artisan words:translate --target=ru --limit=1000

php artisan queue:work database \
  --queue=translation \
  --tries=3 \
  --timeout=600 \
  --stop-when-empty
```

Фоновый запуск с отдельным логом:

```bash
nohup php artisan queue:work database \
  --queue=translation \
  --tries=3 \
  --timeout=600 \
  --stop-when-empty \
  > /tmp/quiz_poll-translation-worker.log 2>&1 &
```

```bash
tail -f /tmp/quiz_poll-translation-worker.log
pgrep -af "artisan queue:work.*queue=translation"
```

Worker с `--stop-when-empty` завершится автоматически после очистки очереди.

### Просмотр прогресса ИИ-перевода

```bash
php artisan tinker
```

```php
$total = App\Models\Word::where('locale', 'en')->count();

$done = App\Models\Word::where('locale', 'en')
    ->whereHas('translations', fn ($query) => $query->where('locale', 'ru'))
    ->count();

$remaining = $total - $done;
$percent = round($done / max(1, $total) * 100, 1);

compact('total', 'done', 'remaining', 'percent');

DB::table('jobs')->where('queue', 'translation')->count();
DB::table('failed_jobs')->count();
```

### Безопасная остановка и продолжение

Найти PID только нужного worker:

```bash
pgrep -af "artisan queue:work.*queue=translation"
```

Попросить Laravel завершить текущую задачу и остановиться:

```bash
kill -TERM <PID>
```

Не используйте `kill -9`: текущее задание может остаться зарезервированным. Необработанные задания
в таблице `jobs` не теряются. Для продолжения достаточно снова запустить ту же команду
`queue:work`; повторно выполнять `words:translate` нужно только после очистки уже созданной очереди.

После остановки worker Ollama можно выгрузить из памяти:

```bash
ollama stop qwen2.5:3b
```

### Перевод через OpenAI

```env
AI_TRANSLATION_DRIVER=openai
AI_TRANSLATION_BATCH_SIZE=20
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TRANSLATION_MODEL=gpt-4o-mini
OPENAI_TIMEOUT=60
```

После изменения `.env`:

```bash
php artisan config:clear
php artisan words:translate --target=ru --limit=1000
php artisan queue:work database --queue=translation --tries=3 --timeout=600 --stop-when-empty
```

Провайдер возвращает структурированный результат. В БД сохраняются перевод, confidence, замечания,
название провайдера и модель. Переводы с низкой уверенностью или пометкой о латинице необходимо
проверять вручную.

## Настройка Telegram

### 1. Создать бота

Создайте бота через `@BotFather`, получите token и запишите его в `TELEGRAM_BOT_TOKEN`.

Проверить доступ:

```bash
php artisan telegram:bot-info
```

### 2. Установить публичный webhook

Telegram требует публичный HTTPS URL:

```bash
php artisan telegram:webhook:set https://example.com --secret="your-secret"
php artisan telegram:webhook:info
```

Webhook будет установлен на:

```text
https://example.com/api/telegram/webhook
```

Если передаётся полный URL:

```bash
php artisan telegram:webhook:set https://example.com/custom/path --full-url
```

Удалить webhook:

```bash
php artisan telegram:webhook:delete
php artisan telegram:webhook:delete --drop-pending-updates
```

Зарегистрировать список команд Telegram:

```bash
php artisan telegram:set-commands
```

### 3. Подключить группу

1. Добавьте бота в Telegram-группу.
2. Назначьте его администратором.
3. Разрешите отправку сообщений и опросов.
4. Выполните `/connect` в группе.
5. Проверьте `/settings`.
6. Выполните `/send_now` для тестового опроса.

## Telegram-команды

Команды для всех участников:

| Команда | Назначение |
|---|---|
| `/help` | Показать помощь |
| `/my`, `/my_week` | Личная статистика за день или неделю |
| `/stats`, `/stats_week` | Статистика группы |
| `/top`, `/top_week` | Рейтинг участников |
| `/findword apple` | Найти слово и все его переводы |

Команды администраторов:

| Команда | Назначение |
|---|---|
| `/connect`, `/disconnect` | Подключить или отключить группу |
| `/settings` | Текущая конфигурация группы |
| `/set_polls_per_day 15` | Количество опросов в день, от 1 до 50 |
| `/set_time 09:00 22:00` | Интервал отправки |
| `/set_timezone Asia/Tashkent` | Часовой пояс |
| `/set_level A1` | Уровень A1–C2 или `all` |
| `/set_language ru` | Язык перевода: `ru` или `uz` |
| `/set_direction forward` | `forward`, `reverse` или `mixed` |
| `/pause`, `/resume` | Приостановить или возобновить опросы |
| `/send_now` | Отправить опрос немедленно |
| `/addword apple яблоко A1` | Добавить слово вручную |
| `/disableword apple` | Отключить слово |

Административные команды проверяют права пользователя в Telegram-группе.

## Планировщик

Laravel scheduler настроен следующим образом:

- `telegram:send-word-quiz` — каждые 5 минут;
- `telegram:close-expired-polls` — каждые 10 минут.

В production cron должен запускать scheduler каждую минуту:

```cron
* * * * * cd /path/to/quiz_poll && php artisan schedule:run >> /dev/null 2>&1
```

Ручной запуск:

```bash
php artisan telegram:send-word-quiz
php artisan telegram:close-expired-polls
```

## Очереди

В Docker Supervisor автоматически поддерживает worker очереди `default`:

```text
php artisan queue:work --queue=default --sleep=3 --tries=2 --max-jobs=500
```

Очередь `translation` намеренно отделена, поскольку ИИ-задачи выполняются долго и локальному Ollama
нужен доступ к host network. Не запускайте общий worker без `--queue`, если не хотите, чтобы он
забирал AI-задания.

Показать упавшие задания:

```bash
php artisan queue:failed
```

Повторить конкретное или все задания:

```bash
php artisan queue:retry <ID>
php artisan queue:retry all
```

Удалять failed jobs следует только после анализа причины:

```bash
php artisan queue:forget <ID>
php artisan queue:flush
```

## Разработка без Docker

Потребуются PHP 8.3+, Composer, PostgreSQL, Node.js/npm и необходимые PHP-расширения.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

npm install
npm run build
```

Запуск всех dev-процессов:

```bash
composer run dev
```

Отдельный web-сервер:

```bash
php artisan serve
```

## Тесты и качество кода

```bash
composer test
```

Или напрямую:

```bash
php artisan test
vendor/bin/pint --test
```

Автоматическое форматирование:

```bash
vendor/bin/pint
```

Тесты используют SQLite в памяти и не должны обращаться к production-базе.

## Развёртывание в production

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

После deployment:

```bash
php artisan telegram:bot-info
php artisan telegram:webhook:info
php artisan schedule:list
php artisan queue:failed
```

Обязательно:

- установить `APP_DEBUG=false`;
- использовать случайный `APP_KEY` и `TELEGRAM_WEBHOOK_SECRET`;
- ограничить доступ к PostgreSQL и Redis;
- настроить HTTPS;
- запускать scheduler и queue workers под Supervisor/systemd;
- резервировать PostgreSQL;
- контролировать `storage/logs` и `failed_jobs`;
- не публиковать `.env`, Telegram token и AI API keys.

## Ручная проверка после запуска

1. Убедиться, что миграции применены: `php artisan migrate:status`.
2. Проверить импорт через `words:import --dry-run`.
3. Проверить бота: `telegram:bot-info`.
4. Проверить webhook: `telegram:webhook:info`.
5. Добавить бота в группу и выполнить `/connect`.
6. Проверить `/settings` и `/send_now`.
7. Ответить на опрос несколькими пользователями.
8. Проверить `/my`, `/stats` и `/top`.
9. Проверить `/pause` и `/resume`.
10. Убедиться, что scheduler отправляет опросы по расписанию.
11. Для ИИ-перевода проверить очередь, лог worker и новые строки `word_translations`.

## Лицензия

Проект основан на Laravel, распространяемом по лицензии MIT. Учитывайте условия использования и
лицензирования исходных словарных CSV отдельно.
