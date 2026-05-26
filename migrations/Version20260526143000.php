<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create workout program tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE workout_program (id INT AUTO_INCREMENT NOT NULL, profile_id INT NOT NULL, name VARCHAR(120) NOT NULL, description VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EF45EF68CCFA12B8 (profile_id), INDEX idx_workout_program_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE workout_program_exercise (id INT AUTO_INCREMENT NOT NULL, program_id INT NOT NULL, exercise_id INT NOT NULL, position INT NOT NULL, target_sets INT NOT NULL, target_reps_min INT NOT NULL, target_reps_max INT NOT NULL, rest_seconds INT NOT NULL, INDEX IDX_BFFB85493EB8070A (program_id), INDEX IDX_BFFB8549C1E6F1F (exercise_id), INDEX idx_workout_program_exercise_position (position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE workout_program ADD CONSTRAINT FK_EF45EF68CCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workout_program_exercise ADD CONSTRAINT FK_BFFB85493EB8070A FOREIGN KEY (program_id) REFERENCES workout_program (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workout_program_exercise ADD CONSTRAINT FK_BFFB8549C1E6F1F FOREIGN KEY (exercise_id) REFERENCES exercise (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE workout_program_exercise');
        $this->addSql('DROP TABLE workout_program');
    }
}
