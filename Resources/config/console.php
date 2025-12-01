<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function(ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();

    $services->defaults()
        ->private();

    $services->set('jms_job_queue.command.clean_up', \JMS\JobQueueBundle\Command\CleanUpCommand::class)
        ->args([
            service('doctrine'),
            service('jms_job_queue.job_manager'),
        ])
        ->tag('console.command');

    $services->set('jms_job_queue.command.mark_job_incomplete', \JMS\JobQueueBundle\Command\MarkJobIncompleteCommand::class)
        ->args([
            service('doctrine'),
            service('jms_job_queue.job_manager'),
        ])
        ->tag('console.command');

    $services->set('jms_job_queue.command.run', \JMS\JobQueueBundle\Command\RunCommand::class)
        ->args([
            service('doctrine'),
            service('jms_job_queue.job_manager'),
            service('event_dispatcher'),
            '$queueOptionsDefault' => '%jms_job_queue.queue_options_defaults%',
            '$queueOptions' => '%jms_job_queue.queue_options%',
        ])
        ->tag('console.command');

    $services->set('jms_job_queue.command.schedule', \JMS\JobQueueBundle\Command\ScheduleCommand::class)
        ->args([
            service('doctrine'),
            tagged_iterator('jms_job_queue.scheduler'),
            tagged_iterator('jms_job_queue.cron_command'),
        ])
        ->tag('console.command');
};
