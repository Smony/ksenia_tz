# Обработка входящего звонка

Тестовое задание: `ProcessIncomingCallJob` для очереди Redis при параллельных воркерах.

Стек: PHP 8.2, Laravel 11. Код рассчитан на встраивание в существующий проект (модели, миграции, job, сервисы).

## Как устроено

1. Входящий звонок пишется в `calls` со статусом `incoming`.
2. В очередь `incoming-calls` уходит `ProcessIncomingCallJob($callId)`.
3. `IncomingCallProcessor`:
   - ищет клиента по нормализованному номеру;
   - резервирует оператора в транзакции с `lockForUpdate` (чтобы два воркера не взяли одного);
   - обновляет звонок;
   - шлёт событие в телефонию через `TelephonyGateway`;
   - пишет шаги в `call_processing_logs` и в канал `calls`.

При падении телефонии откатывается назначение: звонок снова `incoming`, у оператора уменьшается `active_calls`.

## Почему так под нагрузку

| Риск | Что сделано |
|------|-------------|
| Два воркера — один оператор | `OperatorSelector::reserveNext()` + `lockForUpdate` |
| Job выполнился дважды | `ShouldBeUnique` по `call_id`, проверка статуса `assigned` |
| Телефония упала после резерва | rollback оператора и звонка, job уходит в retry |
| Нет свободных операторов | `NoAvailableOperatorException`, backoff, повтор |

Job: `tries = 5`, backoff `[5, 15, 60, 120]` секунд, отдельная очередь `incoming-calls`.

## Запуск тестов

```bash
composer install
./vendor/bin/phpunit
```

## Интеграция

После создания записи звонка:

```php
ProcessIncomingCallJob::dispatch($call->id);
```

В `.env` проекта:

```
TELEPHONY_ENDPOINT=https://...
QUEUE_CONNECTION=redis
```

Воркеры:

```bash
php artisan queue:work redis --queue=incoming-calls --tries=5
```

Для уникальности job нужен Redis/cache driver с поддержкой `ShouldBeUnique`.

## Что бы добавил в проде (вне скоупа ТЗ)

- метрики: время до назначения, доля retry, очередь без операторов;
- circuit breaker на телефонию;
- отдельный dead-letter для звонков после исчерпания попыток;
- webhook от телефонии «оператор освободился» → `OperatorSelector::release()`.

## Структура

```
app/Jobs/ProcessIncomingCallJob.php
app/Services/IncomingCallProcessor.php
app/Services/OperatorSelector.php
app/Services/ClientResolver.php
app/Services/CallProcessingLogger.php
app/Services/Telephony/
database/migrations/
tests/Feature/ProcessIncomingCallJobTest.php
```
