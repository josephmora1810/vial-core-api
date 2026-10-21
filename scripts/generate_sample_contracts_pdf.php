<?php

/**
 * Script para generar los 3 tipos de Contratos de Servicio en PDF con datos de prueba (Fake)
 * y guardarlos en `storage/contracts/` para revisión visual de diseño.
 *
 * Uso:
 *   php scripts/generate_sample_contracts_pdf.php
 */

require_once __DIR__ . '/../autoload.php';

use App\Domain\Contracts\Entities\Plan;
use App\Domain\Contracts\Entities\ServiceContract;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Shared\ValueObjects\Uuid;
use App\Domain\Users\Entities\User;
use App\Domain\Users\ValueObjects\Email;
use App\Domain\Users\ValueObjects\Names;
use App\Domain\Users\ValueObjects\NationalId;
use App\Domain\Users\ValueObjects\Phone;
use App\Domain\Users\ValueObjects\Surnames;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use App\Infrastructure\Pdf\FpdfContractPdfGenerator;

$outputDir = __DIR__ . '/../storage/contracts';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$generator = new FpdfContractPdfGenerator();

// 1. Datos Fake de Plan, Cliente y Vehículo
$plan = new Plan(
    name: 'Plan Gold Vial',
    priceUsd: 60.00,
    durationMonths: 12,
    maxServices: 5,
    maxKm: 120.0,
    description: 'Asistencia vial completa en la Gran Caracas',
    id: 2
);

$client = User::create(
    uuid: Uuid::v7(),
    names: new Names('Carlos Eduardo'),
    surnames: new Surnames('Pérez Rodríguez'),
    email: new Email('carlos.perez@ubikgo.com'),
    password: null,
    phone: new Phone('04141234567'),
    identificationCard: new NationalId('V-18456789')
);

$vehicle = Vehicle::create(
    userId: 1,
    plateNumber: new PlateNumber('AB123CD'),
    niv: new Niv('8Z1ABCDE123456789'),
    engineSerial: new EngineSerial('2ZR9876543'),
    year: 2022,
    color: 'BLANCO PERLA',
    vehicleClassId: 1,
    vehicleSubclassId: 1,
    brandId: 1,
    modelId: 1,
    brandName: 'TOYOTA',
    modelName: 'COROLLA',
    isVerified: true
);

$fakeAddress = 'AV. FRANCISCO DE MIRANDA, RES. PARQUE CRISTAL, CHACAO, CARACAS';
$fakeClassLabel = 'AUTOMÓVIL / SEDAN';
$secretKey = $_ENV['APP_KEY'] ?? 'ubikgo-secret-contract-key';
$appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8088', '/');

// =========================================================================
// 1. CONTRATO PENDIENTE DE PAGO (Marca de agua diagonal "NO PAGADO")
// =========================================================================
$contractPending = new ServiceContract(
    clientId: 1,
    planId: 2,
    remainingServices: 5,
    remainingKm: 120.0,
    vehicleId: 1,
    status: ContractStatus::PendingPayment,
    expiresAt: null,
    id: 101
);
$codePending = $contractPending->generateVerificationCode();
$contractPending->setIntegrityHash($contractPending->calculateIntegrityHash($secretKey, $vehicle->getPlateNumber()->getValue()));
$urlPending = "{$appUrl}/api/v1/contracts/{$codePending}/pdf";

$filePending = $outputDir . '/contrato_1_pendiente_pago.pdf';
file_put_contents(
    $filePending,
    $generator->generate(
        contract: $contractPending,
        plan: $plan,
        client: $client,
        vehicle: $vehicle,
        downloadUrl: $urlPending,
        clientAddress: $fakeAddress,
        vehicleClassLabel: $fakeClassLabel,
        branchName: 'PRINCIPAL - CARACAS',
        sellerName: 'EJECUTIVO VENTAS UBIKGO'
    )
);
echo "[OK] Generado (PENDING_PAYMENT): " . realpath($filePending) . " [Código: {$codePending}]" . PHP_EOL;

// =========================================================================
// 2. CONTRATO ACTIVO / PAGADO (Sin marca de agua, con sello "PAGADO" y fechas)
// =========================================================================
$contractActive = new ServiceContract(
    clientId: 1,
    planId: 2,
    remainingServices: 5,
    remainingKm: 120.0,
    vehicleId: 1,
    status: ContractStatus::PendingPayment,
    expiresAt: null,
    id: 102
);
$contractActive->activate(12);
$codeActive = $contractActive->generateVerificationCode();
$contractActive->setIntegrityHash($contractActive->calculateIntegrityHash($secretKey, $vehicle->getPlateNumber()->getValue()));
$urlActive = "{$appUrl}/api/v1/contracts/{$codeActive}/pdf";

$fileActive = $outputDir . '/contrato_2_activo_pagado.pdf';
file_put_contents(
    $fileActive,
    $generator->generate(
        contract: $contractActive,
        plan: $plan,
        client: $client,
        vehicle: $vehicle,
        downloadUrl: $urlActive,
        clientAddress: $fakeAddress,
        vehicleClassLabel: $fakeClassLabel,
        branchName: 'PRINCIPAL - CARACAS',
        sellerName: 'EJECUTIVO VENTAS UBIKGO'
    )
);
echo "[OK] Generado (ACTIVE):          " . realpath($fileActive) . " [Código: {$codeActive}]" . PHP_EOL;

// =========================================================================
// 3. CONTRATO CANCELADO / VENCIDO (Marca de agua "CANCELADO" + sello "PAGADO")
// =========================================================================
$contractCancelled = new ServiceContract(
    clientId: 1,
    planId: 2,
    remainingServices: 2,
    remainingKm: 45.0,
    vehicleId: 1,
    status: ContractStatus::PendingPayment,
    expiresAt: null,
    id: 103
);
$contractCancelled->activate(12);
$contractCancelled->cancel();
$codeCancelled = $contractCancelled->generateVerificationCode();
$contractCancelled->setIntegrityHash($contractCancelled->calculateIntegrityHash($secretKey, $vehicle->getPlateNumber()->getValue()));
$urlCancelled = "{$appUrl}/api/v1/contracts/{$codeCancelled}/pdf";

$fileCancelled = $outputDir . '/contrato_3_cancelado_vencido.pdf';
file_put_contents(
    $fileCancelled,
    $generator->generate(
        contract: $contractCancelled,
        plan: $plan,
        client: $client,
        vehicle: $vehicle,
        downloadUrl: $urlCancelled,
        clientAddress: $fakeAddress,
        vehicleClassLabel: $fakeClassLabel,
        branchName: 'PRINCIPAL - CARACAS',
        sellerName: 'EJECUTIVO VENTAS UBIKGO'
    )
);
echo "[OK] Generado (CANCELLED):       " . realpath($fileCancelled) . " [Código: {$codeCancelled}]" . PHP_EOL;
