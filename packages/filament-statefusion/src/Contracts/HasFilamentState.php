<?php

namespace A909M\FilamentStateFusion\Contracts;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

interface HasFilamentState extends HasColor, HasDescription, HasIcon, HasLabel {}
