<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Models\MiddoBox;
use Illuminate\View\View;

class MiddoBoxPrintController extends Controller
{
    public function __invoke(MiddoBox $middoBox): View
    {
        return view('operation.middo-boxes.print', [
            'box' => $middoBox,
        ]);
    }
}
