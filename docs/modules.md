# Módulos del CRM — Horus Group SRL

Todo el sistema es interno: solo el personal autenticado accede. La única página pública es la verificación de certificados.

## Roles y permisos

Los permisos están definidos en `App\Enums\Permission` y los siembra `RoleSeeder` de forma idempotente.

| Permiso | Super Admin | Coordinador Académico | Ponente |
|---|:-:|:-:|:-:|
| `users.manage` | ✅ | — | — |
| `students.view` / `students.manage` | ✅ | ✅ / ✅ | ✅ / — |
| `courses.manage`, `teachers.manage` | ✅ | ✅ | — |
| `editions.view` / `editions.manage` | ✅ | ✅ / ✅ | solo sus ediciones / — |
| `enrollments.manage` | ✅ | ✅ | — |
| `grades.manage` | ✅ | ✅ | solo sus ediciones |
| `certificates.view` / `certificates.issue` | ✅ | ✅ / ✅ | descarga de sus ediciones / — |
| `imports.run`, `reports.view` | ✅ | ✅ | — |

- El **Super Admin** pasa todas las comprobaciones gracias a `Gate::before`.
- El **Ponente** se vincula a un registro de `teachers` mediante `teachers.user_id`. Las Policies (`CourseEditionPolicy`, `CertificatePolicy`) y el scope `CourseEdition::visibleTo()` lo limitan a las ediciones que dicta.
- El frontend recibe `auth.permissions` solo para ocultar menús y botones. La autorización real siempre ocurre en el backend.

## Módulos

| Módulo | Ruta | Qué hace |
|---|---|---|
| Panel | `/dashboard` | Indicadores (participantes, ediciones del año, matrículas por estado, certificados vigentes) y ediciones recientes, según el alcance del usuario |
| Participantes | `/students` | Alta, edición, búsqueda (DNI, nombres o nombre completo con `ILIKE`), historial académico. Solo se eliminan si no tienen matrículas |
| Cursos | `/courses` | Catálogo. El código **queda bloqueado** cuando el curso tiene ediciones, porque forma parte de los códigos de registro |
| Ponentes | `/teachers` | Catálogo de docentes. Su cuenta de acceso se vincula en Usuarios |
| Ediciones | `/editions` | Convocatorias con filtros por curso, año y ponente. El curso y el año quedan bloqueados cuando hay matrículas |
| Matrículas y notas | página de la edición | Búsqueda del participante y matrícula con código correlativo (`EnrollStudent`). Registro masivo de notas en una sola transacción; el estado lo deriva el backend |
| Certificados | página de la edición | Emitir (idempotente, solo aprobados), reemitir (anula el anterior y conserva el historial), anular con motivo, descargar el PDF |
| Verificación pública | `/validar/{hash}` | Muestra el estado válido, anulado o no encontrado (404), con datos mínimos. Sin DNI, correo ni teléfono. Límite de 30 consultas por minuto por IP y `noindex` |
| Importar | `/imports` | El libro de cursos histórico tal como se usaba (una hoja por curso) o la plantilla del CRM. Validación en seco, después importación de todo o nada. Idempotente |
| Reportes | `/reports` | Matrículas, notas y certificados con filtros y exportación a Excel con los mismos filtros |
| Usuarios | `/users` | Asignación de rol y vínculo con un ponente. Nadie puede quitarse su propio rol de Super Admin ni dejar el sistema sin Super Admin |

## Certificados (PDF y QR)

- `App\Services\CertificateIssuer` bloquea la fila de la matrícula (`FOR UPDATE`) y verifica que esté aprobada. El índice único parcial garantiza un solo certificado vigente por matrícula.
- El PDF se genera con **barryvdh/laravel-dompdf** a partir de `resources/views/pdf/certificate.blade.php` (A4 horizontal).
- Se guarda en el disco **privado** `local` (`storage/app/private/certificates/{hash}.pdf`) y solo se descarga a través de la Policy. Si falta el archivo, se regenera.
- El QR apunta a `/validar/{hash}` y se genera en SVG con **bacon/bacon-qr-code v3**.

> **Desviación del stack documentada:** el prompt maestro indicaba `simplesoftwareio/simple-qrcode`. Esa librería no publica versiones desde 2021 y exige `bacon/bacon-qr-code` ^2, mientras que Fortify (la 2FA del starter kit) requiere ^3. No pueden convivir. Se usa directamente bacon v3, que es la librería que simple-qrcode envolvía, y queda declarada como dependencia explícita.

## Importación histórica

El importador detecta el formato del archivo: si la primera hoja tiene las columnas de la plantilla, usa la plantilla; si no, lo lee como libro de cursos.

### Libro de cursos (formato real, p. ej. «CURSOS 2024.xlsx»)

Lo lee `App\Services\Import\LegacyWorkbookReader`.

