<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>EasyLife · Google</title>
    @isset($returnUrl)
        <meta http-equiv="refresh" content="0;url={{ $returnUrl }}">
    @endisset
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f6fb; color: #1f2937; display: flex; min-height: 100vh;
               align-items: center; justify-content: center; margin: 0; padding: 16px; }
        .card { background: #fff; border-radius: 16px; padding: 32px 24px; max-width: 380px; text-align: center;
                box-shadow: 0 6px 24px rgba(0,0,0,.08); }
        .icon { font-size: 48px; }
        a.btn { display: inline-block; margin-top: 16px; background: #4F46E5; color: #fff; padding: 12px 20px;
                border-radius: 10px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <div class="icon">{{ $ok ? '✅' : '⚠️' }}</div>
    <h2>EasyLife</h2>
    <p>{{ $message }}</p>
    @isset($returnUrl)
        <a class="btn" href="{{ $returnUrl }}">Volver a EasyLife</a>
    @endisset
</div>
@isset($returnUrl)
    <script>window.location.replace(@json($returnUrl));</script>
@endisset
</body>
</html>
