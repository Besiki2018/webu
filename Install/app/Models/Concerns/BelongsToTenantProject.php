<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToTenantProject
{
    public static function bootBelongsToTenantProject(): void
    {
        static::addGlobalScope('tenant_project', function (Builder $builder) {
            $context = app(TenantContext::class);

            if (! $context->hasProject()) {
                return;
            }

            $builder->where(
                $builder->qualifyColumn(static::tenantProjectColumn()),
                $context->projectId()
            );
        });

        static::saving(function (Model $model): void {
            $context = app(TenantContext::class);

            if (! $context->hasProject()) {
                return;
            }

            $column = static::tenantProjectColumn();
            $contextProjectId = $context->projectId();
            $modelProjectId = $model->getAttribute($column);

            if ($modelProjectId === null || $modelProjectId === '') {
                $model->setAttribute($column, $contextProjectId);

                return;
            }

            if ((string) $modelProjectId !== (string) $contextProjectId) {
                throw new LogicException(sprintf(
                    'Cross-tenant write blocked on [%s]: context project [%s], model project [%s].',
                    static::class,
                    $contextProjectId,
                    $modelProjectId
                ));
            }
        });
    }

    public function scopeWithoutTenantProject(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant_project');
    }

    protected static function tenantProjectColumn(): string
    {
        return 'project_id';
    }
}

