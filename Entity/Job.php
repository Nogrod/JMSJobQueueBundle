<?php

declare(strict_types=1);

/*
 * Copyright 2012 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\JobQueueBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JMS\JobQueueBundle\Exception\InvalidStateTransitionException;
use JMS\JobQueueBundle\Exception\LogicException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
#[ORM\Entity]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\Table(name: 'jms_jobs')]
#[ORM\Index(columns: ['command'], name: 'cmd_search_index')]
#[ORM\Index(columns: ['state', 'priority', 'id'], name: 'sorting_index')]
class Job implements \Stringable
{
    /** State if job is inserted, but not yet ready to be started. */
    final public const STATE_NEW = 'new';

    /**
     * State if job is inserted, and might be started.
     *
     * It is important to note that this does not automatically mean that all
     * jobs of this state can actually be started, but you have to check
     * isStartable() to be absolutely sure.
     *
     * In contrast to NEW, jobs of this state at least might be started,
     * while jobs of state NEW never are allowed to be started.
     */
    final public const STATE_PENDING = 'pending';

    /** State if job was never started, and will never be started. */
    final public const STATE_CANCELED = 'canceled';

    /** State if job was started and has not exited, yet. */
    final public const STATE_RUNNING = 'running';

    /** State if job exists with a successful exit code. */
    final public const STATE_FINISHED = 'finished';

    /** State if job exits with a non-successful exit code. */
    final public const STATE_FAILED = 'failed';

    /** State if job exceeds its configured maximum runtime. */
    final public const STATE_TERMINATED = 'terminated';

    /**
     * State if an error occurs in the runner command.
     *
     * The runner command is the command that actually launches the individual
     * jobs. If instead an error occurs in the job command, this will result
     * in a state of FAILED.
     */
    final public const STATE_INCOMPLETE = 'incomplete';

    /**
     * State if an error occurs in the runner command.
     *
     * The runner command is the command that actually launches the individual
     * jobs. If instead an error occurs in the job command, this will result
     * in a state of FAILED.
     */
    final public const DEFAULT_QUEUE = 'default';

    final public const MAX_QUEUE_LENGTH = 50;

    final public const PRIORITY_LOW = -5;

    final public const PRIORITY_DEFAULT = 0;

    final public const PRIORITY_HIGH = 5;

    #[ORM\Id, ORM\Column(type: Types::BIGINT, options: ['unsigned' => true]), ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 15)]
    private ?string $state = null;

    #[ORM\Column(type: Types::STRING, length: Job::MAX_QUEUE_LENGTH)]
    private ?string $queue = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $priority = 0;

    #[ORM\Column(name: 'createdAt', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'startedAt', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(name: 'checkedAt', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checkedAt = null;

    #[ORM\Column(name: 'workerName', type: Types::STRING, length: 50, nullable: true)]
    private ?string $workerName = null;

    #[ORM\Column(name: 'executeAfter', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $executeAfter = null;

    #[ORM\Column(name: 'closedAt', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $closedAt = null;

    /**
     * @var Collection<int, Job>
     */
    #[ORM\ManyToMany(targetEntity: 'Job', fetch: 'EAGER')]
    #[ORM\JoinTable(name: 'jms_job_dependencies', joinColumns: [new ORM\JoinColumn(name: 'source_job_id', referencedColumnName: 'id')], inverseJoinColumns: [new ORM\JoinColumn(name: 'dest_job_id', referencedColumnName: 'id')])]
    private Collection $dependencies;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $output = null;

    #[ORM\Column(name: 'errorOutput', type: Types::TEXT, nullable: true)]
    private ?string $errorOutput = null;

    #[ORM\Column(name: 'exitCode', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $exitCode = null;

    #[ORM\Column(name: 'maxRuntime', type: Types::SMALLINT, options: ['unsigned' => true])]
    private ?int $maxRuntime = 0;

    #[ORM\Column(name: 'maxRetries', type: Types::SMALLINT, options: ['unsigned' => true])]
    private ?int $maxRetries = 0;

    #[ORM\ManyToOne(targetEntity: 'Job', inversedBy: 'retryJobs')]
    #[ORM\JoinColumn(name: 'originalJob_id', referencedColumnName: 'id')]
    private ?Job $originalJob = null;

    /**
     * @var Collection<int, Job> */
    #[ORM\OneToMany(mappedBy: 'originalJob', targetEntity: 'Job', cascade: ['persist', 'remove', 'detach', 'refresh'])]
    private Collection $retryJobs;

    #[ORM\Column(name: 'stackTrace', type: Types::JSON, nullable: true)]
    private $stackTrace;

    #[ORM\Column(type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $runtime = null;

    #[ORM\Column(name: 'memoryUsage', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $memoryUsage = null;

    #[ORM\Column(name: 'memoryUsageReal', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $memoryUsageReal = null;

    #[ORM\Column(type: Types::STRING)]
    private string $command;

    #[ORM\Column(type: Types::JSON)]
    private array $args = [];

    /**
     * This may store any entities which are related to this job, and are
     * managed by Doctrine.
     *
     * It is effectively a many-to-any association.
     */
    private Collection $relatedEntities;

    public static function create(string $command, array $args = [], bool $confirmed = true, string $queue = self::DEFAULT_QUEUE, string $priority = self::PRIORITY_DEFAULT): Job
    {
        return new self($command, $args, $confirmed, $queue, $priority);
    }

    public static function isNonSuccessfulFinalState(string $state): bool
    {
        return in_array($state, [self::STATE_CANCELED, self::STATE_FAILED, self::STATE_INCOMPLETE, self::STATE_TERMINATED], true);
    }

    public static function getStates(): array
    {
        return [self::STATE_NEW, self::STATE_PENDING, self::STATE_CANCELED, self::STATE_RUNNING, self::STATE_FINISHED, self::STATE_FAILED, self::STATE_TERMINATED, self::STATE_INCOMPLETE];
    }

    public function __construct(string $command, array $args = [], bool $confirmed = true, string $queue = self::DEFAULT_QUEUE, int $priority = self::PRIORITY_DEFAULT)
    {
        if (trim($queue) === '') {
            throw new \InvalidArgumentException('$queue must not be empty.');
        }

        if (strlen($queue) > self::MAX_QUEUE_LENGTH) {
            throw new \InvalidArgumentException(sprintf('The maximum queue length is %d, but got "%s" (%d chars).', self::MAX_QUEUE_LENGTH, $queue, strlen($queue)));
        }
        $this->command = $command;
        $this->args = $args;
        $this->state = $confirmed ? self::STATE_PENDING : self::STATE_NEW;
        $this->queue = $queue;
        $this->priority = $priority * -1;
        $this->createdAt = new \DateTime();
        $this->executeAfter = new \DateTime();
        $this->executeAfter = $this->executeAfter->modify('-1 second');

        $this->dependencies = new ArrayCollection();
        $this->retryJobs = new ArrayCollection();
        $this->relatedEntities = new ArrayCollection();
    }

    public function __clone()
    {
        $this->state = self::STATE_PENDING;
        $this->createdAt = new \DateTime();
        $this->startedAt = null;
        $this->checkedAt = null;
        $this->closedAt = null;
        $this->workerName = null;
        $this->output = null;
        $this->errorOutput = null;
        $this->exitCode = null;
        $this->stackTrace = null;
        $this->runtime = null;
        $this->memoryUsage = null;
        $this->memoryUsageReal = null;
        $this->relatedEntities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setWorkerName(?string $workerName): void
    {
        $this->workerName = $workerName;
    }

    public function getWorkerName(): ?string
    {
        return $this->workerName;
    }

    public function getPriority(): int
    {
        return $this->priority * -1;
    }

    public function isInFinalState(): bool
    {
        return ! $this->isNew() && ! $this->isPending() && ! $this->isRunning();
    }

    public function isStartable(): bool
    {
        foreach ($this->dependencies as $dep) {
            if ($dep->getState() !== self::STATE_FINISHED) {
                return false;
            }
        }

        return true;
    }

    public function setState($newState): void
    {
        if ($newState === $this->state) {
            return;
        }

        switch ($this->state) {
            case self::STATE_NEW:
                if (! in_array($newState, [self::STATE_PENDING, self::STATE_CANCELED], true)) {
                    throw new InvalidStateTransitionException($this, $newState, [self::STATE_PENDING, self::STATE_CANCELED]);
                }

                if (self::STATE_CANCELED === $newState) {
                    $this->closedAt = new \DateTime();
                }

                break;

            case self::STATE_PENDING:
                if (! in_array($newState, [self::STATE_RUNNING, self::STATE_CANCELED], true)) {
                    throw new InvalidStateTransitionException($this, $newState, [self::STATE_RUNNING, self::STATE_CANCELED]);
                }

                if ($newState === self::STATE_RUNNING) {
                    $this->startedAt = new \DateTime();
                    $this->checkedAt = new \DateTime();
                } elseif ($newState === self::STATE_CANCELED) {
                    $this->closedAt = new \DateTime();
                }

                break;

            case self::STATE_RUNNING:
                if (! in_array($newState, [self::STATE_FINISHED, self::STATE_FAILED, self::STATE_TERMINATED, self::STATE_INCOMPLETE])) {
                    throw new InvalidStateTransitionException($this, $newState, [self::STATE_FINISHED, self::STATE_FAILED, self::STATE_TERMINATED, self::STATE_INCOMPLETE]);
                }

                $this->closedAt = new \DateTime();

                break;

            case self::STATE_FINISHED:
            case self::STATE_FAILED:
            case self::STATE_TERMINATED:
            case self::STATE_INCOMPLETE:
                throw new InvalidStateTransitionException($this, $newState);

            default:
                throw new LogicException('The previous cases were exhaustive. Unknown state: '.$this->state);
        }

        $this->state = $newState;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getClosedAt(): ?\DateTimeInterface
    {
        return $this->closedAt;
    }

    public function getExecuteAfter(): ?\DateTimeInterface
    {
        return $this->executeAfter;
    }

    public function setExecuteAfter(\DateTime $executeAfter): void
    {
        $this->executeAfter = $executeAfter;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getRelatedEntities(): Collection
    {
        return $this->relatedEntities;
    }

    public function isClosedNonSuccessful(): bool
    {
        return self::isNonSuccessfulFinalState($this->state);
    }

    public function findRelatedEntity($class): ?object
    {
        foreach ($this->relatedEntities as $entity) {
            if ($entity instanceof $class) {
                return $entity;
            }
        }

        return null;
    }

    public function addRelatedEntity($entity): void
    {
        if (! is_object($entity)) {
            throw new \RuntimeException('$entity must be an object.');
        }

        if ($this->relatedEntities->contains($entity)) {
            return;
        }

        $this->relatedEntities->add($entity);
    }

    public function getDependencies(): Collection
    {
        return $this->dependencies;
    }

    public function hasDependency(Job $job)
    {
        return $this->dependencies->contains($job);
    }

    public function addDependency(Job $job): void
    {
        if ($this->dependencies->contains($job)) {
            return;
        }

        if ($this->mightHaveStarted()) {
            throw new \LogicException('You cannot add dependencies to a job which might have been started already.');
        }

        $this->dependencies->add($job);
    }

    public function getRuntime(): ?int
    {
        return $this->runtime;
    }

    public function setRuntime($time): void
    {
        $this->runtime = (int) $time;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function getMemoryUsageReal(): ?int
    {
        return $this->memoryUsageReal;
    }

    public function addOutput(string $output): void
    {
        $this->output .= $output;
    }

    public function addErrorOutput(string $output): void
    {
        $this->errorOutput .= $output;
    }

    public function setOutput(?string $output): void
    {
        $this->output = $output;
    }

    public function setErrorOutput(?string $output): void
    {
        $this->errorOutput = $output;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function getErrorOutput(): ?string
    {
        return $this->errorOutput;
    }

    public function setExitCode(?int $code): void
    {
        $this->exitCode = $code;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function setMaxRuntime($time): void
    {
        $this->maxRuntime = (int) $time;
    }

    public function getMaxRuntime(): ?int
    {
        return $this->maxRuntime;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getMaxRetries(): ?int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries($tries): void
    {
        $this->maxRetries = (int) $tries;
    }

    public function isRetryAllowed()
    {
        // If no retries are allowed, we can bail out directly, and we
        // do not need to initialize the retryJobs relation.
        if (0 === $this->maxRetries) {
            return false;
        }

        return count($this->retryJobs) < $this->maxRetries;
    }

    public function getOriginalJob(): self
    {
        if (!$this->originalJob instanceof Job) {
            return $this;
        }

        return $this->originalJob;
    }

    public function setOriginalJob(Job $job): void
    {
        if (self::STATE_PENDING !== $this->state) {
            throw new \LogicException($this.' must be in state "PENDING".');
        }

        if ($this->originalJob instanceof Job) {
            throw new \LogicException($this.' already has an original job set.');
        }

        $this->originalJob = $job;
    }

    public function addRetryJob(Job $job): void
    {
        if (self::STATE_RUNNING !== $this->state) {
            throw new \LogicException('Retry jobs can only be added to running jobs.');
        }

        $job->setOriginalJob($this);
        $this->retryJobs->add($job);
    }

    public function getRetryJobs(): Collection
    {
        return $this->retryJobs;
    }

    public function isRetryJob(): bool
    {
        return $this->originalJob instanceof Job;
    }

    public function isRetried(): bool
    {
        foreach ($this->retryJobs as $job) {
            /** @var Job $job */

            if (! $job->isInFinalState()) {
                return true;
            }
        }

        return false;
    }

    public function checked(): void
    {
        $this->checkedAt = new \DateTime();
    }

    public function getCheckedAt(): ?\DateTimeInterface
    {
        return $this->checkedAt;
    }

    public function setStackTrace(FlattenException $ex): void
    {
        $this->stackTrace = $ex;
    }

    public function getStackTrace()
    {
        return $this->stackTrace;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function isNew(): bool
    {
        return self::STATE_NEW === $this->state;
    }

    public function isPending(): bool
    {
        return self::STATE_PENDING === $this->state;
    }

    public function isCanceled(): bool
    {
        return self::STATE_CANCELED === $this->state;
    }

    public function isRunning(): bool
    {
        return self::STATE_RUNNING === $this->state;
    }

    public function isTerminated(): bool
    {
        return self::STATE_TERMINATED === $this->state;
    }

    public function isFailed(): bool
    {
        return self::STATE_FAILED === $this->state;
    }

    public function isFinished(): bool
    {
        return self::STATE_FINISHED === $this->state;
    }

    public function isIncomplete(): bool
    {
        return self::STATE_INCOMPLETE === $this->state;
    }

    public function __toString(): string
    {
        return sprintf('Job(id = %s, command = "%s")', $this->id, $this->command);
    }

    private function mightHaveStarted()
    {
        if (null === $this->id) {
            return false;
        }

        if (self::STATE_NEW === $this->state) {
            return false;
        }

        return !(self::STATE_PENDING === $this->state && ! $this->isStartable());
    }
}
