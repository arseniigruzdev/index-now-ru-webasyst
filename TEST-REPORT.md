# Отчёт о проверке Index-Now.ru для Webasyst / Shop-Script 1.0.0

Дата проверки: 2026-07-28.

## Результат

Локальная installable-сборка подготовлена:

- архив: `outputs/webasyst-1.0.0/indexnowru-1.0.0-local.tar.gz`;
- размер: 9 208 байт;
- SHA-256: `f63e18070ee69e2c6eb537adabc081ee52e0ba246c4ff296c4404efe4cb437c4`;
- корень архива: единственная папка `indexnowru/`;
- `vendor`: `0`, только для локальной установки и тестирования;
- тесты и `build.ps1` в installable-архив не включены.

Marketplace-сборка не создана. Для неё нужен положительный числовой Webasyst
developer ID:

```powershell
.\build.ps1 -Marketplace -VendorId 123456
```

`build.ps1 -Marketplace` без ID проверен: сборка завершается ошибкой и не
позволяет случайно подготовить пакет Store с `vendor = 0`.

## Выполненные проверки

Все перечисленные проверки завершились успешно.

1. `tests/run-static.ps1`
   - наличие обязательных runtime-, RU-locale- и documentation-файлов;
   - версия `1.0.0`, хук `product_save`, HTTPS endpoint, Bearer-заголовок;
   - активный статус товара и штатный генератор URL Shop-Script;
   - AES-256-GCM и защита Marketplace-сборки от `vendor = 0`;
   - gettext-вызовы для русской локализации настроек;
   - отсутствие отладочных выводов и очевидного логирования секрета.
2. PHP 7.4 CLI
   - `php -l` для всех 7 runtime PHP-файлов;
   - `tests/run.php`.
3. PHP 8.3 CLI
   - `php -l` для всех 7 runtime PHP-файлов;
   - `tests/run.php`.
4. Unit suite без внешних запросов
   - шифрование/дешифрование API-ключа и отсутствие plaintext в настройках;
   - создание и удаление отдельного key-файла;
   - сохранение прежнего секрета при пустом password-поле и явная очистка;
   - JSON payload, endpoint, HTTP method, timeout и Authorization header;
   - отправка активного товара и подавление дубля в одном PHP-запросе;
   - пропуск выключенного плагина, неактивного товара и небезопасного URL;
   - fail-open при исключении API;
   - отсутствие API-ключа и тела ошибки в журнале;
   - валидация Site ID и timeout.
5. GNU gettext
   - `msgfmt --check --check-format` для `shop_indexnowru.po`;
   - скомпилированный результат совпадает с включённым `shop_indexnowru.mo`.
6. Готовый архив
   - повторная распаковка в одноразовом контейнере;
   - проверка единственной корневой папки и отсутствия development-файлов;
   - `php -l` всех PHP-файлов непосредственно из распакованного архива;
   - повторный расчёт SHA-256.

## Официальная база совместимости

Реализация сверена только с официальными материалами:

- структура плагинов и хуки:
  https://developers.webasyst.com/docs/plugins/
- настройки плагинов:
  https://developers.webasyst.com/docs/cookbook/plugins/plugin-settings/
- HTTP-клиент `waNet`:
  https://developers.webasyst.com/docs/cookbook/basics/classes/waNet/
- системные требования:
  https://developers.webasyst.com/docs/cookbook/system-requirements/
- требования Webasyst Store:
  https://developers.webasyst.com/docs/store/webasyst-store-requirements/
- Shop-Script 12.5.0, официальный репозиторий, commit
  `8d8dd3fdb025fb597719553f49640c2f11e9b96d`;
- Webasyst Framework, официальный репозиторий, commit
  `137a12a86b269be2f20aeabb6b8b3f63a5bb230a`.

В Shop-Script 12.5.0 проверены `product_save`,
`shopProduct::getProductUrl(true, true, true)` и
`shopProductModel::STATUS_ACTIVE`. В Webasyst Framework проверены JSON request
format, timeout, TLS verification и ожидаемые HTTP-коды `waNet`.

## Непроверенные места и риски публикации

- Нет доступной живой установки Webasyst / Shop-Script: установка, UI настроек,
  фактический вызов хука и удаление плагина не проверялись end-to-end.
- Внешний API намеренно не вызывался. Реальный ответ, авторизация и сетевые
  ошибки должны быть проверены на тестовом магазине с отдельным API-ключом.
- При нескольких витринах версия 1.0.0 отправляет один URL, который возвращает
  штатный `shopProduct::getProductUrl`; выбор нужной витрины нужно подтвердить
  на реальной конфигурации.
- Отправка выполняется синхронно в `product_save`; при недоступном API сохранение
  товара остаётся успешным, но запрос может добавить до настроенного timeout
  (3–30 секунд) ко времени ответа.
- Права `0600` для key-файла запрашиваются кодом, но итоговые права зависят от
  ОС, пользователя PHP и настроек файловой системы сервера.
- Официальная автоматическая проверка Webasyst Store не запускалась.
- Числовой developer ID неизвестен, поэтому Store-ready архив пока намеренно
  отсутствует.
- Правила Webasyst Store предупреждают, что продукты, которым для основной
  работы нужна внешняя регистрация или дополнительная оплата, могут быть
  отклонены. Основная функция этого плагина требует аккаунт/API-ключ
  Index-Now.ru; до отправки карточки необходимо согласовать позиционирование и
  disclosure с модерацией Store.
