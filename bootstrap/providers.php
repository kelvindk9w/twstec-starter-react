<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    // Os providers dos pacotes do kit (foundation, auth e, quando instalados,
    // accounts, uploads e admin) entram pela descoberta automática de pacotes
    // do Laravel. O painel /admin (twstec/kit-admin, opcional) entra como
    // plugin no App\Providers\Filament\AdminPanelProvider, que o
    // AppServiceProvider registra só com o pacote instalado.
    AppServiceProvider::class,
    HorizonServiceProvider::class,
];
