<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Console;

declare(ticks=10_000_000);

use Doctrine\DBAL\Statement;
use Doctrine\DBAL\Types\Type;
use JMS\JobQueueBundle\Entity\Job;
use Symfony\Bundle\FrameworkBundle\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Records debugging information for executed commands.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Application extends BaseApplication
{
    private $insertStatStmt;

    private ?InputInterface $input = null;

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);

        $this->getDefinition()->addOption(new InputOption('--jms-job-id', null, InputOption::VALUE_REQUIRED, 'The ID of the Job.'));

        $kernel->boot();
        if ($kernel->getContainer()->getParameter('jms_job_queue.statistics')) {
            $this->insertStatStmt = "INSERT INTO jms_job_statistics (job_id, characteristic, createdAt, charValue) VALUES (:jobId, :name, :createdAt, :value)";
            register_tick_function($this->onTick(...));
        }
    }

    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;

        try {
            $rs = parent::doRun($input, $output);
            $this->saveDebugInformation();

            return $rs;
        } catch (\Exception $exception) {
            $this->saveDebugInformation($exception);

            throw $exception;
        }
    }

    public function onTick(): void
    {
        if (! $this->input->hasOption('jms-job-id') || null === $jobId = $this->input->getOption('jms-job-id')) {
            return;
        }

        $characteristics = ['memory' => memory_get_usage()];

        if (!$this->insertStatStmt instanceof Statement) {
            $this->insertStatStmt = $this->getConnection()->prepare($this->insertStatStmt);
        }

        $this->insertStatStmt->bindValue('jobId', $jobId, \PDO::PARAM_INT);
        $this->insertStatStmt->bindValue('createdAt', new \DateTime(), Type::getType('datetime'));

        foreach ($characteristics as $name => $value) {
            $this->insertStatStmt->bindValue('name', $name);
            $this->insertStatStmt->bindValue('value', $value);
            $this->insertStatStmt->execute();
        }
    }

    private function saveDebugInformation(\Exception $ex = null): void
    {
        if (! $this->input->hasOption('jms-job-id') || null === $jobId = $this->input->getOption('jms-job-id')) {
            return;
        }

        $this->getConnection()->executeUpdate(
            "UPDATE jms_jobs SET stackTrace = :trace, memoryUsage = :memoryUsage, memoryUsageReal = :memoryUsageReal WHERE id = :id",
            ['id' => $jobId, 'memoryUsage' => memory_get_peak_usage(), 'memoryUsageReal' => memory_get_peak_usage(true), 'trace' => $ex instanceof \Exception ? FlattenException::create($ex) : null],
            ['id' => \PDO::PARAM_INT, 'memoryUsage' => \PDO::PARAM_INT, 'memoryUsageReal' => \PDO::PARAM_INT, 'trace' => \PDO::PARAM_LOB]
        );
    }

    private function getConnection()
    {
        return $this->getKernel()->getContainer()->get('doctrine')->getManagerForClass(Job::class)->getConnection();
    }
}
