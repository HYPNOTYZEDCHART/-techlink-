<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020223500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1: Enrich Product with condition, warrantyMonths, lowStockThreshold, indexes, and OrderItem with serialNumber';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item ADD serial_number VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE product ADD item_condition VARCHAR(30) DEFAULT \'NEW_SEALED\' NOT NULL, ADD warranty_months INT DEFAULT 12 NOT NULL, ADD low_stock_threshold INT DEFAULT 5 NOT NULL');
        $this->addSql('CREATE INDEX idx_product_brand ON product (brand)');
        $this->addSql('CREATE INDEX idx_product_condition ON product (item_condition)');
        $this->addSql('CREATE INDEX idx_product_sku ON product (sku)');
        $this->addSql('CREATE INDEX idx_product_price ON product (price)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_product_brand ON product');
        $this->addSql('DROP INDEX idx_product_condition ON product');
        $this->addSql('DROP INDEX idx_product_sku ON product');
        $this->addSql('DROP INDEX idx_product_price ON product');
        $this->addSql('ALTER TABLE product DROP item_condition, DROP warranty_months, DROP low_stock_threshold');
        $this->addSql('ALTER TABLE order_item DROP serial_number');
    }
}
