<?php
http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
exit('403 Forbidden - Directory access denied.');

