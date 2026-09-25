<?php

namespace A909M\FilamentStateFusion\Concerns;

use Spatie\ModelStates\State;
use Spatie\ModelStates\Transition;

trait InteractsWithStateAction
{
    protected string | State | null $toState = null;

    /**
     * transitionTo
     */
    public function transitionTo(string | State | null $toState): static
    {
        $this->toState = $toState;

        return $this;
    }

    /**
     * getToState
     */
    public function getToState(): string | State | null
    {
        return $this->evaluate($this->toState);
    }

    /**
     * getFromState
     */
    public function getFromState(): string | State | null
    {
        return $this->evaluate($this->getRecord()->{$this->getAttribute()});
    }

    /**
     * getToStateClass
     */
    public function getToStateClass(): string | State
    {
        return $this->evaluate(new ($this->getToState())($this->getModel()));
    }

    /**
     * getTransitionClass
     */
    public function getTransitionClass(): ?string
    {
        return $this->evaluate($this->getFromState()::config()->resolveTransitionClass($this->getFromState()::getMorphClass(), $this->getToState()::getMorphClass()));
    }

    /**
     * hasTransitionClass
     */
    public function hasTransitionClass(): bool
    {
        return $this->evaluate(class_exists($this->getTransitionClass()));
    }

    /**
     * getClassInstance
     */
    public function getClassInstance(): string | State | Transition
    {
        // return $this->evaluate($this->hasTransitionClass() ? (new ($this->getTransitionClass())(($this->getRecord()))) : $this->getToStateClass());
        return $this->evaluate($this->hasTransitionClass() ? (new ($this->getTransitionClass())((new ($this->getModel())()))) : $this->getToStateClass());
    }
}
