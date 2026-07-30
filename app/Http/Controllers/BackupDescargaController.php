<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupDescargaController extends Controller
{
    public function __invoke(string $fichero): StreamedResponse
    {
        $disk = Storage::disk('backups');

        abort_unless($disk->exists($fichero), 404);

        return $disk->download($fichero);
    }
}
