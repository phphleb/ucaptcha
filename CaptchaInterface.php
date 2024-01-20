<?php

namespace Phphleb\Ucaptcha;

interface CaptchaInterface
{
    public function getSession(): array;

    public function check(string $code);
    
    public function createImage(string $type);

    public function isPassed();

}