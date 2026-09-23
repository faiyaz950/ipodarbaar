<?php

namespace App\Services;

use RuntimeException;

/** The banner isn't the "logo on a white card" layout, so it can't be cropped into a logo. */
class UnsupportedBanner extends RuntimeException {}
