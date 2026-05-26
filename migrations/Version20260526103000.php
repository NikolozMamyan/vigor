<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create VIGOR workout tracking core tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_profile (id INT AUTO_INCREMENT NOT NULL, display_name VARCHAR(100) NOT NULL, username VARCHAR(60) NOT NULL, avatar_url VARCHAR(255) DEFAULT NULL, joined_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', record_metric_preference VARCHAR(40) NOT NULL, weekly_workout_goal INT NOT NULL, weekly_volume_goal INT NOT NULL, preferred_weight_unit VARCHAR(10) NOT NULL, UNIQUE INDEX UNIQ_D95AB405F85E0677 (username), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE exercise (id INT AUTO_INCREMENT NOT NULL, created_by_profile_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL, muscle_group VARCHAR(60) NOT NULL, equipment VARCHAR(60) NOT NULL, image_url VARCHAR(255) DEFAULT NULL, source VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_685D7BA033B1298 (created_by_profile_id), INDEX idx_exercise_source (source), INDEX idx_exercise_muscle_group (muscle_group), UNIQUE INDEX UNIQ_685D7BA0989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE workout_session (id INT AUTO_INCREMENT NOT NULL, profile_id INT NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', duration_seconds INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_13BEE16CCCFA12B8 (profile_id), INDEX idx_workout_session_status (status), INDEX idx_workout_session_started_at (started_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE workout_session_exercise (id INT AUTO_INCREMENT NOT NULL, session_id INT NOT NULL, exercise_id INT NOT NULL, position INT NOT NULL, target_sets INT DEFAULT NULL, target_reps_min INT DEFAULT NULL, target_reps_max INT DEFAULT NULL, rest_seconds INT NOT NULL, INDEX IDX_F9A6E3EA613FECDF (session_id), INDEX IDX_F9A6E3EAC1E6F1F (exercise_id), INDEX idx_workout_session_exercise_position (position), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE workout_set (id INT AUTO_INCREMENT NOT NULL, session_exercise_id INT NOT NULL, position INT NOT NULL, weight NUMERIC(6, 2) NOT NULL, reps INT NOT NULL, completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', estimated_one_rep_max NUMERIC(7, 2) DEFAULT NULL, INDEX IDX_5E3A460A68AB0AE (session_exercise_id), INDEX idx_workout_set_completed_at (completed_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE personal_record (id INT AUTO_INCREMENT NOT NULL, profile_id INT NOT NULL, exercise_id INT NOT NULL, workout_set_id INT NOT NULL, metric VARCHAR(40) NOT NULL, value NUMERIC(7, 2) NOT NULL, previous_value NUMERIC(7, 2) DEFAULT NULL, achieved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8944B7E8CCFA12B8 (profile_id), INDEX IDX_8944B7E8C1E6F1F (exercise_id), INDEX IDX_8944B7E8FA17D886 (workout_set_id), INDEX idx_personal_record_metric (metric), INDEX idx_personal_record_achieved_at (achieved_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_685D7BA033B1298 FOREIGN KEY (created_by_profile_id) REFERENCES user_profile (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE workout_session ADD CONSTRAINT FK_13BEE16CCCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workout_session_exercise ADD CONSTRAINT FK_F9A6E3EA613FECDF FOREIGN KEY (session_id) REFERENCES workout_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workout_session_exercise ADD CONSTRAINT FK_F9A6E3EAC1E6F1F FOREIGN KEY (exercise_id) REFERENCES exercise (id)');
        $this->addSql('ALTER TABLE workout_set ADD CONSTRAINT FK_5E3A460A68AB0AE FOREIGN KEY (session_exercise_id) REFERENCES workout_session_exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personal_record ADD CONSTRAINT FK_8944B7E8CCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personal_record ADD CONSTRAINT FK_8944B7E8C1E6F1F FOREIGN KEY (exercise_id) REFERENCES exercise (id)');
        $this->addSql('ALTER TABLE personal_record ADD CONSTRAINT FK_8944B7E8FA17D886 FOREIGN KEY (workout_set_id) REFERENCES workout_set (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE personal_record');
        $this->addSql('DROP TABLE workout_set');
        $this->addSql('DROP TABLE workout_session_exercise');
        $this->addSql('DROP TABLE workout_session');
        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE user_profile');
    }
}
