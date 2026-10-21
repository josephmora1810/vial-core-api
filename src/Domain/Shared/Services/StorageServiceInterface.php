<?php

namespace App\Domain\Shared\Services;

interface StorageServiceInterface
{
    /**
     * Sube un archivo a almacenamiento remoto (AWS S3) y retorna su URL final.
     *
     * @param string $localFilePath Ruta local del archivo temporal en el servidor
     * @param string $storageKey    Ruta relativa destino en el bucket (ej: documents/users/1/id_card.jpg)
     * @param string|null $contentType Tipo MIME del archivo (opcional, detectado automáticamente si es null)
     * @return string URL pública del objeto almacenado en S3
     */
    public function upload(string $localFilePath, string $storageKey, ?string $contentType = null): string;

    /**
     * Elimina un archivo del almacenamiento remoto.
     *
     * @param string $storageKey Ruta relativa en el bucket
     * @return bool
     */
    public function delete(string $storageKey): bool;

    /**
     * Verifica si un archivo existe en el almacenamiento remoto.
     *
     * @param string $storageKey Ruta relativa en el bucket
     * @return bool
     */
    public function exists(string $storageKey): bool;
}
