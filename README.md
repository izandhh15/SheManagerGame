# SheManagerGame

<p align="center">
  <b>El mánager de fútbol femenino. <a href="https://shemanager.wasmer.app">Juega gratis aquí</a>.</b><br><br>
  <a href="https://ko-fi.com/izandhh"><img src="https://img.shields.io/badge/Ko--fi-Apoya_el_proyecto-FF5E5B?style=for-the-badge&logo=ko-fi&logoColor=white" alt="Ko-fi"></a>
</p>

**SheManagerGame** es un juego de simulación de mánager de fútbol femenino construido con Laravel 13, Tailwind CSS y Alpine.js. Adaptación de [VirtuaFC](https://github.com/pabloroman/virtua-fc) de Pablo Román, realizada con su permiso.

## Características

### Competiciones
- **144 clubes** en 12 ligas femeninas reales (Liga F, Women's Super League, Première Ligue, Frauen-Bundesliga, Serie A Femminile, NWSL, Liga BPI, Eredivisie Vrouwen, AXA Women's Super League…)
- **198 selecciones nacionales** con plantillas de jugadoras reales
- Copas nacionales (Copa de la Reina, Women's FA Cup, Coupe de France…), UWCL, UEFA Women's Europa Cup
- **UEFA Women's Nations League**, **Mundial 2027 (Brasil)** y **Eurocopa 2029 (Alemania)**

### Modos de juego
- **Carrera de club**: gestiona tu equipo, ficha, entrena y compite
- **Carrera de selección**: convoca a las 23 y dirige a tu país
- **Carrera dual (beta)**: lleva un club y una selección a la vez, con calendarios entrelazados

### Datos reales
- Jugadoras 100 % reales (datos de Soccerdonna, sin inventos)
- Medias basadas en las valoraciones oficiales del FC 27
- Fichajes del verano de 2026 verificados en prensa

### Simulación
- Motor de partidos con distribución de goles realista
- Eventos: goles, asistencias, tarjetas, lesiones, sustituciones
- Tácticas: formaciones, mentalidad, posicionamiento avanzado
- Mercado de fichajes, cantera, sistema financiero, moral y forma física

### Idiomas
Español, català, English, français, português y Deutsch.

## Jugar

No necesitas instalar nada: **[shemanager.wasmer.app](https://shemanager.wasmer.app)**

## Desarrollo local

```bash
git clone https://github.com/izandhh15/SheManagerGame.git
cd SheManagerGame
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Requiere PHP 8.2+, Composer y una base de datos (SQLite vale para probar).

## Problemas y sugerencias

Abre un issue en [github.com/izandhh15/SheManagerGame/issues](https://github.com/izandhh15/SheManagerGame/issues).

## Créditos

- Idea, adaptación y mantenimiento: **Izan Delgado**
- Basado en VirtuaFC de **Pablo Román** (con permiso)

## Licencia

Ver [LICENSE](LICENSE).
