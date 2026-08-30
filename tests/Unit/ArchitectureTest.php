<?php

arch('app')
    ->expect('App\Data')
    ->toHaveSuffix('Dto')
    ->toBeClasses();
