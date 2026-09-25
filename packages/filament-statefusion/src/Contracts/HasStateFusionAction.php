<?php

namespace A909M\FilamentStateFusion\Contracts;

use Spatie\ModelStates\State;
use Spatie\ModelStates\Transition;

interface HasStateFusionAction
{
    public function transitionTo(string | State | null $state): static;

    public function getToState(): string | State | null;

    public function getFromState(): string | State | null;

    public function getToStateClass(): string | State;

    public function getTransitionClass(): ?string;

    public function hasTransitionClass(): bool;

    public function getClassInstance(): string | State | Transition;
}
