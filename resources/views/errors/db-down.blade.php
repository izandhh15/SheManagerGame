<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reintentando… — SheManager</title>
    <meta http-equiv="refresh" content="8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .card { max-width: 420px; }
        .ball { font-size: 4rem; margin-bottom: 1rem; animation: bounce 1.2s infinite; }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-18px); }
        }
        h1 { font-size: 1.5rem; margin-bottom: .75rem; }
        p { color: #a0aec0; line-height: 1.6; margin-bottom: 1.5rem; }
        .btn {
            display: inline-block;
            background: #7c3aed;
            color: #fff;
            padding: .75rem 2rem;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { background: #6d28d9; }
        .note { margin-top: 1rem; font-size: .8rem; color: #718096; }
    </style>
</head>
<body>
    <div class="card">
        <div class="ball">⚽</div>
        <h1>Uy, se nos ha ido la pelota…</h1>
        <p>La base de datos no responde ahora mismo. Estamos reintentando automáticamente: esta página se recarga sola en unos segundos.</p>
        <a class="btn" href="javascript:location.reload()">Reintentar ahora</a>
        <p class="note">Si sigue sin ir, espera un minutillo y vuelve a probar. Tus partidas están a salvo.</p>
    </div>
</body>
</html>
