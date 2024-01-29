<?php

namespace JMS\JobQueueBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name = "jms_cron_jobs")
 * @ORM\ChangeTrackingPolicy("DEFERRED_EXPLICIT")
 */
class CronJob
{
    /** @ORM\Id @ORM\Column(type = "integer", options = {"unsigned": true}) @ORM\GeneratedValue(strategy="AUTO") */
    private $id;

    /** @ORM\Column(type = "datetime", name = "lastRunAt") */
    private ?\DateTimeInterface $lastRunAt;

    /** @ORM\Column(type = "string", length = 200, unique = true) */
    private string $command;

    public function __construct(string $command)
    {
        $this->command = $command;
        $this->lastRunAt = new \DateTime();
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getLastRunAt(): \DateTime|\DateTimeInterface|null
    {
        return $this->lastRunAt;
    }
}