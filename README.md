# GestDriver App (estructura modular)
Fecha: 2025-08-31

## Estructura
- `config/db.php` — conexión PDO
- `includes/` — header/footer/helpers
- `public/` — módulos navegables (Dashboard, Repartidores, Cuentas Glovo, Administración, Ajustes, Resumen)
- `migrations/` — SQL para crear tablas y FK
- `uploads/metrics/` — carpeta para ficheros subidos

## Puesta en marcha
1. Crea la BD y tablas:
   ```sql
   SOURCE migrations/001_create_tables.sql;
   SOURCE migrations/002_add_fk_glovo.sql;
   ```
2. Arranca servidor embebido:
   ```bash
   cd public
   php -S localhost:8000
   ```
3. Abre `http://localhost:8000/index.php`

## Notas
- Para subir XLSX en Administración instala PhpSpreadsheet:
  ```bash
  cd public
  composer require phpoffice/phpspreadsheet
  ```
