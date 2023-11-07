<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;

class MainQueueController extends Controller
{
    public function index(Request $request){
        return response([], 200);
    }
}
