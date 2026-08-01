# Index-Now.ru для Webasyst / Shop-Script

Плагин `indexnowru` для Shop-Script 12 отправляет публичный URL активного
товара в Index-Now.ru после публикации или обновления. Сохранение товара не
зависит от ответа внешнего сервиса: ошибки сети, конфигурации и API
обрабатываются без прерывания операции Shop-Script.

## Требования

- Webasyst Installer 4.0.1 или новее.
- Shop-Script 12.0.0 или новее.
- PHP 7.4.24 или новее.
- PHP extensions: OpenSSL и JSON.
- Настроенная витрина Shop-Script, чтобы CMS могла построить абсолютный
  публичный URL товара.

## Установка

Установочный архив должен содержать одну корневую папку `indexnowru/`.

Для ручной установки:

1. Распакуйте `indexnowru/` в `wa-apps/shop/plugins/indexnowru/`.
2. Включите плагин через Installer или добавьте
   `'indexnowru' => true` в `wa-config/apps/shop/plugins.php`.
3. Откройте настройки плагина в Shop-Script.
4. Укажите API-ключ, ID сайта, тайм-аут 3–30 секунд и включите отправку.

## Поведение

- Используется официальный хук Shop-Script `product_save`.
- Обрабатываются только товары со статусом `shopProductModel::STATUS_ACTIVE`.
- URL строится штатным методом `shopProduct::getProductUrl(true, true, true)`.
  При нескольких витринах Shop-Script возвращает канонический URL первой
  подходящей витрины; версия 1.0.0 отправляет один URL.
- Дубликат события для одного товара внутри одного PHP-запроса подавляется.
- HTTPS-запрос выполняется штатным клиентом Webasyst `waNet`.
- Endpoint: `https://index-now.ru/api/v1/submit`.
- Payload:

```json
{
  "siteId": "SITE_ID",
  "urls": ["https://shop.example/product/example/"]
}
```

Отправка URL уведомляет настроенные каналы, но не гарантирует сканирование,
выбор или включение страницы в поисковый индекс.

## Внешний сервис и данные

Плагин не делает внешних запросов, пока администратор не сохранит API-ключ,
ID сайта и не включит автоматическую отправку.

При сохранении активного товара в Index-Now.ru передаются:

- API-ключ в HTTPS-заголовке `Authorization: Bearer …`;
- настроенный ID сайта;
- публичный URL товара.

Документы сервиса:

- сайт: https://index-now.ru/
- политика конфиденциальности: https://index-now.ru/privacy
- пользовательское соглашение: https://index-now.ru/terms
- тарифы и лимиты: https://index-now.ru/pricing

## Защита секрета

API-ключ не выводится обратно в форму и не записывается в журналы. В
`waAppSettingsModel` хранится только AES-256-GCM ciphertext. Отдельный случайный
32-байтовый ключ создаётся в
`wa-config/apps/shop/indexnowru.secret.php`, для файла запрашиваются права
`0600`. При удалении плагина файл ключа удаляется. Если файл утрачен, старый
ciphertext невозможно расшифровать: администратор должен ввести API-ключ
заново.

Журнал `wa-log/shop/plugins/indexnowru.log` содержит только ID товара,
нормализованный HTTP-код и тип исключения. API-ключ, заголовки, URL и тела
ответов туда не записываются.

## Сборка

Локальная installable-сборка с `vendor = 0`:

```powershell
.\build.ps1
```

Она создаёт `outputs/webasyst-1.0.0/indexnowru-1.0.0-local.tar.gz` и подходит
для локальной установки/тестирования, но не для Webasyst Store.

Marketplace-сборка требует числовой developer ID:

```powershell
.\build.ps1 -Marketplace -VendorId 123456
```

Команда завершится ошибкой, если `-Marketplace` указан без положительного
`VendorId`. В Store следует загружать только созданный этой командой
`indexnowru-1.0.0.tar.gz`.

## English metadata

**Name:** Index-Now.ru

**Description:** Submits public Shop-Script product URLs to Index-Now.ru after
publishing or updating. The integration is opt-in and requires the store
owner's API key and site ID. Saving a product remains successful if the
external API is unavailable.

**External service:** When enabled, the plugin sends the API key in the
Authorization header, the configured site ID, and the public product URL to
`https://index-now.ru/api/v1/submit`. See
`https://index-now.ru/privacy` and `https://index-now.ru/terms`.

## Официальные источники Webasyst

Реализация сверена 2026-07-28 только с официальными материалами:

- plugin structure and hooks:
  https://developers.webasyst.com/docs/plugins/
- settings config:
  https://developers.webasyst.com/docs/cookbook/plugins/plugin-settings/
- `waNet`:
  https://developers.webasyst.com/docs/cookbook/basics/classes/waNet/
- system requirements:
  https://developers.webasyst.com/docs/cookbook/system-requirements/
- Webasyst Store packaging and vendor requirements:
  https://developers.webasyst.com/docs/store/webasyst-store-requirements/
- Shop-Script 12.5.0 official source, commit
  `8d8dd3fdb025fb597719553f49640c2f11e9b96d`:
  https://github.com/webasyst/shop-script
- Webasyst Framework official source, commit
  `137a12a86b269be2f20aeabb6b8b3f63a5bb230a`:
  https://github.com/webasyst/webasyst-framework

Ключевые проверенные места в официальном Shop-Script 12.5.0:
`lib/classes/shopProduct.class.php` (`product_save`,
`shopProduct::getProductUrl`) и `lib/model/shopProduct.model.php`
(`STATUS_ACTIVE`).

## Лицензия и поддержка

LGPL-3.0-or-later. Поддержка: support@index-now.ru.

