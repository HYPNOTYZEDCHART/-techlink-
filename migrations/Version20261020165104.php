<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261020165104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE coupon (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, discount_type VARCHAR(20) NOT NULL, discount_value INT NOT NULL, min_order_amount INT DEFAULT 0 NOT NULL, max_uses INT DEFAULT NULL, used_count INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_64BF3F0277153098 (code), INDEX idx_coupon_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_tracking (id INT AUTO_INCREMENT NOT NULL, status_step VARCHAR(50) NOT NULL, title VARCHAR(255) NOT NULL, comment LONGTEXT DEFAULT NULL, timestamp DATETIME NOT NULL, customer_order_id INT NOT NULL, INDEX IDX_498480ADA15A2E17 (customer_order_id), INDEX idx_order_tracking_step (status_step), INDEX idx_order_tracking_timestamp (timestamp), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment_transaction (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(30) NOT NULL, status VARCHAR(30) NOT NULL, amount INT NOT NULL, reference VARCHAR(100) NOT NULL, phone_number VARCHAR(30) DEFAULT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, customer_order_id INT NOT NULL, UNIQUE INDEX UNIQ_84BBD50BAEA34913 (reference), INDEX IDX_84BBD50BA15A2E17 (customer_order_id), INDEX idx_payment_tx_reference (reference), INDEX idx_payment_tx_status (status), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL, sess_data LONGBLOB NOT NULL, sess_time INT NOT NULL, sess_lifetime INT NOT NULL, PRIMARY KEY (sess_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_tracking ADD CONSTRAINT FK_498480ADA15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_transaction ADD CONSTRAINT FK_84BBD50BA15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customer_order ADD payment_method VARCHAR(30) DEFAULT \'CASH_ON_DELIVERY\' NOT NULL, ADD delivery_method VARCHAR(30) DEFAULT \'TIAK_TIAK\' NOT NULL, ADD coupon_code VARCHAR(50) DEFAULT NULL, ADD discount_amount INT DEFAULT 0 NOT NULL, ADD escrow_status VARCHAR(30) DEFAULT \'NONE\' NOT NULL, ADD tracking_number VARCHAR(50) DEFAULT NULL, ADD notes LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3B1CE6A33E1C9C18 ON customer_order (tracking_number)');
        $this->addSql('CREATE INDEX idx_order_tracking_number ON customer_order (tracking_number)');
        $this->addSql('CREATE INDEX idx_order_status ON customer_order (status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_tracking DROP FOREIGN KEY FK_498480ADA15A2E17');
        $this->addSql('ALTER TABLE payment_transaction DROP FOREIGN KEY FK_84BBD50BA15A2E17');
        $this->addSql('DROP TABLE coupon');
        $this->addSql('DROP TABLE order_tracking');
        $this->addSql('DROP TABLE payment_transaction');
        $this->addSql('DROP TABLE sessions');
        $this->addSql('DROP INDEX UNIQ_3B1CE6A33E1C9C18 ON customer_order');
        $this->addSql('DROP INDEX idx_order_tracking_number ON customer_order');
        $this->addSql('DROP INDEX idx_order_status ON customer_order');
        $this->addSql('ALTER TABLE customer_order DROP payment_method, DROP delivery_method, DROP coupon_code, DROP discount_amount, DROP escrow_status, DROP tracking_number, DROP notes');
    }
}
