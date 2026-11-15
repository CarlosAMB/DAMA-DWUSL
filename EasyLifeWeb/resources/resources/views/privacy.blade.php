<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Política de privacidad · EasyLife</title>
    @vite(['resources/sass/app.scss'])
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 820px">
    <h1 class="fw-bold mb-1">Política de privacidad</h1>
    <p class="text-secondary">EasyLife · Última actualización: {{ now()->format('d/m/Y') }}</p>

    <div class="card"><div class="card-body p-4">
        <h2 class="h5 fw-bold">1. Datos que recopilamos</h2>
        <p>Nombre, correo electrónico, teléfono (opcional), preferencias de la app y la información que tú registras: eventos, tareas, proyectos, etiquetas y recordatorios.</p>

        <h2 class="h5 fw-bold">2. Para qué los usamos</h2>
        <p>Únicamente para brindarte el servicio de organización personal: sincronizar tus datos entre dispositivos, mostrarte recordatorios y, si lo autorizas, sincronizar con Google Calendar. No vendemos ni compartimos tus datos con terceros.</p>

        <h2 class="h5 fw-bold">3. Cómo los protegemos</h2>
        <ul>
            <li>Las contraseñas se almacenan con hash <strong>bcrypt</strong> (nadie puede leerlas).</li>
            <li>Las descripciones de eventos, tareas y proyectos, y los tokens de Google se guardan <strong>encriptados (AES-256)</strong>.</li>
            <li>En el teléfono, la sesión se guarda en el almacenamiento seguro del sistema (Keystore / Keychain).</li>
            <li>Los administradores solo ven estadísticas agregadas, nunca el contenido de tus tareas o eventos.</li>
        </ul>

        <h2 class="h5 fw-bold">4. Tus derechos</h2>
        <ul>
            <li><strong>Acceso y portabilidad:</strong> desde la app puedes descargar todos tus datos (JSON, ICS o CSV).</li>
            <li><strong>Rectificación:</strong> puedes editar tu perfil en cualquier momento.</li>
            <li><strong>Supresión:</strong> puedes eliminar tu cuenta; se borran todos tus datos de forma permanente.</li>
            <li><strong>Revocar el consentimiento</strong> de Google Calendar desconectando la integración.</li>
        </ul>

        <h2 class="h5 fw-bold">5. Normativa</h2>
        <p class="mb-0">Esta política se basa en los principios de la Ley para la Protección de Datos Personales de El Salvador y en buenas prácticas internacionales (RGPD): consentimiento, minimización de datos, seguridad y derechos ARCO.</p>
    </div></div>
</div>
</body>
</html>
