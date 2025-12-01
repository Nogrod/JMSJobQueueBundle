<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function(ContainerConfigurator $container) {
    $services = $container->services();
    $parameters = $container->parameters();
    $parameters->set('jms_job_queue.entity.statistics_listener.class', \JMS\JobQueueBundle\Entity\Listener\StatisticsListener::class);

    $services->set('jms_job_queue.entity.statistics_listener', '%jms_job_queue.entity.statistics_listener.class%')
        ->tag('doctrine.event_listener', ['lazy' => true, 'event' => 'postGenerateSchema']);
};
