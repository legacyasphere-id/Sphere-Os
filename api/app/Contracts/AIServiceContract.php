<?php

namespace App\Contracts;

use App\AI\AIRequest;
use App\AI\AIResponse;

interface AIServiceContract
{
    public function complete(AIRequest $request): AIResponse;
}
