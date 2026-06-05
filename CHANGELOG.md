# Changelog

Формат основан на [Keep a Changelog](https://keepachangelog.com/ru/1.0.0/).

## [Unreleased]

## [1.3.2] — 2026-06-05

### Added
- Режим checkout **SberPay Web SDK**: landing `as_sberpay_api.pay`, виджет (`sberpay_widget.js`, vendor UMD).
- Режим checkout **СБП C2B**: landing `as_sberpay_api.sbp`, QR и виджет банков НСПК, polling `sbp_status`, expire по таймауту.
- Frontend-контроллер `controllers/frontend/as_sberpay_api.php`.
- Шаблоны витрины в `var/themes_repository/…/pay.tpl`, `pay_sbp.tpl`.
- JS: `js/addons/as_sberpay_api/` (`sbp_pay.js`, `sberpay_widget.js`, vendor).
- Таблица `?:sberpay_order_meta` для метаданных платежа (SBP payload, snapshot чека и др.).
- Настройка аддона «Показывать ссылки на чеки ОФД»; хук `payment_info` в админке.
- Реквизиты продавца для 54‑ФЗ в настройках процессора (ИНН, email, адрес расчётов, СНО).

### Changed
- Версия аддона `1.1.1` → `1.3.2`; расширен процессор `AsSberPayApi` и `func.php`.
- Return/callback учитывают сценарии SDK и СБП.
- Документация: README (режимы checkout, структура репозитория, troubleshooting).

### Security
- Fallback `X-CLIENT` для виджета СБП — нейтральный `store` (без привязки к конкретному магазину в коде).

---

## [1.1.1]

Публичная подготовка репозитория: LICENSE (MIT), SECURITY.md, CONTRIBUTING.md, release checklist; TLS verification в HTTP-клиенте.

<!-- После публикации репозитория раскомментируйте и подставьте URL:
[Unreleased]: https://github.com/USER/REPO/compare/v1.3.2...HEAD
[1.3.2]: https://github.com/USER/REPO/releases/tag/v1.3.2
[1.1.1]: https://github.com/USER/REPO/releases/tag/v1.1.1
-->
