<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Tests\Functional;

use Doctrine\ORM\EntityManager;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Bundle\FrameworkBundle\Console\Application;

final class CronTest extends BaseTestCase
{
    private \Symfony\Bundle\FrameworkBundle\Console\Application $app;

    public function testSchedulesCommands(): void
    {
        $output = $this->doRun(['--min-job-interval' => 1, '--max-runtime' => 12]);
        $this->assertEquals(2, substr_count((string) $output, 'Scheduling command scheduled-every-few-seconds'), $output);
    }

    protected function setUp(): void
    {
        $this->createClient(['config' => 'persistent_db.yml']);

        if (is_file($databaseFile = self::$kernel->getCacheDir().'/database.sqlite')) {
            unlink($databaseFile);
        }

        $this->importDatabaseSchema();

        $this->app = new Application(self::$kernel);
        $this->app->setAutoExit(false);
        $this->app->setCatchExceptions(false);
    }

    private function doRun(array $args = [])
    {
        array_unshift($args, 'jms-job-queue:schedule');
        $output = new MemoryOutput();
        $this->app->run(new ArrayInput($args), $output);

        return $output->getOutput();
    }

}
