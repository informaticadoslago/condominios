// resources/scripts/export-colores-php.js
import fs from 'fs';
import path from 'path';

const scssPath = path.resolve('resources/scss/parciales/listaColores.scss');
const scssText = fs.readFileSync(scssPath, 'utf-8');

// Buscar todos los mapas tipo $nombre: ( ... );
const regex = /\$([a-zA-Z0-9_-]+)\s*:\s*\(([\s\S]*?)\);/g;

let colores = {};
let match;

while ((match = regex.exec(scssText)) !== null) {
  const mapBody = match[2];
  const lines = mapBody.split('\n').map(l => l.trim()).filter(Boolean);

  for (const line of lines) {
    // Formato: clave: #valor,
    const colorMatch = line.match(/^([a-zA-Z0-9_-]+)\s*:\s*(#[0-9A-Fa-f]{3,8})/);
    if (colorMatch) {
      const key = colorMatch[1];
      const value = colorMatch[2];
      colores[key] = value;
    }
  }
}

if (Object.keys(colores).length === 0) {
  console.error('❌ No se encontraron colores en _listaColores.scss');
  process.exit(1);
}

let phpArray = Object.entries(colores)
  .map(([key, value]) => `    '${key}' => '${value}'`)
  .join(",\n");

const phpContent = `<?php
// ⚠️ Archivo generado automáticamente a partir de parciales/listaColores.scss
return [
${phpArray}
];
`;

fs.writeFileSync(path.resolve('config/doscolores.php'), phpContent);
console.log('✅ config/doscolores.php generado correctamente');
