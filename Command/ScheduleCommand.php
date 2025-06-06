<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ObjectManager;
use JMS\JobQueueBundle\Console\CronCommand;
use JMS\JobQueueBundle\Cron\CommandScheduler;
use JMS\JobQueueBundle\Cron\JobScheduler;
use JMS\JobQueueBundle\Entity\CronJob;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('jms-job-queue:schedule', 'Schedules jobs at defined intervals')]
class ScheduleCommand extends Command
{
    public function __construct(private readonly ManagerRegistry $registry, private readonly iterable $schedulers, private readonly iterable $cronCommands)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-runtime', null, InputOption::VALUE_REQUIRED, 'The maximum runtime of this command.', 3600)
            ->addOption('min-job-interval', null, InputOption::VALUE_REQUIRED, 'The minimum time between schedules jobs in seconds.', 5)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $maxRuntime = $input->getOption('max-runtime');
        if ($maxRuntime > 300) {
            $maxRuntime += random_int(0, (int)($input->getOption('max-runtime') * 0.05));
        }

        if ($maxRuntime <= 0) {
            throw new \RuntimeException('Max. runtime must be greater than zero.');
        }

        $minJobInterval = (int)$input->getOption('min-job-interval');
        if ($minJobInterval <= 0) {
            throw new \RuntimeException('Min. job interval must be greater than zero.');
        }

        $jobSchedulers = $this->populateJobSchedulers();
        if ($jobSchedulers === []) {
            $output->writeln('No job schedulers found, exiting...');

            return 0;
        }

        $jobsLastRunAt = $this->populateJobsLastRunAt($this->registry->getManagerForClass(CronJob::class), $jobSchedulers);

        $startedAt = time();
        while (true) {
            $lastRunAt = microtime(true);
            $now = time();
            if ($now - $startedAt > $maxRuntime) {
                $output->writeln('Max. runtime reached, exiting...');
                break;
            }

            $this->scheduleJobs($output, $jobSchedulers, $jobsLastRunAt);

            $timeToWait = (int) ((microtime(true) - $lastRunAt + $minJobInterval) * 1E6);
            if ($timeToWait > 0) {
                usleep($timeToWait);
            }
        }

        return 0;
    }

    /**
     * @param JobScheduler[] $jobSchedulers
     * @param \DateTime[] $jobsLastRunAt
     */
    private function scheduleJobs(OutputInterface $output, array $jobSchedulers, array &$jobsLastRunAt): void
    {
        foreach ($jobSchedulers as $name => $scheduler) {
            $lastRunAt = $jobsLastRunAt[$name];

            if (! $scheduler->shouldSchedule($name, $lastRunAt)) {
                continue;
            }

            [$success, $newLastRunAt] = $this->acquireLock($name, $lastRunAt);
            $jobsLastRunAt[$name] = $newLastRunAt;

            if ($success) {
                $output->writeln('Scheduling command '.$name);
                $job = $scheduler->createJob($name, $lastRunAt);
                $em = $this->registry->getManagerForClass(Job::class);
                $em->persist($job);
                $em->flush($job);
            }
        }
    }

    private function acquireLock(string $commandName, \DateTime $lastRunAt): array
    {
        /** @var ObjectManager $em */
        $em = $this->registry->getManagerForClass(CronJob::class);
        $con = $em->getConnection();

        $now = new \DateTime();
        $affectedRows = $con->executeStatement(
            "UPDATE jms_cron_jobs SET lastRunAt = :now WHERE command = :command AND lastRunAt = :lastRunAt",
            ['now' => $now, 'command' => $commandName, 'lastRunAt' => $lastRunAt],
            ['now' => 'datetime', 'lastRunAt' => 'datetime']
        );

        if ($affectedRows > 0) {
            return [true, $now];
        }

        /** @var CronJob $cronJob */
        $cronJob = $em->createQuery("SELECT j FROM ".CronJob::class." j WHERE j.command = :command")
            ->setParameter('command', $commandName)
            ->setHint(Query::HINT_REFRESH, true)
            ->getSingleResult();

        return [false, $cronJob->getLastRunAt()];
    }

    private function populateJobSchedulers(): array
    {
        $schedulers = [];
        foreach ($this->schedulers as $scheduler) {
            /** @var JobScheduler $scheduler */
            foreach ($scheduler->getCommands() as $name) {
                $schedulers[$name] = $scheduler;
            }
        }

        foreach ($this->cronCommands as $command) {
            /** @var CronCommand $command */
            if (! $command instanceof Command) {
                throw new \RuntimeException('CronCommand should only be used on Symfony commands.');
            }

            $schedulers[$command->getName()] = new CommandScheduler($command->getName(), $command);
        }

        return $schedulers;
    }

    private function populateJobsLastRunAt(ObjectManager $em, array $jobSchedulers): array
    {
        $jobsLastRunAt = [];

        foreach ($em->getRepository(CronJob::class)->findAll() as $job) {
            /** @var CronJob $job */
            $jobsLastRunAt[$job->getCommand()] = $job->getLastRunAt();
        }

        foreach (array_keys($jobSchedulers) as $name) {
            if (! isset($jobsLastRunAt[$name])) {
                $job = new CronJob($name);
                $em->persist($job);
                $jobsLastRunAt[$name] = $job->getLastRunAt();
            }
        }

        $em->flush();

        return $jobsLastRunAt;
    }
}
