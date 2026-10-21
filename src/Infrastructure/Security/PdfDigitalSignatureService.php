<?php

namespace App\Infrastructure\Security;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use Throwable;

class PdfDigitalSignatureService
{
    private string $certPath;
    private string $keyPath;
    private ?string $passphrase;

    public function __construct(
        ?string $certPath = null,
        ?string $keyPath = null,
        ?string $passphrase = null
    ) {
        $certsDir = dirname(__DIR__, 3) . '/storage/certs';
        $this->certPath   = $certPath ?? ($certsDir . '/ubikgo_signer.crt');
        $this->keyPath    = $keyPath ?? ($certsDir . '/ubikgo_signer.key');
        $this->passphrase = $passphrase;

        $this->ensureCertificateExists();
    }

    /**
     * Aplica la firma criptográfica digital (SHA256withRSA) y el sello digital X.509 de UBIK SERVICE C.A.
     * sobre los bytes generados del PDF.
     */
    public function sign(string $pdfBinary): string
    {
        if ($pdfBinary === '') {
            return '';
        }

        try {
            $privateKey = openssl_pkey_get_private(
                file_get_contents($this->keyPath),
                $this->passphrase ?? ''
            );

            if ($privateKey === false) {
                return $pdfBinary;
            }

            // 1. Extraer huella digital (Fingerprint) del certificado X.509
            $certContent = file_get_contents($this->certPath);
            $certX509 = openssl_x509_read($certContent);
            $fingerprint = $certX509 ? openssl_x509_fingerprint($certX509, 'sha256') : '';

            // 2. Firmar criptográficamente el contenido original del PDF con la clave privada de UbikGo
            $binarySignature = '';
            $signed = openssl_sign($pdfBinary, $binarySignature, $privateKey, OPENSSL_ALGO_SHA256);

            if (!$signed) {
                return $pdfBinary;
            }

            $base64Sig = base64_encode($binarySignature);
            $sha256Hash = hash('sha256', $pdfBinary);

            // 3. Estampar metadatos y sello digital criptográfico en la estructura del PDF
            $signatureTrailer = "\n%UBIKGO-DIGITAL-SIGNATURE-START\n"
                . "%Issuer: UBIK SERVICE C.A. - RIF J-50209850-0\n"
                . "%Algorithm: SHA256withRSA (2048-bit)\n"
                . "%Document-SHA256: {$sha256Hash}\n"
                . "%Certificate-Fingerprint-SHA256: {$fingerprint}\n"
                . "%Signature-PKCS1: {$base64Sig}\n"
                . "%Timestamp: " . gmdate('Y-m-d\TH:i:s\Z') . "\n"
                . "%UBIKGO-DIGITAL-SIGNATURE-END\n"
                . "%%EOF\n";

            return $pdfBinary . $signatureTrailer;
        } catch (Throwable) {
            // Si ocurre algún fallo de OpenSSL, retorna el binario original sin interrumpir el flujo
            return $pdfBinary;
        }
    }

    /**
     * Verifica criptográficamente si un PDF firmado por UbikGo mantiene su integridad o si fue adulterado.
     * Retorna true si la firma es auténtica y no fue alterada ninguna letra o byte.
     */
    public function verify(string $pdfBinaryWithSignature): bool
    {
        $startTag = "\n%UBIKGO-DIGITAL-SIGNATURE-START\n";
        $endTag   = "%UBIKGO-DIGITAL-SIGNATURE-END\n";

        $startPos = strpos($pdfBinaryWithSignature, $startTag);
        $endPos   = strpos($pdfBinaryWithSignature, $endTag);

        if ($startPos === false || $endPos === false || $endPos <= $startPos) {
            return false;
        }

        // Separar PDF original exacto del bloque de firma (sin alterar un solo byte)
        $originalPdf = substr($pdfBinaryWithSignature, 0, $startPos);
        $metaBlock   = substr($pdfBinaryWithSignature, $startPos, $endPos - $startPos);

        // Extraer firma base64
        if (!preg_match('/%Signature-PKCS1:\s*([A-Za-z0-9+\/=\r\n]+)/', $metaBlock, $matches)) {
            return false;
        }

        $signature = base64_decode(trim($matches[1]));
        if ($signature === false || $signature === '') {
            return false;
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($this->certPath));
        if ($publicKey === false) {
            return false;
        }

        // Verificar firma digital con la clave pública del certificado
        $result = openssl_verify($originalPdf, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * Auto-genera el certificado digital X.509 autofirmado y par de claves RSA 2048-bit
     * si aún no existen en el directorio de almacenamiento.
     */
    private function ensureCertificateExists(): void
    {
        if (file_exists($this->certPath) && file_exists($this->keyPath)) {
            return;
        }

        $certsDir = dirname($this->certPath);
        if (!is_dir($certsDir)) {
            @mkdir($certsDir, 0755, true);
        }

        $dn = [
            'countryName'            => 'VE',
            'stateOrProvinceName'    => 'Distrito Capital',
            'localityName'           => 'Caracas',
            'organizationName'       => 'UBIK SERVICE C.A.',
            'organizationalUnitName' => 'Departamento de Seguridad Digital y Emisión de Contratos',
            'commonName'             => 'UBIK SERVICE C.A. - Certificado Digital de Contratos UbikGo',
            'emailAddress'           => 'legal@ubikgo.com',
        ];

        $cnfPath = $certsDir . '/openssl.cnf';
        $configArgs = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg'       => 'sha256',
        ];
        if (file_exists($cnfPath)) {
            $configArgs['config'] = $cnfPath;
        }

        $privKey = openssl_pkey_new($configArgs);

        if ($privKey === false) {
            return;
        }

        $csr = openssl_csr_new($dn, $privKey, $configArgs);
        if ($csr === false) {
            return;
        }

        // Certificado con 10 años de vigencia (3650 días)
        $cert = openssl_csr_sign($csr, null, $privKey, 3650, $configArgs);
        if ($cert === false) {
            return;
        }

        openssl_x509_export($cert, $certOut);
        openssl_pkey_export($privKey, $keyOut, $this->passphrase, $configArgs);

        file_put_contents($this->certPath, $certOut);
        file_put_contents($this->keyPath, $keyOut);
    }
}
