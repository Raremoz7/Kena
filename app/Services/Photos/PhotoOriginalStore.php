<?php

namespace App\Services\Photos;

use App\Models\Photo;

/**
 * Onde moram os originais em alta das fotos. O download passa sempre pelo
 * servidor (rota autenticada que confere a compra) e lê daqui: o comprador
 * nunca vê o endereço real do arquivo.
 *
 * Hoje: pasta local (LocalPhotoOriginalStore). Depois: Google Drive, com o id
 * do arquivo em `photos.original_ref`.
 */
interface PhotoOriginalStore
{
    /** Consulta barata (sem baixar nada): a foto já tem original para entregar? */
    public function has(Photo $photo): bool;

    /**
     * Abre o original da foto para entrega. Devolve null quando a foto ainda
     * não tem original guardado (ex.: fotos de demonstração).
     */
    public function open(Photo $photo): ?PhotoOriginal;
}
