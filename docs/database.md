# Modelo de datos — Horus CRM

Motor: **PostgreSQL 18** (`DB_CONNECTION=pgsql`). Todas las restricciones de integridad viven en la base de datos, así se cumplen sin importar por dónde entren los datos (UI, importación Excel, tinker).

## Diagrama relacional

```
users ── model_has_roles ── roles          (Spatie, solo personal interno)

courses 1──* course_editions *──1 teachers (teacher_id nullable)
   │                │
   │                1
   │                │
   1                *
registration_sequences   enrollments *──1 students
 (course_id, year)          │
                            1
                            │
                            *
                       certificates  (uno vigente + historial)
```

No existe relación entre `users` y `students`: los participantes no tienen cuenta.

## Tablas

| Tabla | Propósito | Claves y restricciones |
|---|---|---|
| `students` | Participantes | `dni varchar(15)` UNIQUE, CHECK no vacío; email y teléfono opcionales |
| `courses` | Catálogo | `code varchar(10)` UNIQUE, CHECK `^[A-Z0-9]+$` |
| `teachers` | Ponentes | `dni varchar(15)` UNIQUE, CHECK no vacío |
| `course_editions` | Convocatorias | FK `course_id`, FK `teacher_id` (nullable); CHECK `end_date >= start_date`; CHECK `year BETWEEN 2000 AND 2100` |
| `registration_sequences` | Contador del correlativo | UNIQUE `(course_id, year)`; CHECK `last_number >= 0` |
| `enrollments` | Matrículas y notas | UNIQUE `registration_code`; UNIQUE `(student_id, course_edition_id)`; CHECK nota 0–20; CHECK estado ∈ {cursando, aprobado, desaprobado}; CHECK `(grade IS NULL) = (status = 'cursando')` |
| `certificates` | Emisiones | UNIQUE `unique_hash`; índice único parcial `(enrollment_id) WHERE revoked_at IS NULL`; CHECK `revoked_at >= issued_at` |

Tipos: PK `bigint` (`bigserial`), DNI y códigos en `varchar` (conservan ceros a la izquierda), notas en `numeric(4,2)`, fechas académicas en `date`, sellos de tiempo en `timestamp(0)`.

## Estrategia de eliminación

Todas las FK usan **`ON DELETE RESTRICT`**. No se puede borrar un curso, ponente, edición, participante o matrícula que tenga dependientes: el historial académico y los certificados emitidos quedan protegidos. Para dar de baja algo se usarán anulaciones explícitas (por ejemplo `certificates.revoked_at`), no borrados en cascada.

## Índices y su justificación

PostgreSQL **no** indexa automáticamente las columnas FK; cada índice responde a una consulta concreta:

| Índice | Consulta que optimiza |
|---|---|
| `students(dni)` UNIQUE | Búsqueda de un participante por DNI e importación sin duplicados |
| `students(last_name, first_name)` | Listados ordenados y búsqueda por apellidos |
| `teachers(dni)` UNIQUE, `courses(code)` UNIQUE | Búsqueda exacta y unicidad |
| `course_editions(course_id)` | Ediciones de un curso y comprobación de RESTRICT |
| `course_editions(teacher_id)` | Ediciones de un ponente y comprobación de RESTRICT |
| `course_editions(year)` | Reportes por año académico |
| `registration_sequences(course_id, year)` UNIQUE | Bloqueo de la fila contador (`FOR UPDATE`) |
| `enrollments(registration_code)` UNIQUE | Búsqueda por código de registro |
| `enrollments(student_id, course_edition_id)` UNIQUE | Evita duplicados; su primera columna cubre el historial de un participante |
| `enrollments(course_edition_id)` | Lista de matriculados por edición |
| `enrollments(status)` | Reportes por estado |
| `certificates(unique_hash)` UNIQUE | Verificación pública `/validar/{hash}` |
| `certificates(enrollment_id)` | Historial de certificados de una matrícula |
| `certificates_one_active_per_enrollment` (parcial) | Garantiza un solo certificado vigente |

## Reglas de negocio implementadas

### Evaluación (`App\Enums\EnrollmentStatus::fromGrade`)
- Sin nota → `cursando`; nota ≥ `config('academic.passing_grade')` (14) → `aprobado`; si no, `desaprobado`.
- `Enrollment::$status` no es asignable masivamente: el evento `saving` lo recalcula siempre a partir de la nota. Lo que envíe el frontend se ignora.
- El umbral 14 **no** está en un CHECK de la base, para poder cambiarlo sin migración. La base solo exige coherencia entre nota nula y estado `cursando`, y que la nota esté entre 0 y 20.

