<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Retry;

use JMS\JobQueueBundle\Entity\Job;

class ExponentialRetryScheduler implements RetryScheduler
{
    public function __construct(private $base = 5)
    {
    }

    public function scheduleNextRetry(Job $originalJob): \DateTime
    {
        return new \DateTime('+'.($this->base ** count($originalJob->getRetryJobs())).' seconds');
    }
}
