<?php

namespace App\Services\Photos\Drive;

use RuntimeException;

/** Falha ao falar com o Google Drive. A mensagem é escrita para o organizador ler. */
final class DriveException extends RuntimeException {}
