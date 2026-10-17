<?php

declare(strict_types=1);

// Keep / and /index.php serving the try-out page when a server prefers PHP indexes.
readfile( __DIR__ . '/index.html' );
