<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260717013700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_item ADD selected_color VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE order_item ADD selected_color VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD colors VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_item DROP selected_color');
        $this->addSql('ALTER TABLE order_item DROP selected_color');
        $this->addSql('ALTER TABLE product DROP colors');
    }
}
