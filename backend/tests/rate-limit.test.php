<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config/database.php';

if (!liquorsoftRateLimitCheck('test-rate-limit', 'tester@example.invalid', 2, 60)) {
    fwrite(STDERR, "Rate limit check did not allow the first attempt.\n");
    exit(1);
}
if (!liquorsoftRateLimitCheck('test-rate-limit', 'tester@example.invalid', 2, 60)) {
    fwrite(STDERR, "Rate limit check did not allow the second attempt within the window.\n");
    exit(1);
}
liquorsoftRateLimitHit('test-rate-limit', 'tester@example.invalid', 60);
liquorsoftRateLimitHit('test-rate-limit', 'tester@example.invalid', 60);
if (liquorsoftRateLimitCheck('test-rate-limit', 'tester@example.invalid', 2, 60)) {
    fwrite(STDERR, "Rate limit check did not enforce the maximum before the window elapsed.\n");
    exit(1);
}
liquorsoftRateLimitClear('test-rate-limit', 'tester@example.invalid');
if (!liquorsoftRateLimitCheck('test-rate-limit', 'tester@example.invalid', 2, 60)) {
    fwrite(STDERR, "Rate limit clear did not reset the counter.\n");
    exit(1);
}

echo "Rate limit checks passed.\n";
