<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616101317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_transaction ADD sfc_user_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_transaction ADD CONSTRAINT FK_DB2CCC44354A1C9 FOREIGN KEY (sfc_user_id) REFERENCES sfc_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DB2CCC44354A1C9 ON user_transaction (sfc_user_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_transaction DROP CONSTRAINT FK_DB2CCC44354A1C9
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_DB2CCC44354A1C9
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_transaction DROP sfc_user_id
        SQL);
    }
}
