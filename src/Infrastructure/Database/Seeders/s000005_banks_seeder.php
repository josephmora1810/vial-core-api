<?php

namespace App\Infrastructure\Database\Seeders;

use PDO;

class s000005_banks_seeder
{
    public function run(PDO $db): void
    {
        $banks = [
            ['code' => '0102', 'name' => 'Banco de Venezuela'],
            ['code' => '0104', 'name' => 'Banco Venezolano de Crédito'],
            ['code' => '0105', 'name' => 'Banco Mercantil'],
            ['code' => '0108', 'name' => 'BBVA Provincial'],
            ['code' => '0114', 'name' => 'Bancaribe'],
            ['code' => '0115', 'name' => 'Banco Exterior'],
            ['code' => '0128', 'name' => 'Banco Caroní'],
            ['code' => '0134', 'name' => 'Banesco'],
            ['code' => '0137', 'name' => 'Banco Sofitasa'],
            ['code' => '0138', 'name' => 'Banco Plaza'],
            ['code' => '0151', 'name' => 'BFC Banco Fondo Común'],
            ['code' => '0156', 'name' => '100% Banco'],
            ['code' => '0157', 'name' => 'DelSur Banco Universal'],
            ['code' => '0163', 'name' => 'Banco del Tesoro'],
            ['code' => '0168', 'name' => 'Bancrecer'],
            ['code' => '0169', 'name' => 'R4 Banco Microfinanciero'],
            ['code' => '0171', 'name' => 'Banco Activo'],
            ['code' => '0172', 'name' => 'Bancamiga'],
            ['code' => '0174', 'name' => 'Banplus'],
            ['code' => '0175', 'name' => 'Banco Digital de los Trabajadores (Bicentenario)'],
            ['code' => '0177', 'name' => 'Banfanb'],
            ['code' => '0191', 'name' => 'Banco Nacional de Crédito (BNC)'],
        ];

        $stmt = $db->prepare("
            INSERT INTO banks (code, name, is_active)
            VALUES (:code, :name, 1)
            ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = 1
        ");

        foreach ($banks as $bank) {
            $stmt->execute($bank);
        }
    }
}
