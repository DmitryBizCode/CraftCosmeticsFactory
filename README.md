# MaliTech - Система управления контейнерами

![PHP](https://img.shields.io/badge/PHP-8.3-blue.svg)
![MySQL](https://img.shields.io/badge/MySQL-Latest-orange.svg)
![Docker](https://img.shields.io/badge/Docker-Ready-cyan.svg)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-purple.svg)

Веб-приложение для управления контейнерами с поддержкой IoT датчиков, аренды, доставки и мониторинга.

## 🚀 Возможности

- **Управление контейнерами** - регистрация, отслеживание, управление состоянием
- **IoT мониторинг** - 8 типов датчиков с реальным временем
- **Система аренды** - бронирование контейнеров, управление заказами
- **Отслеживание доставок** - маршруты, статусы, трекинг
- **Управление клиентами** - профили, настройки, история

## 📋 Требования

- Docker
- Docker Compose
- Git

## 🛠 Установка

### 1. Клонировать репозиторий
```bash
git clone <your-repo-url>
cd MaliTech
```

### 2. Запустить Docker
```bash
cd phpdocker
docker-compose up -d
```

### 3. Установить зависимости (если нужно)
```bash
composer install
```

### 4. Подождать инициализации БД
```bash
docker-compose logs mysql
```

## 🌐 Доступ

| Сервис | URL | Учетные данные |
|--------|-----|----------------|
| Веб-приложение | http://localhost:65000 | - |
| phpMyAdmin | http://localhost:8080 | - |
| MySQL | localhost:3306 | user / 12345 |

## 📁 Структура проекта

```
MaliTech/
├── index.php              # Точка входа
├── src/                   # Исходный код
│   ├── Controllers/       # Контроллеры
│   ├── Model/            # Модели данных
│   └── Services/         # Бизнес-логика
├── views/                # Шаблоны Twig
│   ├── pages/            # Страницы
│   ├── partials/         # Части страниц
│   ├── stylesheet/       # CSS стили
│   └── templates/        # Базовые шаблоны
├── phpdocker/            # Docker конфигурация
│   ├── docker-compose.yml
│   └── init.sql/         # SQL скрипты
├── vendor/               # Зависимости
├── var/                  # Кэш и временные файлы
└── image/                # Загруженные изображения
```

## 🗄 База данных

**База:** `container`  
**13 таблиц:** clients, containers, countries, ports, routes, rentals, shipments, statuses, transactions, maintenances, metrics, owners, container_client

См. подробную схему в `DATABASE_SCHEMA.md`

## 📊 IoT Датчики

Приложение поддерживает 8 типов датчиков:

| Тип | Точность | Единица |
|-----|----------|---------|
| temperatures | ±2°C | °C |
| humidity | ±5% | % |
| vibrometers | ±0.1 м/с² | м/с² |
| inclines | ±1° | градусы |
| openings | 0/1 | бинарный |
| gps | ±5 м | метры |
| illuminated | ±10 люкс | люкс |
| gases | ±0.5 ppm | ppm |

## 🔄 Маршруты API

### Страницы
- `/` - Главная
- `?action=sign` - Вход/Регистрация
- `?action=contact` - Контакты
- `?action=services` - Услуги
- `?action=profile&id={id}` - Профиль
- `?action=orders&id={id}` - Заказы
- `?action=rental&id={id}` - Аренда
- `?action=detail&id={id}&id_containers={cid}` - Детали

## 🧑‍💻 Разработка

### Запуск в режиме разработки
```bash
docker-compose up
```

### Логи
```bash
# Все сервисы
docker-compose logs -f

# Только PHP
docker-compose logs -f php-fpm

# Только MySQL
docker-compose logs -f mysql

# Только Nginx
docker-compose logs -f webserver
```

### Bash в контейнере
```bash
docker-compose exec php-fpm bash
```

### Очистка кэша
```bash
rm -rf var/cache/*
```

### Перезапуск
```bash
docker-compose down
docker-compose up -d
```

## 📝 Команды Docker

| Команда | Описание |
|---------|----------|
| `docker-compose up -d` | Запуск в фоне |
| `docker-compose up` | Запуск с логами |
| `docker-compose down` | Остановка |
| `docker-compose stop` | Приостановка |
| `docker-compose restart` | Перезапуск |
| `docker-compose logs -f` | Логи в реальном времени |
| `docker-compose ps` | Список контейнеров |

## 🔍 Технологический стек

- **Backend:** PHP 8.3
- **Frontend:** Bootstrap 5, Twig 3
- **Database:** MySQL
- **Web Server:** Nginx
- **Infrastructure:** Docker
- **Dependencies:** Composer

## ⚠️ Известные проблемы

- Несогласованность ключей в таблице `clients` (см. `DATABASE_SCHEMA.md`)
- Пустая папка `image/` не используется
- Отсутствует `.env` для конфигурации

## 🛡 Безопасность

⚠️ **Для production:**
- Изменить пароли БД
- Настроить HTTPS
- Добавить CSRF защиту
- Настроить `.env` файлы
- Убрать debug режим Twig

## 📚 Документация

- [Структура проекта](PROJECT_STRUCTURE.md)
- [Схема БД](DATABASE_SCHEMA.md)

## 🤝 Вклад

1. Fork проекта
2. Создать feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit изменения (`git commit -m 'Add some AmazingFeature'`)
4. Push в branch (`git push origin feature/AmazingFeature`)
5. Создать Pull Request

## 📄 Лицензия

Не указана

## 👤 Автор

**Dmitry Beznosiuk**
- GitHub: [@DmitryBizCode](https://github.com/DmitryBizCode)

## 🙏 Благодарности

- [Twig](https://twig.symfony.com/)
- [Bootstrap](https://getbootstrap.com/)
- [PHPDocker.io](https://www.phpdocker.io/)
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)

---

**Версия:** 1.0.0  
**Последнее обновление:** 2024
# CraftCosmeticsFactory
