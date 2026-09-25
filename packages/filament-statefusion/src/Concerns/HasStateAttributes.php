<?php

namespace A909M\FilamentStateFusion\Concerns;

use Closure;

trait HasStateAttributes
{
    protected Closure | string | null $attribute = null;

    public function getAttribute(): string
    {
        // * @phpstan-ignore-next-line */
        return $this->evaluate($this->attribute ?? (string) array_key_first($this->getModel()::getDefaultStates()->toArray()));
    }

    public function attribute(string | Closure | null $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }
}
