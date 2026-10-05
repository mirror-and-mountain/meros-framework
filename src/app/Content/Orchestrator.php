<?php

namespace MM\Meros\App\Content;

use MM\Meros\Contracts\Orchestrators\ContentOrchestrator;

class Orchestrator extends ContentOrchestrator {
    protected function configure(): void {
        $this->registerCorePostTypes();
        $this->postTypes(Form::class)->make();
    }

    /**
     * Registers WordPress core post types (posts and pages) for the framework.
     * 
     * This is so users can add custom fields to core post types using the framework's api.
     *
     * @return void
     */
    private function registerCorePostTypes(): void {
        $this->postTypes()->make(function ($postType) {
            $postType->name('post');
            $postType->core(true);
        });

        $this->postTypes()->make(function ($postType) {
            $postType->name('page');
            $postType->core(true);
        });
    }
}