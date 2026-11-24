<?php

return [

    // Only a token opens the API: a web session does not, so there is nothing to protect from CSRF.
    'guard' => [],
    'stateful' => [],
    'routes' => false,

    // Each token carries its own expiry, set by IssueToken.
    'expiration' => null,

];
