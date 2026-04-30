# Release checklist (публичный репозиторий)

Использовать перед тегом и публикацией на GitHub.

## Секреты и обезличивание

- [ ] `gitleaks detect --source .` или `trufflehog filesystem .` — без находок critical
- [ ] `rg -i "password|secret|BEGIN (RSA|OPENSSH)|private_key|api_key" --glob '!*.po'`
- [ ] Поиск по кастомным маркерам компании (домены, GitLab, внутренние IP)
- [ ] Убедиться, что **нет** `.env`, `*.pem`, `id_rsa`, дампов SQL, каталогов `var/logs/`
- [ ] В README и примерах только `example.com`, плейсхолдеры мерчанта

## Git

- [ ] Если ранее в истории были `.gitlab-ci.yml` с IP, `task-tracker`, креды — **новая история**:
  `git checkout --orphan public-main`, добавить файлы, commit, `git push --force` в **новый** публичный remote  
  **или** `git filter-repo` / BFG по согласованию с командой
- [ ] Проверить сообщения коммитов: `git log --all | rg -i 'internal|client|password|uroven'` (пример)
- [ ] После утечки кредов в истории — **ротация паролей API** в Сбербанке
- [ ] Локальный `.git/config`: перед пушем убедиться, что в инструкциях для команды не светится приватный origin

## Код и платежи

- [ ] Callback/return: ручной smoke-тест на тестовом терминале Сбера
- [ ] Логирование выключено на production-стенде или уровень минимален
- [ ] Нет временного отключения `SSL_VERIFYPEER` в форке без документированной причины

## Документация и метаданные

- [ ] `README.md`: версии CS-Cart, установка, конфигурация, Security, Author
- [ ] `LICENSE` copyright год и имя совпадают с заявленными
- [ ] `CHANGELOG.md` обновлён
- [ ] Версия в `addon.xml` согласована с тегом git

## Юридическое

- [ ] Нет NDA-формулировок, имён клиентов, ссылок на закрытые таск-трекеры
- [ ] При необходимости — проверка IP третьих сторон (только публичная документация Сбера / CS-Cart)

## После релиза

- [ ] GitHub: включить Security Advisories (если доступно)
- [ ] Опубликовать release notes по `CHANGELOG.md`
