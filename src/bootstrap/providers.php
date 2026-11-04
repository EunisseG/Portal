<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\IntegrationsServiceProvider;
use App\Providers\MailConfigServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IntegrationsServiceProvider::class,
    MailConfigServiceProvider::class,
];
