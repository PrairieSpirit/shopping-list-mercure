# 🧳 Collaborative Travel Shopping List

Real-time collaborative SPA using **Mercure** for pub/sub push.
**PHP 8.4 · Symfony 7 · MySQL 8 · Mercure · Vanilla JS**

---

## Завдання
Написати додаток, який дозволяє кільком людям одночасно редагувати список покупок (рядки) для подорожей.

- Цей додаток має бути односторінковим і не мати вимог до авторизації.
- Має бути функція додавання, видалення та редагування рядків.
- Для отримання та відправлення змін необхідно використовувати AJAX.
- Зміни, внесені одним користувачем, мають негайно відображатися на сторінках інших користувачів без перезавантаження.

---

## Quick Start

```bash
git clone https://github.com/PrairieSpirit/shopping-list-mercure.git && cd shopping-list-mercure

make up          # starts php + nginx + mysql + mercure
make install     # composer install
make migrate     # create DB + run migrations

# open http://localhost:8080 in two tabs
```

---

## Architecture

```
Browser A ──AJAX──▶ PHP (ItemController → ItemService)
                         │
                         └──HTTP publish──▶ Mercure Hub
                                                │
                              SSE push ◀────────┘
                                │
Browser A ◀─────────────────────┤
Browser B ◀─────────────────────┘
```

**Flow:**
1. Browser loads → `GET /api/items` (initial list)
2. Browser subscribes to Mercure hub via `EventSource`
3. User creates/updates/deletes → AJAX to PHP API
4. `ItemService` saves to MySQL → publishes `Update` to Mercure hub
5. Mercure pushes event to **all** subscribed browsers instantly

---

## Real-time: Mercure

Mercure is a pub/sub protocol built on SSE (Server-Sent Events).
The hub (`dunglas/mercure`) runs as a separate Docker service and is
proxied through nginx at `/.well-known/mercure`.

**Events published by PHP:**
```json
{"type": "item.created", "data": {...}}
{"type": "item.updated", "data": {...}}
{"type": "item.deleted", "data": {"id": 42}}
```

**Why Mercure over plain SSE:**
- No long-running PHP process in the web container
- Proper pub/sub with JWT auth (ready for production)
- Handles reconnections, multiple topics, message history

---

## REST API

Base: `http://localhost:8080/api`

| Method | Endpoint | Body | Status |
|---|---|---|---|
| GET | `/api/items` | — | 200 |
| GET | `/api/items?since=<ISO>` | — | 200 |
| POST | `/api/items` | `{"text":"..."}` | 201 |
| PUT | `/api/items/{id}` | `{"text":"...","is_done":true}` | 200 |
| DELETE | `/api/items/{id}` | — | 204 |

---

## Services

| Container | Image | Port |
|---|---|---|
| shopping_php | php:8.4-fpm (build) | internal |
| shopping_nginx | nginx:alpine | **8080** |
| shopping_mysql | mysql:8.0 | 3306 |
| shopping_mercure | dunglas/mercure:latest | internal (via nginx) |

---

## Commands

| Command | Action |
|---|---|
| `make up` | Start all (4) containers |
| `make down` | Stop containers |
| `make install` | Run `composer install` inside PHP container |
| `make create-test-db` | Create test DB and grant privileges |
| `make migrate` | Create DB + run migrations |
| `make test` | PHPUnit tests |
| `make shell` | Bash in PHP container |
| `make logs` | Follow all logs |
| `make reset` | Full reset (removes DB volume) |
| `make cc` | Clear Symfony cache |

---

## Tests


```bash
make create-test-db
# Ensures `symfony_test` database exists and assigns full privileges on it to `symfony_user`
```

```bash
make test
# Ensures test DB exists, runs migrations, then executes PHPUnit tests
```

10 test cases: empty list, list, create (valid/empty/missing/too-long),
update (text, is_done, 404, empty), delete (success, 404).

---

## Trade-offs

| Decision | Why |
|---|---|
| Mercure over plain SSE | No Doctrine cache issues, proper pub/sub, production-ready |
| Mercure over WebSocket (Ratchet) | No custom PHP server process needed, Symfony-native |
| Anonymous subscribers | No auth required per TZ — simplifies setup |
| Optimistic UI | User sees change instantly; Mercure confirms to others |
| Vanilla JS | No build step, no framework overhead for this scope |
