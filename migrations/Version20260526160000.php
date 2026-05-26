<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create weekly goal table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE weekly_goal (id INT AUTO_INCREMENT NOT NULL, profile_id INT NOT NULL, week_start_date DATE NOT NULL, target_workouts INT NOT NULL, target_volume INT NOT NULL, target_training_minutes INT NOT NULL, INDEX IDX_3ABF82B5CCFA12B8 (profile_id), INDEX idx_weekly_goal_week_start_date (week_start_date), UNIQUE INDEX uniq_weekly_goal_profile_week (profile_id, week_start_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE weekly_goal ADD CONSTRAINT FK_3ABF82B5CCFA12B8 FOREIGN KEY (profile_id) REFERENCES user_profile (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE weekly_goal');
    }
}
