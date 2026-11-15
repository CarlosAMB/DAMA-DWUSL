# EasyLife · Guía rápida para el equipo

## Qué hay en esta carpeta

| Archivo | Para qué sirve |
|---|---|
| `EasyLife.apk` | La app para instalar en un teléfono Android |
| `EasyLife_Documento_Proyecto.docx` / `.pdf` | Documento del proyecto (Word y PDF) |
| `EasyLife_Guion_Video.docx` / `.pdf` | Guion del video: qué dice y qué muestra cada uno |
| `EasyLife_Presentacion.pptx` | Diapositivas del video (con notas del presentador) |
| `capturas/`, `diagramas/` | Imágenes usadas en el documento y los slides |

## 1. Probar la app (2 minutos, no hace falta instalar nada en la PC)

1. Pasar `EasyLife.apk` al teléfono (WhatsApp, Drive o cable) y abrirlo.
2. Si Android lo pide, permitir **"Instalar apps de fuentes desconocidas"**.
3. Abrir EasyLife y entrar con la cuenta de prueba, o crear una cuenta propia con **Regístrate**:
   - Correo: `demo@easylife.test`
   - Contraseña: `Demo123*`
4. Aceptar el permiso de notificaciones.

> La primera vez puede tardar hasta **1 minuto**: el servidor gratuito de Render se duerme tras 15 minutos sin uso.
> Si sale "El servidor está iniciando", esperar un momento y volver a intentar.

## 2. Ver el panel administrativo

- Abrir https://dama-dwusl.onrender.com/admin
- Correo: `admin@easylife.test` · Contraseña: `Admin123*`

## 3. Grabar el video

1. Leer **su parte** del guion (`EasyLife_Guion_Video.pdf`). El reparto está en la primera página.
2. Practicar la demostración en el teléfono con la cuenta demo.
3. Abrir el código que indica su escena (la tabla "Archivos de código que se muestran" está al final del guion).
4. Las partes de Render, Firebase y Google Cloud las graba **Carlos Alexander**: es el único con acceso.

## 4. (Opcional) Ejecutar el proyecto en la computadora

Solo si quieren ver el código funcionando localmente. Necesitan XAMPP (MySQL), PHP 8.2+, Composer, Node.js 20+ y Android Studio.

```bash
# Servidor (después de crear la base "easylife" en phpMyAdmin)
cd EasyLifeWeb
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

# App (en otra terminal, con el emulador abierto)
cd EasyLifeMovil
npm install
npx expo start --android
```

Sin las claves de Firebase y Google, la app local funciona igual (inicio de sesión directo con Laravel), pero sin tiempo real, push ni Google. Los detalles están en el `README.md` de la raíz.

> **Importante para quien comparte la carpeta:** `EasyLifeWeb/.env`, `EasyLifeMovil/.env`,
> `EasyLifeWeb/storage/app/*.json` y el `client_secret_*.json` contienen claves privadas.
> Compártanlos solo dentro del equipo y nunca los suban a GitHub ni al documento.
