<?php

namespace App\Infrastructure\Pdf;

use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Services\ContractPdfGeneratorInterface;
use App\Domain\Users\Entities\User;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Infrastructure\Security\PdfDigitalSignatureService;

/**
 * Extensión interna de FPDF 1.9 con soporte para rectángulos redondeados,
 * pestañas curvas de encabezado de sección y codificación Windows-1252.
 */
class UbikContractFpdf extends \FPDF
{
    /**
     * Convierte texto UTF-8 a Windows-1252 (CP1252) requerido por las fuentes estándar de FPDF.
     */
    public function encodeText(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : $text;
    }

    /**
     * Dibuja un rectángulo con esquinas redondeadas.
     * $style: 'D' (borde), 'F' (relleno), 'DF' o 'FD' (ambos)
     */
    public function RoundedRect(float $x, float $y, float $w, float $h, float $r, string $style = 'D'): void
    {
        $k = $this->k;
        $hp = $this->h;
        if ($style === 'F') {
            $op = 'f';
        } elseif ($style === 'FD' || $style === 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }
        $myArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));

        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_arc($xc + $r * $myArc, $yc - $r, $xc + $r, $yc - $r * $myArc, $xc + $r, $yc);

        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_arc($xc + $r, $yc + $r * $myArc, $xc + $r * $myArc, $yc + $r, $xc, $yc + $r);

        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_arc($xc - $r * $myArc, $yc + $r, $xc - $r, $yc + $r * $myArc, $xc - $r, $yc);

        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_arc($xc - $r, $yc - $r * $myArc, $xc - $r * $myArc, $yc - $r, $xc, $yc - $r);

        $this->_out($op);
    }

    /**
     * Dibuja la pestaña superior izquierda característica del diseño de UbikGo
     * (esquina superior izquierda redondeada y borde derecho con curva suave).
     */
    public function SectionHeaderTab(float $x, float $y, float $w, float $h, float $topLeftRadius = 3.8, float $bottomRightCurve = 5.5): void
    {
        $k = $this->k;
        $hp = $this->h;
        $myArc = 4 / 3 * (sqrt(2) - 1);
        $r = $topLeftRadius;
        $cr = $bottomRightCurve;

        // Empezar después de la esquina superior izquierda
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        // Línea superior hasta el ancho de la pestaña
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $y) * $k));
        // Bajar y curvar hacia la izquierda en la esquina inferior derecha de la pestaña
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - ($y + $h - $cr)) * $k));
        $xc = $x + $w - $cr;
        $yc = $y + $h - $cr;
        $this->_arc($xc + $cr, $yc + $cr * $myArc, $xc + $cr * $myArc, $yc + $cr, $xc, $yc + $cr);
        // Línea inferior hacia el borde izquierdo
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - ($y + $h)) * $k));
        // Subir por el borde izquierdo hasta el inicio del arco superior izquierdo
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - ($y + $r)) * $k));
        $xc2 = $x + $r;
        $yc2 = $y + $r;
        $this->_arc($xc2 - $r, $yc2 - $r * $myArc, $xc2 - $r * $myArc, $yc2 - $r, $xc2, $yc2 - $r);
        $this->_out('f');
    }

    protected function _arc(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): void
    {
        $h = $this->h;
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c ',
            $x1 * $this->k,
            ($h - $y1) * $this->k,
            $x2 * $this->k,
            ($h - $y2) * $this->k,
            $x3 * $this->k,
            ($h - $y3) * $this->k
        ));
    }

    /**
     * Dibuja una trama sutil de micro-seguridad repetitiva en el fondo (anti-IA, anti-clonación y anti-recorte).
     * Si un estafador intenta borrar texto con IA o recortar campos, la trama de fondo se rompe visiblemente.
     */
    public function renderMicroSecurityPattern(string $patternText, float $yStart = 55.0, float $yEnd = 245.0): void
    {
        $this->SetFont('Helvetica', 'B', 4.5);
        $this->SetTextColor(238, 236, 246); // Tono lavanda extremadamente tenue

        $fullLine = str_repeat($patternText . '   •   ', 3);
        for ($y = $yStart; $y <= $yEnd; $y += 6.0) {
            $this->SetXY(10.0, $y);
            $this->Cell(195.9, 2.5, $this->encodeText($fullLine), 0, 0, 'C');
        }
    }
}

/**
 * Implementación del generador PDF de Contratos de Servicio (Plan de Grúa UbikGo)
 * usando FPDF 1.9 y PHPQRCode.
 */
class FpdfContractPdfGenerator implements ContractPdfGeneratorInterface
{
    private const PURPLE_R = 78;
    private const PURPLE_G = 66;
    private const PURPLE_B = 147;

    private const ORANGE_R = 243;
    private const ORANGE_G = 130;
    private const ORANGE_B = 24;

    private const DARK_R = 30;
    private const DARK_G = 30;
    private const DARK_B = 38;

    private string $assetsDir;
    private PdfDigitalSignatureService $signatureService;

    public function __construct(?string $assetsDir = null, ?PdfDigitalSignatureService $signatureService = null)
    {
        $this->assetsDir = $assetsDir ?? (__DIR__ . '/../../../public/assets/contracts');
        $this->signatureService = $signatureService ?? new PdfDigitalSignatureService();
    }

