<?php

namespace App\Tests\Service\Exercise;

use App\Entity\Exercise;
use App\Service\Exercise\ExerciseCatalogService;
use PHPUnit\Framework\TestCase;

final class ExerciseCatalogServiceUnitTest extends TestCase
{
    public function testItNormalizesExerciseForApiResponses(): void
    {
        $exercise = (new Exercise())
            ->setName('Row maison')
            ->setSlug('row-maison')
            ->setMuscleGroup('Dos')
            ->setEquipment('Halteres')
            ->setSource(Exercise::SOURCE_CUSTOM);

        $service = (new \ReflectionClass(ExerciseCatalogService::class))->newInstanceWithoutConstructor();

        self::assertSame([
            'id' => null,
            'name' => 'Row maison',
            'category' => 'Dos',
            'tag' => 'Halteres',
            'image' => 'https://placehold.co/600x400/18181b/ccff00?text=VIGOR',
            'source' => Exercise::SOURCE_CUSTOM,
            'isCustom' => true,
        ], $service->normalizeExercise($exercise));
    }
}
