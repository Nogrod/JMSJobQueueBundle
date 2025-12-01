<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function(ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $parameters->set('jms_job_queue.entity.many_to_any_listener.class', \JMS\JobQueueBundle\Entity\Listener\ManyToAnyListener::class);
    $parameters->set('jms_job_queue.twig.extension.class', \JMS\JobQueueBundle\Twig\JobQueueExtension::class);
    $parameters->set('jms_job_queue.retry_scheduler.class', \JMS\JobQueueBundle\Retry\ExponentialRetryScheduler::class);
    $parameters->set('jms_job_queue.job_manager.class', \JMS\JobQueueBundle\Entity\Repository\JobManager::class);

    $services->set('jms_job_queue.retry_scheduler', '%jms_job_queue.retry_scheduler.class%');

    $services->set('jms_job_queue.entity.many_to_any_listener', '%jms_job_queue.entity.many_to_any_listener.class%')
        ->args([service('doctrine')])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postGenerateSchema'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postLoad'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postPersist'])
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'preRemove']);

    $services->set('jms_job_queue.twig.extension', '%jms_job_queue.twig.extension.class%')
        ->tag('twig.extension');

    $services->set('jms_job_queue.job_manager', '%jms_job_queue.job_manager.class%')
        ->public()
        ->args([
            service('doctrine'),
            service('event_dispatcher'),
            service('jms_job_queue.retry_scheduler'),
        ]);
};
