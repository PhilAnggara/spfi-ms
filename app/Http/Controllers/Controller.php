<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function test()
    {
        dd('Test method from base Controller called');
    }
}
