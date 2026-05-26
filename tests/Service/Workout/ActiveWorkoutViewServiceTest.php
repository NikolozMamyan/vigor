<?php

namespace App\Tests\Service\Workout;

use App\Service\Workout\ActiveWorkoutViewService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActiveWorkoutViewServiceTest extends KernelTestCase
{
    public function testItBuildsActiveWorkoutShape(): void
    {
        self::bootKernel();

        $workout = self::getContainer()->get(ActiveWorkoutViewService::class)->build();

        self::assertArrayHasKey('title', $workout);
        self::assertArrayHasKey('sets', $workout);
        self::assertArrayHasKey('targetLabel', $workout);
        self::assertNotEmpty($workout['sets']);
    }
}
