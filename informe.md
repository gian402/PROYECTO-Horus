# Informe de avance — Horus CRM

CRM interno de **Horus Group SRL**: participantes, cursos, ediciones, matrículas, notas y certificados con verificación pública. Este documento resume lo desarrollado y lo que falta, para que quien continúe pueda retomar sin contexto previo.

- **Stack:** Laravel 13 · PHP 8.4 · PostgreSQL 18 · Vue 3 + TypeScript + Inertia v3 · Tailwind 4 · Pest 5 · Spatie Permission · dompdf · bacon QR v3 · maatwebsite/excel.
- **Estado de las pruebas:** Pest completo **311 de 311 pasan** (1330 aserciones). También pasan Pint, `npm run types:check`, `npx vp check` y `npm run build`.
- **Más detalle:** [README.md](README.md) (puesta en marcha), [docs/modules.md](docs/modules.md) (módulos y reglas), [docs/database.md](docs/database.md) (modelo de datos).

## 1. Reglas del proyecto que se mantienen

- El sistema es **solo interno**. No existe módulo ni rol de estudiante; los participantes no tienen cuenta.
- Solo PostgreSQL y solo Pest.
- `horus_prueba` es la base de desarrollo. Las pruebas usan `horus_prueba_test`; `tests/TestCase.php` aborta si el nombre de la base no termina en `_test`.
- Las migraciones se ejecutan a mano y solo con autorización. Nada de `migrate:fresh` ni `db:wipe` sobre datos reales.
- No guardar credenciales ni secretos en el repositorio.
- Notas de 0 a 20. Aprueba con 14 o más.
- Código de registro `HGC-{CURSO}-{NN}-{AAA}`: correlativo por curso y año, con el año en 3 dígitos (por ejemplo `HGC-SM-12-024`). El código del curso admite guiones internos (`NUT-DIET`).

## 2. Lo desarrollado

### 2.1 Base y seguridad
- Proyecto Laravel con el starter kit de Vue. Fortify con login, **registro**, recuperación de contraseña, 2FA y confirmación de contraseña.
- En local (`APP_ENV=local`), el primer registro recibe el rol Super Admin. En producción nunca se aplica.
- Comando `php artisan horus:create-admin` para crear el primer administrador.
- Roles y permisos (`Super Admin`, `Coordinador Académico`, `Ponente`) con Policies. El Ponente solo ve y califica sus propias ediciones.

### 2.2 Módulos
| Módulo | Ruta | Resumen |
|---|---|---|
| Panel | `/dashboard` | Tendencias mes contra mes, gráfico mensual, anillo por estado, actividad reciente y ediciones recientes |
| Participantes | `/students` | Alta, edición, búsqueda por DNI o nombre, historial |
| Cursos | `/courses` | Catálogo; el código se bloquea cuando ya hay ediciones |
| Ponentes | `/teachers` | Catálogo de docentes |
| Ediciones | `/editions` | Convocatorias; fechas opcionales (se muestra solo el año si faltan) |
| Matrículas y notas | página de la edición | Correlativo por curso y año, notas masivas, matrícula como ponente |
| Certificados | página de la edición | Emitir, reemitir con historial, anular con motivo, descargar PDF |
| Verificación pública | `/validar/{hash}` | Válido, anulado o no encontrado; sin datos sensibles; límite de 30 consultas por minuto |
| Importar | `/imports` | Plantilla del CRM y libro de cursos histórico; validación en seco y luego todo o nada |
| Reportes | `/reports` | Filtros y exportación a Excel (con columna de rol) |
| Usuarios | `/users` | Rol y vínculo con un ponente; protege al último Super Admin |

### 2.3 Registros históricos
- Migración aditiva `support_historical_records`: fechas de edición opcionales (ambas o ninguna), `enrollments.role` (participante o ponente) y `enrollments.historical`.
- Aprobado sin nota permitido solo para registros históricos o ponentes.
- Importador que reconoce los dos formatos. Se probó con un libro real. **Decisión del usuario: ese Excel no se importa**; se crean cursos, participantes y matrículas nuevos desde el CRM.
- `horus_prueba` contiene hoy los 9 cursos del catálogo, sin participantes ni matrículas.

