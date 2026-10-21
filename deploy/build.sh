#!/usr/bin/env bash
# Construye el paquete que se sube a htdocs/public_html en un hosting PHP.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$ROOT/deploy/htdocs"
ZIP="$ROOT/deploy/liquorsoft-htdocs.zip"

echo "==> Compilando Angular (producción)"
npm --prefix "$ROOT/LiquorSoft-angular" run build

BROWSER_DIR="$(find "$ROOT/LiquorSoft-angular/dist" -maxdepth 3 -type d -name browser -print -quit)"
if [[ -z "$BROWSER_DIR" || ! -f "$BROWSER_DIR/index.html" ]]; then
  echo "ERROR: no se encontró index.html en dist/*/browser" >&2
  exit 1
fi

echo "==> Validando sintaxis PHP"
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find "$ROOT/backend/api" -type f -name '*.php' -print0)

echo "==> Ensamblando $OUT"
rm -rf "$OUT"
mkdir -p "$OUT"
cp -a "$BROWSER_DIR/." "$OUT/"
cp -a "$ROOT/backend/api" "$OUT/api"
cp "$ROOT/deploy/htaccess.txt" "$OUT/.htaccess"
cp "$ROOT/deploy/env.production.example" "$OUT/.env.example"

TOKEN="$(php -r 'echo bin2hex(random_bytes(16));')"
php -r '$template = file_get_contents($argv[1]); $output = str_replace("__INSTALL_TOKEN__", $argv[2], $template); file_put_contents($argv[3], $output);' \
  "$ROOT/deploy/install-once.php.template" "$TOKEN" "$OUT/install-once.php"
cp "$ROOT/backend/database/schema.sql" "$OUT/schema.sql"
chmod 0644 "$OUT/install-once.php"

# Nunca empaquetar credenciales locales ni archivos de desarrollo.
rm -f "$OUT/.env" "$OUT/.env.local"
find "$OUT" -name '*.map' -delete

echo "==> Creando $ZIP"
rm -f "$ZIP"
(cd "$OUT" && zip -qr "$ZIP" .)

echo
echo "Listo:"
echo "  Carpeta: $OUT"
echo "  ZIP:     $ZIP"
echo "  Tamaño:  $(du -sh "$OUT" | cut -f1)"
echo
echo "Sube el CONTENIDO de la carpeta (o el ZIP descomprimido) a htdocs/public_html."
echo "Luego abre en el navegador:"
echo "  https://TU-SUBDOMINIO/install-once.php?token=$TOKEN"
