#!/usr/bin/env python3
"""Convierte un PDF a texto plano (con layout), invocado desde PHP (PdfplumberLector)."""
import sys

import pdfplumber

ruta = sys.argv[1]

with pdfplumber.open(ruta) as pdf:
    paginas = [pagina.extract_text(layout=True, x_density=5) or "" for pagina in pdf.pages]

print("\n\n".join(paginas))
