<?php

namespace App\Services\Facturas;

use App\Exceptions\GeneracionPlantillaIAException;
use App\Models\TipoCampoPlantillaFactura;
use App\Services\Facturas\Plantillas\ExtractorPosicional;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Genera una plantilla de factura llamando a la API de Claude UNA VEZ por
 * proveedor: no se le pide que extraiga los datos de cada factura (eso sería
 * un coste recurrente), sino que localice, igual que haría un humano marcando
 * con el ratón, dónde está cada valor en el texto — con eso se calcula el
 * ancla con el mismo algoritmo (ExtractorPosicional) que ya usa el marcado
 * manual, y las facturas siguientes de ese proveedor se resuelven gratis.
 */
class GeneradorPlantillaIA
{
    protected const CAMPOS_CON_ANCLA = [
        TipoCampoPlantillaFactura::CIF            => 'cif',
        TipoCampoPlantillaFactura::FECHA          => 'fecha',
        TipoCampoPlantillaFactura::NUMERO_FACTURA => 'numero_factura',
        TipoCampoPlantillaFactura::IMPORTE        => 'importe',
    ];

    /**
     * @return array{razon_social: ?string, cif: ?string, campos: array<int, array{ancla: string, valor: string}>}
     */
    public function generar(string $texto, ?string $cifPropio = null): array
    {
        $clave = config('services.anthropic.key');
        if (! $clave) {
            throw new GeneracionPlantillaIAException(__('No hay ninguna clave de API de Anthropic configurada (ANTHROPIC_API_KEY).'));
        }

        $respuesta = $this->llamarApi($texto, $cifPropio, $clave);
        $valores   = $this->extraerValoresDeRespuesta($respuesta);

        $extractor = new ExtractorPosicional();
        $campos    = [];

        foreach (self::CAMPOS_CON_ANCLA as $tipoCampoId => $clavePayload) {
            $valor = $valores[$clavePayload] ?? null;
            if (! $valor) {
                continue;
            }

            $ancla = $this->calcularAncla($extractor, $texto, $valor);
            if ($ancla !== null) {
                $campos[$tipoCampoId] = $ancla;
            }
        }

        return [
            'razon_social' => $this->siEsLiteral($texto, $valores['razon_social'] ?? null),
            'cif'          => $this->siEsLiteral($texto, $valores['cif'] ?? null),
            'campos'       => $campos,
        ];
    }

    protected function llamarApi(string $texto, ?string $cifPropio, string $clave): array
    {
        $descripcionCif = $cifPropio ? "El CIF del CLIENTE (a excluir) es {$cifPropio}." : '';

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $clave,
                'anthropic-version' => '2023-06-01',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('facturas.ia_modelo'),
                'max_tokens' => 1024,
                'tools'      => [[
                    'name'        => 'extraer_valores_factura',
                    'description' => 'Copia EXACTAMENTE, carácter por carácter, tal como aparece '
                        .'en el texto de la factura (sin reformatear ni corregir nada), el valor '
                        .'de cada campo. Es una factura de un PROVEEDOR a un cliente: el CIF y la '
                        .'razón social deben ser los de quien EMITE la factura (el proveedor), '
                        .'nunca los del cliente que la recibe. '.$descripcionCif.' Si un campo '
                        .'no aparece literalmente en el texto, omítelo.',
                    'input_schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'razon_social'   => ['type' => 'string', 'description' => 'Razón social del proveedor (quien emite), copiada literal.'],
                            'cif'            => ['type' => 'string', 'description' => 'CIF/NIF del proveedor (quien emite), copiado literal.'],
                            'numero_factura' => ['type' => 'string', 'description' => 'Número de factura, copiado literal.'],
                            'fecha'          => ['type' => 'string', 'description' => 'Fecha de emisión de la factura, copiada literal.'],
                            'importe'        => ['type' => 'string', 'description' => 'Importe TOTAL de la factura, copiado literal.'],
                        ],
                        'required' => [],
                    ],
                ]],
                'tool_choice' => ['type' => 'tool', 'name' => 'extraer_valores_factura'],
                'messages'    => [[
                    'role'    => 'user',
                    'content' => "Texto de la factura:\n\n{$texto}",
                ]],
            ]);
        } catch (ConnectionException $e) {
            throw new GeneracionPlantillaIAException(__('No se pudo conectar con la API de Anthropic: :error', ['error' => $e->getMessage()]));
        }

        if ($response->failed()) {
            throw new GeneracionPlantillaIAException(__('La API de Anthropic devolvió un error: :error', ['error' => $response->body()]));
        }

        return $response->json();
    }

    protected function extraerValoresDeRespuesta(array $respuesta): array
    {
        foreach ($respuesta['content'] ?? [] as $bloque) {
            if (($bloque['type'] ?? null) === 'tool_use') {
                return $bloque['input'] ?? [];
            }
        }

        throw new GeneracionPlantillaIAException(__('La respuesta de la IA no trae los datos esperados.'));
    }

    /**
     * La IA a veces "adivina" un valor plausible (p.ej. la razón social a partir de un
     * email) en vez de copiarlo literal, pese a la instrucción de omitirlo si no aparece
     * tal cual. Es la misma garantía que ya se exige a los campos anclables: si no está
     * literalmente en el texto, se descarta en vez de guardarlo como si fuera real.
     */
    protected function siEsLiteral(string $texto, ?string $valor): ?string
    {
        return ($valor && mb_strpos($texto, $valor) !== false) ? $valor : null;
    }

    /** Localiza el valor literal en el texto y calcula su ancla con el mismo algoritmo que el marcado manual, autovalidando que se reencuentra a sí misma. */
    protected function calcularAncla(ExtractorPosicional $extractor, string $texto, string $valor): ?array
    {
        $inicio = mb_strpos($texto, $valor);
        if ($inicio === false) {
            // La IA no copió el valor literal pese a la instrucción: mejor descartarlo que inventar una posición.
            return null;
        }

        $fin       = $inicio + mb_strlen($valor);
        $resultado = $extractor->construirAncla($texto, $inicio, $fin);

        if (! $resultado['ancla'] || $extractor->buscarPorAncla($texto, $resultado['ancla']) !== $resultado['valor']) {
            // El ancla calculada no se reencuentra a sí misma: no serviría en la próxima factura.
            return null;
        }

        return $resultado;
    }
}
