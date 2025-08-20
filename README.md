## Proyecto Hospital

Aplicación PHP para gestión básica de usuarios, empleados y carnés, con módulos para visitantes y bitácora.

### Requisitos
- PHP 8.0+ (XAMPP recomendado)
- MySQL/MariaDB 10.3+ (phpMyAdmin opcional)
- Apache (sirviendo la carpeta `public/`)

### Instalación rápida (XAMPP en Windows)
1. Clona el repositorio en `C:\xampp\htdocs\ProyectoHospital` o la ruta que prefieras.
2. Inicia Apache y MySQL desde XAMPP Control Panel.
3. Crea la base de datos e instala el esquema:
   - Abre phpMyAdmin → Importar → selecciona `database/schema.sql` → Ejecutar.
   - (Opcional) Carga datos iniciales de visitantes: importa `database/seeds.sql`.
4. Configura credenciales en `config/config.php`:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
   - El código intenta fallback a usuario `root` sin contraseña en XAMPP si hay error 1045.
5. Asegura que exista la carpeta de subidas `public/uploads/` (se crea automáticamente al subir imágenes desde la app). Esta carpeta está ignorada por Git.

### Acceso a la aplicación
- URL base local típica: `http://localhost/ProyectoHospital/public/`
- Usuario inicial (creado por `schema.sql`):
  - Usuario: `webmaster`
  - Contraseña: `W3bM@ster2025`

### Roles
- `web_master`: control total
- `admin`: gestión general
- `operador`: operaciones del día a día
- `visor`: solo lectura

### Módulos principales
- Login (`public/login.php`)
- Panel y navegación (`public/index.php`)
- Empleados y carnés (`public/employees.php`, `public/carnes.php`, `public/card.php`)
- Visitantes (`public/visitors.php`)
- Usuarios (`public/users.php`)

### Base de datos
- Esquema principal en `database/schema.sql`.
- Datos iniciales opcionales en `database/seeds.sql`:
  - Crea 100 carnés por tipo de visitante: `cuidador`, `tramitador`, `visitante`.

### Configuración de aplicación
Edita `config/config.php`:

```php
const DB_HOST = 'localhost';
const DB_NAME = 'hospital_db';
const DB_USER = 'root';
const DB_PASS = 'hospital2025';
const APP_BASE_PATH = '/';
```

Si instalas en una subcarpeta distinta, ajusta `APP_BASE_PATH`.

### Subida de imágenes
- Los logos y fotos se guardan en `public/uploads/`.
- La distribución y textos del carné se guardan en `public/uploads/card_config.json`.

### Notas de producción
- Desactiva la visualización de errores en `config/config.php` (`ini_set('display_errors', '0');`).
- Usa un usuario de base de datos con contraseña robusta.
- Protege el directorio `public/uploads/` si sirves en internet.

### Problemas comunes
- “No se subieron `database/` o `lib/` a GitHub”: asegúrate de ejecutar `git add database lib` antes del commit. En este repo ya están versionados.

### Licencia
Uso interno. Ajusta según tus necesidades.


