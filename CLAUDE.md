# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ModuleHttpAlert — модуль расширения MikoPBX, отправляющий HTTP GET вебхуки на внешние серверы при событиях звонков (входящий, набор, ответ, завершение, CDR). Позволяет интегрировать АТС с CRM и другими внешними системами. Если внешний сервер возвращает текст на входящий звонок — текст устанавливается как Caller ID Name.

## Build & Dependencies

```bash
composer install          # установка зависимостей (Guzzle, log-rotation)
```

Минимальные требования: PHP 7.4.6, MikoPBX Core >= 2023.2.160. Тестов нет. Линтера нет.

### JavaScript (Babel)

Исходники JS — только в `public/assets/js/src/` (ES6). Файлы в `public/assets/js/` — скомпилированные ES5 + sourcemaps, генерируются автоматически. **Редактировать только `src/`.**

Ручная компиляция:
```bash
cd /Users/apor/Developement/MikoPBX/MikoPBXUtils
cp babel.config.json babel.config.json.bak
echo '{"presets":[["@babel/preset-env",{"targets":{"chrome":50,"ie":11,"firefox":45}}]]}' > babel.config.json

./node_modules/.bin/babel \
  /Volumes/DevDisk/apor/Developement/MikoPBX/Extensions/ModuleHttpAlert/public/assets/js/src/module-http-alert-index.js \
  --out-dir /Volumes/DevDisk/apor/Developement/MikoPBX/Extensions/ModuleHttpAlert/public/assets/js/ \
  --source-maps

mv babel.config.json.bak babel.config.json
```

В PHPStorm при настроенном File Watcher компиляция происходит автоматически при сохранении.

## Architecture

### Data Flow

```
Входящий звонок → AGI (agi-bin/incomingCallAlert.php) → WorkerHTTP → ClientHTTP → внешний сервер
                                                                                    ↓
Событие в Asterisk → ListenerAMI (AMI events) → WorkerHTTP → ClientHTTP → внешний сервер
```

### Key Layers

- **Lib/HttpAlertConf.php** — центральный класс конфигурации модуля. Определяет воркеры, REST-маршруты, cron-задачи, генерацию диалплана. Точка входа для жизненного цикла модуля (enable/disable).
- **bin/ListenerAMI.php** — фоновый воркер, слушает CdrConnector user events через AMI. Хранит состояние активных звонков, определяет тип (incoming/outgoing/inner), подставляет URL-шаблоны с учётом DID и отправляет задачи в очередь.
- **bin/WorkerHTTP.php** — фоновый воркер, обрабатывает HTTP-запросы через Beanstalk-очередь. Rate limit: 7 запросов/сек.
- **bin/safe.php** — cron-скрипт (каждую минуту), проверяет живы ли воркеры и перезапускает упавшие.
- **agi-bin/incomingCallAlert.php** — AGI-скрипт, вызывается при входящем звонке до набора. Может установить Caller ID Name из ответа HTTP-сервера.
- **Lib/ClientHTTP.php** — обёртка над Guzzle для HTTP GET запросов (timeout 5s, SSL verify off).

### URL Template System

URL-параметры задаются шаблонами с плейсхолдерами: `<date>`, `<id>`, `<uid>`, `<number>`, `<channel>`, `<did>`, `<action>`, `<user>`, `<client>`. WorkerHTTP заменяет плейсхолдеры на реальные значения при отправке.

### Configuration Hierarchy

Модель `ModuleDidUrl` позволяет задать базовый URL и HTTP Basic Auth для конкретного DID. Если для DID нет записи — используется запись с пустым DID (по умолчанию). Глобальные URL-шаблоны событий хранятся в `ModuleHttpAlert`.

### Web UI (MVC, Phalcon)

