<?php

/*
 | The modules installed in this deployment. Products and plugins are added here (later: discovered from packages);
 | the core never names them. Order does not matter: dependencies are resolved from each module's manifest.
 */
return [
    'modules' => [
        \App\Products\Armorhub\ArmorhubModule::class,
    ],
];
