<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\ObserverServiceProvider;

return [
    AppServiceProvider::class,
    AppPanelProvider::class,
    ObserverServiceProvider::class,
];
