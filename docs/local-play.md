# Jugar a SheManagerGame en local

Esta guía es para jugar en tu propio ordenador bajándote el repo, sin
depender de shemanager.wasmer.app. No necesitas cuenta en ningún servicio
de pago: solo Docker (recomendado) o PHP + Postgres en local.

## Opción A: Docker (recomendada)

```bash
git clone https://github.com/izandhh15/SheManagerGame.git
cd SheManagerGame
cp .env.example .env          # si existe; si no, crea uno desde .env.docker
docker compose -f docker-compose.dev.yml up
```

Eso levanta:

- `app` en http://localhost:8000 (la app Laravel)
- `postgres` en localhost:5432 (base `virtua_fc`, usuario `virtua_fc`)
- `redis` en localhost:6379
- `vite` en localhost:5173 (assets en desarrollo)

La primera vez, dentro del contenedor `app`, corre las migraciones y el
seed si hace falta:

```bash
docker compose -f docker-compose.dev.yml exec app php artisan migrate --force
```

Para parar: `docker compose -f docker-compose.dev.yml down`
(la base de datos persiste en el volumen `pgdata`).

## Opción B: PHP + Postgres sin Docker

Requisitos: PHP 8.3, Composer, Node 20+, Postgres 16+.

```bash
git clone https://github.com/izandhh15/SheManagerGame.git
cd SheManagerGame
composer install
npm ci
cp .env.example .env
php artisan key:generate
# Edita .env: DB_CONNECTION=pgsql, DB_HOST=127.0.0.1,
# DB_DATABASE=virtua_fc, DB_USERNAME=virtua_fc, DB_PASSWORD=...
createdb virtua_fc   # o crea la base desde tu cliente Postgres
php artisan migrate --force
npm run build
php artisan serve    # http://127.0.0.1:8000
```

Notas:

- Los tests usan Postgres, no SQLite (`tests/TestCase.php` fija la
  conexión `pgsql`).
- En producción (Wasmer Edge, PHP 32-bit) hay reglas especiales
  documentadas en el GOAL de QA: no usar `iconv(..., 'ASCII//TRANSLIT')`
  ni `hexdec()` de 8 dígitos, y verificar el deploy con
  `wasmer package get` además del exit code. En local no aplican.
- Nunca subas tu `.env` real al repo: está en `.gitignore`.
