<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Twig;

interface LinkGeneratorInterface
{
    public function supports($entity);

    public function generate($entity);

    public function getLinkname($entity);
}
