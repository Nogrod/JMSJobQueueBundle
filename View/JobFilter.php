<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\View;

use Symfony\Component\HttpFoundation\Request;

class JobFilter
{
    public $page;

    public $command;

    public $state;

    public static function fromRequest(Request $request): self
    {
        $filter = new self();
        $filter->page = $request->query->getInt('page', 1);
        $filter->command = $request->query->get('command');
        $filter->state = $request->query->get('state');

        return $filter;
    }

    public function isDefaultPage(): bool
    {
        return $this->page === 1 && empty($this->command) && empty($this->state);
    }

    public function toArray(): array
    {
        return ['page' => $this->page, 'command' => $this->command, 'state' => $this->state];
    }
}
