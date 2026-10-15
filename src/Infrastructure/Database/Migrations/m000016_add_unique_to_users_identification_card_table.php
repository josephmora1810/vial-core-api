<?php

namespace App\Infrastructure\Database\Migrations;

use PDO;

class m000016_add_unique_to_users_identification_card_table
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE users 
            ADD UNIQUE INDEX uq_users_identification_card (identification_card);
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE users 
            DROP INDEX uq_users_identification_card;
        ");
    }
}
