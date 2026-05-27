<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add app authentication sessions and profile password hash';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_profile ADD email VARCHAR(180) DEFAULT NULL');
        $this->addSql("UPDATE user_profile SET email = CONCAT(username, '@vigor.local') WHERE email IS NULL");
        $this->addSql("UPDATE user_profile SET email = 'alex@vigor.local' WHERE username = 'alexvigor'");
        $this->addSql('ALTER TABLE user_profile MODIFY email VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D95AB405E7927C74 ON user_profile (email)');
        $this->addSql('ALTER TABLE user_profile ADD password_hash VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE user_profile SET password_hash = '$2y$10$66wDKkvbEUtk8STgy/DwRug.RkXWOVoste46DOfIpvXZI4XHdwt9W' WHERE username = 'alexvigor' AND password_hash IS NULL");
        $this->addSql('CREATE TABLE auth_session (id INT AUTO_INCREMENT NOT NULL, profile_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, device_id VARCHAR(64) NOT NULL, connection_type VARCHAR(30) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', revoked_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9D790F4CCCFA12B8 (profile_id), INDEX idx_auth_session_token_hash (token_hash), INDEX idx_auth_session_device_id (device_id), INDEX idx_auth_session_expires_at (expires_at), UNIQUE INDEX UNIQ_9D790F4C90C4C4B1 (token_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE auth_session ADD CONSTRAINT FK_9D790F4CCCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE auth_session');
        $this->addSql('ALTER TABLE user_profile DROP password_hash');
        $this->addSql('DROP INDEX UNIQ_D95AB405E7927C74 ON user_profile');
        $this->addSql('ALTER TABLE user_profile DROP email');
    }
}
