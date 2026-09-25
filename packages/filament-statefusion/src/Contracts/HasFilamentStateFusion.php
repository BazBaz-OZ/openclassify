<?php

namespace A909M\FilamentStateFusion\Contracts;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
interface HasFilamentStateFusion
{
    /**
     * getStatesLabel
     *
     * @param  TModel  $model
     */
    public static function getStatesLabel($model): array;

    /**
     * getStatesColor
     *
     * @param  TModel  $model
     */
    public static function getStatesColor($model): array;

    /**
     * getStatesDescription
     *
     * @param  TModel  $model
     */
    public static function getStatesDescription($model): array;

    /**
     * getStatesIcon
     *
     * @param  TModel  $model
     */
    public static function getStatesIcon($model): array;
}
