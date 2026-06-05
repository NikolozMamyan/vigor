<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rebuild estimated 1RM records chronologically and remove cancelled-session records';
    }

    public function up(Schema $schema): void
    {
        $sets = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    ws.id AS set_id,
                    ws.weight,
                    ws.reps,
                    ws.completed_at,
                    session.profile_id,
                    session.status,
                    session.started_at,
                    session_exercise.exercise_id,
                    session_exercise.position AS exercise_position,
                    ws.position AS set_position
                FROM workout_set ws
                INNER JOIN workout_session_exercise session_exercise ON session_exercise.id = ws.session_exercise_id
                INNER JOIN workout_session session ON session.id = session_exercise.session_id
                WHERE ws.completed_at IS NOT NULL
                ORDER BY
                    session.profile_id,
                    session_exercise.exercise_id,
                    ws.completed_at,
                    session.started_at,
                    session_exercise.position,
                    ws.position,
                    ws.id
                SQL,
        );

        $this->connection->executeStatement('DELETE FROM personal_record');

        $bestByExercise = [];

        foreach ($sets as $set) {
            $weight = (float) $set['weight'];
            $reps = (int) $set['reps'];
            $estimate = $this->estimateOneRepMax($weight, $reps);

            $this->connection->update('workout_set', [
                'estimated_one_rep_max' => $estimate > 0 ? number_format($estimate, 2, '.', '') : null,
            ], [
                'id' => (int) $set['set_id'],
            ]);

            if (!\in_array($set['status'], ['active', 'completed'], true) || $estimate <= 0) {
                continue;
            }

            $key = $set['profile_id'].':'.$set['exercise_id'];
            $previous = $bestByExercise[$key] ?? null;

            if (null !== $previous && $estimate <= $previous) {
                continue;
            }

            $this->connection->insert('personal_record', [
                'profile_id' => (int) $set['profile_id'],
                'exercise_id' => (int) $set['exercise_id'],
                'workout_set_id' => (int) $set['set_id'],
                'metric' => 'estimated_1rm',
                'value' => number_format($estimate, 2, '.', ''),
                'previous_value' => null === $previous ? null : number_format($previous, 2, '.', ''),
                'achieved_at' => $set['completed_at'],
            ]);

            $bestByExercise[$key] = $estimate;
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The previous incorrect personal record history cannot be restored.');
    }

    private function estimateOneRepMax(float $weight, int $reps): float
    {
        if ($weight <= 0 || $weight > 2000 || $reps <= 0) {
            return 0.0;
        }

        if (1 === $reps) {
            return round($weight, 2);
        }

        return round($weight * (1 + ($reps / 30)), 2);
    }
}
