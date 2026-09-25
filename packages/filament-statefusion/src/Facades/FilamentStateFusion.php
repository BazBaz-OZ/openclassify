<?php

namespace A909M\FilamentStateFusion\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \A909M\FilamentStateFusion\FilamentStateFusion
 */
class FilamentStateFusion extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \A909M\FilamentStateFusion\FilamentStateFusion::class;
    }
}