- **Hojas:** cada hoja con los encabezados `APELLIDOS Y NOMBRES` y `DNI` es un curso. Las demás (el menú `INICIO`) se ignoran. Cada columna se toma de su **primera** aparición de izquierda a derecha, así el panel `// CONSULTA //` de la derecha no interfiere. `NOTA` y `CELULAR` son opcionales.
- **Curso y año:** se deducen de los códigos de registro de la hoja (el más frecuente). Por ejemplo, `HGC-NUT-DIET-01-2024` da `NUT-DIET` y 2024. Se aceptan los formatos heredados: año de 3 o 4 dígitos y NN de 2 o 3 dígitos. Si un código no corresponde al curso o al año de su hoja, es un error.
- **Cursos nuevos:** si el curso no existe, se crea con el nombre que figura en la fila de título. La previsualización los lista como «se crearán».
- **Ediciones:** una por curso y año, **sin fechas**. Si faltan las fechas, el certificado y la verificación muestran «en el año 2024».
- **Notas:** si hay nota, el estado se deriva de ella. Sin nota, la matrícula queda **aprobada** y marcada `historical = true`.
- **Ponentes:** las filas que siguen a `PONENTE` o `PONENTES` se importan como matrícula con `role = ponente`, conservando su código y con derecho a certificado. Además se crean o vinculan en `teachers` por DNI, y se asignan como ponente de la edición si esta aún no tenía uno. Las filas de ponente vacías se omiten.
- **Nombres:** las dos primeras palabras de `APELLIDOS Y NOMBRES` son los apellidos, y las partículas como `De la` se unen a la palabra siguiente. El resto son los nombres. La previsualización muestra cómo quedó separado cada nombre.
- **DNI:** si tiene 7 dígitos (Excel quitó el cero inicial), se completa con un 0 y la fila lleva un **aviso**, que no bloquea la importación. Si falta el DNI, es un error.
- **Errores por fila** con el formato «Hoja X, fila N». Un código repetido indica en qué otras filas aparece.

### Plantilla del CRM

Columnas: `dni, nombres, apellidos, email, telefono, codigo_curso, fecha_inicio, fecha_fin, anio, codigo_registro, nota`. En este formato el curso debe existir, las fechas son obligatorias y, sin nota, la matrícula queda en estado cursando.

### Reglas comunes

- **Lectura:** todas las celdas se leen como texto (`StringValueBinder`). Así se conservan los ceros iniciales del DNI y una fecha `05/08/2024` se interpreta siempre como **5 de agosto** (DD/MM), nunca con el formato estadounidense. Las fechas reales de Excel se convierten desde el número de serie.
- **Errores:** cualquier error en una fila bloquea toda la importación, y se muestra la lista de errores por fila.
- **Datos existentes:** los datos actuales del participante no se sobrescriben; solo se completan los campos vacíos.
- **Códigos históricos:** se respetan tal cual si no pertenecen a otra matrícula ni están repetidos en el archivo. `registration_sequences.last_number` se ajusta al mayor número importado para que los códigos generados después no colisionen. Los códigos nuevos usan el formato actual, `HGC-SM-12-024`.

## Ponentes con matrícula

En la pantalla de una edición se puede **matricular como ponente**. El ponente recibe código y certificado (con el texto «por su participación como ponente»), no se califica y figura como aprobado. Los indicadores del panel cuentan solo a los participantes.

## Diseño y fuentes

- **Paleta:** azul (color principal, menú lateral azul marino) y ámbar claro (acento). Todo sale de las variables de [resources/css/app.css](../resources/css/app.css); para retocar la marca basta cambiarlas ahí. Hay modo claro y oscuro (botón sol/luna en la barra superior).
- **Fuente:** Montserrat, declarada en `vite.config.ts` (la descarga el build y la aloja en `public/build`).
- **Logo:** `public/images/horus-logo.png` (menú, login, registro, panel, verificación pública y pestaña del navegador).
- **Panel:** tendencias del mes frente al anterior, gráfico mensual del año, anillo de matrículas por estado, actividad reciente y tabla de ediciones. Los gráficos son SVG propios (sin librerías). Todo respeta el alcance del usuario.
- **Certificado PDF:** usa Montserrat Regular y Bold desde `resources/fonts/montserrat/` (licencia OFL incluida). dompdf las registra en `storage/fonts`, carpeta versionada solo por su `.gitignore`. La URL de la fuente en la plantilla es `file://` + ruta absoluta; con una barra de más dompdf no la encuentra y cambia de letra sin avisar (la prueba de certificados lo vigila).

## Idioma

La interfaz está en español: las páginas del kit están traducidas y los módulos se escribieron en español. Los mensajes de validación vienen de `laravel-lang` (`lang/es`), con atributos propios en cada Form Request.
