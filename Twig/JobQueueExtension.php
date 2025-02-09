<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class JobQueueExtension extends AbstractExtension
{
    public function __construct(private readonly array $linkGenerators = [])
    {
    }

    public function getTests(): array
    {
        return [new TwigTest('jms_job_queue_linkable', $this->isLinkable(...))];
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('jms_job_queue_path', $this->generatePath(...), ['is_safe' => ['html' => true]])];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('jms_job_queue_linkname', $this->getLinkname(...)), new TwigFilter('jms_job_queue_args', $this->formatArgs(...))];
    }

    /**
     * @param $maxLength
     */
    public function formatArgs(array $args, $maxLength = 60): string
    {
        $str = '';
        $first = true;
        foreach ($args as $arg) {
            $argLength = strlen((string) $arg);

            if (! $first) {
                $str .= ' ';
            }

            $first = false;

            if (strlen($str) + $argLength > $maxLength) {
                $str .= substr((string) $arg, 0, $maxLength - strlen($str) - 4).'...';
                break;
            }

            $str .= escapeshellarg((string) $arg);
        }

        return $str;
    }

    /**
     * @param $entity
     */
    public function isLinkable($entity): bool
    {
        foreach ($this->linkGenerators as $generator) {
            if ($generator->supports($entity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $entity
     * @return mixed
     */
    public function generatePath($entity)
    {
        foreach ($this->linkGenerators as $generator) {
            if ($generator->supports($entity)) {
                return $generator->generate($entity);
            }
        }

        throw new \RuntimeException(sprintf('The entity "%s" has no link generator.', $entity::class));
    }

    /**
     * @param $entity
     * @return mixed
     */
    public function getLinkname($entity)
    {
        foreach ($this->linkGenerators as $generator) {
            if ($generator->supports($entity)) {
                return $generator->getLinkname($entity);
            }
        }

        throw new \RuntimeException(sprintf('The entity "%s" has no link generator.', $entity::class));
    }

    public function getName(): string
    {
        return 'jms_job_queue';
    }
}
