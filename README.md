# EasyLife — Aplicación para la Organización Personal

Proyecto integrador de **DAMA** (React Native) y **Desarrollo Web con Software Libre** (Laravel).

| Carpeta | Tecnología | Enfoque |
|---|---|---|
| `EasyLifeMovil/` | React Native + Expo SDK 58 | App del **usuario final** (funciona sin conexión) |
| `EasyLifeWeb/` | Laravel 12 + MySQL (XAMPP) | **API REST** + **panel administrativo** |
| `MiPrimeraApp/`, `SistemaClinicaPediatrica/` | — | Proyectos base de referencia (sin modificar) |

```
┌──────────────────────────┐   HTTP/JSON + token Sanctum   ┌────────────────────────────┐
│  EasyLifeMovil (Expo)    │ ────────────────────────────▶ │  EasyLifeWeb (Laravel 12)  │
│  SQLite local (offline)  │   POST /api/v1/sync           │  ├─ API REST /api/v1/*     │
│  Notificaciones locales  │                               │  └─ Panel admin /admin     │
└──────────────────────────┘                               └─────────────┬──────────────┘
                                                                         │
                                                              MySQL (XAMPP) — BD "easylife"
```

---

## 1. Requisitos

- XAMPP con **MySQL** encendido
- PHP 8.2+ y Composer
- Node.js 20+
- Android Studio con un emulador (o un celular con Expo Go)

## 2. Backend y panel web (`EasyLifeWeb`)

```bash
cd EasyLifeWeb
composer install
npm install
cp .env.example .env        # solo la primera vez
php artisan key:generate    # solo la primera vez
```

Crea la base de datos `easylife` en phpMyAdmin (utf8mb4_unicode_ci) y luego:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

- Panel administrativo: http://localhost:8000/admin
- API: http://localhost:8000/api/v1

**Usuarios de prueba** (creados por `database/seeders/UserSeeder.php`):

| Rol | Correo | Contraseña |
|---|---|---|
| Administrador (panel web) | admin@easylife.test | Admin123* |
| Usuario (app móvil) | demo@easylife.test | Demo123* |

Pruebas automatizadas (usan SQLite en memoria, no tocan tu BD):

```bash
php artisan test
```

## 3. App móvil (`EasyLifeMovil`)

```bash
cd EasyLifeMovil
npm install
npx expo start --android
```

- En el **emulador Android** la app se conecta a `http://10.0.2.2:8000` (el localhost de tu PC). No hay que cambiar nada.
- En un **celular físico**: cambia `HOST` en `src/config.js` por la IP de tu PC (ej. `http://192.168.1.10:8000`) y levanta Laravel con `php artisan serve --host=0.0.0.0`.

---

## 4. Funcionalidades

### App móvil (usuario final)
- **Autenticación**: registro con aceptación de la política de privacidad, inicio de sesión, token en `expo-secure-store`.
- **Calendario**: vistas **día, semana, mes y año**; eventos con tipos (reunión, cita, cumpleaños…), ubicación, todo el día.
- **Tareas**: prioridades, fechas límite, estados, **subtareas**, **etiquetas**, filtros y búsqueda; asignación de tareas a eventos.
- **Proyectos**: agrupan tareas, con **progreso automático**.
- **Recordatorios**: varios por evento/tarea, más un **resumen diario** automático (notificaciones locales).
- **Sin conexión**: todo se guarda en SQLite y se sincroniza al volver el internet.
- **Integraciones**: exportar/importar **.ics** (Google Calendar, Outlook, Apple), **.csv** (Excel/Sheets/Todoist) y **.json** (respaldo).
- **Perfil**: datos personales, preferencias (vista del calendario, recordatorio por defecto, resumen diario, modo oscuro), cambio de contraseña, descargar mis datos, eliminar cuenta.

### Panel web (administración)
- **Dashboard** con indicadores y gráficos (Chart.js).
- **Usuarios**: búsqueda, filtros, crear, editar rol, activar/desactivar (cierra sus sesiones), eliminar.
- **Tipos de evento**: catálogo con color e ícono que usa la app.
- **Avisos**: mensajes con vigencia que aparecen en el inicio de la app.
- **Reportes** por rango de fechas con exportación a CSV.
- El administrador solo ve **datos agregados**, nunca el contenido de las tareas o eventos.

---

## 5. Sincronización sin conexión (cómo funciona)

1. Cada registro del usuario tiene un **UUID generado en el teléfono**, así se puede crear sin internet.
2. Al guardar algo, la fila local queda con `dirty = 1` y `client_updated_at = ahora`.
3. Cuando hay conexión, la app envía los cambios a `POST /api/v1/sync`:
   ```json
   { "last_synced_at": "2026-11-14T15:00:00Z",
     "changes": { "projects": [], "tags": [], "events": [], "tasks": [], "subtasks": [], "reminders": [] } }
   ```
4. El servidor aplica cada cambio (si hay conflicto **gana el más reciente**) y responde con todo lo que cambió desde `last_synced_at`, incluidas las eliminaciones (borrado lógico).
5. La app marca lo enviado como sincronizado y guarda lo recibido.

La sincronización se dispara al abrir la app, al recuperar internet, 2 segundos después de cada cambio, cada 5 minutos y al deslizar hacia abajo en Inicio.

## 6. Seguridad y privacidad

| Medida | Dónde |
|---|---|
| Contraseñas con bcrypt | `User` (cast `hashed`) |
| Descripciones y tokens de Google cifrados (AES-256) | casts `encrypted` en los modelos |
| Token de sesión en Keystore/Keychain | `expo-secure-store` |
| Tokens Sanctum con expiración (30 días) y revocables | `AuthController` |
| Límite de intentos de login (5/min) | `RateLimiter` en `AppServiceProvider` |
| Cada usuario solo accede a sus datos | `ResourceController::findOwned`, `SyncService` |
| Usuarios desactivados bloqueados | middleware `active` |
| Consentimiento, acceso (exportar) y supresión (eliminar cuenta) | `ProfileController`, `/privacidad` |

