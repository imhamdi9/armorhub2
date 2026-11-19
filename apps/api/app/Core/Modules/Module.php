<?php

namespace App\Core\Modules;

/**
 * A unit of product or plugin functionality. It adds events, subscribers, and (in later milestones) workflows, roles,
 * settings and integrations through the ModuleContext: it never edits the core.
 */
interface Module
{
    public function manifest(): ModuleManifest;

    public function register(ModuleContext $context): void;
}
