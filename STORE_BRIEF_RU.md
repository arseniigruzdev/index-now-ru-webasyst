# Карточка Webasyst Store

## Основные поля

- Название: **Index-Now.ru для Shop-Script**
- Тип: Shop-Script plugin
- Цена: Free
- Версия: 1.0.0
- Языки: русский, English metadata
- Требования: Shop-Script 12.0.0+, Webasyst Installer 4.0.1+, PHP 7.4.24+
- Сайт: https://index-now.ru/?utm_source=webasyst-store&utm_medium=referral&utm_campaign=integration
- Исходный код: https://github.com/arseniigruzdev/index-now-ru-webasyst
- Поддержка: support@index-now.ru

## Короткое описание

Отправляет публичный URL активного товара Shop-Script в Index-Now.ru после публикации или обновления.

## Полное описание

Плагин подключает Shop-Script к облачному сервису Index-Now.ru. После сохранения активного товара он получает его публичный URL штатным методом Shop-Script и отправляет адрес через HTTPS API.

Возможности:

- автоматическая отправка URL активного товара;
- защита от повторной отправки одного товара в пределах запроса;
- шифрование API-ключа AES-256-GCM;
- отдельный файл ключа с запрашиваемыми правами `0600`;
- русский интерфейс настроек;
- настраиваемый тайм-аут;
- сохранение товара не прерывается при ошибке сети или API;
- API-ключ, заголовки и тело ответа не попадают в журнал.

Интеграция выключена по умолчанию. Администратор магазина самостоятельно указывает API-ключ и Site ID и явно включает отправку. Отправка URL не гарантирует сканирование или включение страницы в поисковый индекс.

## Внешний сервис и данные

После явного включения плагин передаёт на `https://index-now.ru/api/v1/submit`:

- API-ключ в HTTPS-заголовке Authorization;
- настроенный Site ID;
- публичный URL активного товара.

- Privacy: https://index-now.ru/privacy
- Terms: https://index-now.ru/terms
- Pricing: https://index-now.ru/pricing

## Блокер сборки Store

Перед загрузкой необходимо получить положительный числовой Webasyst developer Vendor ID и выполнить:

```powershell
.\build.ps1 -Marketplace -VendorId 123456
```

Команда без Vendor ID должна завершаться ошибкой; локальный архив с `vendor = 0` в Store загружать нельзя.

