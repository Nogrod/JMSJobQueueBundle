<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use JMS\JobQueueBundle\Entity\Repository\JobManager;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('jms-job-queue:mark-incomplete', 'Internal command (do not use). It marks jobs as incomplete.')]
class MarkJobIncompleteCommand extends Command
{
    public function __construct(private readonly ManagerRegistry $registry, private readonly JobManager $jobManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('job-id', InputArgument::REQUIRED, 'The ID of the Job.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var EntityManager $em */
        $em = $this->registry->getManagerForClass(Job::class);

        /** @var Job|null $job */
        $job = $em->createQuery("SELECT j FROM ".Job::class." j WHERE j.id = :id")
            ->setParameter('id', $input->getArgument('job-id'))
            ->getOneOrNullResult();

        if ($job === null) {
            $output->writeln('<error>Job was not found.</error>');

            return 1;
        }

        $this->jobManager->closeJob($job, Job::STATE_INCOMPLETE);

        return 0;
    }
}