    public function generate(
        ServiceContract $contract,
        Plan $plan,
        User $client,
        ?Vehicle $vehicle = null,
        string $downloadUrl = '',
        ?string $clientAddress = null,
        ?string $vehicleClassLabel = null,
        string $branchName = 'PRINCIPAL - CARACAS',
        string $sellerName = 'CANAL DIGITAL UBIKGO'
    ): string {
        $pdf = new UbikContractFpdf('P', 'mm', 'Letter');
        $pdf->SetMargins(10, 8, 10);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTitle($pdf->encodeText('Plan de Grúa UbikGo - Contrato #' . str_pad((string)($contract->getId() ?? 1), 6, '0', STR_PAD_LEFT)));
        $pdf->SetAuthor('UBIK SERVICE C.A. - UbikGo');
        $pdf->SetCreator('UbikGo Core API');

        $contractNumber = str_pad((string)($contract->getId() ?? 1), 6, '0', STR_PAD_LEFT);
        $plate = $vehicle ? $vehicle->getPlateNumber()->getValue() : 'SIN ASIGNAR';
        $verificationCode = $contract->getVerificationCode()
            ?? $contract->generateVerificationCode($contract->getStartsAt() ?? $contract->getCreatedAt());
        $officialUrl = $downloadUrl !== ''
            ? $downloadUrl
            : "https://ubikgo.com/verificar?code={$verificationCode}&plate={$plate}";

        // =========================================================================
        // PÁGINA 1: CARÁTULA DEL CONTRATO (ENCABEZADO, 3 RECUADROS Y FIRMAS/SELLO)
        // =========================================================================
        $pdf->AddPage();

        // Trama de micro-seguridad repetitiva de fondo en página 1 (anti-IA, anti-tampering)
        $cedula = $client->getIdentificationCard()->getValue();
        $patternText = "UBIK SERVICE C.A. • CONTRATO #{$contractNumber} • C.I. {$cedula} • PLACA: {$plate} • {$verificationCode}";
        $pdf->renderMicroSecurityPattern($patternText, 52.0, 255.0);

        $this->renderHeader($pdf, $officialUrl, $verificationCode);
        $this->renderTitleAndMetadata($pdf, $contract, $plan, $branchName, $sellerName);
        $this->renderClientBox($pdf, $contract, $client, $clientAddress);
        $this->renderVehicleBox($pdf, $vehicle, $vehicleClassLabel);
        $this->renderSummaryBox($pdf, $contract, $plan);
        $this->renderFooterAndSignatures($pdf, $contract, $client, $verificationCode);

        // Superponer marca de agua en diagonal de extremo a extremo según estado
        $this->renderStatusWatermark($pdf, $contract->getStatus());

        // =========================================================================
        // PÁGINA 2: CLÁUSULAS DEL CONTRATO DE ADHESIÓN (1 A 34)
        // =========================================================================
        $pdf->AddPage();
        $this->renderClausesPage($pdf, $plan);
        $this->renderStatusWatermark($pdf, $contract->getStatus());

        $rawPdf = $pdf->Output('S');
        return $this->signatureService->sign($rawPdf);
    }

