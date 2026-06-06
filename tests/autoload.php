<?php

// Copyright 2026 Akop Karapetyan
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.

declare(strict_types=1);

// Production autoloader (Grouch\ → src/)
require_once __DIR__ . '/../src/autoload.php';

// Test autoloader (Grouch\Tests\ → tests/)
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Grouch\\Tests\\')) {
        return;
    }
    $relative = substr($class, strlen('Grouch\\Tests\\'));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
