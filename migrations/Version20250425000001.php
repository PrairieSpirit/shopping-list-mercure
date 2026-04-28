<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250425000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create items table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE items (
                id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                text       VARCHAR(500) NOT NULL,
                is_done    TINYINT(1)   NOT NULL DEFAULT 0,
                created_at DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME     NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE items');
    }
}