    /**
     * Renderiza el encabezado superior:
     * - Izquierda/Centro-Izq: Nuevo logo UbikGo (ubikgo-logo.png)
     * - Centro-Der: "SÍGUENOS" + íconos circulares Instagram/Facebook/X + insignia redondeada "@UBIKGO / #SomosUBIK"
     * - Derecha: Recuadro con código QR que apunta a la verificación oficial del PDF
     */
    private function renderHeader(UbikContractFpdf $pdf, string $downloadUrl, string $verificationCode = ''): void
    {
        // 1. Logo nuevo UbikGo (centrado-izquierda como en el diseño de referencia)
        $logoPath = $this->assetsDir . '/ubikgo-logo.png';
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 34, 8, 66, 0, 'PNG');
        }

        // 2. Bloque de redes sociales ("SÍGUENOS" + círculos + pastilla púrpura "@UBIKGO / #SomosUBIK")
        $badgeX = 107.0;
        $badgeY = 17.5;
        $badgeW = 39.0;
        $badgeH = 12.5;

        // Texto "SÍGUENOS" arriba de la pastilla
        $pdf->SetFont('Helvetica', 'B', 6.5);
        $pdf->SetTextColor(self::ORANGE_R, self::ORANGE_G, self::ORANGE_B);
        $pdf->SetXY($badgeX + 8.5, $badgeY - 4.2);
        $pdf->Cell(14, 3.5, $pdf->encodeText('SÍGUENOS'), 0, 0, 'L');

        // 3 íconos circulares (Instagram, Facebook, X)
        $iconSize = 3.5;
        $iconY = $badgeY - 4.2;
        $igPath = $this->assetsDir . '/icon-instagram.png';
        $fbPath = $this->assetsDir . '/icon-facebook.png';
        $xPath  = $this->assetsDir . '/icon-x.png';

        if (file_exists($igPath)) {
            $pdf->Image($igPath, $badgeX + 23.0, $iconY, $iconSize, $iconSize, 'PNG');
        }
        if (file_exists($fbPath)) {
            $pdf->Image($fbPath, $badgeX + 27.2, $iconY, $iconSize, $iconSize, 'PNG');
        }
        if (file_exists($xPath)) {
            $pdf->Image($xPath, $badgeX + 31.4, $iconY, $iconSize, $iconSize, 'PNG');
        }

        // Pastilla púrpura redondeada
        $pdf->SetFillColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->RoundedRect($badgeX, $badgeY, $badgeW, $badgeH, 3.5, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 13.5);
        $pdf->SetXY($badgeX, $badgeY + 1.2);
        $pdf->Cell($badgeW, 5.5, '@UBIKGO', 0, 0, 'C');

        $pdf->SetFont('Helvetica', 'B', 8.2);
        $pdf->SetXY($badgeX, $badgeY + 6.8);
        $pdf->Cell($badgeW - 3.0, 4.2, '#SomosUBIK', 0, 0, 'R');

        // 3. Recuadro QR a la derecha
        $qrBoxX = 152.0;
        $qrBoxY = 6.5;
        $qrBoxSize = 25.5;

        $pdf->SetDrawColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetLineWidth(0.9);
        $pdf->Rect($qrBoxX, $qrBoxY, $qrBoxSize, $qrBoxSize, 'D');

        $qrTarget = $downloadUrl !== '' ? $downloadUrl : 'https://ubikgo.com/verificar';
        $this->renderQrVector($pdf, $qrTarget, $qrBoxX + 1.5, $qrBoxY + 1.5, $qrBoxSize - 3.0);

        // Leyenda anti-phishing oficial debajo del QR
        $pdf->SetFont('Helvetica', 'B', 4.8);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY($qrBoxX - 2.0, $qrBoxY + $qrBoxSize + 0.5);
        $pdf->Cell($qrBoxSize + 4.0, 2.2, $pdf->encodeText('VERIFICACIÓN OFICIAL'), 0, 1, 'C');

        $pdf->SetFont('Helvetica', 'B', 5.2);
        $pdf->SetTextColor(self::ORANGE_R, self::ORANGE_G, self::ORANGE_B);
        $pdf->SetXY($qrBoxX - 2.0, $qrBoxY + $qrBoxSize + 2.7);
        $pdf->Cell($qrBoxSize + 4.0, 2.2, $pdf->encodeText('ubikgo.com/verificar'), 0, 1, 'C');

        if ($verificationCode !== '') {
            $pdf->SetFont('Helvetica', '', 4.6);
            $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
            $pdf->SetXY($qrBoxX - 2.0, $qrBoxY + $qrBoxSize + 4.9);
            $pdf->Cell($qrBoxSize + 4.0, 2.2, $pdf->encodeText('CÓD: ' . $verificationCode), 0, 1, 'C');
        }
    }

    /**
     * Dibuja el código QR de forma vectorial usando PHPQRCode (\QRcode::text).
     */
    private function renderQrVector(UbikContractFpdf $pdf, string $url, float $x, float $y, float $size): void
    {
        class_exists(\QRcode::class);
        $tab = \QRcode::text($url, false, QR_ECLEVEL_M, 3, 0);
        if (!is_array($tab) || empty($tab)) {
            return;
        }

        $numRows = count($tab);
        $numCols = strlen($tab[0]);
        $moduleW = $size / max(1, $numCols);
        $moduleH = $size / max(1, $numRows);

        $pdf->SetFillColor(0, 0, 0);
        for ($r = 0; $r < $numRows; $r++) {
            $rowStr = $tab[$r];
            for ($c = 0; $c < $numCols; $c++) {
                if (($rowStr[$c] ?? '0') === '1') {
                    // Ligero solapamiento (+0.04mm) para evitar líneas blancas entre módulos en visores PDF
                    $pdf->Rect(
                        $x + $c * $moduleW,
                        $y + $r * $moduleH,
                        $moduleW + 0.04,
                        $moduleH + 0.04,
                        'F'
                    );
                }
            }
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $pdf->Link($x, $y, $size, $size, $url);
        }
    }

    /**
     * Renderiza el título "PLAN DE GRUA N° ..." y la fila de metadatos de vigencia, plan y costo.
     */
    private function renderTitleAndMetadata(
        UbikContractFpdf $pdf,
        ServiceContract $contract,
        Plan $plan,
        string $branchName,
        string $sellerName
    ): void {
        $contractNumber = str_pad((string)($contract->getId() ?? 1), 6, '0', STR_PAD_LEFT);
        $isPaid = $contract->getStatus() !== ContractStatus::PendingPayment;
        $endDate = $contract->getExpiresAt();
        $startDate = $contract->getStartsAt()
            ?? ($endDate?->modify('-' . max(1, $plan->getDurationMonths()) . ' months'));

        // Título central como en el diseño: PLAN DE GRUA N° 000001
        $pdf->SetFont('Helvetica', 'B', 11.5);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY(10, 39.0);
        $pdf->Cell(195.9, 5.5, $pdf->encodeText("PLAN DE GRUA N° {$contractNumber}"), 0, 1, 'C');

        // Sub-barra de metadatos del contrato (Sucursal, Plan, Costo, Vigencia Inicio - Fin, Hora, Vendedor)
        $metaY = 46.5;
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);

        // Columna 1: SUCURSAL y VENDEDOR
        $pdf->SetXY(12, $metaY);
        $pdf->Cell(17, 4.2, 'SUCURSAL:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->Cell(48, 4.2, $pdf->encodeText(mb_strtoupper($branchName)), 0, 0, 'L');

        // Columna 2: PLAN ADQUIRIDO y COSTO
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->Cell(26, 4.2, 'PLAN ADQUIRIDO:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $planLabel = $isPaid
            ? mb_strtoupper($plan->getName()) . ' ($' . number_format($plan->getPriceUsd()->getAmount(), 2) . ' USD)'
            : mb_strtoupper($plan->getName()) . ' (POR PAGAR: $' . number_format($plan->getPriceUsd()->getAmount(), 2) . ')';
        $pdf->Cell(52, 4.2, $pdf->encodeText($planLabel), 0, 0, 'L');

        // Columna 3: HORA DE ACTIVACIÓN
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->Cell(11, 4.2, 'HORA:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $horaLabel = ($isPaid && $startDate !== null)
            ? $startDate->format('h:i:s a')
            : $contract->getCreatedAt()->format('h:i:s a') . ' (SOLIC.)';
        $pdf->Cell(28, 4.2, $pdf->encodeText($horaLabel), 0, 1, 'L');

        // Segunda fila de metadatos: VENDEDOR y VIGENCIA (Inicio - Fin con fecha, hora, minuto y segundo)
        $metaY2 = 51.2;
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY(12, $metaY2);
        $pdf->Cell(17, 4.2, 'VENDEDOR:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->Cell(48, 4.2, $pdf->encodeText(mb_strtoupper($sellerName)), 0, 0, 'L');

        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->Cell(16, 4.2, 'VIGENCIA:', 0, 0, 'L');

        if ($isPaid && $startDate !== null && $endDate !== null) {
            $vigenciaText = 'DESDE ' . $startDate->format('d/m/Y h:i:s a') . ' HASTA ' . $endDate->format('d/m/Y h:i:s a');
            $pdf->SetFont('Helvetica', 'B', 7.2);
            $pdf->SetTextColor(20, 110, 45);
        } else {
            $vigenciaText = 'PENDIENTE DE PAGO (SIN VIGENCIA ACTIVA)';
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->SetTextColor(190, 35, 35);
        }
        $pdf->Cell(115, 4.2, $pdf->encodeText($vigenciaText), 0, 1, 'L');
    }

    /**
     * RECUADRO 1: DATOS DEL CLIENTE
     */
    private function renderClientBox(
        UbikContractFpdf $pdf,
        ServiceContract $contract,
        User $client,
        ?string $clientAddress
    ): void {
        $boxX = 10.0;
        $boxY = 58.0;
        $boxW = 195.9;
        $boxH = 29.0;

        // Contenedor redondeado púrpura + pestaña superior izquierda
        $pdf->SetFillColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SectionHeaderTab($boxX, $boxY, 58.0, 8.2, 3.8, 5.5);

        $pdf->SetDrawColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetLineWidth(0.75);
        $pdf->RoundedRect($boxX, $boxY, $boxW, $boxH, 3.8, 'D');

        // Título de la pestaña
        $pdf->SetFont('Helvetica', 'B', 9.2);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($boxX + 3.0, $boxY + 1.8);
        $pdf->Cell(50.0, 4.8, $pdf->encodeText('DATOS DEL CLIENTE'), 0, 0, 'C');

        // Contenido del recuadro
        $row1Y = $boxY + 10.2;
        $row2Y = $boxY + 16.5;
        $row3Y = $boxY + 22.8;

        // Fila 1: Nombre o Razón Social | Cédula / RIF
        $this->drawField($pdf, $boxX + 4.0, $row1Y, 'NOMBRE O RAZÓN SOCIAL:', mb_strtoupper($client->getFullName()), 41.0, 86.0);
        $this->drawField($pdf, $boxX + 134.0, $row1Y, 'CÉDULA / RIF:', mb_strtoupper($client->getIdentificationCard()->getValue()), 23.0, 35.0);

        // Fila 2: Teléfono | Correo Electrónico | Estado
        $this->drawField($pdf, $boxX + 4.0, $row2Y, 'TELÉFONO:', $client->getPhone()->getValue(), 19.0, 35.0);
        $this->drawField($pdf, $boxX + 58.0, $row2Y, 'CORREO:', strtolower($client->getEmail()->getValue()), 16.0, 58.0);

        $statusLabel = match ($contract->getStatus()) {
            ContractStatus::PendingPayment => 'PENDIENTE DE PAGO',
            ContractStatus::Active         => 'ACTIVO / PAGADO',
            ContractStatus::Expired        => 'VENCIDO',
            ContractStatus::Cancelled      => 'CANCELADO',
        };
        $this->drawField($pdf, $boxX + 134.0, $row2Y, 'ESTATUS:', $statusLabel, 17.0, 42.0);

        // Fila 3: Dirección
        $addressText = !empty($clientAddress) ? mb_strtoupper($clientAddress) : 'GRAN CARACAS, DISTRITO CAPITAL, VENEZUELA';
        $this->drawField($pdf, $boxX + 4.0, $row3Y, 'DIRECCIÓN:', $addressText, 20.0, 168.0);
    }

    /**
     * RECUADRO 2: VEHÍCULO ASEGURADO
     */
    private function renderVehicleBox(
        UbikContractFpdf $pdf,
        ?Vehicle $vehicle,
        ?string $vehicleClassLabel
    ): void {
        $boxX = 10.0;
        $boxY = 91.5;
        $boxW = 195.9;
        $boxH = 31.0;

        $pdf->SetFillColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SectionHeaderTab($boxX, $boxY, 58.0, 8.2, 3.8, 5.5);

        $pdf->SetDrawColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetLineWidth(0.75);
        $pdf->RoundedRect($boxX, $boxY, $boxW, $boxH, 3.8, 'D');

        $pdf->SetFont('Helvetica', 'B', 9.2);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($boxX + 3.0, $boxY + 1.8);
        $pdf->Cell(50.0, 4.8, $pdf->encodeText('VEHÍCULO ASEGURADO'), 0, 0, 'C');

        $plate  = $vehicle ? $vehicle->getPlateNumber()->getValue() : 'SIN ASIGNAR';
        $brand  = $vehicle && $vehicle->getBrandName() ? mb_strtoupper($vehicle->getBrandName()) : 'POR DEFINIR';
        $model  = $vehicle && $vehicle->getModelName() ? mb_strtoupper($vehicle->getModelName()) : 'POR DEFINIR';
        $year   = $vehicle ? (string)$vehicle->getYear() : '----';
        $color  = $vehicle ? mb_strtoupper($vehicle->getColor()) : '----';
        $class  = !empty($vehicleClassLabel) ? mb_strtoupper($vehicleClassLabel) : 'PARTICULAR / AUTOMÓVIL';
        $serialM = $vehicle && $vehicle->getEngineSerial() ? mb_strtoupper($vehicle->getEngineSerial()->getValue()) : 'N/A';
        $serialC = $vehicle ? mb_strtoupper($vehicle->getNiv()->getValue()) : 'N/A';

        $row1Y = $boxY + 10.5;
        $row2Y = $boxY + 17.2;
        $row3Y = $boxY + 23.8;

        // Fila 1: Placa | Marca | Modelo | Año
        $this->drawField($pdf, $boxX + 4.0, $row1Y, 'PLACA:', $plate, 14.0, 26.0);
        $this->drawField($pdf, $boxX + 45.0, $row1Y, 'MARCA:', $brand, 15.0, 42.0);
        $this->drawField($pdf, $boxX + 102.0, $row1Y, 'MODELO:', $model, 17.0, 45.0);
        $this->drawField($pdf, $boxX + 165.0, $row1Y, 'AÑO:', $year, 10.0, 18.0);

        // Fila 2: Color | Clase / Tipo | Uso
        $this->drawField($pdf, $boxX + 4.0, $row2Y, 'COLOR:', $color, 14.0, 32.0);
        $this->drawField($pdf, $boxX + 45.0, $row2Y, 'CLASE / TIPO:', $class, 24.0, 88.0);
        $this->drawField($pdf, $boxX + 148.0, $row2Y, 'USO:', 'PARTICULAR', 10.0, 32.0);

        // Fila 3: S/M (Serial Motor) | S/C (Serial Carrocería / NIV)
        $this->drawField($pdf, $boxX + 4.0, $row3Y, 'S/M (SERIAL MOTOR):', $serialM, 35.0, 55.0);
        $this->drawField($pdf, $boxX + 98.0, $row3Y, 'S/C (SERIAL CARROCERÍA / NIV):', $serialC, 51.0, 44.0);
    }

    /**
     * RECUADRO 3: RESUMEN DEL CONTENIDO (30 puntos + cobertura del plan + Declaración Jurada)
     */
    private function renderSummaryBox(
        UbikContractFpdf $pdf,
        ServiceContract $contract,
        Plan $plan
    ): void {
        $boxX = 10.0;
        $boxY = 127.0;
        $boxW = 195.9;
        $boxH = 97.5;

        $pdf->SetFillColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SectionHeaderTab($boxX, $boxY, 64.0, 8.2, 3.8, 5.5);

        $pdf->SetDrawColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetLineWidth(0.75);
        $pdf->RoundedRect($boxX, $boxY, $boxW, $boxH, 3.8, 'D');

        $pdf->SetFont('Helvetica', 'B', 9.2);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($boxX + 3.0, $boxY + 1.8);
        $pdf->Cell(56.0, 4.8, $pdf->encodeText('RESUMEN DEL CONTENIDO'), 0, 0, 'C');

        // Cintillo derecho en la cabecera del recuadro con el resumen cuantitativo del Plan
        $pdf->SetFont('Helvetica', 'B', 6.8);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY($boxX + 66.0, $boxY + 2.2);
        $coverageSummary = sprintf(
            '%s: %d SERVICIOS DE GRÚA (DISP: %d) | COBERTURA: %s KM (DISP: %s KM)',
            mb_strtoupper($plan->getName()),
            $plan->getMaxServices(),
            $contract->getRemainingServices(),
            number_format($plan->getMaxKm(), 0),
            number_format($contract->getRemainingKm(), 0)
        );
        $pdf->Cell($boxW - 69.0, 4.2, $pdf->encodeText($coverageSummary), 0, 0, 'R');

        // 30 ítems en 3 columnas (1..10, 11..20, 21..30) basados en planGrua.blade.php
        $col1 = [
            '1. Validez del contrato un (1) año.',
            '2. Vencido el contrato NO posee mes de gracia.',
            '3. Renovación oportuna mantiene continuidad.',
            '4. Vehículos desde el año 2000 en adelante.',
            '5. Peso máx. del vehículo dos (2) toneladas.',
            '6. Uso particular (Carros, Camionetas y Motos).',
            '7. Vehículos sin cabina de furgón.',
            '8. Vehículo operativo (no chocado previamente).',
            '9. Solicitud vía App UbikGo o Call-Center.',
            '10. Servicio de grúa exclusivamente por avería.',
        ];

        $col2 = [
            '11. Traslado: Plena vía/Resguardo a Domicilio.',
            '12. Traslado: Plena vía/Resguardo a Taller.',
            '13. Traslado: Domicilio a Taller.',
            '14. Traslado: Taller a Taller.',
            '15. Vigencia activa tras confirmación de pago.',
            sprintf('16. Incluye (%d) grúas hasta (%s) km de recorrido.', $plan->getMaxServices(), number_format($plan->getMaxKm(), 0)),
            '17. Kilómetro adicional tiene recargo extra.',
            '18. Maniobra especial (sótano/otros): 10$ a 50$.',
            '19. En choque requiere boleta de liberación vial.',
            '20. Estacionamiento oficial requiere liberación.',
        ];

        $col3 = [
            '21. Aplica por (1) neumático espichado.',
            '22. Batería o llaves dentro: recargo de 10$.',
            '23. (2) neumáticos dañados solo del mismo eje.',
            '24. Reporte de daños en traslado: máx. 24 horas.',
            '25. Reembolso requiere autorización previa.',
            '26. Reembolso sujeto a baremo y revisión.',
            '27. Requiere factura fiscal Providencia 0071.',
            '28. Factura a nombre de UBIK SERVICE C.A.',
            '29. Enviar factura digital/física y fotos de grúa.',
            '30. Pago de reembolso en máx. 7 días continuos.',
        ];

        $startY = $boxY + 10.0;
        $lineH = 4.9;
        $colW = 62.5;

        $pdf->SetFont('Helvetica', '', 6.5);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);

        for ($i = 0; $i < 10; $i++) {
            $y = $startY + ($i * $lineH);
            $pdf->SetXY($boxX + 3.5, $y);
            $pdf->Cell($colW, $lineH, $pdf->encodeText($col1[$i]), 0, 0, 'L');

            $pdf->SetXY($boxX + 3.5 + $colW, $y);
            $pdf->Cell($colW, $lineH, $pdf->encodeText($col2[$i]), 0, 0, 'L');

            $pdf->SetXY($boxX + 3.5 + ($colW * 2), $y);
            $pdf->Cell($colW, $lineH, $pdf->encodeText($col3[$i]), 0, 0, 'L');
        }

        // Línea divisoria interna para la Declaración Jurada de Origen y Destino de Fondos
        $declY = $boxY + 60.5;
        $pdf->SetDrawColor(210, 205, 230);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($boxX + 3.5, $declY, $boxX + $boxW - 3.5, $declY);

        $pdf->SetFont('Helvetica', 'B', 6.8);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY($boxX + 3.5, $declY + 1.2);
        $pdf->Cell($boxW - 7.0, 3.8, $pdf->encodeText('DECLARACIÓN JURADA DE ORIGEN Y DESTINO DE FONDOS'), 0, 1, 'C');

        $declaracion = 'Declaro bajo fe de juramento que los fondos utilizados para el pago del Plan de Grúa provienen de fuentes lícitas y están destinados '
            . 'exclusivamente al cumplimiento de las obligaciones derivadas de este contrato. Asimismo, manifiesto que ni yo ni las personas involucradas en la '
            . 'transacción estamos relacionados con actividades ilícitas como legitimación de capitales o financiamiento al terrorismo, en cumplimiento de las '
            . 'normativas legales vigentes en la República Bolivariana de Venezuela. Autorizo la verificación de esta información por las autoridades competentes y '
            . 'exonero a UBIK SERVICE C.A. de cualquier responsabilidad por información falsa o inexacta proporcionada en esta declaración.';

        $pdf->SetFont('Helvetica', '', 6.2);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->SetXY($boxX + 3.5, $declY + 5.2);
        $pdf->MultiCell($boxW - 7.0, 3.3, $pdf->encodeText($declaracion), 0, 'J');

        // Confirmación de recepción de contrato
        $pdf->SetFont('Helvetica', 'B', 6.2);
        $pdf->SetXY($boxX + 3.5, $boxY + $boxH - 6.0);
        $pdf->Cell(
            $boxW - 7.0,
            4.0,
            $pdf->encodeText('¿RECIBIÓ CONTRATO FÍSICO/DIGITAL?: SÍ [ X ]   NO [   ]    |    UUID VERIFICACIÓN: ' . $contract->getUuid()->getValue()),
            0,
            0,
            'C'
        );
    }

    /**
     * Renderiza el aviso legal inferior, las líneas de firma (Compañía y Cliente)
     * y el sello "PAGADO" (mostrado solo cuando el contrato ya fue pagado: ACTIVE, EXPIRED o CANCELLED).
     */
    private function renderFooterAndSignatures(
        UbikContractFpdf $pdf,
        ServiceContract $contract,
        User $client,
        string $verificationCode = ''
    ): void {
        // Aviso legal en púrpura negrita debajo del 3er recuadro
        $legalY = 226.5;
        $pdf->SetFont('Helvetica', 'B', 5.0);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY(10.0, $legalY);
        $legalNotice = 'EL PRESENTE PLAN DE GRÚA DEBE ESTAR FIRMADO Y SELLADO POR UBIK SERVICE C.A. DE LO CONTRARIO NO TIENE DERECHO LEGAL Y NO SE RECONOCERÁ COMO VÁLIDO PARA EL RECLAMO DE LOS SERVICIOS CONTRATADOS.';
        $pdf->Cell(195.9, 4.0, $pdf->encodeText($legalNotice), 0, 1, 'C');

        $isPaid = $contract->getStatus() !== ContractStatus::PendingPayment;

        // Sello central "UbikGo PAGADO" (se muestra cuando está ACTIVE, EXPIRED o CANCELLED; NO en PENDING_PAYMENT)
        if ($isPaid) {
            $stampPath = $this->assetsDir . '/sello-pagado.png';
            if (file_exists($stampPath)) {
                $pdf->Image($stampPath, 88.0, 232.0, 38.0, 0, 'PNG');
            }

            // Si existe la imagen de la firma del dueño (firma-empresa.png), se coloca sobre la línea izquierda
            $ownerSigPath = $this->assetsDir . '/firma-empresa.png';
            if (file_exists($ownerSigPath)) {
                $pdf->Image($ownerSigPath, 34.0, 236.0, 36.0, 0, 'PNG');
            } else {
                $pdf->SetFont('Helvetica', 'BI', 7.5);
                $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
                $pdf->SetXY(26.0, 245.5);
                $pdf->Cell(52.0, 4.0, $pdf->encodeText('UBIK SERVICE C.A.'), 0, 0, 'C');
            }
        }

        // Líneas de firma (Izquierda: Compañía, Derecha: Cliente)
        $sigLineY = 252.5;
        $pdf->SetDrawColor(30, 30, 30);
        $pdf->SetLineWidth(0.35);

        // Línea izquierda (Compañía)
        $pdf->Line(28.0, $sigLineY, 76.0, $sigLineY);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY(28.0, $sigLineY + 1.2);
        $pdf->Cell(48.0, 4.0, $pdf->encodeText('POR LA COMPAÑÍA'), 0, 0, 'C');

        // Línea derecha (Cliente)
        $pdf->Line(140.0, $sigLineY, 188.0, $sigLineY);
        $pdf->SetXY(140.0, $sigLineY + 1.2);
        $pdf->Cell(48.0, 4.0, $pdf->encodeText('FIRMA CLIENTE'), 0, 1, 'C');

        $pdf->SetFont('Helvetica', '', 6.5);
        $pdf->SetXY(140.0, $sigLineY + 5.0);
        $pdf->Cell(48.0, 3.5, $pdf->encodeText('C.I./RIF: ' . $client->getIdentificationCard()->getValue()), 0, 0, 'C');

        // Recuadro de Autenticidad Digital Criptográfica X.509 PKCS#7 y Hash SHA-256
        $boxX = 10.0;
        $boxY = 261.2;
        $boxW = 195.9;
        $boxH = 9.8;

        $pdf->SetFillColor(248, 248, 252);
        $pdf->SetDrawColor(180, 175, 215);
        $pdf->SetLineWidth(0.25);
        $pdf->RoundedRect($boxX, $boxY, $boxW, $boxH, 1.8, 'DF');

        // Línea 1: Aviso de inmutabilidad criptográfica
        $pdf->SetFont('Helvetica', 'B', 5.0);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY($boxX + 2.0, $boxY + 1.2);
        $pdf->Write(3.0, $pdf->encodeText('[SEGURIDAD CRIPTOGRÁFICA X.509 PKCS#7 / SHA-256] '));

        $pdf->SetFont('Helvetica', '', 4.7);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->Write(3.0, $pdf->encodeText('Documento digital inmutable firmado electrónicamente por UBIK SERVICE C.A. (RIF J-50450532-6).'));

        // Línea 2: Código de verificación + Hash + URL de validación
        $pdf->SetXY($boxX + 2.0, $boxY + 4.8);
        $pdf->SetFont('Helvetica', 'B', 4.8);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->Write(3.0, $pdf->encodeText('CÓDIGO DE VERIFICACIÓN: '));

        $codeText = $verificationCode !== '' ? $verificationCode : ($contract->getVerificationCode() ?? 'UBK-PENDING');
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->Write(3.0, $pdf->encodeText($codeText . '  |  '));

        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->Write(3.0, $pdf->encodeText('HASH INTEGRIDAD: '));

        $hashSnippet = $contract->getIntegrityHash()
            ? (substr($contract->getIntegrityHash(), 0, 20) . '...')
            : 'FIRMADO_DIGITALMENTE';
        $pdf->SetFont('Helvetica', '', 4.6);
        $pdf->Write(3.0, $pdf->encodeText($hashSnippet . '  |  '));

        $pdf->SetFont('Helvetica', 'B', 4.8);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->Write(3.0, $pdf->encodeText('VERIFICACIÓN PÚBLICA: '));

        $pdf->SetTextColor(self::ORANGE_R, self::ORANGE_G, self::ORANGE_B);
        $pdf->Write(3.0, $pdf->encodeText('https://ubikgo.com/verificar'));
    }

    /**
     * Renderiza la Página 2 con las 34 Cláusulas del Contrato de Adhesión.
     */
    private function renderClausesPage(UbikContractFpdf $pdf, Plan $plan): void
    {
        $pdf->SetFont('Helvetica', 'B', 8.0);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY(10.0, 10.0);
        $pdf->Cell(195.9, 5.0, $pdf->encodeText('CONDICIONES GENERALES Y CLÁUSULAS DEL CONTRATO DE ADHESIÓN - UBIK SERVICE C.A. (UBIKGO)'), 0, 1, 'C');

        $maxServices = $plan->getMaxServices();
        $maxKm = number_format($plan->getMaxKm(), 0);
        $planName = mb_strtoupper($plan->getName());

        $clausesText = "UBIK SERVICE C.A. (UBIKGO) Y EL CONTRATANTE establecen de mutuo acuerdo las siguientes cláusulas: "
            . "1- PRIMERA: El presente contrato de adhesión trata sobre un plan pre-pagado de servicio de asistencia vial de grúas adquirido solo y únicamente para el vehículo identificado anteriormente (no es una persona, sino por vehículo). "
            . "2- SEGUNDA: El presente plan de asistencia vial posee validez de un (1) año. Las fechas de vigencia del plan están descritas en el presente contrato. Vencido el lapso de tiempo del contrato del servicio, Ud. entiende que NO posee \"mes de gracia\", y en caso de necesitar utilizar nuestros servicios, deberá adquirir un nuevo plan o efectuar un pago de manera particular de así necesitarlo. O adquirir el Plan de Emergencia al momento del servicio, previa aprobación y pago vía Call Center; dicho contrato consta de ({$maxServices}) servicios de grúas dentro de la Gran Caracas, (se entiende por servicio cualquier traslado solicitado desde el lugar donde se encuentra el vehículo hasta el lugar de los límites geográficos) siempre y cuando el tramo a recorrer no exceda el kilometraje cubierto por el Plan contratado. "
            . "3- TERCERA: UBIK SERVICE C.A. podrá notificar al afiliado a través de las distintas vías de comunicación que su plan está próximo a vencerse. Si el cliente realiza la renovación antes de la fecha de culminación de su contrato contará con la continuidad del servicio, en caso contrario, deberá esperar veinte (20) días continuos para hacer uso de su plan. "
            . "4- CUARTA: Para poder adquirir el servicio de asistencia vial su vehículo debe cumplir con los siguientes requisitos: antigüedad del vehículo desde el año 2000* en adelante, peso de máximo dos (02) toneladas, uso particular (carros, camionetas, motos), sin cabina de furgón y totalmente operativo (no debe estar previamente accidentado). *Para antigüedades menores al año 2000 (1990 - 1999), el vehículo sólo puede optar por un plan Classic bajo una tarifa especial de contratación por antigüedad. "
            . "5- QUINTA: Ud. podrá solicitar nuestros servicios mediante nuestra aplicación oficial UBIKGO o nuestro Call-Center / WhatsApp de atención al afiliado. "
            . "6- SEXTA: Los servicios adquiridos mediante los planes sólo se prestarán si el vehículo sufrió una avería que le imposibilite andar y será auxiliado hacia el lugar requerido por la persona, siempre y cuando el tramo a recorrer no exceda el kilometraje cubierto por el plan contratado. "
            . "7- SÉPTIMA: El plan de asistencia vial contratado por el cliente será responsabilidad plena y absoluta de UBIK SERVICE C.A. entendiéndose así que cualquier reclamo, crítica y/o sugerencia del servicio debe ser comunicado a esta compañía. "
            . "8- OCTAVA: Los traslados de grúas autorizados por UBIK SERVICE C.A. deben tener las siguientes características: 1. Plena vía/Resguardo - Domicilio, 2. Plena vía/Resguardo - Taller, 3. Domicilio - Taller o 4. Taller - Taller. "
            . "9- NOVENA: El inicio de la vigencia del servicio de grúas contratado estará activo una vez verificado el pago, y el cliente lo podrá disfrutar conforme a las condiciones del plan adquirido. "
            . "10- DÉCIMA: El Plan {$planName} de UBIK SERVICE C.A. cuenta con ({$maxServices}) servicios de grúas manteniendo el límite de {$maxKm} kilómetros por servicio (se entiende por servicio cualquier traslado solicitado desde el lugar donde se encuentra el vehículo hasta el lugar de destino del mismo dentro de los límites geográficos); si dicho recorrido excede los kilómetros estipulados en dicho contrato, cada kilómetro adicional tendrá un costo de 3$. "
            . "11- DÉCIMA PRIMERA: Con la compra de este Plan usted está autorizando a UBIK SERVICE C.A. a utilizar su imagen a fin de que la misma sea divulgada y/o incorporada con fines publicitarios institucionales en medios de comunicación audiovisuales, radiales, gráficos, internet, vía pública y/o cualquier otro soporte conforme el plan de medios. "
            . "12- DÉCIMA SEGUNDA: De solicitar el servicio de grúa que requiera una maniobra especial, entendiéndose ésta a todas aquellas que no se encuentren en plena vía: estacionamiento, sótano y las que el operador del Call Center y operador de grúa determinen, el cliente acepta que debe realizar un pago adicional que oscila entre 10$ y 50$ por la maniobra especial para prestarle el servicio de traslado siempre y cuando el tramo a recorrer no exceda el kilometraje cubierto por el plan contratado. "
            . "13- DÉCIMA TERCERA: En caso de que el vehículo haya chocado contra otro vehículo o algún elemento de la nación UBIK SERVICE C.A. no podrá prestar sus servicios de auxilio vial hasta que las autoridades de tránsito hayan liberado el vehículo. Una vez el conductor y/o propietario del vehículo tenga la boleta de liberación, la compañía podrá prestar el servicio de grúa pertinente. "
            . "14- DÉCIMA CUARTA: En caso de que el vehículo se encuentre en un estacionamiento adscrito a un ente gubernamental y ya el cliente posea la boleta de liberación, éste debe garantizar que el vehículo se encuentre apto para ser retirado del lugar, de esta forma se agiliza el proceso. "
            . "15- DÉCIMA QUINTA: Se prestará el servicio de grúa si la falla presentada es por (1) neumático espichado, batería descargada por luces encendidas, llaves dejadas dentro del vehículo (ESTOS DOS ÚLTIMOS TENDRÁN UN RECARGO ADICIONAL DE 10$). "
            . "16- DÉCIMA SEXTA: En caso de que el vehículo tenga (2) neumáticos dañados debido al siniestro ocurrido en ese momento UBIK SERVICE C.A. solo prestará el servicio si ambos neumáticos son del mismo eje (delanteros o traseros), para poder realizar el servicio con una grúa de gancho \"Wheel Lift\". "
            . "17- DÉCIMA SÉPTIMA: De suscitarse algún daño al vehículo afiliado durante el traslado en la grúa asignada, el cliente entiende que debe informar de manera inmediata a la empresa UBIK SERVICE C.A. Pasadas las 24 horas de prestarse el servicio, la empresa NO se hace responsable por los daños ocasionados al mismo por negligencia del gruero o colaboradores. "
            . "18- DÉCIMA OCTAVA: UBIK SERVICE C.A. reconocerá y pagará el reembolso de un servicio de grúa bajo las siguientes condiciones: 1. Debe ser autorizado por la compañía. 2. Se reconocerá el monto de forma parcial o total según la revisión exhaustiva del caso presentado y el monto baremo de precios, restando el equivalente del tiempo transcurrido desde la vigencia del contrato de UBIK SERVICE C.A. 3. El cliente debe enviar una factura fiscal emitida bajo las exigencias dictadas por la Providencia Administrativa 0071 del SENIAT (recibos y notas de entrega no son válidas). 4. La factura debe contener los datos fiscales de UBIK SERVICE C.A. y datos del servicio como placa, marca y modelo del vehículo, dirección de origen y destino. 5. La factura debe ser enviada de forma digital y en físico, adjuntando fotos cuando estén enganchando el vehículo y cuando estén bajando el vehículo en el destino. 6. El reembolso se pagará a la cuenta bancaria titular del cliente solicitante transcurrido un máximo de 7 días continuos luego de recibir la factura en físico. "
            . "19- DÉCIMA NOVENA: UBIK SERVICE C.A. no reconocerá ningún servicio de asistencia vial (grúa, auxilio de batería, cambio e inflado de caucho y acompañamiento vial) tomado de forma particular cuando no sean autorizados por la compañía. Por lo tanto, no se realizará el reembolso de cantidades pagadas a proveedores externos a la compañía cuando no sean autorizados previamente por el respectivo personal de la empresa. "
            . "20- VIGÉSIMA: Un pago adicional son los incurridos en casos anteriores o aquellos que el operador del Call Center y Operador de Grúa consideren. Los mismos deben ser efectuados a UBIK SERVICE C.A. en el plazo de 24 horas de haberse prestado el servicio. En caso contrario, la empresa se reserva el derecho de cancelar la suscripción inmediata del vehículo afiliado durante un (1) año o de manera permanente (a discreción de la compañía). "
            . "21- VIGÉSIMA PRIMERA: Si el recorrido establecido por el cliente desde el lugar de la avería al lugar del destino excede el kilometraje indicado en el plan adquirido, deberá efectuar un pago adicional correspondiente a la distancia adicional. "
            . "22- VIGÉSIMA SEGUNDA: Se considerará servicio fallido cuando el cliente confirma que va a adquirir el servicio, el operador de grúa está en camino al lugar de la avería y el cliente decide anularlo sin previo aviso al operador del Call Center. El cliente perderá ese servicio, es decir será descontado de los que tenga disponibles, y en caso de no disponer de ninguno se le cobrará como un servicio particular. "
            . "23- VIGÉSIMA TERCERA: Si el lugar del servicio de destino establecido por el cliente es un taller, debe asegurar que se encuentre abierto y en disposición de recibir el vehículo a la fecha y hora de llegada del mismo. En caso contrario el cliente es enteramente responsable de efectuar un pago particular por el traslado al siguiente destino o por el tiempo de espera. "
            . "24- VIGÉSIMA CUARTA: UBIK SERVICE C.A. acepta realizar servicio a vehículos de uso particular (Motos, Automóviles, Camionetas y/o Rústicos sin cabina de furgón) con blindaje de nivel I y II únicamente si el cliente efectúa un pago particular por nivel de blindaje que posee el vehículo. "
            . "25- VIGÉSIMA QUINTA: UBIK SERVICE C.A. no prestará servicio de asistencia vial grúa a vehículos por averías propiciadas por negligencia como: atascos negligentes en barro/arena, personas bajo efectos de sustancias psicoactivas (alcohol etílico) y/o psicotrópicas (drogas), vehículo accidentado en zonas sin acceso vial, y cualquier otra avería que el operador considere negligente. "
            . "26- VIGÉSIMA SEXTA: UBIK SERVICE C.A. se reserva el derecho de enviar colaboradores (pertenecientes a la empresa) al lugar de una incidencia para constatar los datos/averías suministrados por el cliente al momento de solicitar el servicio del vehículo afiliado. El cliente autoriza a los colaboradores a verificar la información y tomar fotografías para dejar constancia del procedimiento realizado. El servicio procederá únicamente si UBIK SERVICE C.A. autoriza al operador de grúa. En caso que el cliente no permita que los colaboradores realicen el procedimiento de verificación, el servicio queda anulado. "
            . "27- VIGÉSIMA SÉPTIMA: UBIK SERVICE C.A. no prestará servicio de asistencia vial (grúa) en forma de rescate. Se entiende por rescate vehículos volcados, \"encunetados\" a más de dos (2) metros y/o condición que determine el operador del Call Center y el operador de grúa ejecutar una maniobra de rescate. "
            . "28- VIGÉSIMA OCTAVA: Verificación legal del vehículo: UBIK SERVICE C.A. verifica ante el Ministerio Público el vehículo que requiera el servicio. De encontrarse solicitado por este ente, la compañía procederá inmediatamente a la no prestación del servicio y cancelación del plan en caso de ser afiliado. Además, se vetará al cliente contratante de forma permanente y no podrá disfrutar de los servicios que ofrece la compañía. En caso que un cliente sufra de hurto de su vehículo, éste debe notificarlo a UBIK SERVICE C.A. para darle de baja en el sistema. "
            . "29- VIGÉSIMA NOVENA: UBIK SERVICE C.A. no prestará servicio de asistencia vial si el conductor y/o propietario del vehículo no posee el carnet de circulación y documento de identificación personal consigo en el momento y lugar en que el vehículo esté accidentado. "
            . "30- TRIGÉSIMA: UBIK SERVICE C.A. se reserva el derecho de cancelar y/o posponer cualquier servicio de asistencia vial (grúa) en caso de manifestaciones sociales, protestas, guerra, toque de queda, catástrofes naturales, conmoción civil que afecte las vías y el paso de los vehículos y/o cualquier eventualidad que imposibilite la normal circulación de vehículos en la vía. "
            . "31- TRIGÉSIMA PRIMERA: En caso de que el cliente cometa actos fraudulentos para solicitar los servicios de asistencia vial (grúa) y aprovecharse de la buena fe de la compañía, UBIK SERVICE C.A. procederá a la no prestación de servicio e inmediatamente a la cancelación del plan contratado; además, se vetará al cliente contratante por una duración de un (1) año o de manera permanente (a discreción de la compañía) sin reembolsar el pago recibido por la contratación del presente plan. "
            . "32- TRIGÉSIMA SEGUNDA: Se entiende por actos fraudulentos: indicar al operador del Call Center un origen y/o destino diferente al real, indicar un vehículo con diferente placa, marca, modelo, color e indicar una avería diferente a la real, afiliar un vehículo ya accidentado o cualquier información que el operador considere falsa o fraudulenta. En vista de esto, se le recomienda al cliente suministrar una información veraz y detallada. La empresa se reserva el derecho de solicitar soportes fotográficos para validar la información suministrada, cuando las condiciones así lo permitan. UBIK SERVICE C.A. se reserva el derecho de cancelar la suscripción del cliente en cualquier momento de la vigencia del plan, si la compañía así lo considera pertinente. Asimismo, la compañía se reserva el derecho de negar la renovación del plan. "
            . "33- TRIGÉSIMA TERCERA: UBIK SERVICE C.A. se reserva el derecho de afiliación o renovación del cliente cuando la empresa lo considere necesario por representar un riesgo potencial en la aceptación del presente contrato o en el incumplimiento de las políticas internas establecidas en la organización. "
            . "34- TRIGÉSIMA CUARTA: UBIK SERVICE C.A. se reserva el derecho de modificar los términos y condiciones del servicio dentro de los límites fijados por las leyes de la República Bolivariana de Venezuela, cuando la empresa lo considere necesario para mejorar su servicio.";

        $pdf->SetFont('Helvetica', '', 6.85);
        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetXY(10.0, 16.0);
        $pdf->MultiCell(195.9, 3.42, $pdf->encodeText($clausesText), 0, 'J');
    }

    /**
     * Superpone la marca de agua diagonal semitransparente de extremo a extremo según el estado:
     * - PENDING_PAYMENT -> watermark-no-pagado.png
     * - ACTIVE          -> sin marca de agua
     * - CANCELLED / EXPIRED -> watermark-cancelado.png
     */
    private function renderStatusWatermark(UbikContractFpdf $pdf, ContractStatus $status): void
    {
        if ($status === ContractStatus::Active) {
            return;
        }

        $watermarkFile = match ($status) {
            ContractStatus::PendingPayment => $this->assetsDir . '/watermark-no-pagado.png',
            ContractStatus::Cancelled,
            ContractStatus::Expired        => $this->assetsDir . '/watermark-cancelado.png',
        };

        if (file_exists($watermarkFile)) {
            // De extremo a extremo en diagonal sobre la hoja Carta (215.9 x 279.4 mm)
            $pdf->Image($watermarkFile, 12.0, 48.0, 192.0, 0, 'PNG');
        }
    }

    /**
     * Helper para dibujar un par Etiqueta + Valor con línea inferior punteada/suave.
     */
    private function drawField(
        UbikContractFpdf $pdf,
        float $x,
        float $y,
        string $label,
        string $value,
        float $labelW,
        float $valueW
    ): void {
        $pdf->SetFont('Helvetica', 'B', 7.2);
        $pdf->SetTextColor(self::PURPLE_R, self::PURPLE_G, self::PURPLE_B);
        $pdf->SetXY($x, $y);
        $pdf->Cell($labelW, 4.5, $pdf->encodeText($label), 0, 0, 'L');

        $pdf->SetFont('Helvetica', 'B', 7.4);
        $pdf->SetTextColor(self::DARK_R, self::DARK_G, self::DARK_B);
        $pdf->SetXY($x + $labelW, $y);
        $pdf->Cell($valueW, 4.5, $pdf->encodeText($value), 0, 0, 'L');

        // Línea sutil bajo el valor
        $pdf->SetDrawColor(215, 212, 232);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($x + $labelW, $y + 4.4, $x + $labelW + $valueW - 1.5, $y + 4.4);
    }
}