- **App/Controllers/ModuleHttpAlertController.php** — CRUD для глобальных настроек и DID-правил
- **App/Forms/** — определения форм (Phalcon Forms)
- **App/Views/** — Volt-шаблоны (Semantic UI)
- **public/assets/js/src/** — JS для форм, использует общий MikoPBX `Form` объект для отправки

### Compatibility

**Lib/MikoPBXVersion.php** — абстракция для совместимости Phalcon 4 / Phalcon 5 (PBX >= 2024.2.3). Возвращает корректные имена классов Di, Validation, Logger, Text в зависимости от версии.

### Logging

Логи пишутся в `/storage/usbdisk1/mikopbx/log/ModuleHttpAlert/`. Ротация: 5 файлов, минимум 10MB. Класс `Lib/Logger.php`.

### REST API

Один тестовый endpoint: `GET /pbxcore/api/module-http-alert/v1/test` — логирует полученные параметры и возвращает timestamp. Полезен для отладки вебхуков.

## Deployment (SSH)

### ЗАПРЕЩЕНО

- `rsync`, `cp -r`, `tar` для установки модуля — ломает БД и симлинки
- `rm -rf /storage/.../ModuleHttpAlert` — удалит базу данных модуля безвозвратно
- Ручное копирование файлов в директорию модуля на сервере

### Установка через WorkerModuleInstaller (единственный правильный способ)

```bash
# 1. Создать архив локально
cd /Volumes/DevDisk/apor/Developement/MikoPBX/Extensions/ModuleHttpAlert
zip -r ../ModuleHttpAlert.zip . \
  -x "*.git*" -x "*tasks.md*" -x "*.DS_Store*" -x "*CLAUDE.md*"

# 2. Загрузить на сервер
scp ../ModuleHttpAlert.zip root@<server>:/tmp/

# 3. На сервере: создать settings.json и запустить установщик
ssh root@<server> << 'ENDSSH'
cat > /tmp/settings.json << 'EOF'
{
    "currentModuleDir": "/storage/usbdisk1/mikopbx/custom_modules/ModuleHttpAlert",
    "filePath": "/tmp/ModuleHttpAlert.zip",
    "uniqid": "ModuleHttpAlert"
}
EOF
php -f /usr/www/src/PBXCoreREST/Workers/WorkerModuleInstaller.php start /tmp/settings.json
ENDSSH
```

### После установки: сброс кэшей

```bash
ssh root@<server> 'redis-cli -n 4 FLUSHDB && rm -rf /var/tmp/www_cache/volt/* && php -r "opcache_reset();" 2>/dev/null'
```

Три уровня кэша: Redis DB 4 (переводы), Volt-шаблоны, OPcache (байткод PHP). Без сброса OPcache сервер продолжит выполнять старый код.

### Бэкап БД при обновлении

```bash
# До установки — сохранить БД
scp root@<server>:/storage/usbdisk1/mikopbx/custom_modules/ModuleHttpAlert/db/* /tmp/backup/

# После установки — восстановить
scp /tmp/backup/* root@<server>:/storage/usbdisk1/mikopbx/custom_modules/ModuleHttpAlert/db/
```

### Перезапуск воркеров после деплоя

```bash
ssh root@<server> "ps aux | grep -E 'ListenerAMI|WorkerHTTP' | grep -v grep | awk '{print \$2}' | xargs kill -9"
```

Воркеры будут автоматически перезапущены safe.php через cron (до 1 минуты).

## Общие принципы разработки MikoPBX-модулей

### Совместимость PHP 7.4 + Phalcon 4/5

**Запрещённый синтаксис (PHP 8+):**
- `match` — использовать `switch`
- Union types (`int|string`) — использовать PHPDoc
- Named arguments — только позиционные
- Constructor promotion — явное присваивание свойств
- Nullsafe `?->` — явная проверка на null
- `str_contains()`, `str_starts_with()`, `str_ends_with()` — использовать `strpos()`
- `array_is_list()` — не использовать

**Phalcon 4 vs 5:**
- `findFirst()` возвращает `false` (P4) или `null` (P5) → проверять через `!$record`
- Классы Di, Logger, Validation — получать через `MikoPBXVersion::getDefaultDi()` и т.д.

### Скрипты в bin/ и agi-bin/

Каждый скрипт обязан начинаться с:
```php
require_once('Globals.php');
```
`Globals.php` — симлинк на `/usr/www/src/Core/Config/Globals.php`, создаётся автоматически при установке через WorkerModuleInstaller.

### Модели (Phalcon ORM)

- Таблицы с префиксом `m_ModuleHttpAlert`
- Аннотации `@Primary`, `@Identity`, `@Column` для автогенерации схемы
- Параметризованные запросы: `findFirst(['conditions' => 'field = :val:', 'bind' => ['val' => $value]])`
- Миграций нет — схема создаётся из аннотаций в `PbxExtensionSetup::installDB()`

### REST API

- Маршруты регистрируются в `HttpAlertConf::getPBXCoreRESTAdditionalRoutes()`
- Базовый путь: `/pbxcore/api/module-http-alert/v1/`
- `noAuth: true` — публичный endpoint без авторизации
- При парсинге JSON — удалять UTF-8 BOM (`\xEF\xBB\xBF`), не использовать `getJsonRawBody()` напрямую

### Воркеры (фоновые процессы)

- Наследуют `WorkerBase` из MikoPBX Core
- Регистрируются в `HttpAlertConf::getModuleWorkers()` с типом `CHECK_BY_PID_NOT_ALERT`
- IPC через Beanstalk: `WorkerHTTP::invoke('httpGet', [$url, $params])`
- PID — в колонке `$2` (BusyBox `ps`)

### UI

- CSS-фреймворк: Semantic UI (не Bootstrap)
- HTML ID: kebab-case
- JS-паттерн: глобальный объект `const ModuleHttpAlertIndex = { ... }`
- Формы: Phalcon Forms + Volt-шаблоны

## Conventions

- PSR-4 autoload: namespace `Modules\ModuleHttpAlert\` → корень модуля
- Все переводы — в `Messages/*.php` (22 языка), ключи с префиксом `module_httpAlert_`
- Модели наследуют `ModuleBaseClass` из MikoPBX Core
- Конфигурационный класс наследует `ConfigClass`
- Воркеры наследуют `WorkerBase` из Core
