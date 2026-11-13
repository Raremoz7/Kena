<?php

namespace App\Services\Photos;

/**
 * De onde vêm as fotos exportadas. Hoje uma pasta local; o Google Drive entra
 * como outra implementação, sem mexer no importador.
 */
interface PhotoSource
{
    /**
     * Fotos em ordem estável (a ordem vira a posição na galeria).
     *
     * @return iterable<SourcePhoto>
     */
    public function photos(): iterable;
}
