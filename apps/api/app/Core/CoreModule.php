<?php

namespace App\Core;

use App\Core\Modules\Module;
use App\Core\Modules\ModuleContext;
use App\Core\Modules\ModuleManifest;

/** The core's own contribution to the event catalog and the step types every blueprint may use. Always installed. */
class CoreModule implements Module
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest('core', 'Platform core', (string) Core::VERSION, 'Events, workflows, blueprints and modules.');
    }

    public function register(ModuleContext $context): void
    {
        $context->event('blueprint.applied', ['blueprint_id' => 'string', 'application_id' => 'int', 'tenant_id' => 'int?'], 'A blueprint was applied: a new market or business was launched');
        $context->event('surface.published', ['surface' => 'string', 'version' => 'int', 'locale' => 'string', 'tenant_id' => 'int?'], 'Admin-editable content was published');
        $context->blueprintStep(\App\Core\Blueprints\Steps\WorkflowOverrideStep::class);
    }
}
