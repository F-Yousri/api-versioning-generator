<?php

namespace FYousri\APIVersioning;

use Illuminate\Support\Facades\Facade;

/**
 * @see \FYousri\APIVersioning\Skeleton\SkeletonClass
 */
class APIVersioningFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'api-versioning';
    }
}
