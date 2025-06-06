<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use JMS\JobQueueBundle\Entity\Job;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('jms-job-queue:clean-up', 'Cleans up jobs which exceed the maximum retention time.')]
class CleanUpCommand extends Command
{
    public function __construct(private readonly ManagerRegistry $registry, private readonly JobManager $jobManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('max-retention', null, InputOption::VALUE_REQUIRED, 'The maximum retention time (value must be parsable by DateTime).', '7 days')
            ->addOption('max-retention-succeeded', null, InputOption::VALUE_REQUIRED, 'The maximum retention time for succeeded jobs (value must be parsable by DateTime).', '1 hour')
            ->addOption('per-call', null, InputOption::VALUE_REQUIRED, 'The maximum number of jobs to clean-up per call.', 1000)
            ->addOption('jms-job-id', null, InputOption::VALUE_OPTIONAL, 'The JMS Job Id.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->registry->getManagerForClass(Job::class);
        $con = $em->getConnection();

        $this->cleanUpExpiredJobs($em, $con, $input);
        $this->collectStaleJobs($em);

        return 0;
    }

    private function collectStaleJobs(EntityManager $em): void
    {
        foreach ($this->findStaleJobs($em) as $job) {
            if ($job->isRetried()) {
                continue;
            }

            $this->jobManager->closeJob($job, Job::STATE_INCOMPLETE);
        }
    }

    /**
     * @return Job[]
     */
    private function findStaleJobs(EntityManager $em)
    {
        $excludedIds = [-1];

        do {
            $em->clear();

            /** @var Job $job */
            $job = $em->createQuery("SELECT j FROM ".Job::class." j
                                      WHERE j.state = :running AND j.workerName IS NOT NULL AND j.checkedAt < :maxAge
                                                AND j.id NOT IN (:excludedIds)")
                ->setParameter('running', Job::STATE_RUNNING)
                ->setParameter('maxAge', new \DateTime('-5 minutes'), 'datetime')
                ->setParameter('excludedIds', $excludedIds)
                ->setMaxResults(1)
                ->getOneOrNullResult();

            if ($job !== null) {
                $excludedIds[] = $job->getId();

                yield $job;
            }
        } while ($job !== null);
    }

    private function cleanUpExpiredJobs(EntityManager $em, Connection $con, InputInterface $input): void
    {
        $incomingDepsSql = $con->getDatabasePlatform()->modifyLimitQuery("SELECT 1 FROM jms_job_dependencies WHERE dest_job_id = :id", 1);

        $count = 0;
        foreach ($this->findExpiredJobs($em, $input) as $job) {
            /** @var Job $job */

            ++$count;

            $result = $con->executeQuery($incomingDepsSql, ['id' => $job->getId()]);
            if ($result->fetchOne() !== false) {
                $em->wrapInTransaction(function () use ($em, $job): void {
                    $this->resolveDependencies($em, $job);
                    $em->remove($job);
                });

                continue;
            }

            $em->remove($job);

            if ($count >= $input->getOption('per-call')) {
                break;
            }
        }

        $em->flush();
    }

    private function resolveDependencies(EntityManager $em, Job $job): void
    {
        // If this job has failed, or has otherwise not succeeded, we need to set the
        // incoming dependencies to failed if that has not been done already.
        if (! $job->isFinished()) {
            foreach ($this->jobManager->findIncomingDependencies($job) as $incomingDep) {
                if ($incomingDep->isInFinalState()) {
                    continue;
                }

                $finalState = Job::STATE_CANCELED;
                if ($job->isRunning()) {
                    $finalState = Job::STATE_FAILED;
                }

                $this->jobManager->closeJob($incomingDep, $finalState);
            }
        }

        $em->getConnection()->executeStatement("DELETE FROM jms_job_dependencies WHERE dest_job_id = :id", ['id' => $job->getId()]);
    }

    private function findExpiredJobs(EntityManager $em, InputInterface $input): \Generator
    {
        $succeededJobs = fn (array $excludedIds): mixed => $em->createQuery("SELECT j FROM ".Job::class." j WHERE j.closedAt < :maxRetentionTime AND j.originalJob IS NULL AND j.state = :succeeded AND j.id NOT IN (:excludedIds)")
            ->setParameter('maxRetentionTime', new \DateTime('-'.$input->getOption('max-retention-succeeded')))
            ->setParameter('excludedIds', $excludedIds)
            ->setParameter('succeeded', Job::STATE_FINISHED)
            ->setMaxResults(100)
            ->getResult();
        yield from $this->whileResults($succeededJobs);

        $finishedJobs = fn (array $excludedIds): mixed => $em->createQuery("SELECT j FROM ".Job::class." j WHERE j.closedAt < :maxRetentionTime AND j.originalJob IS NULL AND j.id NOT IN (:excludedIds)")
            ->setParameter('maxRetentionTime', new \DateTime('-'.$input->getOption('max-retention')))
            ->setParameter('excludedIds', $excludedIds)
            ->setMaxResults(100)
            ->getResult();
        yield from $this->whileResults($finishedJobs);

        $canceledJobs = fn (array $excludedIds): mixed => $em->createQuery("SELECT j FROM ".Job::class." j WHERE j.state = :canceled AND j.createdAt < :maxRetentionTime AND j.originalJob IS NULL AND j.id NOT IN (:excludedIds)")
            ->setParameter('maxRetentionTime', new \DateTime('-'.$input->getOption('max-retention')))
            ->setParameter('canceled', Job::STATE_CANCELED)
            ->setParameter('excludedIds', $excludedIds)
            ->setMaxResults(100)
            ->getResult();
        yield from $this->whileResults($canceledJobs);
    }

    private function whileResults(callable $resultProducer)
    {
        $excludedIds = [-1];

        do {
            /** @var Job[] $jobs */
            $jobs = $resultProducer($excludedIds);
            foreach ($jobs as $job) {
                $excludedIds[] = $job->getId();
                yield $job;
            }
        } while (! empty($jobs));
    }
}
