# Horus CRM

CRM interno de **Horus Group SRL** para gestionar participantes, cursos, ediciones, matrículas, calificaciones y certificados con verificación pública.

> Sistema exclusivamente interno: solo el personal se autentica. Los participantes no tienen cuenta ni acceso al sistema.

## Stack

- Laravel 13 · PHP 8.3+ (desarrollo con 8.4 en Laragon)
- PostgreSQL 18 (`pgsql`)
- Starter kit oficial de Vue: Inertia v3, Vue 3, TypeScript, Tailwind 4, Fortify y Wayfinder
- Pest 5 · Spatie Laravel Permission 8
- dompdf (certificados PDF) · bacon/bacon-qr-code (QR) · maatwebsite/excel (importación y exportación)

## Autenticación

Activado en Fortify: login, **registro** (`/register`), recuperación de contraseña, verificación en dos pasos (2FA) y confirmación de contraseña.

**Desarrollo local:** con `APP_ENV=local`, quien se registra en `/register` recibe automáticamente el rol **Super Admin**, aunque todavía no se haya ejecutado `RoleSeeder`. Así cada integrante del equipo crea su cuenta en su propia máquina sin pasos manuales.

- Se controla con `REGISTER_AS_SUPER_ADMIN`; por defecto vale `true` solo si `APP_ENV=local`.
- En **producción nunca** se aplica, aunque la variable esté en `true`: las cuentas nuevas quedan sin rol.
- Para desactivar el registro, quita `Features::registration()` en `config/fortify.php`.

Desactivado a propósito: verificación por correo, passkeys y que un usuario elimine su propia cuenta.

## Puesta en marcha local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edita `.env` y completa `DB_PASSWORD`. Por defecto la aplicación usa la base `horus_prueba` en `127.0.0.1:5432`.

Las migraciones se ejecutan **manualmente y solo con autorización** sobre la base de desarrollo:

```bash
php artisan migrate
php artisan db:seed      # roles iniciales (idempotente)
```

### Primer Super Admin

```bash
php artisan horus:create-admin                       # interactivo, pide la contraseña sin mostrarla
php artisan horus:create-admin --name="Nombre" --email=correo@dominio --generate-password
```

Con `--generate-password` la contraseña se muestra una sola vez. Cámbiala en **Ajustes → Seguridad** y activa la 2FA.

### Desarrollo

```bash
php artisan serve        # backend
npm run dev              # Vite con recarga en caliente
npm run build            # assets de producción
npm run types:check      # verificación TypeScript
```

## Pruebas

Las pruebas usan una base PostgreSQL **separada**, `horus_prueba_test`, que se borra y recrea. `tests/TestCase.php` aborta si la base no es PostgreSQL o si su nombre no termina en `_test`.

```bash
./vendor/bin/pest                          # todas (requiere `npm run build` para las páginas Inertia)
./vendor/bin/pest --testsuite=Unit
./vendor/bin/pest --testsuite=Feature
./vendor/bin/pest --testsuite=Concurrency  # procesos PHP reales compitiendo por el correlativo
```

## Documentación

- [docs/modules.md](docs/modules.md): módulos, roles y permisos, certificados, importación.
- [docs/database.md](docs/database.md): modelo relacional, restricciones, índices, reglas de negocio y concurrencia.
