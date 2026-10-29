#!/bin/bash

IMG_DIR="/home/rod-mint/LiquorSoft/LiquorSoft-angular/public/images/productos"
UA="LiquorSoftBot/1.0 (contact: admin@liquorsoft.com)"

download_product() {
    local search_term="$1"
    local output_name="$2"
    
    local search_url="https://commons.wikimedia.org/w/api.php?action=query&list=search&srsearch=${search_term}&srnamespace=6&srlimit=3&format=json"
    local search_result=$(curl -s -A "$UA" "$search_url" 2>/dev/null)
    
    local file_title=$(echo "$search_result" | python3 -c "import sys,json; d=json.load(sys.stdin); results=d.get('query',{}).get('search',[]); print(results[0]['title'] if results else '')" 2>/dev/null)
    
    if [ -z "$file_title" ]; then
        echo "  NO ENCONTRADO: $search_term"
        return 1
    fi
    
    local encoded_title=$(echo "$file_title" | sed 's/ /_/g')
    local info_url="https://commons.wikimedia.org/w/api.php?action=query&titles=${encoded_title}&prop=imageinfo&iiprop=url&format=json"
    local info_result=$(curl -s -A "$UA" "$info_url" 2>/dev/null)
    
    local image_url=$(echo "$info_result" | python3 -c "import sys,json; d=json.load(sys.stdin); pages=d.get('query',{}).get('pages',{}); [print(p['imageinfo'][0]['url']) for p in pages.values() if 'imageinfo' in p]" 2>/dev/null | head -1 | sed 's/?utm_source=.*//')
    
    if [ -z "$image_url" ]; then
        echo "  SIN URL: $file_title"
        return 1
    fi
    
    local output_file="${IMG_DIR}/${output_name}"
    curl -sL -A "$UA" -o "$output_file" "$image_url" 2>/dev/null
    
    if file "$output_file" | grep -q "image"; then
        local size=$(ls -la "$output_file" | awk '{print $5}')
        echo "  OK: $output_name ($size bytes)"
        return 0
    else
        echo "  ERROR: $output_name no es imagen"
        rm -f "$output_file"
        return 1
    fi
}

declare -a products=(
    "Aguardiente Antioqueño:aguardiente-antioqueno.jpg"
    "Johnnie Walker:johnnie-walker.jpg"
    "Club Colombia beer:club-colombia.jpg"
    "Old Parr whisky:old-parr.jpg"
    "Buchanans whisky:buchanans.jpg"
    "Jack Daniels:jack-daniels.jpg"
    "Absolut Vodka:absolut.jpg"
    "Jose Cuervo tequila:jose-cuervo.jpg"
    "Don Julio tequila:don-julio.jpg"
    "Patron tequila:patron.jpg"
    "Gordon's Gin:gordons-gin.jpg"
    "Beefeater Gin:beefeater.jpg"
    "Baileys:baileys.jpg"
    "Smirnoff Vodka:smirnoff.jpg"
    "Grey Goose Vodka:grey-goose.jpg"
    "Schweppes tonic:schweppes.jpg"
)

success=0
fail=0
for item in "${products[@]}"; do
    search_term="${item%%:*}"
    output_name="${item##*:}"
    if [ -f "${IMG_DIR}/${output_name}" ]; then
        echo "  YA EXISTE: $output_name"
        success=$((success + 1))
        continue
    fi
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
