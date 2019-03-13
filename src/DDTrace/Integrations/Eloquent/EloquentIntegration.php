<?php

namespace DDTrace\Integrations\Eloquent;

use DDTrace\Integrations\Integration;
use DDTrace\Integrations\AbstractIntegration;
use DDTrace\Tag;
use DDTrace\Type;
use DDTrace\GlobalTracer;

class EloquentIntegration extends AbstractIntegration
{
    const NAME = 'eloquent';

    /**
     * @return string The integration name.
     */
    public function getName()
    {
        return self::NAME;
    }

    public static function load()
    {
        // getModels($columns = ['*'])
        dd_trace('Illuminate\Database\Eloquent\Builder', 'getModels', function () {
            $args = func_get_args();
            $scope = GlobalTracer::get()->startActiveSpan('eloquent.get');
            $span = $scope->getSpan();
            $sql = $this->getQuery()->toSql();
            $span->setTag(Tag::RESOURCE_NAME, $sql);
            $span->setTag(Tag::DB_STATEMENT, $sql);
            $span->setTag(Tag::SPAN_TYPE, Type::SQL);

            return include __DIR__ . '/../../try_catch_finally.php';
        });

        // performInsert(Builder $query)
        dd_trace('Illuminate\Database\Eloquent\Model', 'performInsert', function () {
            $args = func_get_args();
            $eloquentQueryBuilder = $args[0];
            $scope = GlobalTracer::get()->startActiveSpan('eloquent.insert');
            $span = $scope->getSpan();
            $sql = $eloquentQueryBuilder->getQuery()->toSql();
            $span->setTag(Tag::RESOURCE_NAME, $sql);
            $span->setTag(Tag::DB_STATEMENT, $sql);
            $span->setTag(Tag::SPAN_TYPE, Type::SQL);

            return include __DIR__ . '/../../try_catch_finally.php';
        });

        // performUpdate(Builder $query)
        dd_trace('Illuminate\Database\Eloquent\Model', 'performUpdate', function () {
            $args = func_get_args();
            $eloquentQueryBuilder = $args[0];
            $scope = GlobalTracer::get()->startActiveSpan('eloquent.update');
            $span = $scope->getSpan();
            $sql = $eloquentQueryBuilder->getQuery()->toSql();
            $span->setTag(Tag::RESOURCE_NAME, $sql);
            $span->setTag(Tag::DB_STATEMENT, $sql);
            $span->setTag(Tag::SPAN_TYPE, Type::SQL);

            return include __DIR__ . '/../../try_catch_finally.php';
        });

        // public function delete()
        dd_trace('Illuminate\Database\Eloquent\Model', 'delete', function () {
            $scope = GlobalTracer::get()->startActiveSpan('eloquent.delete');
            $scope->getSpan()->setTag(Tag::SPAN_TYPE, Type::SQL);

            return include __DIR__ . '/../../try_catch_finally.php';
        });

        return Integration::LOADED;
    }
}
