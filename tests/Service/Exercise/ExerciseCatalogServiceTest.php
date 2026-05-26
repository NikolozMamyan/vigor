<?php

namespace App\Tests\Service\Exercise;

use App\Service\Exercise\ExerciseCatalogService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExerciseCatalogServiceTest extends KernelTestCase
{
    public function testItBuildsCatalogShape(): void
    {
        self::bootKernel();

        $catalog = self::getContainer()->get(ExerciseCatalogService::class)->build();

        self::assertArrayHasKey('categories', $catalog);
        self::assertArrayHasKey('exercises', $catalog);
        self::assertNotEmpty($catalog['categories']);
        self::assertNotEmpty($catalog['exercises']);
    }
}