### 2.4 Certificado PDF
- A4 horizontal, **una sola hoja**, con QR de verificación, código de registro y texto especial para ponentes.
- Tipografía Montserrat (Regular y Bold) en `resources/fonts/montserrat/`, licencia OFL incluida.
- `storage/fonts` está versionada solo por su `.gitignore` (dompdf falla si la carpeta no existe). `CertificateIssuer` también la crea si falta.
- La prueba parte de caché de fuentes vacía y exige ambas variantes de Montserrat. Con una ruta mal formada dompdf cambia de letra sin avisar.

### 2.5 Diseño
- Paleta azul + ámbar claro con modo claro y oscuro. Todo sale de las variables de `resources/css/app.css`.
- Fuente Montserrat en toda la interfaz (`vite.config.ts`).
- Logo del ojo de Horus en menú, login, registro, panel, verificación y pestaña del navegador.
- **Login y registro** con diseño partido: panel azul con el ojo sobre un disco ámbar y formulario a la derecha. Lo comparten recuperación de contraseña y 2FA.
- **Panel:** barra superior fija con buscador de participantes, conmutador de tema y menú de usuario con su rol. Menú lateral con submenú «Catálogo». Gráficos SVG propios, sin librerías.
- Tablas con encabezado azulado y fila resaltada en ámbar al pasar el cursor.
- Se revisaron 11 pantallas a 1280 px y 390 px, sin desbordes.

### 2.6 Correcciones hechas por el camino
- Reportes y edición ya no cuentan al ponente como aprobado.
- La tecla Enter envía el diálogo de motivo (reemitir y anular).
- El certificado ya no se parte en dos hojas.
- Lectura de fechas DD/MM y de DNI con cero inicial en la importación.

## 3. Cómo continuar (puesta en marcha)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # completar DB_PASSWORD
php artisan migrate                                 # solo con autorización, sobre horus_prueba
php artisan db:seed                                 # roles
npm run build
./vendor/bin/pest
```

Registrarse en `/register` en local da el rol Super Admin. Los pasos completos están en el [README](README.md).

## 4. Pendientes

### 4.1 Cierre de lo ya construido
- [ ] **Concurrencia de emisión de certificados:** está cubierta por `FOR UPDATE` y el índice único parcial, pero no hay prueba multi-proceso (sí la hay para el correlativo).
- [ ] **`NavUser.vue`** quedó sin uso desde que el menú de usuario pasó a la barra superior. Borrarlo si ya no se necesita.
- [ ] **Buscador en celular:** la barra superior lo oculta por falta de espacio. Falta darle un botón o una pantalla propia.
- [ ] **Panel del Ponente:** muestra el total de participantes porque tiene `students.view`. Si debe ver solo los suyos, hay que limitar ese indicador.
- [ ] **Pruebas de frontend:** no hay pruebas automáticas de componentes; la interfaz se verificó a mano en el navegador.

### 4.2 Para salir a producción
- [ ] Configurar correo real (`MAIL_MAILER` está en `log`); lo necesita la recuperación de contraseña.
- [ ] Cola de trabajos: `QUEUE_CONNECTION=database`; definir si se usará un worker.
- [ ] Respaldo de la base y del disco privado `storage/app/private/certificates`.
- [ ] Revisar `APP_ENV`, `APP_DEBUG` y que `REGISTER_AS_SUPER_ADMIN` no esté activo. Decidir si se deja el registro abierto.
- [ ] Integración continua (no hay `.github`): correr Pint, tipos, build y Pest en cada cambio.
- [ ] Obligar 2FA al Super Admin (hoy es opcional).

### 4.3 Posibles mejoras (no pedidas aún)
- Registro de auditoría (quién calificó, emitió o anuló).
- Envío del certificado por correo al participante.
- Carga de datos reales: crear participantes y ediciones nuevas desde la plantilla del CRM.

## 5. Estado del repositorio

- Rama principal con 3 commits previos: base del CRM, módulos M0–M9 con importador histórico, y este (diseño, Montserrat, panel, certificados y correcciones).
- Las pruebas no tocan `horus_prueba`: usan `horus_prueba_test`.
- La base temporal `horus_e2e` que se usó para revisar la interfaz en el navegador **ya se borró**, igual que su `.env.e2e`.
