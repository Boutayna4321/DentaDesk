<?php

namespace App\Booking;

use RuntimeException;

class SlotTaken extends RuntimeException
{
    public function __construct()
    {
        parent::__construct("Ce créneau vient d'être pris. Choisissez-en un autre.");
    }
}
