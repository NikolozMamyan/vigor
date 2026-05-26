<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Complete V1 workout program and session fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workout_program ADD estimated_duration_minutes INT DEFAULT NULL');
        $this->addSql('ALTER TABLE workout_program_exercise ADD target_weight NUMERIC(6, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE workout_session ADD program_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE workout_session ADD CONSTRAINT FK_13BEE16C3EB8070A FOREIGN KEY (program_id) REFERENCES workout_program (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_13BEE16C3EB8070A ON workout_session (program_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workout_session DROP FOREIGN KEY FK_13BEE16C3EB8070A');
        $this->addSql('DROP INDEX IDX_13BEE16C3EB8070A ON workout_session');
        $this->addSql('ALTER TABLE workout_session DROP program_id');
        $this->addSql('ALTER TABLE workout_program_exercise DROP target_weight');
        $this->addSql('ALTER TABLE workout_program DROP estimated_duration_minutes');
    }
}
