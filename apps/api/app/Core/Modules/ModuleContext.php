<?php

namespace App\Core\Modules;

use App\Core\Blueprints\Blueprint;
use App\Core\Blueprints\BlueprintRegistry;
use App\Core\Blueprints\BlueprintStepHandler;
use App\Core\Events\EventCatalog;
use App\Core\Events\EventDefinition;
use App\Core\Events\SubscriberRegistry;
use App\Core\Workflow\Action;
use App\Core\Workflow\Guard;
use App\Core\Workflow\WorkflowDefinition;
use App\Core\Workflow\WorkflowRegistry;

/** The extension points a module may use. Adding a new kind of extension means adding a method here, once. */
class ModuleContext
{
    public function __construct(private string $moduleId, private EventCatalog $events, private SubscriberRegistry $subscribers, private ?WorkflowRegistry $workflows = null, private ?BlueprintRegistry $blueprints = null)
    {
    }

    /**
     * Declare an event this module owns.
     *
     * @param array<string, string> $schema field => type (int|string|bool|float|array|mixed, "?" = optional)
     */
    public function event(string $name, array $schema = [], string $description = '', int $version = 1): void
    {
        $this->events->register(new EventDefinition($name, $this->moduleId, $schema, $description, $version));
    }

    /** Declare a launch bundle (see Blueprint::fromArray). */
    public function blueprint(array $definition): void
    {
        $this->blueprints->register(Blueprint::fromArray($definition, $this->moduleId));
    }

    /** @param class-string<BlueprintStepHandler> $handler */
    public function blueprintStep(string $handler): void
    {
        $this->blueprints->registerHandler(app($handler));
    }

    /** Declare a workflow this module owns, as data (see WorkflowDefinition::fromArray). */
    public function workflow(array $definition): void
    {
        $this->workflows->register(WorkflowDefinition::fromArray($definition, $this->moduleId));
    }

    /** @param class-string<Guard> $guard */
    public function guard(string $guard): void
    {
        $this->workflows->registerGuard(app($guard));
    }

    /** @param class-string<Action> $action */
    public function action(string $action): void
    {
        $this->workflows->registerAction(app($action));
    }

    /** @param class-string<\App\Core\Events\EventSubscriber> $subscriber */
    public function subscribe(string $subscriber): void
    {
        $this->subscribers->register($subscriber, $this->moduleId);
    }
}
