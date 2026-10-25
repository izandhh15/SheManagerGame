# Entrar con Google / Discord (sin registrarse con contraseña)

El código ya está en el juego (0.5.19+) y la tabla `social_accounts`
ya existe en producción. Los botones solo aparecen en el login y en
el registro cuando el proveedor está configurado. Falta solo crear
las dos aplicaciones OAuth (son cuentas de Izan, no se pueden crear
desde aquí) y poner los secretos en Wasmer.

## 1. Google

1. En [Google Cloud Console](https://console.cloud.google.com/),
   crea un proyecto (o usa uno existente) y una credencial
   **ID de cliente de OAuth 2.0** de tipo **Aplicación web**.
2. Orígenes autorizados de JavaScript: `https://shemanager.wasmer.app`
3. URI de redirección autorizada:
   `https://shemanager.wasmer.app/auth/google/callback`
4. Apunta el **ID de cliente** y el **secreto de cliente**.

## 2. Discord

1. En el [Portal de desarrolladores de Discord](https://discord.com/developers/applications),
   crea una aplicación nueva.
2. En **OAuth2 → Redirects**, añade:
   `https://shemanager.wasmer.app/auth/discord/callback`
3. Apunta el **Client ID** y el **Client Secret** (OAuth2 → General).

## 3. Secretos en Wasmer

```bash
wasmer app secret set GOOGLE_CLIENT_ID <id> -a izandhh15/shemanager
wasmer app secret set GOOGLE_CLIENT_SECRET <secreto> -a izandhh15/shemanager
wasmer app secret set DISCORD_CLIENT_ID <id> -a izandhh15/shemanager
wasmer app secret set DISCORD_CLIENT_SECRET <secreto> -a izandhh15/shemanager
```

Tras ponerlos, redespliega (o espera al siguiente deploy): los
botones "Continuar con Google" y "Continuar con Discord" aparecerán
solos en `/login` y en `/register`.

## Cómo funciona

- Primera vez con Google/Discord: se crea la cuenta automáticamente
  (nombre, correo verificado y nombre de usuario único para los
  amigos) y se entra directamente. Nada de formulario ni contraseña.
- Si el correo ya tiene cuenta: se enlaza el proveedor a esa cuenta
  y se entra con ella.
- Con sesión iniciada, entrar por el proveedor enlaza la cuenta
  social a la cuenta actual (luego se puede entrar por cualquiera).
- Los tokens se guardan cifrados. El `state` de OAuth se verifica en
  sesión en el callback.
- Amigos: el sistema interno no cambia (añadir por nombre de
  usuario, peticiones, carreras de amigos). El login social solo
  quita el registro con contraseña de en medio.

## Notas de despliegue (pooler Neon)

- `migrate --force` completo FALLA en producción: la migración vieja
  de Telescope (2024_12_13) está pendiente pero su tabla ya existe
  en parte, y el pooler aborta la transacción (25P02).
- Las migraciones nuevas en prod deben correrse con `--path` al
  fichero concreto, y las migraciones DDL multi-sentencia deben
  llevar `public $withinTransaction = false;` (ver la migración de
  `social_accounts`).
