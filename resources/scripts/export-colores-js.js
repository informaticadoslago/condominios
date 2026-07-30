// resources/scripts/export-colores-js.js
// -------------------------------------------------------------
// ⚙️  Genera resources/js/colores.js a partir de los mapas
//     definidos en resources/scss/parciales/_listaColores.scss
// -------------------------------------------------------------

import fs from "fs";
import path from "path";

// Ruta al archivo SCSS que contiene los mapas de colores
const scssPath = path.resolve("resources/scss/parciales/listaColores.scss");
const outputPath = path.resolve("resources/js/colores.js");

// Leer el SCSS como texto
if (!fs.existsSync(scssPath)) {
  console.error("❌ No se encontró el archivo:", scssPath);
  process.exit(1);
}

const scssText = fs.readFileSync(scssPath, "utf-8");

// Buscar todos los mapas Sass tipo `$nombre: (...)`
const matches = [...scssText.matchAll(/\$([a-zA-Z0-9-_]+)\s*:\s*\(([\s\S]*?)\);/g)];

if (matches.length === 0) {
  console.error("❌ No se encontraron mapas de colores en:", scssPath);
  process.exit(1);
}

let jsObj = {};

// Recorrer cada mapa encontrado
for (const match of matches) {
  const [, nombreMapa, contenido] = match;

  contenido.split("\n").forEach((line) => {
    const l = line.trim();
    if (!l || l.startsWith("//")) return;

    // Coincidencias tipo: color: #xxxxxx,
    const m = l.match(/^\s*([a-zA-Z0-9-_]+)\s*:\s*(#[0-9A-Fa-f]{3,6}|rgb\(.*\))\s*,?\s*$/);
    if (m) {
      const key = m[1].trim();
      const value = m[2].trim();
      jsObj[key] = value;
    }
  });
}

// Escribir el archivo JS de salida
const jsContent =
  "// ⚠️ Archivo generado automáticamente a partir de _listaColores.scss\n" +
  "export default " +
  JSON.stringify(jsObj, null, 2) +
  ";\n";

fs.writeFileSync(outputPath, jsContent);

console.log(`✅ colores.js generado correctamente con ${Object.keys(jsObj).length} colores`);
