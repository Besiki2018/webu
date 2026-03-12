<?php

namespace App\Support;

use App\Models\Project;
use LogicException;

class TenantContext
{
    protected ?Project $project = null;

    public function clear(): void
    {
        $this->project = null;
    }

    public function hasProject(): bool
    {
        return $this->project !== null;
    }

    public function project(): ?Project
    {
        return $this->project;
    }

    public function projectId(): ?string
    {
        return $this->project?->id;
    }

    public function setProject(Project $project): void
    {
        if ($this->project !== null && $this->project->id !== $project->id) {
            throw new LogicException(sprintf(
                'Tenant context conflict: current [%s], attempted [%s].',
                $this->project->id,
                $project->id
            ));
        }

        $this->project = $project;
    }
}