### Código de registro (`App\Services\RegistrationCodeGenerator`)
- Formato `HGC-{CURSO}-{NN}-{AAA}`, por ejemplo `HGC-PA-01-024`. NN tiene al menos 2 dígitos (crece a `100` cuando hace falta) y AAA es el año con 3 dígitos (`2024 % 1000`).
- **Alcance del correlativo: por curso y año** (confirmado). Todas las ediciones de PA de 2024 comparten la misma secuencia.
- Concurrencia: `INSERT … ON CONFLICT DO NOTHING` crea la fila contador sin carreras, `SELECT … FOR UPDATE` la bloquea hasta el COMMIT, y el UNIQUE de `registration_code` es la última barrera. Nunca se usa `MAX()+1`.
- `App\Actions\Enrollments\EnrollStudent` asigna el código y crea la matrícula en una sola transacción. Si algo falla, el número se revierte y no quedan huecos.

### Certificados (reemisión con historial)
- Una matrícula puede tener varios certificados, pero **solo uno vigente** (`revoked_at IS NULL`), garantizado por un índice único parcial.
- Reemitir consiste en marcar el certificado vigente con `revoked_at` y un motivo, y crear uno nuevo. `Enrollment::activeCertificate()` devuelve el vigente y `certificates()` el historial completo.
- `unique_hash` son 64 caracteres hexadecimales de `random_bytes(32)`. Se genera en el modelo y no se puede asignar masivamente. Nunca se expone un ID secuencial.
- La regla "solo se emite si la matrícula está aprobada" se aplicará en el servicio de emisión (módulo Certificados), con prueba propia.

## Roles

`Database\Seeders\RoleSeeder` crea (de forma idempotente) los roles Super Admin, Coordinador Académico y Ponente. No hay rol de estudiante. `DatabaseSeeder` no crea usuarios con contraseñas predeterminadas.

## Pruebas

- Pest 5. `tests/Feature` usa `RefreshDatabase` contra **`horus_prueba_test`**, configurada en `phpunit.xml` con `force="true"`.
- `Tests\TestCase::setUpTraits()` aborta, antes de que `RefreshDatabase`, `DatabaseMigrations` o `DatabaseTruncation` actúen, si el driver no es `pgsql` o si la base no termina en `_test`. Así nunca se borra `horus_prueba`. La prueba `tests/Unit/TestingDatabaseGuardTest.php` lo verifica. La guarda no puede ir en `beforeRefreshingDatabase()`, porque el método del trait tiene prioridad y la anularía.
- Última ejecución completa: **106 pruebas, 106 exitosas, 260 aserciones, 0 fallos, 0 omitidas** (PostgreSQL 18, `horus_prueba_test`). Incluye dominio, autenticación y ajustes del starter kit, el comando de administrador y la concurrencia.
- No se usa SQLite para validar restricciones de PostgreSQL.

## Concurrencia real (suite `Concurrency`)

`tests/Concurrency/RegistrationCodeConcurrencyTest.php` lanza procesos PHP independientes (`tests/Concurrency/enroll-worker.php`), cada uno con su propia conexión a PostgreSQL. Usa `DatabaseTruncation` en lugar de `RefreshDatabase`, porque los datos tienen que estar confirmados para que los otros procesos los vean.

| Escenario | Qué demuestra |
|---|---|
| 20 procesos arrancan en el mismo microsegundo, sin fila contador previa | `ON CONFLICT DO NOTHING` + `FOR UPDATE`: 20 matrículas, códigos `HGC-PA-01-024` a `HGC-PA-20-024` sin repetir, `last_number = 20` |
| La prueba retiene el bloqueo de la fila y lanza 10 procesos | Durante 4 s los 10 siguen esperando y no hay matrículas confirmadas. Al liberar el bloqueo, salen 10 códigos consecutivos |
| Con la fila de PA bloqueada, se matricula en INY | El bloqueo es por curso y año: otro curso no espera |

**Contraprueba:** al quitar temporalmente `lockForUpdate()` del generador, la suite falla. Varios procesos obtienen `HGC-PA-02-024` y PostgreSQL los rechaza con `enrollments_registration_code_unique`. Esto confirma que la prueba detecta la condición de carrera. Después se restauró el código.

Resultado: 3 ejecuciones consecutivas, 3 de 3 pruebas exitosas en cada una.

## Pendientes

- Servicio de emisión de certificados (validar aprobación, PDF, QR).