> Nota: la base SQLite del teléfono no está cifrada en Expo Go. En una compilación nativa se puede activar SQLCipher (`useSQLCipher` en el plugin de `expo-sqlite`).

## 7. Endpoints principales de la API

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/auth/register`, `/auth/login` | Registro / inicio de sesión (devuelve token) |
| POST | `/auth/logout` · GET `/auth/me` | Cerrar sesión / usuario actual |
| PUT | `/profile`, `/profile/password` | Datos y preferencias / contraseña |
| GET · DELETE | `/profile/export`, `/profile` | Descargar mis datos / eliminar cuenta |
| CRUD | `/events`, `/tasks`, `/subtasks`, `/projects`, `/tags`, `/reminders` | Recursos del usuario |
| PATCH | `/tasks/{id}/complete` | Completar / reabrir tarea |
| GET | `/calendar?from=&to=` | Eventos y tareas de un rango |
| POST | `/sync` | Sincronización offline |
| GET · POST | `/export/{ics,csv,json}`, `/import` | Integración con otras apps |
| GET | `/event-types`, `/announcements` | Catálogos del panel |
| GET · POST · DELETE | `/google/auth-url`, `/google/sync`, `/google` | Conectar, sincronizar y desconectar Google |

## 8. Sincronización con Google Calendar y Google Tasks

- **Eventos ↔ Google Calendar** (calendario principal) y **tareas ↔ Google Tasks** (lista por defecto), en ambos sentidos.
- El flujo OAuth 2.0 lo hace **Laravel** (`GoogleClient`): la app pide `/google/auth-url`, abre la URL con `expo-web-browser`, Google regresa a `/google/callback` y el servidor guarda los tokens **cifrados** y redirige de vuelta a la app.
- Cada `POST /sync` también sincroniza con Google (si hubo cambios locales o pasaron 2 min). Lo que llega de Google viaja al teléfono en esa misma respuesta. Conflictos: gana el cambio más reciente.

**Configuración:**

1. En Google Cloud Console: habilitar *Google Calendar API* y *Google Tasks API*, configurar la pantalla de consentimiento (External, modo prueba, agregar usuarios de prueba) y crear un cliente OAuth *Aplicación web* con la URI de redirección `http://localhost:8000/google/callback`.
2. En `EasyLifeWeb/.env`:
   ```env
   GOOGLE_CLIENT_ID=...apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_REDIRECT_URI=http://localhost:8000/google/callback
   ```
   y luego `php artisan config:clear`.
3. Con el emulador encendido: `adb reverse tcp:8000 tcp:8000` (Google solo acepta `localhost`, no `10.0.2.2`).
4. En la app: **Perfil → Integraciones → Conectar con Google**.

> En modo prueba Google muestra "Google no ha verificado esta app" (pulsar *Continuar*) y el acceso vence a los 7 días; si pasa, la app pide reconectar.

## 9. Firebase (Authentication, Firestore en tiempo real y notificaciones push)

Modelo **híbrido**: Laravel sigue siendo la API principal y el panel administrativo; Firebase aporta:

| Servicio | Uso en EasyLife |
|---|---|
| **Firebase Authentication** | Registro, inicio de sesión y recuperación de contraseña (Firebase guarda la contraseña cifrada). La app envía el ID token a `POST /api/v1/auth/firebase`; Laravel verifica la firma RS256 con las llaves públicas de Google y entrega su token Sanctum. |
| **Cloud Firestore** | Espejo en tiempo real de los datos del usuario en `users/{uid}/{tabla}/{id}`. Laravel escribe cada cambio; los teléfonos escuchan con `onSnapshot` y lo ven al instante. Título, nombre, descripción y ubicación viajan **cifrados (AES-256 + HMAC)** con una llave propia de cada usuario. Reglas en `firebase/firestore.rules` (solo lectura del propio usuario). |
| **Cloud Messaging (FCM)** | Push desde el panel: al publicar un aviso, marcar *Enviar también como notificación push*. Requiere el APK (en Expo Go no hay push). |
| **Cifrado local** | La descripción y la ubicación se guardan cifradas en SQLite con la misma llave, que vive en SecureStore (Keystore). |

Las cuentas creadas antes de Firebase (ej. `demo@easylife.test`) se vinculan solas al iniciar sesión con su contraseña.

**Configuración:**

1. En https://console.firebase.google.com → *Agregar proyecto* → elegir el proyecto de Google Cloud que ya usas para Calendar.
2. *Authentication* → *Comenzar* → habilitar **Correo electrónico/contraseña**.
3. *Firestore Database* → *Crear base de datos* (modo producción) → pestaña *Reglas* → pegar `firebase/firestore.rules` → *Publicar*.
4. *Configuración del proyecto* → *Tus apps* → **App web** (`</>`) → copiar los valores a `EasyLifeMovil/.env` (ver `.env.example`).
5. *Configuración del proyecto* → *Cuentas de servicio* → *Generar nueva clave privada* → guardar el JSON como `EasyLifeWeb/storage/app/firebase-credentials.json` y en `EasyLifeWeb/.env`:
   ```env
   FIREBASE_PROJECT_ID=tu-proyecto
   FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
   ```
   Luego `php artisan config:clear` y, si ya había usuarios vinculados, `php artisan firebase:mirror`.
6. Para el APK (push): *Tus apps* → **App Android** con el paquete de la app → descargar `google-services.json` a `EasyLifeMovil/`.
