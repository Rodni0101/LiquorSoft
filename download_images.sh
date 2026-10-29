#!/bin/bash
set -e

IMG_DIR="/home/rod-mint/LiquorSoft/LiquorSoft-angular/public/images/productos"
UA="LiquorSoftBot/1.0 (contact: admin@liquorsoft.com)"

download_product() {
    local search_term="$1"
    local output_name="$2"
    
    echo "=== Buscando: $search_term ==="
    
    # Buscar en Wikimedia Commons
    local search_url="https://commons.wikimedia.org/w/api.php?action=query&list=search&srsearch=${search_term}&srnamespace=6&srlimit=3&format=json"
    local search_result=$(curl -s -A "$UA" "$search_url" 2>/dev/null)
    
    # Extraer el primer título de archivo
    local file_title=$(echo "$search_result" | python3 -c "import sys,json; d=json.load(sys.stdin); results=d.get('query',{}).get('search',[]); print(results[0]['title'] if results else '')" 2>/dev/null)
    
    if [ -z "$file_title" ]; then
        echo "  No se encontraron resultados para: $search_term"
        return 1
    fi
    
    echo "  Encontrado: $file_title"
    
    # Obtener URL directa de la imagen
    local encoded_title=$(echo "$file_title" | sed 's/ /_/g')
    local info_url="https://commons.wikimedia.org/w/api.php?action=query&titles=${encoded_title}&prop=imageinfo&iiprop=url&format=json"
    local info_result=$(curl -s -A "$UA" "$info_url" 2>/dev/null)
    
    local image_url=$(echo "$info_result" | python3 -c "import sys,json; d=json.load(sys.stdin); pages=d.get('query',{}).get('pages',{}); [print(p['imageinfo'][0]['url']) for p in pages.values() if 'imageinfo' in p]" 2>/dev/null | head -1)
    
    if [ -z "$image_url" ]; then
        echo "  No se pudo obtener URL de imagen para: $file_title"
        return 1
    fi
    
    # Quitar parámetros de tracking de la URL
    image_url=$(echo "$image_url" | sed 's/?utm_source=.*//')
    
    # Descargar la imagen
    local output_file="${IMG_DIR}/${output_name}"
    curl -sL -A "$UA" -o "$output_file" "$image_url" 2>/dev/null
    
    # Verificar que sea una imagen válida
    if file "$output_file" | grep -q "image"; then
        local size=$(ls -la "$output_file" | awk '{print $5}')
        echo "  DESCARGADO: $output_name ($size bytes)"
        return 0
    else
        echo "  ERROR: Archivo no es una imagen válida: $output_name"
        rm -f "$output_file"
        return 1
    fi
}

# Lista de productos a buscar (término de búsqueda, nombre de archivo de salida)
declare -a products=(
    "Tequila Reposado bottle 750ml:tequila-reposado.jpg"
    "London Dry Gin bottle 700ml:gin-london-dry.jpg"
    "Tonic water premium bottle 200ml:tonica-premium.jpg"
    "Rose wine bottle 750ml:vino-rosado.jpg"
    "Aguardiente Antioqueño bottle:aguardiente-antioqueno.jpg"
    "Ron Medellín 8 Años bottle:ron-medellin-8.jpg"
    "Old Parr 12 Years bottle:old-parr-12.jpg"
    "Buchanan's 12 Years bottle:buchanans-12.jpg"
    "Johnnie Walker Black Label bottle:johnnie-walker-black.jpg"
    "Jack Daniel's Old No. 7 bottle:jack-daniels.jpg"
    "Absolut Vodka bottle:absolut-vodka.jpg"
    "José Cuervo Reposado bottle:jose-cuervo-reposado.jpg"
    "Tanqueray London Dry Gin bottle:tanqueray-gin.jpg"
    "Club Colombia beer bottle:club-colombia.jpg"
    "Águila beer bottle:aguila-beer.jpg"
    "Baileys Irish Cream bottle:baileys.jpg"
    "Schweppes Indian Tonic Water bottle:schweppes-tonic.jpg"
    "Don Julio Tequila bottle:don-julio.jpg"
    "Patrón Tequila bottle:patron-tequila.jpg"
    "Grey Goose Vodka bottle:grey-goose.jpg"
)

success=0
fail=0
for item in "${products[@]}"; do
    search_term="${item%%:*}"
    output_name="${item##*:}"
    if download_product "$search_term" "$output_name"; then
        success=$((success + 1))
    else
        fail=$((fail + 1))
    fi
    sleep 1
done

echo ""
echo "=== RESUMEN ==="
echo "Exitosos: $success"
echo "Fallidos: $fail"
