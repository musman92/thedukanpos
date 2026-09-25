<?php

namespace App\Contracts;

interface AddonLifecycle
{
    public function afterInstall(): void;

    public function beforeRemove(): void;
}
