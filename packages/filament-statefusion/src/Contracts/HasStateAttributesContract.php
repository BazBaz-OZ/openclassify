<?php

namespace A909M\FilamentStateFusion\Contracts;

interface HasStateAttributesContract
{
    public function getAttribute(): string;

    public function attribute(string | \Closure | null $attribute): static;
}
